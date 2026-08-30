<?php

declare(strict_types=1);

/*
 * Critical coverage is intentionally maintained as paths, not inferred from a
 * class name. Prefixes are bounded source domains; `paths` names high-risk
 * files that must appear in Clover so a new untested critical file cannot be
 * hidden by an unrelated, well-covered neighbour.
 */
return [
    'payments' => [
        'paths' => [
            'app/Models/AccountingInvoice.php',
            'app/Models/AccountingInvoiceBatch.php',
            'app/Models/FeeCalculation.php',
            'app/Models/Payment.php',
            'app/Models/PaymentAuthority.php',
            'app/Models/PaymentInstallment.php',
            'app/Models/PaymentSchedule.php',
            'app/Models/PaymentWebhookEvent.php',
            'app/Models/ServiceRatePackage.php',
            'app/Services/Accounting/ProposalInvoiceScheduler.php',
            'app/Http/Controllers/Advisor/PaymentController.php',
            'app/Http/Controllers/Webhook/PaymentWebhookController.php',
        ],
        'prefixes' => ['app/Services/Payments/'],
    ],
    'dates' => [
        'paths' => [
            'app/Models/CalendarConnection.php',
            'app/Models/CalendarEventMapping.php',
            'app/Http/Controllers/CalendarController.php',
            'app/Http/Controllers/Advisor/CalendarController.php',
            'app/Http/Controllers/Settings/CalendarController.php',
        ],
        'prefixes' => [
            'app/Services/Calendar/',
            'app/Services/Integration/GoogleCalendar/',
        ],
    ],
    'scoring' => [
        'paths' => [
            'app/Models/AdvisoryReadinessSignal.php',
            'app/Models/KnowledgeAssessment.php',
            'app/Models/NpoDimensionScore.php',
            'app/Models/NpoSocialEnterpriseScorecard.php',
            'app/Models/PlanAssessment.php',
            'app/Models/ReadinessAssessment.php',
            'app/Models/StrategicBudgetAssessment.php',
            'app/Jobs/RecomputeDataQualityScore.php',
            'app/Jobs/RunEntrepreneurPlanAssessment.php',
        ],
        'prefixes' => [
            'app/Services/DataQuality/',
            'app/Services/Entrepreneurs/Assessment',
            'app/Services/Entrepreneurs/AdvisoryReadiness.php',
            'app/Services/Entrepreneurs/Readiness.php',
            'app/Services/Npo/NpoHealthScorer.php',
            'app/Services/Npo/SocialEnterpriseAssessment.php',
        ],
    ],
    'calculations' => [
        'paths' => [
            'app/Models/BusinessValuation.php',
            'app/Models/DdValuation.php',
            'app/Models/EntrepreneurBudget.php',
            'app/Models/NpoValueCalculation.php',
            'app/Models/PvCalculation.php',
            'app/Models/StrategicBudget.php',
            'app/Services/Entrepreneurs/BudgetCalculator.php',
            'app/Services/Entrepreneurs/BudgetFundingReadiness.php',
            'app/Services/Entrepreneurs/EntrepreneurBudgetService.php',
            'app/Services/Npo/NpoValueCalculator.php',
        ],
        'prefixes' => [
            'app/Services/Budgets/',
            'app/Services/Fees/',
            'app/Services/Pv/',
            'app/Services/Integrations/IntegrationScopeCalculator.php',
        ],
    ],
    'reports' => [
        'paths' => [
            'app/Jobs/ComposeReport.php',
            'app/Jobs/RerenderReportArtifacts.php',
            'app/Models/Report.php',
            'app/Models/ReportSection.php',
            'app/Models/ReportSectionComment.php',
            'app/Models/ReportSectionRevision.php',
            'app/Http/Controllers/Advisor/ReportController.php',
            'app/Http/Controllers/Portal/ReportController.php',
        ],
        'prefixes' => ['app/Services/Reports/'],
    ],
    'client_screen' => [
        'paths' => [
            'app/Models/CoBrowseAction.php',
            'app/Models/CoBrowseConnection.php',
            'app/Models/CoBrowseSession.php',
            'app/Models/ScreenShareConnection.php',
            'app/Models/ScreenShareSession.php',
            'app/Models/ScreenShareSignalMessage.php',
        ],
        'prefixes' => [
            'app/Http/Controllers/CoBrowse/',
            'app/Http/Controllers/ScreenShare/',
            'app/Services/CoBrowse/',
            'app/Services/ScreenShare/',
        ],
    ],
];
