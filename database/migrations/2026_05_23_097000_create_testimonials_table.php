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
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('source_type', 40)->default('nps');
            $table->unsignedTinyInteger('source_score')->nullable();
            $table->text('quote')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->string('display_mode', 24)->default('anonymous');
            $table->string('display_name')->nullable();
            $table->string('status', 32)->default('requested');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('requested_at')->nullable();
            $table->timestampTz('consented_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'status']);
            $table->index(['marketing_consent', 'status']);
            $table->index(['source_type', 'source_score']);
        });

        $this->installRlsPolicy();
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }

    private function installRlsPolicy(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE testimonials ENABLE ROW LEVEL SECURITY;
            ALTER TABLE testimonials FORCE ROW LEVEL SECURITY;

            CREATE POLICY testimonials_client_scope ON testimonials
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
