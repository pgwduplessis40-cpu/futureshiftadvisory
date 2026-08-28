<?php

declare(strict_types=1);

return [
    /*
     * These are release-control targets, not aspirational documentation. The
     * line counts are the ratcheted ceiling until a feature has been extracted.
     * `assert-monolith-size.php --production` enforces the final limits.
     */
    'monoliths' => [
        'app/Http/Controllers/Advisor/ClientController.php' => [
            'ceiling' => 1109,
            'production_limit' => 500,
            'contract_tests' => ['tests/Feature/Advisor'],
        ],
        'app/Http/Controllers/Advisor/EntrepreneurController.php' => [
            'ceiling' => 1492,
            'production_limit' => 500,
            'contract_tests' => ['tests/Feature/Advisor', 'tests/Feature/Entrepreneurs'],
        ],
        'app/Http/Controllers/Portal/EntrepreneurPlanController.php' => [
            'ceiling' => 1342,
            'production_limit' => 500,
            'contract_tests' => ['tests/Feature/Entrepreneurs'],
        ],
        'resources/js/pages/advisor/clients/Show.tsx' => [
            'ceiling' => 7780,
            'production_limit' => 1000,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Advisor'],
        ],
        'resources/js/pages/advisor/Dashboard.tsx' => [
            'ceiling' => 4880,
            'production_limit' => 1000,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Advisor/DashboardTest.php'],
        ],
        'resources/js/pages/portal/entrepreneur/Plan.tsx' => [
            'ceiling' => 5805,
            'production_limit' => 1000,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Entrepreneurs'],
        ],
        'app/Services/Reports/ReportComposer.php' => [
            'ceiling' => 5332,
            'production_limit' => 500,
            'contract_tests' => [
                'tests/Feature/Reports/ReportComposerTest.php',
                'tests/Feature/Npo/GovernanceReviewReportTest.php',
                'tests/Feature/Npo/NpoReportSuiteTest.php',
                'tests/Feature/Npo/FunderAccountabilityReportTest.php',
            ],
        ],
    ],

    /*
     * A model in this registry must use an explicit $fillable allow-list.
     * Its service layer owns transitions for status, identity, authority,
     * amounts, tokens and approvals; request input is never passed through.
     */
    'sensitive_models' => [
        'App\\Models\\Payment' => 'app/Models/Payment.php',
        'App\\Models\\PaymentAuthority' => 'app/Models/PaymentAuthority.php',
        'App\\Models\\PaymentInstallment' => 'app/Models/PaymentInstallment.php',
        'App\\Models\\PaymentSchedule' => 'app/Models/PaymentSchedule.php',
        'App\\Models\\PaymentWebhookEvent' => 'app/Models/PaymentWebhookEvent.php',
        'App\\Models\\Report' => 'app/Models/Report.php',
        'App\\Models\\ReportSection' => 'app/Models/ReportSection.php',
        'App\\Models\\ReportSectionRevision' => 'app/Models/ReportSectionRevision.php',
        'App\\Models\\ReportSectionComment' => 'app/Models/ReportSectionComment.php',
        'App\\Models\\AuditEvent' => 'app/Models/AuditEvent.php',
        'App\\Models\\SecurityAudit' => 'app/Models/SecurityAudit.php',
        'App\\Models\\User' => 'app/Models/User.php',
        'App\\Models\\DeviceRegistration' => 'app/Models/DeviceRegistration.php',
        'App\\Models\\InviteToken' => 'app/Models/InviteToken.php',
        'App\\Models\\ClientTeamMember' => 'app/Models/ClientTeamMember.php',
        'App\\Models\\AdvisorTeamMember' => 'app/Models/AdvisorTeamMember.php',
        'App\\Models\\NpoBoardMember' => 'app/Models/NpoBoardMember.php',
        'App\\Models\\NpoFunderReportLink' => 'app/Models/NpoFunderReportLink.php',
        'App\\Models\\NpoFunderReportSession' => 'app/Models/NpoFunderReportSession.php',
        'App\\Models\\MfaFactor' => 'app/Models/MfaFactor.php',
        'App\\Models\\AdvisorApiClient' => 'app/Models/AdvisorApiClient.php',
        'App\\Models\\IntegrationCredential' => 'app/Models/IntegrationCredential.php',
        'App\\Models\\AccountingConnection' => 'app/Models/AccountingConnection.php',
        'App\\Models\\CalendarConnection' => 'app/Models/CalendarConnection.php',
        'App\\Models\\MailOAuthConnection' => 'app/Models/MailOAuthConnection.php',
        'App\\Models\\NzToolConnection' => 'app/Models/NzToolConnection.php',
        'App\\Models\\PracticeAccountingConnection' => 'app/Models/PracticeAccountingConnection.php',
        'App\\Models\\CryptoRotation' => 'app/Models/CryptoRotation.php',
        'App\\Models\\Consent' => 'app/Models/Consent.php',
        'App\\Models\\ProposalSignoffStep' => 'app/Models/ProposalSignoffStep.php',
        'App\\Models\\ScreenShareConnection' => 'app/Models/ScreenShareConnection.php',
        'App\\Models\\ScreenShareSession' => 'app/Models/ScreenShareSession.php',
        'App\\Models\\ScreenShareSignalMessage' => 'app/Models/ScreenShareSignalMessage.php',
        'App\\Models\\CoBrowseConnection' => 'app/Models/CoBrowseConnection.php',
        'App\\Models\\CoBrowseSession' => 'app/Models/CoBrowseSession.php',
        'App\\Models\\CoBrowseAction' => 'app/Models/CoBrowseAction.php',
        'App\\Models\\DdGuestLink' => 'app/Models/DdGuestLink.php',
    ],
];
