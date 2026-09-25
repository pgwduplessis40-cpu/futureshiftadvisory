<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_leads', function (Blueprint $table): void {
            $table->string('invite_path', 64)->nullable();
            $table->index(['status', 'invite_path', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('prospect_leads', function (Blueprint $table): void {
            $table->dropIndex(['status', 'invite_path', 'created_at']);
            $table->dropColumn('invite_path');
        });
    }
};
