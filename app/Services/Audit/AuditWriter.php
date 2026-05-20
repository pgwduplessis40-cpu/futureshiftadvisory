<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\Client;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The single sanctioned write path into the audit_events table.
 *
 * All controllers, jobs, console commands, and middleware funnel through
 * AuditWriter so that:
 *   1. Every event is redacted before persistence (PII never reaches
 *      audit_events in raw form - see App\Services\Audit\Redactor).
 *   2. Actor identity, role, IP, user agent, and request_id are populated
 *      consistently from the current request and Auth context.
 *   3. The append-only Postgres trigger never has to reject an attempted
 *      mutation originating in our code (any attempted mutation is by
 *      definition a defect).
 *
 * Phase 1 surface area is intentionally small - more domain-specific
 * shortcuts (recordClientCreated, recordDocumentVerified, etc.) land in
 * the WO that introduces the relevant entity.
 *
 * @see PLAN.md section 7.3
 * @see docs/architecture/audit-trail.md
 */
final class AuditWriter
{
    public function __construct(
        private readonly Redactor $redactor,
        private readonly Request $request,
    ) {}

    /**
     * Persist an audit event.
     *
     * @param  string  $action  Dot-delimited action name (e.g. "client.created", "terms.accepted").
     * @param  Model|null  $subject  The Eloquent model the action affected; its morph type and id are recorded.
     * @param  array<string, mixed>|null  $before  Pre-mutation snapshot (will be redacted).
     * @param  array<string, mixed>|null  $after  Post-mutation snapshot (will be redacted).
     * @param  Authenticatable|null  $actor  Override the auth resolver (system jobs supply null).
     * @param  array<string, mixed>  $context  Free-form additional context merged into `after` (will be redacted).
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?Authenticatable $actor = null,
        array $context = [],
    ): AuditEvent {
        $actor = $actor ?? Auth::user();

        $afterPayload = $after;
        if ($context !== []) {
            $afterPayload = array_merge($afterPayload ?? [], ['context' => $context]);
        }

        return AuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now(),
            'actor_user_id' => $actor?->getAuthIdentifier(),
            'actor_role' => $this->resolveActorRole($actor),
            'client_id' => $this->resolveClientId($subject),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey() === null ? null : (string) $subject->getKey(),
            'before' => $before === null ? null : $this->redactor->redact($before),
            'after' => $afterPayload === null ? null : $this->redactor->redact($afterPayload),
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'request_id' => $this->resolveRequestId(),
        ]);
    }

    /**
     * Convenience for view-tracking on sensitive endpoints (document
     * downloads, T&C reads). Same redaction and context rules as record().
     */
    public function recordRead(string $action, ?Model $subject = null, array $context = []): AuditEvent
    {
        return $this->record(
            action: $action,
            subject: $subject,
            before: null,
            after: null,
            context: $context,
        );
    }

    private function resolveActorRole(?Authenticatable $actor): ?string
    {
        if ($actor === null) {
            return 'system';
        }

        if (method_exists($actor, 'fsaRole')) {
            return (string) $actor->fsaRole();
        }

        return 'authenticated';
    }

    private function resolveClientId(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        if (isset($subject->client_id)) {
            return (string) $subject->client_id;
        }

        // The subject IS a client model.
        if ($subject->getMorphClass() === 'client' || $subject->getMorphClass() === Client::class) {
            return $subject->getKey() === null ? null : (string) $subject->getKey();
        }

        return null;
    }

    private function resolveRequestId(): string
    {
        // Header set by the upstream proxy in production. Fallback to a
        // stable per-request UUID so chain verification still works in
        // local dev.
        $existing = $this->request->headers->get('X-Request-Id');
        if (is_string($existing) && $existing !== '' && strlen($existing) <= 64) {
            return $existing;
        }

        /** @var string|null $cached */
        $cached = $this->request->attributes->get('fsa.request_id');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $new = (string) Str::uuid();
        $this->request->attributes->set('fsa.request_id', $new);

        return $new;
    }
}
