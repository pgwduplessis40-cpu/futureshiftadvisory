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
            $table->string('company_name', 160)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            $table->dropColumn('company_name');
        });
    }
};
