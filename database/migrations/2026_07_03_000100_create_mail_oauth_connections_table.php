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
        Schema::create('mail_oauth_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('provider', 40);
            $table->string('mailbox_email');
            $table->string('external_account_id')->nullable();
            $table->string('status', 32);
            $table->text('access_token_envelope');
            $table->jsonb('access_token_envelope_meta')->nullable();
            $table->text('refresh_token_envelope')->nullable();
            $table->jsonb('refresh_token_envelope_meta')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('connected_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->index(['provider', 'status']);
            $table->index(['provider', 'mailbox_email']);
        });

        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_oauth_connections');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE mail_oauth_connections ENABLE ROW LEVEL SECURITY;
            ALTER TABLE mail_oauth_connections FORCE ROW LEVEL SECURITY;

            CREATE POLICY mail_oauth_connections_admin_scope ON mail_oauth_connections
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
        SQL);
    }
};
