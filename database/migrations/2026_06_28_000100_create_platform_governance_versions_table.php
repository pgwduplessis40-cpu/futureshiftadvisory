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
        Schema::create('platform_governance_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->unsignedInteger('version')->unique();
            $table->jsonb('principles');
            $table->jsonb('roles');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestampTz('activated_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('is_active');
            $table->index('created_by_user_id');
        });

        $this->seedInitialVersion();
        $this->installRlsPolicies();
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_governance_versions');
    }

    private function installRlsPolicies(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE platform_governance_versions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE platform_governance_versions FORCE ROW LEVEL SECURITY;

            CREATE POLICY platform_governance_versions_admin_scope ON platform_governance_versions
                USING (fsa_current_role() IN ('super_admin', 'system'))
                WITH CHECK (fsa_current_role() IN ('super_admin', 'system'));
        SQL);
    }

    private function seedInitialVersion(): void
    {
        DB::table('platform_governance_versions')->insert([
            'version' => 1,
            'principles' => json_encode([
                'Every AI output on the Future Shift Advisory platform - analysis, guidance, scoring, recommendation, document review, or resource - must be honest, evidence-based, accurate, free from bias, and truthful.',
                'The reputation of Future Shift Advisory rests on the quality and integrity of the advice this platform provides.',
                'An AI that flatters, inflates, or misleads - even with good intentions - causes harm to the people who rely on it and damage to the practice that cannot be undone.',
                'Accuracy and honesty are not in conflict with care and encouragement. The platform delivers both.',
                'Entrepreneurs are often making life-defining decisions. Many have no prior business experience. If the AI inflates their readiness or misrepresents plan quality, FSA has misled a vulnerable person at a critical life decision point. The human cost is real. The reputational cost to FSA is severe.',
                'Accuracy discrepancies are NEVER suppressed. Not for any reason. Every contradiction between claimed and documented facts is surfaced to the advisor.',
                'Entrepreneurs are often making life-defining decisions. The platform is honest, evidence-based, accurate, free from bias, and truthful always.',
                'Honest assessments are delivered with genuine encouragement to improve. Never one without the other.',
            ], JSON_THROW_ON_ERROR),
            'roles' => json_encode([
                'Mentor | Advisor | Partner',
                'FA&P (Financial Planning and Analysis)',
                'CFO (Chief Financial Officer)',
                'FM (Finance Manager)',
                'COO (Chief Operating Officer)',
                'BA (Business Analyst)',
                'FA (Financial Analyst)',
                'Due diligence professional',
            ], JSON_THROW_ON_ERROR),
            'notes' => 'Initial platform governance baseline.',
            'is_active' => true,
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
