<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestampTz('deactivation_requested_at')->nullable()->after('suspended_reason');
            $table->text('deactivation_requested_reason')->nullable()->after('deactivation_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'deactivation_requested_at',
                'deactivation_requested_reason',
            ]);
        });
    }
};
