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
        Schema::table('integration_fee_bands', function (Blueprint $table): void {
            $table->decimal('hosting_monthly_cost', 12, 2)->nullable()->after('scope_description');
            $table->decimal('hosting_markup_percent', 7, 2)->nullable()->after('hosting_monthly_cost');
        });

        DB::table('integration_fee_bands')
            ->whereNull('hosting_monthly_cost')
            ->update(['hosting_monthly_cost' => 20.66]);
        DB::table('integration_fee_bands')
            ->whereNull('hosting_markup_percent')
            ->update(['hosting_markup_percent' => 100]);

        Schema::table('integration_scopes', function (Blueprint $table): void {
            $table->boolean('fsa_hosting_enabled')->default(false)->after('quoted_fee');
        });
    }

    public function down(): void
    {
        Schema::table('integration_scopes', function (Blueprint $table): void {
            $table->dropColumn('fsa_hosting_enabled');
        });

        Schema::table('integration_fee_bands', function (Blueprint $table): void {
            $table->dropColumn(['hosting_monthly_cost', 'hosting_markup_percent']);
        });
    }
};
