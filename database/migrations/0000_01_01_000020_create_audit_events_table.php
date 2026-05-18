<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the immutable audit_events table and installs the Postgres
 * trigger that rejects all UPDATE and DELETE statements against it.
 *
 * Per spec section 4 ("Immutable Audit Trail") and PLAN.md section 6.1,
 * audit_events records every meaningful action on the platform:
 * timestamp, actor, role, action, affected record, IP, device,
 * before/after values. Append-only is enforced at the database layer
 * so a compromised application user cannot rewrite history.
 *
 * Cross-tenant by design: a single audit_events table holds events
 * across all clients. RLS is NOT applied to audit_events because:
 *   1. The append-only trigger gives stronger guarantees than RLS.
 *   2. Cross-tenant queries (e.g. the chain-verification job) need to
 *      see everything regardless of caller scope.
 *   3. Authorization at the read side is enforced by AuditEventPolicy
 *      (super_admin only in Phase 1).
 *
 * @see PLAN.md WO-03
 * @see docs/architecture/audit-trail.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->timestampTz('occurred_at')->default(DB::raw('now()'));

            // Actor: nullable because system-triggered events (scheduled
            // jobs, webhook intake) have no human user.
            $table->uuid('actor_user_id')->nullable();
            $table->string('actor_role')->nullable();

            // Tenant scope.
            $table->uuid('client_id')->nullable();

            // What happened, and on what.
            $table->string('action');                  // e.g. "client.created"
            $table->string('subject_type')->nullable(); // morph type
            $table->string('subject_id')->nullable();   // morph id (string because uuid OR int)

            // Before/after snapshots after redaction (jsonb).
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();

            // Request context.
            $table->string('ip', 45)->nullable();      // ipv6 max
            $table->text('user_agent')->nullable();
            $table->uuid('request_id')->nullable();
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->index('occurred_at');
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['client_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('request_id');
        });

        if ($this->onPostgres()) {
            $this->installAppendOnlyTrigger();
        }
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS audit_events_no_update ON audit_events;
                DROP TRIGGER IF EXISTS audit_events_no_delete ON audit_events;
                DROP TRIGGER IF EXISTS audit_events_no_truncate ON audit_events;
                DROP FUNCTION IF EXISTS fsa_audit_events_block_mutation();
            SQL);
        }

        Schema::dropIfExists('audit_events');
    }

    private function installAppendOnlyTrigger(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fsa_audit_events_block_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_events is append-only; % is forbidden', TG_OP
                    USING ERRCODE = 'P0001';
            END;
            $$;

            COMMENT ON FUNCTION fsa_audit_events_block_mutation() IS
                'Rejects any UPDATE/DELETE/TRUNCATE on audit_events. '
                'Append-only is non-negotiable (spec section 4).';

            CREATE TRIGGER audit_events_no_update
                BEFORE UPDATE ON audit_events
                FOR EACH ROW
                EXECUTE FUNCTION fsa_audit_events_block_mutation();

            CREATE TRIGGER audit_events_no_delete
                BEFORE DELETE ON audit_events
                FOR EACH ROW
                EXECUTE FUNCTION fsa_audit_events_block_mutation();

            CREATE TRIGGER audit_events_no_truncate
                BEFORE TRUNCATE ON audit_events
                FOR EACH STATEMENT
                EXECUTE FUNCTION fsa_audit_events_block_mutation();
        SQL);
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
