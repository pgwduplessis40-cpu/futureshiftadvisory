<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('risk_score')->default(0)->after('last_activity');
            $table->timestamp('step_up_at')->nullable()->after('risk_score');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn(['risk_score', 'step_up_at']);
        });
    }
};
