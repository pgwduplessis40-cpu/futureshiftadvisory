<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Client;
use App\Models\PortalWorkspaceDraft;
use App\Models\User;
use App\Services\Audit\AuditWriter;

final class PortalWorkspaceDrafts
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, string $draftKey): array
    {
        $draft = $this->find($user, $draftKey);

        return $draft instanceof PortalWorkspaceDraft && is_array($draft->payload)
            ? $draft->payload
            : [];
    }

    public function savedAt(User $user, string $draftKey): ?string
    {
        return $this->find($user, $draftKey)?->saved_at?->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(User $user, ?Client $client, string $draftKey, array $payload): PortalWorkspaceDraft
    {
        $draft = PortalWorkspaceDraft::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'draft_key' => $draftKey,
            ],
            [
                'client_id' => $client?->getKey(),
                'payload' => $payload,
                'saved_at' => now(),
            ],
        );

        if ($draft->wasRecentlyCreated) {
            $this->auditWriter->record(
                'portal.workspace_draft_started',
                subject: $client,
                actor: $user,
                after: [
                    'draft_key' => $draftKey,
                    'field_count' => count($payload),
                ],
            );
        }

        return $draft;
    }

    public function forget(User $user, string $draftKey): void
    {
        PortalWorkspaceDraft::query()
            ->where('user_id', $user->getKey())
            ->where('draft_key', $draftKey)
            ->delete();
    }

    private function find(User $user, string $draftKey): ?PortalWorkspaceDraft
    {
        return PortalWorkspaceDraft::query()
            ->where('user_id', $user->getKey())
            ->where('draft_key', $draftKey)
            ->first();
    }
}
