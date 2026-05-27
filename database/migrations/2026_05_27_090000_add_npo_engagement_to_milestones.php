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
        Schema::table('milestones', function (Blueprint $table): void {
            $table->uuid('npo_engagement_id')->nullable()->after('client_id');
            $table->index(['npo_engagement_id', 'status'], 'milestones_npo_engagement_status_idx');
        });

        Schema::table('milestone_actions', function (Blueprint $table): void {
            $table->uuid('npo_engagement_id')->nullable()->after('client_id');
            $table->index(['npo_engagement_id', 'status'], 'milestone_actions_npo_engagement_status_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE milestones
                ADD CONSTRAINT milestones_npo_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE milestone_actions
                ADD CONSTRAINT milestone_actions_npo_engagement_client_fk
                FOREIGN KEY (npo_engagement_id, client_id)
                REFERENCES npo_engagements (id, client_id)
                ON DELETE CASCADE
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE milestones DROP CONSTRAINT IF EXISTS milestones_npo_engagement_client_fk');
            DB::statement('ALTER TABLE milestone_actions DROP CONSTRAINT IF EXISTS milestone_actions_npo_engagement_client_fk');
        }

        Schema::table('milestone_actions', function (Blueprint $table): void {
            $table->dropIndex('milestone_actions_npo_engagement_status_idx');
            $table->dropColumn('npo_engagement_id');
        });

        Schema::table('milestones', function (Blueprint $table): void {
            $table->dropIndex('milestones_npo_engagement_status_idx');
            $table->dropColumn('npo_engagement_id');
        });
    }
};
