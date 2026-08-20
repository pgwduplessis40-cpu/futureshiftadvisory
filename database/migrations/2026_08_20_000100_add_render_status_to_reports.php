<?php

declare(strict_types=1);

use App\Models\Report;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('render_status', 40)
                ->default(Report::RENDER_STATUS_RENDERED)
                ->after('pptx_byte_size');
            $table->timestampTz('render_failed_at')->nullable()->after('render_status');
            $table->text('render_error')->nullable()->after('render_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn(['render_status', 'render_failed_at', 'render_error']);
        });
    }
};
