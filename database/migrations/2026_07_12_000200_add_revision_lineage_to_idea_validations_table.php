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
        Schema::table('idea_validations', function (Blueprint $table): void {
            $table->unsignedInteger('revision_number')->default(1)->after('entrepreneur_profile_id');
            $table->foreignUuid('previous_validation_id')
                ->nullable()
                ->after('revision_number')
                ->constrained('idea_validations')
                ->nullOnDelete();
            $table->index('previous_validation_id');
        });

        $revisions = [];
        $previousIds = [];

        DB::table('idea_validations')
            ->select(['id', 'entrepreneur_profile_id'])
            ->orderBy('entrepreneur_profile_id')
            ->orderBy('evaluated_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (object $validation) use (&$revisions, &$previousIds): void {
                $profileId = (string) $validation->entrepreneur_profile_id;
                $revision = ($revisions[$profileId] ?? 0) + 1;

                DB::table('idea_validations')
                    ->where('id', $validation->id)
                    ->update([
                        'revision_number' => $revision,
                        'previous_validation_id' => $previousIds[$profileId] ?? null,
                    ]);

                $revisions[$profileId] = $revision;
                $previousIds[$profileId] = $validation->id;
            });

        Schema::table('idea_validations', function (Blueprint $table): void {
            $table->unique(['entrepreneur_profile_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::table('idea_validations', function (Blueprint $table): void {
            $table->dropForeign(['previous_validation_id']);
            $table->dropUnique(['entrepreneur_profile_id', 'revision_number']);
            $table->dropIndex(['previous_validation_id']);
            $table->dropColumn(['revision_number', 'previous_validation_id']);
        });
    }
};
