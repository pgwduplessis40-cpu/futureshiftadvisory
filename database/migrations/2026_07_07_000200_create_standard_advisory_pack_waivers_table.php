<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_advisory_pack_waivers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('waived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('modules');
            $table->text('reason');
            $table->timestampTz('waived_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['client_id', 'revoked_at', 'waived_at'], 'standard_advisory_pack_waivers_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_advisory_pack_waivers');
    }
};
