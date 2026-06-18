<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->string('actor_user_key')->nullable()->after('actor_user_id');
            $table->index(['actor_user_key', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex(['actor_user_key', 'occurred_at']);
            $table->dropColumn('actor_user_key');
        });
    }
};
