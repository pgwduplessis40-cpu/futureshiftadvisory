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
        Schema::create('invite_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('email');
            $table->string('target_role', 80);
            $table->string('target_user_type', 40);
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('email');
            $table->index(['target_user_type', 'target_role']);
            $table->index(['expires_at', 'accepted_at']);
        });

        Schema::create('mfa_factors', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24)->default('totp');
            $table->string('label')->nullable();
            $table->text('secret_envelope')->nullable();
            $table->text('recovery_codes_envelope')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'type']);
            $table->index('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_factors');
        Schema::dropIfExists('invite_tokens');
    }
};
