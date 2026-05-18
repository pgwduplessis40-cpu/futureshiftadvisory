<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Read-only Eloquent model over the immutable audit_events table.
 *
 * The model has no timestamps (the table uses occurred_at instead) and
 * no setter helpers. All writes go through App\Services\Audit\AuditWriter
 * which performs redaction. Any code that calls $auditEvent->save() or
 * $auditEvent->delete() will fail at the database trigger anyway, but
 * we disable updates here as belt-and-braces.
 *
 * @property string $id
 * @property Carbon $occurred_at
 * @property string|null $actor_user_id
 * @property string|null $actor_role
 * @property string|null $client_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $request_id
 */
final class AuditEvent extends Model
{
    use HasUuids;

    protected $table = 'audit_events';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'before' => 'array',
        'after' => 'array',
    ];

    /**
     * Audit rows must never be updated through Eloquent. The Postgres
     * trigger will reject the UPDATE anyway, but failing early in PHP
     * gives a clearer stack trace and prevents accidental query
     * construction in tests.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException(
                'audit_events is append-only; updating an existing AuditEvent is forbidden.'
            );
        }

        return parent::save($options);
    }
}
