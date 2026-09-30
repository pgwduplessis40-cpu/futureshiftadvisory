<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve the original combined-policy evidence while allowing every
     * new public purchase acceptance to bind the Terms of Use and Privacy
     * Policy versions together.
     */
    public function up(): void
    {
        Schema::table('terms_acceptances', function (Blueprint $table): void {
            $table->foreignUuid('privacy_policy_version_id')
                ->nullable()
                ->after('terms_version_id')
                ->constrained('terms_versions')
                ->restrictOnDelete();
        });

        Schema::table('idea_validation_purchases', function (Blueprint $table): void {
            $table->foreignUuid('privacy_policy_version_id')
                ->nullable()
                ->after('terms_version_id')
                ->constrained('terms_versions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('idea_validation_purchases', function (Blueprint $table): void {
            $table->dropForeign(['privacy_policy_version_id']);
            $table->dropColumn('privacy_policy_version_id');
        });

        Schema::table('terms_acceptances', function (Blueprint $table): void {
            $table->dropForeign(['privacy_policy_version_id']);
            $table->dropColumn('privacy_policy_version_id');
        });
    }
};
