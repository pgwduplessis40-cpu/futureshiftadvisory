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
        Schema::create('funnel_events', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('flow', 80);
            $table->string('step', 120);
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('entered_at');
            $table->timestampTz('completed_at')->nullable();
            $table->boolean('abandoned')->default(false);
            $table->timestampsTz();

            $table->index(['flow', 'step']);
            $table->index(['client_id', 'flow']);
            $table->index(['user_id', 'flow']);
            $table->index(['entered_at', 'completed_at', 'abandoned']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_events');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE funnel_events ENABLE ROW LEVEL SECURITY;
            ALTER TABLE funnel_events FORCE ROW LEVEL SECURITY;

            CREATE POLICY funnel_events_scope ON funnel_events
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR (
                        client_id IS NULL
                        AND user_id IS NOT NULL
                        AND user_id::text = fsa_current_user_id()
                    )
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR (
                        client_id IS NOT NULL
                        AND client_id::text = ANY (fsa_current_client_ids())
                    )
                    OR (
                        client_id IS NULL
                        AND user_id IS NOT NULL
                        AND user_id::text = fsa_current_user_id()
                    )
                );
        SQL);
    }
};
