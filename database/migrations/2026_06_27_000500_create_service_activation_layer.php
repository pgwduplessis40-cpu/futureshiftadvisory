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
        Schema::create('service_rate_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('service_type', 40);
            $table->string('package_name', 160);
            $table->string('client_label', 160);
            $table->string('billing_model', 40);
            $table->decimal('fixed_fee', 12, 2)->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->decimal('retainer_amount', 12, 2)->nullable();
            $table->decimal('purchase_price_min', 14, 2)->nullable();
            $table->decimal('purchase_price_max', 14, 2)->nullable();
            $table->string('currency', 3)->default('NZD');
            $table->text('scope_description');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('effective_from')->useCurrent();
            $table->timestampTz('effective_to')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['service_type', 'is_active', 'effective_from']);
            $table->index(['purchase_price_min', 'purchase_price_max']);
            $table->index('created_by_user_id');
        });

        Schema::create('service_activations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('service_rate_package_id')->nullable()->constrained('service_rate_packages')->nullOnDelete();
            $table->string('service_type', 40);
            $table->string('client_label', 160);
            $table->string('status', 40)->default('requested');
            $table->jsonb('intake')->nullable();
            $table->jsonb('selected_package_snapshot')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('accepted_at')->nullable();
            $table->text('acceptance_text')->nullable();
            $table->jsonb('terms_reference')->nullable();
            $table->foreignUuid('related_dd_engagement_id')->nullable()->constrained('dd_engagements')->nullOnDelete();
            $table->foreignUuid('related_entrepreneur_profile_id')->nullable()->constrained('entrepreneur_profiles')->nullOnDelete();
            $table->foreignUuid('client_message_thread_id')->nullable()->constrained('message_threads')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'service_type', 'status']);
            $table->index(['advisor_id', 'status']);
            $table->index('service_rate_package_id');
            $table->index('related_dd_engagement_id');
            $table->index('related_entrepreneur_profile_id');
        });

        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('entrepreneur_profiles', 'client_id')) {
                $table->foreignUuid('client_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('clients')
                    ->nullOnDelete();
                $table->index('client_id');
            }
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('entrepreneur_profiles', 'client_id')) {
                $table->dropForeign(['client_id']);
                $table->dropIndex(['client_id']);
                $table->dropColumn('client_id');
            }
        });

        Schema::dropIfExists('service_activations');
        Schema::dropIfExists('service_rate_packages');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE service_rate_packages ENABLE ROW LEVEL SECURITY;
            ALTER TABLE service_rate_packages FORCE ROW LEVEL SECURITY;

            CREATE POLICY service_rate_packages_read_scope ON service_rate_packages
                FOR SELECT
                USING (fsa_current_role() IN ('super_admin', 'system', 'advisor', 'junior_advisor', 'entrepreneur_mentor'));

            CREATE POLICY service_rate_packages_write_scope ON service_rate_packages
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));

            ALTER TABLE service_activations ENABLE ROW LEVEL SECURITY;
            ALTER TABLE service_activations FORCE ROW LEVEL SECURITY;

            CREATE POLICY service_activations_scope ON service_activations
                USING (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR advisor_id::text = fsa_current_user_id()
                )
                WITH CHECK (
                    fsa_current_role() IN ('super_admin', 'system')
                    OR client_id::text = ANY (fsa_current_client_ids())
                    OR advisor_id::text = fsa_current_user_id()
                );
        SQL);
    }
};
