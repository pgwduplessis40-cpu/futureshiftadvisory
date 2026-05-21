<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendRlsContext();

        Schema::create('clients', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('engagement_type', 80);
            $table->string('nzbn', 13)->nullable()->index();
            $table->string('legal_name');
            $table->string('trading_name')->nullable();
            $table->string('entity_type')->nullable();
            $table->jsonb('address')->nullable();
            $table->boolean('gst_registered')->default(false);
            $table->jsonb('directors')->nullable();
            $table->string('filing_status')->nullable();
            $table->string('data_quality', 40)->default('insufficient');
            $table->jsonb('registry_sources')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('primary_contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('engagement_type_locked_at')->nullable();
            $table->timestampsTz();

            $table->index('engagement_type');
            $table->index('data_quality');
            $table->index('created_by_user_id');
            $table->index('primary_contact_user_id');
        });

        Schema::create('client_team', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('granted_modules')->nullable();
            $table->string('role', 80);
            $table->timestampsTz();

            $table->unique(['client_id', 'user_id']);
            $table->index(['user_id', 'client_id']);
            $table->index('role');
        });

        Schema::create('conflict_declarations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('declaration');
            $table->timestampTz('declared_at');
            $table->timestampsTz();

            $table->index(['client_id', 'advisor_id']);
            $table->index('declared_at');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_declarations');
        Schema::dropIfExists('client_team');
        Schema::dropIfExists('clients');

        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                DROP FUNCTION IF EXISTS fsa_set_request_context(text, text, text);

                CREATE OR REPLACE FUNCTION fsa_set_request_context(
                    p_role text,
                    p_client_ids text
                )
                RETURNS void
                LANGUAGE plpgsql
                AS $$
                BEGIN
                    PERFORM set_config('fsa.role', COALESCE(p_role, ''), false);
                    PERFORM set_config('fsa.client_ids', COALESCE(p_client_ids, ''), false);
                END;
                $$;

                DROP FUNCTION IF EXISTS fsa_current_user_id();
            SQL);
        }
    }

    private function extendRlsContext(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS fsa_set_request_context(text, text);

            CREATE OR REPLACE FUNCTION fsa_set_request_context(
                p_role text,
                p_client_ids text,
                p_user_id text DEFAULT NULL
            )
            RETURNS void
            LANGUAGE plpgsql
            AS $$
            BEGIN
                PERFORM set_config('fsa.role', COALESCE(p_role, ''), false);
                PERFORM set_config('fsa.client_ids', COALESCE(p_client_ids, ''), false);
                PERFORM set_config('fsa.user_id', COALESCE(p_user_id, ''), false);
            END;
            $$;

            CREATE OR REPLACE FUNCTION fsa_current_user_id()
            RETURNS text
            LANGUAGE sql
            STABLE
            AS $$
                SELECT NULLIF(current_setting('fsa.user_id', true), '');
            $$;
        SQL);
    }

    private function installRlsPolicies(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE clients ENABLE ROW LEVEL SECURITY;
            ALTER TABLE clients FORCE ROW LEVEL SECURITY;

            CREATE POLICY clients_scope_select_update_delete ON clients
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR id::text = ANY (fsa_current_client_ids())
                );

            CREATE POLICY clients_advisor_insert ON clients
                FOR INSERT
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system', 'advisor')
                );

            ALTER TABLE client_team ENABLE ROW LEVEL SECURITY;
            ALTER TABLE client_team FORCE ROW LEVEL SECURITY;

            CREATE POLICY client_team_scope_select_update_delete ON client_team
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR user_id::text = fsa_current_user_id()
                    OR client_id::text = ANY (fsa_current_client_ids())
                );

            CREATE POLICY client_team_advisor_insert ON client_team
                FOR INSERT
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system', 'advisor')
                    AND user_id::text = fsa_current_user_id()
                );

            ALTER TABLE conflict_declarations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE conflict_declarations FORCE ROW LEVEL SECURITY;

            CREATE POLICY conflict_declarations_scope ON conflict_declarations
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR advisor_id::text = fsa_current_user_id()
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        advisor_id::text = fsa_current_user_id()
                        AND fsa_current_role() = 'advisor'
                    )
                );
        SQL);
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
