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
        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->json('source_file')->nullable();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('alter table terms_versions alter column reviewer_reference type text');

            return;
        }

        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->text('reviewer_reference')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->dropColumn('source_file');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('update terms_versions set reviewer_reference = left(reviewer_reference, 255) where length(reviewer_reference) > 255');
            DB::statement('alter table terms_versions alter column reviewer_reference type varchar(255) using reviewer_reference::varchar(255)');

            return;
        }

        Schema::table('terms_versions', function (Blueprint $table): void {
            $table->string('reviewer_reference')->nullable()->change();
        });
    }
};
