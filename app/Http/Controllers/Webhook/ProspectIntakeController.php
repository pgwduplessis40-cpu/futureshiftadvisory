<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\ProspectLead;
use App\Models\User;
use App\Notifications\ProspectLeadReceivedNotification;
use App\Services\Audit\AuditWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ProspectIntakeController extends Controller
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function store(Request $request): JsonResponse
    {
        [$validSignature, $failureReason] = $this->hasValidSignature($request);

        if (! $validSignature) {
            $this->auditWriter->record('prospect_intake.signature_rejected', after: [
                'reason' => $failureReason,
                'source_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'company' => ['nullable', 'string', 'max:255'],
            'engagement_interest' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
            'source' => ['required', 'string', Rule::in([
                'start_business_journey',
                'request_advisory_conversation',
            ])],
            'dedupe_key' => ['nullable', 'string', 'max:128'],
        ]);

        $rawPayload = $request->getContent();
        $recipients = $this->advisorRecipients();
        $assignedAdvisor = $recipients->first(
            fn (User $user): bool => $user->user_type === User::TYPE_ADVISOR,
        );

        $lead = ProspectLead::query()->firstOrCreate(
            ['dedupe_key' => $this->dedupeKey($validated)],
            [
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'company' => $validated['company'] ?? null,
                'engagement_interest' => $validated['engagement_interest'] ?? null,
                'message' => $validated['message'],
                'source' => $validated['source'],
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'status' => ProspectLead::STATUS_NEW,
                'assigned_advisor_user_id' => $assignedAdvisor?->getKey(),
                'payload_hash' => hash('sha256', $rawPayload),
                'intake_payload' => $validated,
            ],
        );

        if ($lead->wasRecentlyCreated) {
            Notification::send($recipients, new ProspectLeadReceivedNotification($lead));

            $this->auditWriter->record('prospect_intake.received', subject: $lead, after: [
                'source' => $lead->source,
                'email' => $lead->email,
                'assigned_advisor_user_id' => $lead->assigned_advisor_user_id,
            ]);
        }

        return response()->json([
            'prospect_lead' => [
                'id' => $lead->id,
                'status' => $lead->status,
            ],
        ], $lead->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function hasValidSignature(Request $request): array
    {
        $secret = config('security.prospect_intake_secret');
        if (! is_string($secret) || trim($secret) === '') {
            return [false, 'secret_not_configured'];
        }

        $timestamp = $request->header('X-FSA-Timestamp');
        if (! is_string($timestamp) || ! ctype_digit($timestamp)) {
            return [false, 'timestamp_missing'];
        }

        $tolerance = (int) config('security.prospect_intake_tolerance_seconds', 300);
        if (abs(now()->getTimestamp() - (int) $timestamp) > $tolerance) {
            return [false, 'timestamp_out_of_window'];
        }

        $signature = $request->header('X-FSA-Signature');
        if (! is_string($signature) || trim($signature) === '') {
            return [false, 'signature_missing'];
        }

        $provided = Str::startsWith($signature, 'sha256=')
            ? Str::after($signature, 'sha256=')
            : $signature;
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $provided)
            ? [true, null]
            : [false, 'signature_mismatch'];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function dedupeKey(array $validated): string
    {
        if (isset($validated['dedupe_key']) && is_string($validated['dedupe_key']) && $validated['dedupe_key'] !== '') {
            return $validated['dedupe_key'];
        }

        return hash('sha256', implode('|', [
            $validated['source'],
            Str::lower((string) $validated['email']),
            trim((string) $validated['message']),
        ]));
    }

    /**
     * @return Collection<int, User>
     */
    private function advisorRecipients(): Collection
    {
        $advisors = User::query()
            ->where('user_type', User::TYPE_ADVISOR)
            ->orderBy('id')
            ->get();

        if ($advisors->isNotEmpty()) {
            return $advisors;
        }

        return User::query()
            ->where('user_type', User::TYPE_SUPER_ADMIN)
            ->orderBy('id')
            ->get();
    }
}
