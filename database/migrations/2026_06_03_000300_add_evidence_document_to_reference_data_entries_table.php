<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reference_data_entries', function (Blueprint $table): void {
            $table->foreignUuid('evidence_document_id')
                ->nullable()
                ->after('learning_update_id')
                ->constrained('documents');

            $table->index('evidence_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('reference_data_entries', function (Blueprint $table): void {
            $table->dropForeign(['evidence_document_id']);
            $table->dropColumn('evidence_document_id');
        });
    }
};
