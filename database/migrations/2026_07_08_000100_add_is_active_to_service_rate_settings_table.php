<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_rate_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_rate_settings', 'is_active')) {
                $table->boolean('is_active')->default(true);
                $table->index('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_rate_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('service_rate_settings', 'is_active')) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            }
        });
    }
};
