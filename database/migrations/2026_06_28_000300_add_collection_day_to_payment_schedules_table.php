<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table): void {
            $table->unsignedTinyInteger('collection_day')->nullable()->after('currency');
            $table->index('collection_day');
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table): void {
            $table->dropIndex(['collection_day']);
            $table->dropColumn('collection_day');
        });
    }
};
