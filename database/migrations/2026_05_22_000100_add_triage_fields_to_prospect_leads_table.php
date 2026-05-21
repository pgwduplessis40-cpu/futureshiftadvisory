<?php

declare(strict_types=1);

use App\Models\ProspectLead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_leads', function (Blueprint $table): void {
            $table->string('status', 32)->default(ProspectLead::STATUS_NEW)->index();
            $table->foreignId('assigned_advisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dedupe_key', 128)->nullable()->unique();
            $table->char('payload_hash', 64)->nullable()->index();
            $table->jsonb('intake_payload')->nullable();
            $table->string('triage_outcome', 32)->nullable()->index();
            $table->text('triage_notes')->nullable();
            $table->timestampTz('triaged_at')->nullable();
            $table->foreignId('triaged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('invite_token_id')->nullable()->constrained('invite_tokens')->nullOnDelete();

            $table->index(['status', 'created_at']);
            $table->index(['assigned_advisor_user_id', 'status']);
        });

        if ($this->onPostgres()) {
            DB::statement(sprintf(
                "ALTER TABLE prospect_leads ADD CONSTRAINT prospect_leads_status_check CHECK (status IN ('%s'))",
                implode("','", ProspectLead::statuses()),
            ));

            DB::statement(sprintf(
                "ALTER TABLE prospect_leads ADD CONSTRAINT prospect_leads_triage_outcome_check CHECK (triage_outcome IS NULL OR triage_outcome IN ('%s'))",
                implode("','", ProspectLead::triageOutcomes()),
            ));
        }
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::statement('ALTER TABLE prospect_leads DROP CONSTRAINT IF EXISTS prospect_leads_triage_outcome_check');
            DB::statement('ALTER TABLE prospect_leads DROP CONSTRAINT IF EXISTS prospect_leads_status_check');
        }

        Schema::table('prospect_leads', function (Blueprint $table): void {
            $table->dropForeign(['assigned_advisor_user_id']);
            $table->dropForeign(['triaged_by_user_id']);
            $table->dropForeign(['invite_token_id']);

            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['assigned_advisor_user_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropIndex(['payload_hash']);
            $table->dropIndex(['triage_outcome']);
            $table->dropUnique(['dedupe_key']);

            $table->dropColumn([
                'status',
                'assigned_advisor_user_id',
                'dedupe_key',
                'payload_hash',
                'intake_payload',
                'triage_outcome',
                'triage_notes',
                'triaged_at',
                'triaged_by_user_id',
                'invite_token_id',
            ]);
        });
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
