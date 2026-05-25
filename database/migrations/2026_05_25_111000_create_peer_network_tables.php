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
        Schema::table('peer_network_members', function (Blueprint $table): void {
            $table->string('membership_type', 40)->default('benchmark_community')->after('community');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE peer_network_members DROP CONSTRAINT peer_network_members_user_id_community_unique');
            DB::statement('ALTER TABLE peer_network_members DROP CONSTRAINT peer_network_members_community_pseudonym_unique');
        }

        Schema::table('peer_network_members', function (Blueprint $table): void {
            $table->unique(['user_id', 'community', 'membership_type'], 'peer_members_user_community_type_unique');
            $table->unique(['community', 'membership_type', 'pseudonym'], 'peer_members_community_type_pseudonym_unique');
            $table->index(['community', 'membership_type', 'status'], 'peer_members_community_type_status_index');
        });

        Schema::create('peer_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('peer_network_member_id')->constrained('peer_network_members')->cascadeOnDelete();
            $table->string('community', 40);
            $table->text('body');
            $table->timestampTz('posted_at');
            $table->timestampTz('visible_at')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reported_at')->nullable();
            $table->text('report_reason')->nullable();
            $table->timestampsTz();

            $table->index(['community', 'visible_at']);
            $table->index('peer_network_member_id');
            $table->index('reported_by_user_id');
        });

        Schema::create('peer_post_moderation', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('peer_post_id')->unique()->constrained('peer_posts')->cascadeOnDelete();
            $table->string('status', 40)->default('pending');
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('moderated_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'moderated_at']);
            $table->index('moderated_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_post_moderation');
        Schema::dropIfExists('peer_posts');

        Schema::table('peer_network_members', function (Blueprint $table): void {
            $table->dropIndex('peer_members_community_type_status_index');
            $table->dropUnique('peer_members_user_community_type_unique');
            $table->dropUnique('peer_members_community_type_pseudonym_unique');
            $table->dropColumn('membership_type');

            $table->unique(['user_id', 'community']);
            $table->unique(['community', 'pseudonym']);
        });
    }
};
