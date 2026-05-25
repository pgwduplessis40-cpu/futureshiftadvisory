<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\SecurityAudit;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class SecurityAuditManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<int, string>  $scope
     */
    public function prepare(string $period, ?string $auditor = null, array $scope = []): SecurityAudit
    {
        $audit = SecurityAudit::query()->updateOrCreate(
            ['period' => $period],
            [
                'auditor' => $auditor,
                'scope' => $scope === [] ? $this->defaultScope() : array_values($scope),
                'status' => SecurityAudit::STATUS_EVIDENCE_READY,
                'evidence_manifest' => $this->evidenceManifest(),
                'findings' => [],
                'prepared_at' => now(),
                'started_at' => now(),
            ],
        );

        $this->audit->record('security_audit.evidence_prepared', subject: $audit, after: [
            'period' => $audit->period,
            'auditor' => $audit->auditor,
            'scope' => $audit->scope,
        ]);

        return $audit;
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    public function addFinding(SecurityAudit $audit, array $finding): SecurityAudit
    {
        $title = trim((string) ($finding['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Security audit findings require a title.');
        }

        $findings = $audit->findings ?? [];
        $findings[] = [
            'id' => (string) ($finding['id'] ?? Str::uuid()),
            'title' => $title,
            'severity' => (string) ($finding['severity'] ?? 'medium'),
            'owner' => $finding['owner'] ?? null,
            'status' => 'open',
            'summary' => $finding['summary'] ?? null,
            'remediation' => $finding['remediation'] ?? null,
            'opened_at' => now()->toIso8601String(),
            'closed_at' => null,
            'resolution' => null,
        ];

        $audit->forceFill([
            'findings' => $findings,
            'status' => SecurityAudit::STATUS_REMEDIATION,
        ])->save();

        $this->audit->record('security_audit.finding_added', subject: $audit, after: [
            'finding' => Arr::last($findings),
        ]);

        return $audit->refresh();
    }

    public function closeFinding(SecurityAudit $audit, string $findingId, string $resolution): SecurityAudit
    {
        $findings = $audit->findings ?? [];
        $matched = false;

        foreach ($findings as $index => $finding) {
            if (($finding['id'] ?? null) !== $findingId) {
                continue;
            }

            $matched = true;
            $findings[$index]['status'] = 'closed';
            $findings[$index]['resolution'] = $resolution;
            $findings[$index]['closed_at'] = now()->toIso8601String();
        }

        if (! $matched) {
            throw new InvalidArgumentException("Security audit finding [{$findingId}] was not found.");
        }

        $audit->forceFill([
            'findings' => array_values($findings),
            'status' => $this->allFindingsClosed($findings)
                ? SecurityAudit::STATUS_IN_REVIEW
                : SecurityAudit::STATUS_REMEDIATION,
        ])->save();

        $this->audit->record('security_audit.finding_closed', subject: $audit, after: [
            'finding_id' => $findingId,
            'status' => $audit->status,
        ]);

        return $audit->refresh();
    }

    public function closeAudit(SecurityAudit $audit, ?string $reportPath = null): SecurityAudit
    {
        $findings = $audit->findings ?? [];
        if (! $this->allFindingsClosed($findings)) {
            throw new LogicException('Security audit cannot close while findings remain open.');
        }

        $audit->forceFill([
            'status' => SecurityAudit::STATUS_CLOSED,
            'report_path' => $reportPath ?? $audit->report_path,
            'closed_at' => now(),
        ])->save();

        $this->audit->record('security_audit.closed', subject: $audit, after: [
            'period' => $audit->period,
            'report_path' => $audit->report_path,
            'findings_count' => count($findings),
        ]);

        return $audit->refresh();
    }

    /**
     * @return array<int, string>
     */
    public function defaultScope(): array
    {
        return [
            'cryptography',
            'hsm-key-management',
            'row-level-security',
            'audit-trail',
            'ai-integrity',
            'file-upload-security',
            'payments',
            'privacy-and-consent',
            'legal-terms',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function evidenceManifest(): array
    {
        $files = [
            'PLAN.md',
            'PLAN-PHASE4.md',
            'IMPLEMENTATION.md',
            'docs/architecture/security-decisions.md',
            'docs/architecture/key-envelope.md',
            'docs/architecture/hsm-key-management.md',
            'docs/architecture/postgres-rls.md',
            'docs/architecture/audit-trail.md',
            'docs/architecture/session-security.md',
            'docs/architecture/secure-file-storage.md',
            'docs/architecture/ai-integrity.md',
            'docs/architecture/payments.md',
            'docs/legal/terms-v1.md',
        ];

        return [
            'generated_at' => now()->toIso8601String(),
            'checklist_path' => 'docs/security/audit-checklist.md',
            'files' => array_map(fn (string $path): array => [
                'path' => $path,
                'exists' => File::exists(base_path($path)),
                'sha256' => File::exists(base_path($path)) ? hash_file('sha256', base_path($path)) : null,
            ], $files),
            'commands' => [
                'php artisan fsa:audit:verify',
                'php artisan envelopes:rewrap --target=2 --dry-run',
                'php artisan hsm:rotate-kek --dry-run',
                'php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit --no-coverage',
                'npm run lint:check',
                'npm run types:check',
                'npm run format:check',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function allFindingsClosed(array $findings): bool
    {
        foreach ($findings as $finding) {
            if (($finding['status'] ?? 'open') !== 'closed') {
                return false;
            }
        }

        return true;
    }
}
