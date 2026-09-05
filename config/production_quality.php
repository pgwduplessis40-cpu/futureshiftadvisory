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
            'ceiling' => 434,
            'production_limit' => 500,
            'contract_tests' => ['tests/Feature/Advisor'],
        ],
        'app/Http/Controllers/Advisor/EntrepreneurController.php' => [
            'ceiling' => 395,
            'production_limit' => 500,
            'contract_tests' => ['tests/Feature/Advisor', 'tests/Feature/Entrepreneurs'],
        ],
        'app/Http/Controllers/Advisor/AdvisorEntrepreneurWorkspacePayload.php' => [
            'ceiling' => 284,
            'production_limit' => 500,
            'contract_tests' => ['tests/Feature/Advisor', 'tests/Feature/Entrepreneurs'],
        ],
        'app/Http/Controllers/Portal/EntrepreneurPlanController.php' => [
            'ceiling' => 404,
            'production_limit' => 500,
            'contract_tests' => ['tests/Feature/Entrepreneurs'],
        ],
        'app/Http/Controllers/Portal/DashboardController.php' => [
            'ceiling' => 1237,
            'production_limit' => 1237,
            'contract_tests' => ['tests/Feature/Portal/PortalWorkspaceDraftTest.php'],
        ],
        'resources/js/pages/advisor/clients/Show.tsx' => [
            'ceiling' => 7780,
            'production_limit' => 1000,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Advisor'],
        ],
        'resources/js/pages/advisor/Dashboard.tsx' => [
            'ceiling' => 376,
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
                'tests/Feature/Entrepreneurs/AssessmentReportTest.php',
                'tests/Feature/Dd/DdReportTest.php',
            ],
        ],
        'app/Services/Budgets/StrategicBudgetService.php' => [
            'ceiling' => 2758,
            'production_limit' => 3000,
            'contract_tests' => ['tests/Feature/Budgets', 'tests/Unit/Entrepreneurs/BudgetCalculatorTest.php'],
        ],
        'resources/js/pages/portal/StrategicPlanBudget.tsx' => [
            'ceiling' => 2616,
            'production_limit' => 3000,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Budgets'],
        ],
        'resources/js/pages/portal/Dashboard.tsx' => [
            'ceiling' => 3732,
            'production_limit' => 3732,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Portal/PortalWorkspaceDraftTest.php'],
        ],
        'resources/js/pages/portal/dd/BusinessPlan.tsx' => [
            'ceiling' => 1386,
            'production_limit' => 1386,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Portal/PortalWorkspaceDraftTest.php'],
        ],
        'resources/js/pages/portal/entrepreneur/plan-workspace-actions.tsx' => [
            'ceiling' => 1040,
            'production_limit' => 1040,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Portal/PortalWorkspaceDraftTest.php'],
        ],
        'resources/js/pages/portal/onboarding/Step.tsx' => [
            'ceiling' => 1130,
            'production_limit' => 1130,
            'contract_tests' => ['resources/js/**/*.test.{ts,tsx}', 'tests/Feature/Portal/PortalWorkspaceDraftTest.php'],
        ],
        'app/Services/Entrepreneurs/BudgetCalculator.php' => [
            'ceiling' => 1360,
            'production_limit' => 1500,
            'contract_tests' => ['tests/Unit/Entrepreneurs/BudgetCalculatorTest.php', 'tests/Feature/Entrepreneurs/BudgetRunwayTest.php'],
        ],
    ],

    // Any newly created source file over this size is rejected. Existing
    // legacy files outside the explicit registry may not grow in a PR.
    'unregistered_source_line_limit' => 1000,

    /*
     * A model in this registry must use an explicit $fillable allow-list.
     * Its service layer owns transitions for status, identity, authority,
     * amounts, tokens and approvals; request input is never passed through.
     */
    'sensitive_models' => [
        'App\\Models\\AccountingInvoice' => 'app/Models/AccountingInvoice.php',
        'App\\Models\\FeeCalculation' => 'app/Models/FeeCalculation.php',
        'App\\Models\\ClientFunderRecord' => 'app/Models/ClientFunderRecord.php',
        'App\\Models\\ServiceRatePackage' => 'app/Models/ServiceRatePackage.php',
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
        'App\\Models\\BillingAdjustment' => 'app/Models/BillingAdjustment.php',
        'App\\Models\\ServiceActivation' => 'app/Models/ServiceActivation.php',
        'App\\Models\\FinancialAlert' => 'app/Models/FinancialAlert.php',
        'App\\Models\\DdEngagement' => 'app/Models/DdEngagement.php',
    ],

    // This count starts after the first financial-model migration. Every
    // touched broad model must move to an explicit allow-list and lower this
    // ceiling; it must never increase.
    'legacy_broad_guarded_model_ceiling' => 146,
];
