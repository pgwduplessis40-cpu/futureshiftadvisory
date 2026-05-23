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
        Schema::table('consents', function (Blueprint $table): void {
            $table->foreignId('revoked_by_user_id')->nullable()->after('captured_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable()->after('captured_at');

            $table->index(['client_id', 'type', 'election', 'revoked_at']);
            $table->index('revoked_by_user_id');
        });

        Schema::table('referrals', function (Blueprint $table): void {
            $table->foreignUuid('conflict_declaration_id')->nullable()->after('panel_member_id')->constrained('conflict_declarations')->nullOnDelete();
            $table->foreignUuid('consent_id')->nullable()->after('conflict_declaration_id')->constrained('consents')->nullOnDelete();

            $table->index('conflict_declaration_id');
            $table->index('consent_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE consents ALTER COLUMN proposal_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropIndex(['conflict_declaration_id']);
            $table->dropIndex(['consent_id']);
            $table->dropConstrainedForeignId('conflict_declaration_id');
            $table->dropConstrainedForeignId('consent_id');
        });

        Schema::table('consents', function (Blueprint $table): void {
            $table->dropIndex(['client_id', 'type', 'election', 'revoked_at']);
            $table->dropIndex(['revoked_by_user_id']);
            $table->dropConstrainedForeignId('revoked_by_user_id');
            $table->dropColumn('revoked_at');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE consents ALTER COLUMN proposal_id SET NOT NULL');
        }
    }
};
