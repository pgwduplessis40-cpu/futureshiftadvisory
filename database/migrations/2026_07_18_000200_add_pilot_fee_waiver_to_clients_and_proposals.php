<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('pilot_fee_waiver_enabled')->default(false)->after('onboarding_wizard_state');
            $table->timestampTz('pilot_fee_waiver_starts_at')->nullable()->after('pilot_fee_waiver_enabled');
            $table->timestampTz('pilot_fee_waiver_expires_at')->nullable()->after('pilot_fee_waiver_starts_at');
            $table->text('pilot_fee_waiver_reason')->nullable()->after('pilot_fee_waiver_expires_at');
            $table->foreignId('pilot_fee_waiver_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('pilot_fee_waiver_reason');
            $table->timestampTz('pilot_fee_waiver_approved_at')->nullable()->after('pilot_fee_waiver_approved_by_user_id');
            $table->index(['pilot_fee_waiver_enabled', 'pilot_fee_waiver_expires_at']);
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->jsonb('pricing_terms')->nullable()->after('acceptance_terms');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropColumn('pricing_terms');
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex(['pilot_fee_waiver_enabled', 'pilot_fee_waiver_expires_at']);
            $table->dropConstrainedForeignId('pilot_fee_waiver_approved_by_user_id');
            $table->dropColumn([
                'pilot_fee_waiver_enabled',
                'pilot_fee_waiver_starts_at',
                'pilot_fee_waiver_expires_at',
                'pilot_fee_waiver_reason',
                'pilot_fee_waiver_approved_at',
            ]);
        });
    }
};
