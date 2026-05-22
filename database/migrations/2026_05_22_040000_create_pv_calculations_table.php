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
        Schema::create('pv_calculations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('discount_method', 48);
            $table->decimal('discount_rate', 9, 6);
            $table->text('discount_rate_rationale');
            $table->jsonb('inputs');
            $table->jsonb('result');
            $table->timestampTz('as_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('source_attributions');
            $table->timestampsTz();

            $table->index(['client_id', 'type', 'as_at']);
            $table->index(['discount_method', 'as_at']);
            $table->index('created_by_user_id');
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('pv_calculations');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE pv_calculations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE pv_calculations FORCE ROW LEVEL SECURITY;

            CREATE POLICY pv_calculations_client_scope ON pv_calculations
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
