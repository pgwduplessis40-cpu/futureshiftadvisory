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
        Schema::table('screen_share_connections', function (Blueprint $table): void {
            $table->uuid('client_id')->nullable()->change();
            $table->foreignUuid('entrepreneur_profile_id')
                ->nullable()
                ->after('client_id')
                ->constrained('entrepreneur_profiles')
                ->cascadeOnDelete();
            $table->index(
                ['entrepreneur_profile_id', 'user_id', 'participant_type', 'expires_at'],
                'screen_share_connections_entrepreneur_presence_idx',
            );
        });

        Schema::table('screen_share_sessions', function (Blueprint $table): void {
            $table->uuid('client_id')->nullable()->change();
            $table->foreignUuid('entrepreneur_profile_id')
                ->nullable()
                ->after('client_id')
                ->constrained('entrepreneur_profiles')
                ->cascadeOnDelete();
            $table->index(
                ['entrepreneur_profile_id', 'status', 'expires_at'],
                'screen_share_sessions_entrepreneur_scope_idx',
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE screen_share_connections
                ADD CONSTRAINT screen_share_connections_one_subject
                CHECK (
                    (client_id IS NOT NULL AND entrepreneur_profile_id IS NULL)
                    OR (client_id IS NULL AND entrepreneur_profile_id IS NOT NULL)
                );

                ALTER TABLE screen_share_sessions
                ADD CONSTRAINT screen_share_sessions_one_subject
                CHECK (
                    (client_id IS NOT NULL AND entrepreneur_profile_id IS NULL)
                    OR (client_id IS NULL AND entrepreneur_profile_id IS NOT NULL)
                );
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE screen_share_sessions DROP CONSTRAINT IF EXISTS screen_share_sessions_one_subject');
            DB::statement('ALTER TABLE screen_share_connections DROP CONSTRAINT IF EXISTS screen_share_connections_one_subject');
        }

        Schema::table('screen_share_sessions', function (Blueprint $table): void {
            $table->dropIndex('screen_share_sessions_entrepreneur_scope_idx');
            $table->dropForeign(['entrepreneur_profile_id']);
            $table->dropColumn('entrepreneur_profile_id');
            $table->uuid('client_id')->nullable(false)->change();
        });

        Schema::table('screen_share_connections', function (Blueprint $table): void {
            $table->dropIndex('screen_share_connections_entrepreneur_presence_idx');
            $table->dropForeign(['entrepreneur_profile_id']);
            $table->dropColumn('entrepreneur_profile_id');
            $table->uuid('client_id')->nullable(false)->change();
        });
    }
};
