<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Security\SecurityAuditManager;
use Illuminate\Console\Command;

final class PrepareSecurityAudit extends Command
{
    protected $signature = 'security-audit:prepare
                            {period? : Audit period label, for example 2026 or 2026-2027.}
                            {--auditor= : External auditor or firm name.}
                            {--scope=* : Repeatable scope item. Defaults to the standard security/legal scope.}';

    protected $description = 'Prepare the annual third-party security and legal audit evidence manifest.';

    public function handle(SecurityAuditManager $manager): int
    {
        $period = $this->argument('period');
        $period = is_string($period) && $period !== '' ? $period : now()->format('Y');
        $auditor = $this->option('auditor');
        $scope = $this->option('scope');

        $audit = $manager->prepare(
            period: $period,
            auditor: is_string($auditor) && $auditor !== '' ? $auditor : null,
            scope: is_array($scope) ? array_values(array_map('strval', $scope)) : [],
        );

        $files = $audit->evidence_manifest['files'] ?? [];

        $this->info(sprintf(
            'Security audit %s prepared with %d evidence file(s).',
            $audit->period,
            is_array($files) ? count($files) : 0,
        ));

        return self::SUCCESS;
    }
}
