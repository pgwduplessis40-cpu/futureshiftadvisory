<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('invite_tokens', 'intended_service_type')) {
                $table->string('intended_service_type', 80)->nullable()->after('target_user_type');
            }

            if (! Schema::hasColumn('invite_tokens', 'intended_package_scope')) {
                $table->string('intended_package_scope', 80)->nullable()->after('intended_service_type');
            }

            $table->index(['intended_service_type', 'intended_package_scope']);
        });

        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('entrepreneur_profiles', 'intended_service_type')) {
                $table->string('intended_service_type', 80)->nullable()->after('invite_token_id');
            }

            if (! Schema::hasColumn('entrepreneur_profiles', 'intended_package_scope')) {
                $table->string('intended_package_scope', 80)->nullable()->after('intended_service_type');
            }

            $table->index(['intended_service_type', 'intended_package_scope']);
        });
    }

    public function down(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table): void {
            $table->dropIndex(['intended_service_type', 'intended_package_scope']);
            $table->dropColumn([
                'intended_service_type',
                'intended_package_scope',
            ]);
        });

        Schema::table('entrepreneur_profiles', function (Blueprint $table): void {
            $table->dropIndex(['intended_service_type', 'intended_package_scope']);
            $table->dropColumn([
                'intended_service_type',
                'intended_package_scope',
            ]);
        });
    }
};
