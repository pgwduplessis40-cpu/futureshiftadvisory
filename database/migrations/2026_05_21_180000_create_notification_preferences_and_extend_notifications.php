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
        Schema::create('communication_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('channel', 32)->default('both');
            $table->string('frequency', 32)->default('immediate');
            $table->string('timezone', 64)->default('Pacific/Auckland');
            $table->timestampsTz();

            $table->index(['channel', 'frequency']);
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->string('type');
                $table->morphs('notifiable');
                $table->jsonb('data');
                $table->string('urgency', 32)->default('normal');
                $table->jsonb('channel_decision')->nullable();
                $table->timestampTz('read_at')->nullable();
                $table->timestampsTz();

                $table->index(['urgency', 'created_at']);
            });

            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('notifications', 'urgency')) {
                $table->string('urgency', 32)->default('normal')->after('data');
            }

            if (! Schema::hasColumn('notifications', 'channel_decision')) {
                $table->jsonb('channel_decision')->nullable()->after('urgency');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('communication_preferences');
    }
};
