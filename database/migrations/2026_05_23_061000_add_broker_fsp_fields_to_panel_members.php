<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_members', function (Blueprint $table): void {
            $table->string('fsp_number')->nullable()->after('application');
            $table->string('fsp_status', 40)->nullable()->after('fsp_number');
            $table->timestampTz('fsp_last_checked_at')->nullable()->after('fsp_status');

            $table->index(['panel_type', 'fsp_status']);
            $table->index('fsp_last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('panel_members', function (Blueprint $table): void {
            $table->dropIndex(['panel_type', 'fsp_status']);
            $table->dropIndex(['fsp_last_checked_at']);
            $table->dropColumn(['fsp_number', 'fsp_status', 'fsp_last_checked_at']);
        });
    }
};
