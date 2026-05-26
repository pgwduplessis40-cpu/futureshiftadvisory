<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\PortalOfflineSyncRecord;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use JsonException;

final class PortalOfflineSync
{
    public const SYNC_HEADER = 'X-Portal-Offline-Sync';

    public const IDEMPOTENCY_HEADER = 'X-Idempotency-Key';

    public const CLIENT_HEADER = 'X-Portal-Client-Id';

    public function isSync(Request $request): bool
    {
        return $request->headers->has(self::SYNC_HEADER);
    }

    public function queuedClient(Request $request): Client
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true), 403);

        $clientId = trim((string) $request->headers->get(self::CLIENT_HEADER, ''));
        abort_if($clientId === '', 422, 'Missing queued portal client for offline sync.');
        abort_unless(in_array($clientId, $user->accessibleClientIds(), true), 403, 'Queued portal client is no longer accessible.');

        $client = Client::query()
            ->whereKey($clientId)
            ->where('status', '!=', ClientStatus::SUSPENDED->value)
            ->first();

        abort_unless($client instanceof Client, 403, 'Queued portal client is no longer accessible.');

        return $client;
    }

    /**
     * @param  Closure(Client): JsonResponse  $callback
     */
    public function handle(Request $request, string $operation, Closure $callback): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $client = $this->queuedClient($request);
        $idempotencyKey = trim((string) $request->headers->get(self::IDEMPOTENCY_HEADER, ''));
        abort_if($idempotencyKey === '', 422, 'Missing idempotency key for offline sync.');

        $fingerprint = $this->requestFingerprint($request);

        return DB::transaction(function () use ($callback, $client, $fingerprint, $idempotencyKey, $operation, $user): JsonResponse {
            $record = PortalOfflineSyncRecord::query()
                ->where('user_id', $user->getKey())
                ->where('client_id', $client->getKey())
                ->where('operation', $operation)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($record instanceof PortalOfflineSyncRecord) {
                if (! hash_equals($record->request_fingerprint, $fingerprint)) {
                    return response()->json([
                        'message' => 'Offline sync idempotency key was reused with a different payload.',
                    ], 409);
                }

                return response()->json($record->response_payload, $record->status_code);
            }

            $response = $callback($client);
            $payload = $this->jsonPayload($response);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return response()->json($payload, $response->getStatusCode());
            }

            PortalOfflineSyncRecord::query()->create([
                'user_id' => $user->getKey(),
                'client_id' => $client->getKey(),
                'operation' => $operation,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'response_payload' => $payload,
                'status_code' => $response->getStatusCode(),
            ]);

            return response()->json($payload, $response->getStatusCode());
        });
    }

    private function requestFingerprint(Request $request): string
    {
        return hash('sha256', $this->encode([
            'body' => $this->normalise($request->request->all()),
            'files' => $this->normaliseFiles($request->allFiles()),
        ]));
    }

    /**
     * @param  array<string, mixed>  $files
     * @return array<string, mixed>
     */
    private function normaliseFiles(array $files): array
    {
        $normalised = [];

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->getRealPath();
                $normalised[$key] = [
                    'content_sha256' => is_string($path) && is_file($path) ? hash_file('sha256', $path) : null,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ];

                continue;
            }

            $normalised[$key] = is_array($file)
                ? $this->normaliseFiles($file)
                : $this->normalise($file);
        }

        ksort($normalised);

        return $normalised;
    }

    private function normalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalised = array_map(fn (mixed $entry): mixed => $this->normalise($entry), $value);

        if (Arr::isAssoc($normalised)) {
            ksort($normalised);
        }

        return $normalised;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(JsonResponse $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return serialize($payload);
        }
    }
}
