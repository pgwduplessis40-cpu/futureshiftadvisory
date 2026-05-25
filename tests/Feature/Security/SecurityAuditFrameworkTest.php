<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\SecurityAudit;
use App\Services\Security\SecurityAuditManager;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SecurityAuditFrameworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepares_evidence_and_tracks_findings_to_closure(): void
    {
        app(RequestContext::class)->apply('system', []);
        $manager = app(SecurityAuditManager::class);

        $audit = $manager->prepare('2026', 'External Reviewer Ltd', ['crypto', 'rls']);

        $this->assertSame(SecurityAudit::STATUS_EVIDENCE_READY, $audit->status);
        $this->assertSame(['crypto', 'rls'], $audit->scope);
        $this->assertNotEmpty($audit->evidence_manifest['files']);

        $audit = $manager->addFinding($audit, [
            'title' => 'Document production HSM ceremony',
            'severity' => 'high',
            'owner' => 'security',
            'remediation' => 'Attach ceremony record before go-live.',
        ]);
        $findingId = $audit->findings[0]['id'];

        $this->assertSame(SecurityAudit::STATUS_REMEDIATION, $audit->status);

        $audit = $manager->closeFinding($audit, $findingId, 'Ceremony evidence attached.');
        $this->assertSame(SecurityAudit::STATUS_IN_REVIEW, $audit->status);

        $audit = $manager->closeAudit($audit, 'audits/2026/security-legal-report.pdf');
        $this->assertSame(SecurityAudit::STATUS_CLOSED, $audit->status);
        $this->assertSame('audits/2026/security-legal-report.pdf', $audit->report_path);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'security_audit.closed',
            'subject_id' => $audit->id,
        ]);
    }
}
