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
            if (! Schema::hasColumn('service_rate_settings', 'npo_service_discount_percent')) {
                $table->decimal('npo_service_discount_percent', 5, 2)->default(30)->after('currency');
            }

            if (! Schema::hasColumn('service_rate_settings', 'npo_retainer_discount_percent')) {
                $table->decimal('npo_retainer_discount_percent', 5, 2)->default(35)->after('npo_service_discount_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_rate_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('service_rate_settings', 'npo_retainer_discount_percent')) {
                $table->dropColumn('npo_retainer_discount_percent');
            }

            if (Schema::hasColumn('service_rate_settings', 'npo_service_discount_percent')) {
                $table->dropColumn('npo_service_discount_percent');
            }
        });
    }
};
