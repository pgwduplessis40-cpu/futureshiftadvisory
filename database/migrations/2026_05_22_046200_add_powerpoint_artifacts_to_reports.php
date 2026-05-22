<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('pptx_path')->nullable()->after('pdf_byte_size');
            $table->unsignedInteger('pptx_byte_size')->nullable()->after('pptx_path');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn(['pptx_path', 'pptx_byte_size']);
        });
    }
};
