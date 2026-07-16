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
        Schema::table('integration_fee_bands', function (Blueprint $table): void {
            $table->text('scope_description')->nullable()->after('currency');
        });

        DB::table('integration_fee_bands')->update([
            'scope_description' => DB::raw("CASE complexity_band
                WHEN 'S' THEN 'Up to two connected systems and one straightforward one-way workflow. Includes discovery confirmation, field mapping, configuration, testing, handover, and 30 days of support. Excludes custom code, data migration, and legacy or no-API work.'
                WHEN 'M' THEN 'Two to three systems and up to two workflows or one two-way connection. Includes moderate field mapping, workflow configuration, user acceptance testing, monitoring setup, team handover, and 45 days of hypercare. Excludes substantial data migration and bespoke integrations.'
                WHEN 'L' THEN 'Three to five systems, multiple operational or finance workflows, and moderate-to-high transformations. Includes exception handling, functional testing, go-live support, documentation, and 90 days of optimisation. Vendor costs and major data remediation are excluded.'
                ELSE 'Complex multi-system, legacy or no-API, high-volume, or partner-led work. Includes paid discovery, a phased implementation plan, governance, testing, and change control. Detailed scope and price are agreed in staged statements of work.'
            END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('integration_fee_bands', function (Blueprint $table): void {
            $table->dropColumn('scope_description');
        });
    }
};
