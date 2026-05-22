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
        Schema::create('red_flags', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('analysis_finding_id')
                ->nullable()
                ->unique()
                ->constrained('analysis_findings')
                ->nullOnDelete();
            $table->string('source_type', 80)->nullable();
            $table->string('source_key', 128)->nullable();
            $table->string('category', 40);
            $table->string('severity', 40);
            $table->string('headline');
            $table->text('detail');
            $table->timestampTz('surfaced_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->unique(['source_type', 'source_key', 'client_id']);
            $table->index(['client_id', 'resolved_at']);
            $table->index(['client_id', 'acknowledged_at']);
            $table->index(['category', 'severity']);
            $table->index('surfaced_at');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('red_flags');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE red_flags ENABLE ROW LEVEL SECURITY;
            ALTER TABLE red_flags FORCE ROW LEVEL SECURITY;

            CREATE POLICY red_flags_client_scope ON red_flags
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                );
        SQL);
    }
};
