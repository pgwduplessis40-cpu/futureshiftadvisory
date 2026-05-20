<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\AuditEvent;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Model;

/**
 * Drop-in trait for controllers (and any other class) that need to
 * record audit events without manually resolving AuditWriter from the
 * container each time.
 *
 * Use:
 *
 *   class ClientController
 *   {
 *       use WritesAudit;
 *
 *       public function store(Request $request)
 *       {
 *           $client = Client::create($request->validated());
 *           $this->audit('client.created', $client, after: $client->toArray());
 *           return $client;
 *       }
 *   }
 *
 * @see App\Services\Audit\AuditWriter
 */
trait WritesAudit
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $context
     */
    protected function audit(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        array $context = [],
    ): AuditEvent {
        return app(AuditWriter::class)->record(
            action: $action,
            subject: $subject,
            before: $before,
            after: $after,
            context: $context,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function auditRead(string $action, ?Model $subject = null, array $context = []): AuditEvent
    {
        return app(AuditWriter::class)->recordRead($action, $subject, $context);
    }
}
