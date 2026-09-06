<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table): void {
            $table->jsonb('service_offer_snapshot')->nullable();
            $table->jsonb('service_offer_accepted_snapshot')->nullable();
            $table->timestampTz('service_offer_accepted_at')->nullable();
            $table->foreignId('service_offer_accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_offer_accepted_by_user_id');
            $table->dropColumn(['service_offer_snapshot', 'service_offer_accepted_snapshot', 'service_offer_accepted_at']);
        });
    }
};
