<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            $table->boolean('pilot_fee_waiver_enabled')->default(false)->after('client_id');
            $table->timestampTz('pilot_fee_waiver_starts_at')->nullable()->after('pilot_fee_waiver_enabled');
            $table->timestampTz('pilot_fee_waiver_expires_at')->nullable()->after('pilot_fee_waiver_starts_at');
            $table->text('pilot_fee_waiver_reason')->nullable()->after('pilot_fee_waiver_expires_at');
            $table->foreignId('pilot_fee_waiver_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('pilot_fee_waiver_reason');
            $table->timestampTz('pilot_fee_waiver_approved_at')->nullable()->after('pilot_fee_waiver_approved_by_user_id');
            $table->index(
                ['pilot_fee_waiver_enabled', 'pilot_fee_waiver_expires_at'],
                'entrepreneur_profiles_pilot_fee_waiver_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            $table->dropIndex('entrepreneur_profiles_pilot_fee_waiver_index');
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
