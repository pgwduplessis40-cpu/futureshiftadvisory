<?php

declare(strict_types=1);

use App\Models\CalendarConnection;
use App\Models\CalendarEventMapping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('external_account_id')->nullable();
            $table->string('external_account_email')->nullable();
            $table->text('access_token_envelope');
            $table->jsonb('access_token_envelope_meta')->nullable();
            $table->text('refresh_token_envelope')->nullable();
            $table->jsonb('refresh_token_envelope_meta')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->text('sync_token')->nullable();
            $table->text('delta_link')->nullable();
            $table->string('status', 32)->default(CalendarConnection::STATUS_CONNECTED);
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'provider', 'external_account_id'], 'calendar_connections_account_unique');
            $table->index(['user_id', 'status']);
            $table->index(['provider', 'status']);
        });

        Schema::create('calendar_event_mappings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('calendar_connection_id')->constrained('calendar_connections')->cascadeOnDelete();
            $table->foreignUuid('meeting_id')->nullable()->constrained('meetings')->nullOnDelete();
            $table->string('external_event_id');
            $table->string('etag')->nullable();
            $table->timestampTz('provider_updated_at')->nullable();
            $table->string('direction', 32)->default(CalendarEventMapping::DIRECTION_PUSH);
            $table->string('origin', 32)->default(CalendarEventMapping::ORIGIN_FSA);
            $table->string('title')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->jsonb('attendees')->nullable();
            $table->boolean('is_external_only')->default(false);
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();

            $table->unique(['calendar_connection_id', 'external_event_id'], 'calendar_event_mappings_external_unique');
            $table->index('meeting_id');
            $table->index(['calendar_connection_id', 'is_external_only', 'starts_at']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_mappings');
        Schema::dropIfExists('calendar_connections');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(sprintf(
            <<<'SQL'
                ALTER TABLE calendar_connections
                    ADD CONSTRAINT calendar_connections_provider_check CHECK (provider IN ('%s')),
                    ADD CONSTRAINT calendar_connections_status_check CHECK (status IN ('%s'));

                ALTER TABLE calendar_event_mappings
                    ADD CONSTRAINT calendar_event_mappings_direction_check CHECK (direction IN ('%s')),
                    ADD CONSTRAINT calendar_event_mappings_origin_check CHECK (origin IN ('%s'));

                ALTER TABLE calendar_connections ENABLE ROW LEVEL SECURITY;
                ALTER TABLE calendar_connections FORCE ROW LEVEL SECURITY;

                CREATE POLICY calendar_connections_user_scope ON calendar_connections
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR user_id::text = fsa_current_user_id()
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR user_id::text = fsa_current_user_id()
                    );

                ALTER TABLE calendar_event_mappings ENABLE ROW LEVEL SECURITY;
                ALTER TABLE calendar_event_mappings FORCE ROW LEVEL SECURITY;

                CREATE POLICY calendar_event_mappings_user_scope ON calendar_event_mappings
                    USING (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR EXISTS (
                            SELECT 1
                            FROM calendar_connections
                            WHERE calendar_connections.id = calendar_event_mappings.calendar_connection_id
                              AND calendar_connections.user_id::text = fsa_current_user_id()
                        )
                    )
                    WITH CHECK (
                        fsa_current_role() IN ('super_admin', 'system')
                        OR EXISTS (
                            SELECT 1
                            FROM calendar_connections
                            WHERE calendar_connections.id = calendar_event_mappings.calendar_connection_id
                              AND calendar_connections.user_id::text = fsa_current_user_id()
                        )
                    );
            SQL,
            implode("','", CalendarConnection::providers()),
            implode("','", CalendarConnection::statuses()),
            implode("','", CalendarEventMapping::directions()),
            implode("','", CalendarEventMapping::origins()),
        ));
    }
};
