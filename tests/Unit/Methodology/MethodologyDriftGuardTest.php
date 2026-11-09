<?php

declare(strict_types=1);

namespace Tests\Unit\Methodology;

use App\Services\Accounting\IdeaValidationPaymentLedger;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Dashboards\AdvisorDashboardClientScope;
use App\Services\Dashboards\AdvisorPaymentDashboardPayload;
use App\Services\Dashboards\BusinessHealthRadarBuilder;
use App\Services\Dashboards\BusinessHealthSnapshotWriter;
use App\Services\Dashboards\PaymentStatusReport;
use App\Services\DataQuality\DataQualityInsufficientException;
use App\Services\DataQuality\DataQualityScore;
use App\Services\DataQuality\DataQualitySignal;
use App\Services\DataQuality\Gate;
use App\Services\DataQuality\QuestionnaireCompletenessResult;
use App\Services\Dd\AcquisitionPlanRequirements;
use App\Services\Dd\ClientCapability;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdAdviceReportGenerator;
use App\Services\Dd\DdDisclaimer;
use App\Services\Dd\DdOnboarding;
use App\Services\Dd\PlanBuilder;
use App\Services\Dd\PostAcquisition;
use App\Services\Dd\Workstreams\DdEvidenceAssembler;
use App\Services\Dd\Workstreams\DdNzCheckProvider;
use App\Services\Dd\Workstreams\DdWorkstreamModule;
use App\Services\Dd\Workstreams\DdWorkstreamRunner;
use App\Services\Entrepreneurs\AdvisorEntrepreneurCapacity;
use App\Services\Entrepreneurs\AdvisoryConversion;
use App\Services\Entrepreneurs\ApprovedIdeaPlanStarter;
use App\Services\Entrepreneurs\AssessmentEvidenceScopeCorrection;
use App\Services\Entrepreneurs\AssessmentFeedback;
use App\Services\Entrepreneurs\AssessmentScoring;
use App\Services\Entrepreneurs\Benchmarking;
use App\Services\Entrepreneurs\BudgetFundingReadiness;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;
use App\Services\Entrepreneurs\BusinessPlanIdentity;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use App\Services\Entrepreneurs\BusinessPlanSnapshot;
use App\Services\Entrepreneurs\CanonicalEntrepreneurWorkspace;
use App\Services\Entrepreneurs\EntrepreneurBudgetService;
use App\Services\Entrepreneurs\EntrepreneurDocumentTemplate;
use App\Services\Entrepreneurs\EntrepreneurGamification;
use App\Services\Entrepreneurs\EntrepreneurInviteOffer;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Entrepreneurs\EntrepreneurJourney;
use App\Services\Entrepreneurs\EntrepreneurMilestones;
use App\Services\Entrepreneurs\EntrepreneurPlanBudgetCheckout;
use App\Services\Entrepreneurs\EntrepreneurPlanBudgetProvisioner;
use App\Services\Entrepreneurs\EntrepreneurPoints;
use App\Services\Entrepreneurs\EntrepreneurPromptRegistry;
use App\Services\Entrepreneurs\EntrepreneurServiceOffer;
use App\Services\Entrepreneurs\EntrepreneurStreak;
use App\Services\Entrepreneurs\ExecutiveSummaryContext;
use App\Services\Entrepreneurs\ExecutiveSummaryEligibility;
use App\Services\Entrepreneurs\ExecutiveSummaryRecovery;
use App\Services\Entrepreneurs\ExternalIssueReview;
use App\Services\Entrepreneurs\ExternalStripeRefundReconciler;
use App\Services\Entrepreneurs\FixedCostCadenceQuantityGuard;
use App\Services\Entrepreneurs\Forecast;
use App\Services\Entrepreneurs\FounderBriefFinancials;
use App\Services\Entrepreneurs\FounderBriefPages;
use App\Services\Entrepreneurs\FounderBriefReconciliation;
use App\Services\Entrepreneurs\FounderBriefReconciliationFacts;
use App\Services\Entrepreneurs\FounderBriefReconciliationTopics;
use App\Services\Entrepreneurs\FounderBriefRelease;
use App\Services\Entrepreneurs\FounderChangeRequestMessage;
use App\Services\Entrepreneurs\FoundingAdvisoryService;
use App\Services\Entrepreneurs\FunderReadyBriefBuilder;
use App\Services\Entrepreneurs\FunderReadyBusinessPlanBuilder;
use App\Services\Entrepreneurs\Guidance;
use App\Services\Entrepreneurs\IdeaValidationCancellation;
use App\Services\Entrepreneurs\IdeaValidationCheckout;
use App\Services\Entrepreneurs\IdeaValidationRegistrationConflict;
use App\Services\Entrepreneurs\IdeaViabilityGate;
use App\Services\Entrepreneurs\LivingPlan;
use App\Services\Entrepreneurs\PlanAiContext;
use App\Services\Entrepreneurs\PlanBudgetActionMapper;
use App\Services\Entrepreneurs\PlanBudgetActionMessage;
use App\Services\Entrepreneurs\PlanBudgetCoherence;
use App\Services\Entrepreneurs\PlanBudgetFollowUp;
use App\Services\Entrepreneurs\PlanBudgetGuide;
use App\Services\Entrepreneurs\PlanBudgetReviewDecision;
use App\Services\Entrepreneurs\PlanDocuments;
use App\Services\Entrepreneurs\PlanIssueReadiness;
use App\Services\Entrepreneurs\PlanRequirements;
use App\Services\Entrepreneurs\ReleaseGate as PlanReleaseGate;
use App\Services\Fees\PilotFeeWaiverManager;
use App\Services\Fees\ProposalPricingTerms;
use App\Services\Fees\ServiceRateManager;
use App\Services\Integration\IntegrationHealthBander;
use App\Services\Panels\Coach\CoachPanel;
use App\Services\Payments\AmbiguousPaymentOutcome;
use App\Services\Payments\ApplyProposalPaymentOutcome;
use App\Services\Payments\AuthorityCapture;
use App\Services\Payments\BillingAdjustmentAllocator;
use App\Services\Payments\ClientBillingCode;
use App\Services\Payments\DefinitivePaymentDecline;
use App\Services\Payments\Gateway;
use App\Services\Payments\IdeaValidationHistoricalQuote;
use App\Services\Payments\IdeaValidationPaymentIntent;
use App\Services\Payments\IdeaValidationPaymentIntentRequest;
use App\Services\Payments\IdeaValidationPaymentReconciliationService;
use App\Services\Payments\InstallmentPaymentProcessor;
use App\Services\Payments\InstallmentScheduleBuilder;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentAuthorityToken;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentRefundRequest;
use App\Services\Payments\PaymentRefundResult;
use App\Services\Payments\PaymentSetupIntent;
use App\Services\Payments\PaymentWebhookReconciler;
use App\Services\Payments\PaymentWebhookVerifier;
use App\Services\Payments\ReceiptGenerator;
use App\Services\Payments\ScheduleBuilder;
use App\Services\Pv\DiscountRateResult;
use App\Services\Pv\IndustryWaccRefresher;
use App\Services\Pv\PvWaterfallReportChart;
use App\Services\Pv\ValuationMultipleProvider;
use App\Services\Pv\ValuationMultipleRefresher;
use App\Services\Reports\ReportComposer;
use App\Services\Wellbeing\WellbeingCheckinService;
use App\Support\Methodology\MethodologyRegistry;
use App\Support\Methodology\ProvidesMethodology;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Tests\TestCase;

final class MethodologyDriftGuardTest extends TestCase
{
    private const ID_PATTERN = MethodologyRegistry::ID_PATTERN;

    private const DESIGNATED_NAMESPACE_ROOTS = [
        'App\\Services\\Analytics\\',
        'App\\Services\\Dashboards\\',
        'App\\Services\\DataQuality\\',
        'App\\Services\\Dd\\',
        'App\\Services\\Entrepreneurs\\',
        'App\\Services\\Fees\\',
        'App\\Services\\Panels\\Coach\\',
        'App\\Services\\Payments\\',
        'App\\Services\\Pv\\',
        'App\\Services\\Wellbeing\\',
    ];

    private const DESIGNATED_CLASSES = [
        AnalysisRunner::class,
        IntegrationHealthBander::class,
        ReportComposer::class,
    ];

    private const EXCLUDED_CLASSES = [
        BusinessHealthSnapshotWriter::class => 'Delegates radar row construction and only persists snapshots.',
        AdvisorDashboardClientScope::class => 'Scopes visible dashboard clients and entrepreneur workflow records; it does not calculate an advisory methodology.',
        AdvisorPaymentDashboardPayload::class => 'Dashboard payload composer for payment status and reconciliation work; it does not calculate an advisory methodology.',
        PaymentStatusReport::class => 'Summarises payment lifecycle state, not a formula owner.',
        DataQualityInsufficientException::class => 'Exception class.',
        DataQualityScore::class => 'DTO returned by the scorer.',
        DataQualitySignal::class => 'DTO returned by the scorer.',
        Gate::class => 'Policy gate around data quality, not a scoring method.',
        QuestionnaireCompletenessResult::class => 'DTO returned by the completeness calculator.',
        DataRoom::class => 'Data-room file workflow.',
        AcquisitionPlanRequirements::class => 'Plan requirement checklist for the DD portal workflow.',
        ClientCapability::class => 'DD support-mode capture and confirmation workflow, not an advisory methodology surface.',
        DdAdviceReportGenerator::class => 'Readiness orchestration before delegating to the report methodology.',
        DdDisclaimer::class => 'Static disclaimer copy.',
        DdOnboarding::class => 'DD workflow orchestration.',
        PlanBuilder::class => 'Builds plan text around DD artefacts.',
        PostAcquisition::class => 'Workflow migration into advisory after DD.',
        DdEvidenceAssembler::class => 'Evidence assembly helper.',
        DdNzCheckProvider::class => 'External check provider.',
        DdWorkstreamModule::class => 'Analysis module adapter.',
        DdWorkstreamRunner::class => 'Workstream runner/orchestrator.',
        AdvisorEntrepreneurCapacity::class => 'Capacity gate, not a methodology surface in this track.',
        ApprovedIdeaPlanStarter::class => 'Approved Idea Validation snapshot-to-plan seeding workflow; it does not calculate advisor methodology.',
        AdvisoryConversion::class => 'Conversion workflow from entrepreneur to advisory client.',
        AssessmentEvidenceScopeCorrection::class => 'Assessment evidence-map comparison helper; the scoring methodology is owned by Assessment.',
        AssessmentFeedback::class => 'Advisor and founder assessment-feedback drafting workflow, not a methodology calculation.',
        AssessmentScoring::class => 'Shared scoring helper; owned methodology services disclose the scoring formula.',
        Benchmarking::class => 'Privacy-gated cohort report; excluded until a client-safe aggregate methodology is surfaced.',
        BudgetFundingReadiness::class => 'Funding-readiness presentation state; the budget formula is owned by BudgetCalculator.',
        BudgetPackBuilder::class => 'Budget-pack renderer; forecast formula is owned by BudgetCalculator.',
        BusinessPlanExecutiveSummary::class => 'Executive-summary synthesis workflow; it stores reviewed plan wording and does not own advisory scoring methodology.',
        BusinessPlanIdentity::class => 'Business-name resolution helper; it selects existing identity data and does not calculate or disclose advisory methodology.',
        BusinessPlanPreviewRenderer::class => 'Business-plan PDF renderer; requirement completion is presentation state, not an advisory methodology surface.',
        BusinessPlanSnapshot::class => 'Submitted-plan snapshot serializer; assessment scoring methodology is owned by Assessment.',
        CanonicalEntrepreneurWorkspace::class => 'Selects the canonical existing entrepreneur workspace for a client; it does not calculate or disclose advisory methodology.',
        EntrepreneurBudgetService::class => 'Budget persistence workflow; forecast formula is owned by BudgetCalculator.',
        EntrepreneurDocumentTemplate::class => 'Document-template selection helper, not an advisory methodology surface.',
        EntrepreneurGamification::class => 'Gamification payload renderer; requirement and milestone rules are not advisor methodology disclosures.',
        EntrepreneurInviteOffer::class => 'Snapshots admin-set package terms and records invitation consent; pricing and waiver policy are delegated, not an advisory calculation methodology.',
        EntrepreneurInviteReconciler::class => 'Invite reconciliation workflow.',
        EntrepreneurJourney::class => 'Journey-state orchestration that presents existing approval, completion, and advisory facts; it does not calculate a methodology.',
        EntrepreneurMilestones::class => 'Milestone-award persistence workflow.',
        EntrepreneurPlanBudgetCheckout::class => 'Authenticated Stripe checkout orchestration for the BP&B add-on; it does not calculate advisor methodology.',
        EntrepreneurPlanBudgetProvisioner::class => 'Paid BP&B activation workflow that waits for advisor approval; it does not calculate advisor methodology.',
        EntrepreneurPoints::class => 'Gamification reward allocation is operational engagement logic, not an advisor methodology disclosure.',
        EntrepreneurPromptRegistry::class => 'Prompt id registry, not a calculation method.',
        EntrepreneurServiceOffer::class => 'Reads the current Service Rate for offer availability and display; pricing and GST calculations are owned elsewhere.',
        EntrepreneurStreak::class => 'Gamification streak persistence workflow.',
        ExecutiveSummaryContext::class => 'Builds an immutable plan-and-budget fingerprint for summary provenance; it does not calculate advisory guidance.',
        ExecutiveSummaryEligibility::class => 'Enforces the published summary eligibility policy; it does not calculate or disclose an advisory methodology.',
        ExecutiveSummaryRecovery::class => 'Queues and tracks recovery of an eligible summary; it does not calculate an advisory methodology.',
        FounderBriefRelease::class => 'Records an advisor release decision against existing eligibility and document findings; it does not change the assessment or budget calculations.',
        FounderBriefPages::class => 'Composes bounded document extracts from saved plan and Budget outputs; owns no scoring or financial methodology.',
        FounderBriefFinancials::class => 'Presents saved Budget calculations in the founder brief without changing the forecasting methodology.',
        FounderBriefReconciliation::class => 'Records an explicit advisor comparison bound to existing source inputs; owns no scoring or financial calculation methodology.',
        FounderBriefReconciliationTopics::class => 'Defines editorial reconciliation prompts; owns no financial calculations.',
        FounderBriefReconciliationFacts::class => 'Presents existing saved inputs and calculated results for advisor comparison.',
        ExternalStripeRefundReconciler::class => 'Staff-only verification and recovery workflow; it compares exact recorded payment facts without calculating advisory methodology.',
        ExternalIssueReview::class => 'External-issue document-quality gate; plan and budget methodologies are owned elsewhere.',
        FixedCostCadenceQuantityGuard::class => 'Input-quality guard that detects a likely duplicated billing-period count; the forecast methodology is owned by BudgetCalculator.',
        FoundingAdvisoryService::class => 'Founding engagement and rolling-roadmap orchestration; it applies the finalised plan baseline and does not own a separate calculation methodology.',
        FounderChangeRequestMessage::class => 'Founder change-request message workflow.',
        FunderReadyBriefBuilder::class => 'Funder-ready lender brief renderer; it composes approved plan, executive-summary, and Budget Pack outputs without owning a calculation methodology.',
        FunderReadyBusinessPlanBuilder::class => 'Funder-ready plan renderer; it maps existing plan, summary, evidence, and Budget Pack outputs without owning a calculation methodology.',
        Guidance::class => 'AI guidance workflow; formula-like predictive score is not exposed by the methodology surface yet.',
        IdeaValidationCancellation::class => 'Cancellation eligibility and Stripe refund orchestration; it does not calculate the advisor Idea Validation methodology.',
        IdeaValidationCheckout::class => 'Account registration and Stripe checkout orchestration; it does not calculate the advisor Idea Validation methodology.',
        IdeaValidationRegistrationConflict::class => 'Duplicate registration exception; it does not calculate the advisor Idea Validation methodology.',
        IdeaViabilityGate::class => 'Advisor approval gate over the IdeaValidationService methodology, not a separate methodology.',
        LivingPlan::class => 'Plan section workflow.',
        PlanAiContext::class => 'AI drafting-context assembler, not a calculation method.',
        PlanBudgetActionMapper::class => 'Groups existing plan-budget diagnostics into founder actions; the assessment and budget services own the underlying methodologies.',
        PlanBudgetActionMessage::class => 'Formats the canonical plan-budget action payload for the founder change request; it does not calculate a methodology.',
        PlanBudgetFollowUp::class => 'Routes existing assessment findings and records completion state; the assessment owns the underlying methodology.',
        PlanBudgetReviewDecision::class => 'Verifies continuity of existing advisor decisions without changing forecast calculations or assessment requirements.',
        PlanBudgetCoherence::class => 'Assessment evidence reconciliation helper; the assessment methodology is owned by Assessment.',
        PlanBudgetGuide::class => 'Compares explicit source claims with saved results for the existing assessment and brief workflow; it does not set or calculate assumptions.',
        PlanReleaseGate\AssessmentGate::class => 'Assessment capture adapter; release methodology is owned by EvaluateReleaseGate.',
        Forecast\FounderMonth::class => 'Immutable founder-time input contract; calculations are owned by RevenueProjection.',
        Forecast\CashOpening::class => 'Immutable opening-cash input contract; calculations are owned by CashLedger.',
        Forecast\CashMovement::class => 'Immutable sourced cash movement; calculations are owned by CashLedger.',
        Forecast\FundingSource::class => 'Immutable named funding input; its draw is the recorded amount on the recorded date, and the ledger it enters is owned by CashLedger.',
        Forecast\TradeItem::class => 'Immutable obligation and settlement contract; calculations are owned by TradeBalances.',
        Forecast\OwnerPayStep::class => 'Immutable dated pay-policy input; calculations are owned by OwnerPaySchedule.',
        Forecast\AssetInput::class => 'Immutable asset policy input; calculations are owned by AssetSchedule.',
        Forecast\TaxPolicy::class => 'Immutable supplied tax policy; calculations are owned by TaxProjection.',
        Forecast\TaxPeriod::class => 'Immutable tax-period input; calculations are owned by TaxProjection.',
        Forecast\BankOpening::class => 'Immutable cash bridge opening; calculations are owned by BankCashBridge.',
        Forecast\ActualBalances::class => 'Immutable reported balances; validation is owned by ActualMonthReview.',
        Forecast\ActualProfit::class => 'Immutable reported profit lines; validation is owned by ActualMonthReview.',
        Forecast\ActualSource::class => 'Immutable import and review provenance; validation is owned by ActualMonthReview.',
        Forecast\ActualMonth::class => 'Immutable monthly actual input; validation is owned by ActualMonthReview.',
        Forecast\ActualsWindow::class => 'Immutable reviewed window and future-period guard; calculations are owned by ActualsRebase.',
        Forecast\RevenueStream::class => 'Immutable revenue-stream input contract; calculations are owned by RevenueProjection.',
        Forecast\StreamMonth::class => 'Immutable monthly driver input contract; calculations are owned by RevenueProjection.',
        Forecast\ScenarioShock::class => 'Immutable typed shock declaration; application is owned by ScenarioApplication.',
        Forecast\ScenarioDefinition::class => 'Immutable named scenario contract; the required set is owned by ScenarioCatalogue.',
        Forecast\ShockReport::class => 'Immutable requested-against-effective comparison; scenario results are owned by ScenarioApplication.',
        Forecast\ReservePolicy::class => 'Immutable reserve policy and its recorded basis; funding sizing is owned by FundingMeasures.',
        Forecast\DebtService::class => 'Immutable facility terms for one stated period; the cover ratio is owned by LenderMetrics.',
        Forecast\ScenarioOutcome::class => 'Immutable per-scenario result bundle; the comparison is owned by ScenarioRunner.',
        Forecast\Storage\StoredValues::class => 'Typed reads out of stored JSON; computes nothing.',
        Forecast\Storage\DriverCodec::class => 'Rebuilds driver inputs through the engine constructors; computes nothing.',
        Forecast\Storage\ActualsCodec::class => 'Rebuilds reviewed actuals through the engine constructors; computes nothing.',
        Forecast\Storage\ForecastModelCodec::class => 'Decodes a stored reconstruction; every calculation stays with the engine that owns it.',
        Forecast\Storage\ForecastModelInputs::class => 'Immutable bundle of engine input contracts; computes nothing.',
        Forecast\Storage\ForecastEngineVersion::class => 'Names the composite engine version; computes nothing.',
        Forecast\Storage\ForecastModelStore::class => 'Persistence, revision and selection workflow; no financial calculation lives here.',
        PlanReleaseGate\BudgetDraft::class => 'Budget PDF delivery workflow; release methodology is owned by EvaluateReleaseGate.',
        PlanReleaseGate\BudgetFacts::class => 'Adapts saved budget values into sourced release facts; release comparisons are owned by EvaluateReleaseGate.',
        PlanReleaseGate\CandidateBlocks::class => 'Extracts candidate document content for release checks.',
        PlanReleaseGate\CanonicalJson::class => 'Canonical serialisation and hashing helper.',
        PlanReleaseGate\Checks::class => 'Decimal comparison and subcheck helper for the EvaluateReleaseGate methodology.',
        PlanReleaseGate\ClaimVersionStore::class => 'Authorised append-only claim persistence workflow.',
        PlanReleaseGate\DocumentBlock::class => 'Sourced document-content DTO.',
        PlanReleaseGate\DocumentContext::class => 'Document scope and source-facts DTO.',
        PlanReleaseGate\DraftDelivery::class => 'Draft rendering orchestration and source-change guard.',
        PlanReleaseGate\Fact::class => 'Typed sourced fact DTO with decimal conversion.',
        PlanReleaseGate\FactCodec::class => 'Typed fact serialisation helper.',
        PlanReleaseGate\FactSet::class => 'Fact collection lookup helper.',
        PlanReleaseGate\FactValidation::class => 'Fact date, period and horizon validation helper.',
        PlanReleaseGate\FundingPresentation::class => 'Presents cash deficit, reserve shortfall and recorded requests separately; the release methodology owns their checks.',
        PlanReleaseGate\GateContextFactory::class => 'Assembles captured source facts and document scope for the evaluator.',
        PlanReleaseGate\GateReport::class => 'Aggregates and presents evaluated subchecks under the EvaluateReleaseGate methodology.',
        PlanReleaseGate\GateRunRecorder::class => 'Append-only source/result and audit persistence.',
        PlanReleaseGate\GeneratedNarrative::class => 'Audited generated-text hygiene transformation, not a financial calculation.',
        PlanReleaseGate\LegacyGateAdapter::class => 'Maps evaluated release findings into existing guidance.',
        PlanReleaseGate\PdfDraftRenderer::class => 'Primary/fallback PDF rendering adapter.',
        PlanReleaseGate\PhaseOneRules::class => 'Registers the enabled rule implementations for EvaluateReleaseGate.',
        PlanReleaseGate\PlanFacts::class => 'Extracts source claims without selecting new financial assumptions.',
        PlanReleaseGate\ReleaseGate::class => 'Orchestrates policy, risk decisions and the EvaluateReleaseGate methodology.',
        PlanReleaseGate\RenderedDraft::class => 'Rendered draft bytes and candidate metadata DTO.',
        PlanReleaseGate\RiskAcceptance::class => 'Source-bound risk-decision matching helper under the release methodology.',
        PlanReleaseGate\RiskDecisionStore::class => 'Authorised append-only risk-decision persistence workflow.',
        PlanReleaseGate\RuleCatalogue::class => 'Versioned metadata for the rules owned by EvaluateReleaseGate.',
        PlanReleaseGate\RuleConfiguration::class => 'Versioned policy parameters and immutable floors for the release methodology.',
        PlanReleaseGate\RuleDefinition::class => 'Rule metadata and exception-eligibility DTO.',
        PlanReleaseGate\RulePolicyStore::class => 'Authorised append-only policy persistence workflow.',
        PlanReleaseGate\RuleResult::class => 'Four-state subcheck aggregation under the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\AssetClassificationRule::class => 'MI-05 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\CapacityRule::class => 'PM-04 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\CashLedgerRule::class => 'MI-01 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\CommittedCostRule::class => 'PM-09 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\ElapsedActualsRule::class => 'MI-07 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\FixedCostRule::class => 'MI-03 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\FundingPositionRule::class => 'PM-12 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\FundingSourcesRule::class => 'MI-02 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\HorizonRule::class => 'MI-06 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\HygieneRule::class => 'DH-01 through DH-06 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\MarginRule::class => 'PM-07 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\MonthThirteenBridgeRule::class => 'MI-08 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\OpeningCashRule::class => 'PM-03 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\OwnerPayRule::class => 'PM-02 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\ProfitAllocationRule::class => 'PM-08 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\ProfitabilityRule::class => 'PM-01 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\RevenueRampRule::class => 'PM-05 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\RunwayDefinitionRule::class => 'PM-10 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\ScenarioFundingRule::class => 'SS-03 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\ScenarioInputs::class => 'Scenario-input comparison helper for the SS-01 rule owned by EvaluateReleaseGate.',
        PlanReleaseGate\Rules\ScenarioRule::class => 'SS-01 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\SharedCapacityRule::class => 'PM-11 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\StreamMembershipRule::class => 'PM-06 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\StressDimensionRule::class => 'SS-02 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\Rules\TaxRule::class => 'MI-04 implementation within the EvaluateReleaseGate methodology.',
        PlanReleaseGate\SourceCapture::class => 'Captures source data, evidence references and dependency hashes.',
        PlanReleaseGate\SourceRecords::class => 'Captured source-record access and reference helper.',
        PlanReleaseGate\SourceReference::class => 'Source identity and version DTO.',
        PlanReleaseGate\SourceVersion::class => 'Captured source-version DTO.',
        PlanReleaseGate\Subcheck::class => 'Typed rule subcheck result DTO.',
        \App\Services\Entrepreneurs\PlanBuilder::class => 'Plan scaffolding workflow.',
        PlanDocuments::class => 'Document verification helper.',
        PlanIssueReadiness::class => 'External-issue status presentation state; budget and plan rules are owned elsewhere.',
        PlanRequirements::class => 'Static plan requirement checklist; completion is presentation state, not an advisory methodology surface.',
        CoachPanel::class => 'Coach referral workflow orchestration.',
        PilotFeeWaiverManager::class => 'Pilot waiver lifecycle workflow; it does not calculate advisory fees.',
        ProposalPricingTerms::class => 'Proposal pricing-term value object and renderer.',
        AuthorityCapture::class => 'Payment-authority capture workflow.',
        AmbiguousPaymentOutcome::class => 'Payment-recovery outcome DTO.',
        ApplyProposalPaymentOutcome::class => 'Payment application outcome DTO.',
        BillingAdjustmentAllocator::class => 'Ledger-credit allocation workflow, not an advisor methodology disclosure.',
        ClientBillingCode::class => 'Billing-code formatter.',
        IdeaValidationPaymentLedger::class => 'Accounting workflow that records settled payment and refund evidence in Xero; it does not calculate an advisory methodology.',
        DefinitivePaymentDecline::class => 'Payment-decline exception type.',
        Gateway::class => 'Gateway adapter.',
        IdeaValidationHistoricalQuote::class => 'Historical payment-quote DTO supplied as evidence during reconciliation.',
        IdeaValidationPaymentIntent::class => 'DTO returned by the payment gateway setup.',
        IdeaValidationPaymentIntentRequest::class => 'DTO passed to the payment gateway setup.',
        InstallmentPaymentProcessor::class => 'Direct-debit payment processing workflow.',
        InstallmentScheduleBuilder::class => 'Installment persistence workflow.',
        PaymentAuthorityRequest::class => 'DTO.',
        PaymentAuthorityToken::class => 'DTO.',
        PaymentChargeRequest::class => 'DTO.',
        PaymentChargeResult::class => 'DTO.',
        PaymentGatewayException::class => 'Exception class.',
        IdeaValidationPaymentReconciliationService::class => 'Super-admin payment recovery workflow; it verifies recorded Stripe evidence and delegates settlement without owning an advisory calculation methodology.',
        PaymentRefundRequest::class => 'DTO passed to the payment gateway refund operation.',
        PaymentRefundResult::class => 'DTO returned by the payment gateway refund operation.',
        PaymentSetupIntent::class => 'DTO returned by payment gateway setup.',
        PaymentWebhookReconciler::class => 'Webhook persistence and reconciliation workflow.',
        PaymentWebhookVerifier::class => 'Security verification, not methodology disclosure.',
        ReceiptGenerator::class => 'Receipt rendering workflow.',
        ScheduleBuilder::class => 'Payment schedule persistence workflow.',
        DiscountRateResult::class => 'DTO returned by the discount-rate resolver.',
        IndustryWaccRefresher::class => 'Data refresh job.',
        PvWaterfallReportChart::class => 'Report chart renderer.',
        ValuationMultipleProvider::class => 'Multiple lookup provider, not a formula owner.',
        ValuationMultipleRefresher::class => 'Data refresh job.',
        ServiceRateManager::class => 'Admin-set service-rate store and discount provider; fee formulas that consume it are owned by FeeCalculator.',
        WellbeingCheckinService::class => 'Check-in persistence workflow.',
    ];

    public function test_marked_classes_and_registry_entries_round_trip(): void
    {
        $registry = new MethodologyRegistry;
        $entries = $registry->all();

        foreach ($this->markedConcreteClasses() as $class) {
            $ids = $class::methodologyIds();

            $this->assertNotEmpty($ids, "Marked class [{$class}] must declare at least one methodology id.");
            $this->assertSame(array_values(array_unique($ids)), array_values($ids), "Marked class [{$class}] has duplicate methodology ids.");

            foreach ($ids as $id) {
                $this->assertIsString($id, "Marked class [{$class}] ids must be strings.");
                $this->assertMatchesRegularExpression(self::ID_PATTERN, $id, "Marked class [{$class}] id [{$id}] is malformed.");
                $this->assertArrayHasKey($id, $entries, "Marked class [{$class}] declares dangling id [{$id}].");
            }
        }

        foreach ($entries as $entry) {
            $owner = $entry->owningService;
            $reflection = new ReflectionClass($owner);

            $this->assertFalse($reflection->isEnum(), "Methodology [{$entry->id}] owner must not be an enum.");
            $this->assertFalse(str_starts_with($owner, 'App\\Models\\'), "Methodology [{$entry->id}] owner must not be a model.");
            $this->assertFalse(str_starts_with($owner, 'App\\Enums\\'), "Methodology [{$entry->id}] owner must not be an enum.");
            $this->assertTrue($reflection->implementsInterface(ProvidesMethodology::class), "Methodology [{$entry->id}] owner [{$owner}] is not marked.");
            $this->assertContains($entry->id, $owner::methodologyIds(), "Methodology [{$entry->id}] is not declared by owner [{$owner}].");
        }
    }

    public function test_designated_calculation_classes_are_marked_or_explicitly_excluded(): void
    {
        foreach (array_keys(self::EXCLUDED_CLASSES) as $class) {
            $this->assertTrue(class_exists($class), "Methodology scan exclusion [{$class}] no longer exists.");
        }

        $failures = [];

        foreach ($this->designatedConcreteClasses() as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->implementsInterface(ProvidesMethodology::class)) {
                continue;
            }

            if (array_key_exists($class, self::EXCLUDED_CLASSES)) {
                continue;
            }

            $failures[] = $class;
        }

        $this->assertSame([], $failures, 'Designated calculation classes must be marked or explicitly excluded.');
    }

    public function test_referenced_methodology_ids_resolve_to_registry_entries(): void
    {
        $registry = new MethodologyRegistry;

        foreach ($this->referencedMethodologyIds() as $reference => $id) {
            $this->assertArrayHasKey($id, $registry->all(), "Methodology reference [{$reference}] points at unknown id [{$id}].");
        }
    }

    public function test_portal_and_export_sources_do_not_expose_methodology_ids(): void
    {
        $forbiddenReferences = [];

        foreach ($this->forbiddenDisclosureFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            if (is_string($contents) && str_contains($contents, 'methodology_id')) {
                $forbiddenReferences[] = $file->getPathname();
            }
        }

        $this->assertSame([], $forbiddenReferences, 'Portal/client/export sources must not expose internal methodology ids.');
        $this->assertMethodSourceDoesNotContainMethodologyId(BusinessHealthRadarBuilder::class, 'portalPayload');
        $this->assertMethodSourceDoesNotContainMethodologyId(BusinessHealthRadarBuilder::class, 'healthFindingsPayload');
    }

    /**
     * @return array<int, class-string>
     */
    private function markedConcreteClasses(): array
    {
        return array_values(array_filter(
            $this->appConcreteClasses(),
            static fn (string $class): bool => (new ReflectionClass($class))->implementsInterface(ProvidesMethodology::class),
        ));
    }

    /**
     * @return array<int, class-string>
     */
    private function designatedConcreteClasses(): array
    {
        $classes = array_filter(
            $this->appConcreteClasses(),
            static fn (string $class): bool => self::isDesignatedNamespaceClass($class),
        );

        return array_values(array_unique([
            ...$classes,
            ...self::DESIGNATED_CLASSES,
        ]));
    }

    private static function isDesignatedNamespaceClass(string $class): bool
    {
        foreach (self::DESIGNATED_NAMESPACE_ROOTS as $root) {
            if (str_starts_with($class, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, class-string>
     */
    private function appConcreteClasses(): array
    {
        $classes = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $class = $this->classFromAppFile($file);

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return class-string|null
     */
    private function classFromAppFile(SplFileInfo $file): ?string
    {
        $path = $file->getPathname();
        $appPath = app_path();

        if (! str_starts_with($path, $appPath)) {
            return null;
        }

        $relative = substr($path, strlen($appPath) + 1);
        $class = 'App\\'.str_replace(['/', '\\'], '\\', substr($relative, 0, -4));

        /** @var class-string $class */
        return $class;
    }

    /**
     * @return array<string, string>
     */
    private function referencedMethodologyIds(): array
    {
        $references = [];
        $patterns = [
            '/[\'"]methodology_id[\'"]\s*=>\s*[\'"](?P<id>[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*)[\'"]/',
            '/\bmethodology_id\s*:\s*[\'"](?P<id>[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*)[\'"]/',
        ];

        foreach ([app_path(), resource_path('js')] as $root) {
            foreach ($this->phpAndTypescriptFiles($root) as $file) {
                $contents = file_get_contents($file->getPathname());

                if (! is_string($contents)) {
                    continue;
                }

                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        $references[$file->getPathname().':'.$match[0]] = $match['id'];
                    }
                }
            }
        }

        return $references;
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function forbiddenDisclosureFiles(): array
    {
        $roots = [
            app_path('Http/Controllers/Portal'),
            app_path('Http/Controllers/Public'),
            app_path('Services/Pdf'),
            app_path('Services/Portal'),
            app_path('Services/Pptx'),
            app_path('Services/Reports'),
            resource_path('js/pages/portal'),
        ];

        return collect($roots)
            ->filter(fn (string $root): bool => is_dir($root))
            ->flatMap(fn (string $root): array => $this->phpAndTypescriptFiles($root))
            ->values()
            ->all();
    }

    private function assertMethodSourceDoesNotContainMethodologyId(string $class, string $method): void
    {
        $reflection = new ReflectionMethod($class, $method);
        $source = file($reflection->getFileName());

        $this->assertIsArray($source);

        $methodSource = implode('', array_slice(
            $source,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));

        $this->assertStringNotContainsString('methodology_id', $methodSource, "{$class}::{$method} must remain client safe.");
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function phpFiles(string $root): array
    {
        return $this->filesWithExtensions($root, ['php']);
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function phpAndTypescriptFiles(string $root): array
    {
        return $this->filesWithExtensions($root, ['php', 'ts', 'tsx']);
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, SplFileInfo>
     */
    private function filesWithExtensions(string $root, array $extensions): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if (in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
