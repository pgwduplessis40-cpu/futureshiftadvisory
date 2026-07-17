<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\FeeMethod;
use App\Enums\NpoConversionStatus;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoSocialEnterpriseType;
use App\Enums\NpoTiritiMode;
use App\Enums\QuestionnaireSet;
use App\Enums\ReportType;
use App\Models\BillingAdjustment;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientFunderAlert;
use App\Models\Consent;
use App\Models\Document;
use App\Models\EconomicIndicator;
use App\Models\FeeCalculation;
use App\Models\Funder;
use App\Models\IntegrationFeeBand;
use App\Models\IntegrationScope;
use App\Models\LearningUpdate;
use App\Models\NpoComplianceAlert;
use App\Models\NpoDimensionScore;
use App\Models\NpoTensionAnalysis;
use App\Models\NpoValueCalculation;
use App\Models\OutcomeFollowUp;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\Questionnaire;
use App\Models\QuoteSourceExtraction;
use App\Models\QuoteSourceExtractionDocument;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\StrategicBudget;
use App\Models\StrategicPlan;
use App\Models\StrategicPlanMilestone;
use App\Models\Template;
use App\Models\User;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Fees\FeeCalculator;
use App\Services\Integrations\IntegrationScopeService;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Proposals\ProposalBuilder;
use App\Services\Proposals\SignedProposalEvidenceRenderer;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

final class TestingSeedDataSeeder extends Seeder
{
    private CarbonInterface $now;

    /**
     * @var array<string, User>
     */
    private array $users = [];

    /**
     * @var array<string, Client>
     */
    private array $clients = [];

    /**
     * @var array<string, string|int|null>
     */
    private array $ids = [];

    public function run(): void
    {
        $this->now = now()->copy()->seconds(0);

        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            TermsVersionSeeder::class,
            StandardAdvisoryQuestionnaireSeeder::class,
            DdSpecificQuestionnaireSeeder::class,
            PostAcquisitionGapQuestionnaireSeeder::class,
            EntrepreneurReadinessQuestionnaireSeeder::class,
            GovernanceReviewQuestionnaireSeeder::class,
            StandardNpoQuestionnaireSeeder::class,
            NzResourceSeeder::class,
            RatingFrameworkSeeder::class,
            FoundingRatingFrameworkValuesSeeder::class,
            UserSeeder::class,
        ]);

        app(RequestContext::class)->apply('system', []);

        DB::transaction(function (): void {
            $this->seedUsers();
            $this->seedProposalTemplate();
            $this->seedProspectIntake();
            $this->seedClients();
            $this->seedClientAllocationTestData();
            $this->seedServicePackagesAndActivationFlow();
            $this->seedClientDocumentsAndQuestionnaires();
            $this->seedFinancialsAndAnalysis();
            $this->seedIntegrationEfficiencyService();
            $this->seedEntrepreneurJourney();
            $this->seedIdeaValidationTestScenarios();
            $this->seedGoalsProposalsAndPayments();
            $this->seedNpoModuleData();
            $this->seedEngagementTouchpoints();
            $this->seedPanelAndReferralData();
            $this->seedDueDiligenceJourney();
            $this->seedOutcomeFollowUpFixtures();
            $this->seedStrategicPlanTestData();
            $this->seedBulkCommunicationsAndExpiryReminders();
        });
    }

    private function seedUsers(): void
    {
        $records = [
            'admin' => ['Seed Super Admin', 'seed.admin@futureshiftadvisory.test', User::TYPE_SUPER_ADMIN, 45],
            'advisor' => ['Seed Lead Advisor', 'seed.advisor@futureshiftadvisory.test', User::TYPE_ADVISOR, 30],
            'transferAdvisor' => ['Seed Receiving Advisor', 'seed.receiving.advisor@futureshiftadvisory.test', User::TYPE_ADVISOR, 30],
            'junior' => ['Seed Junior Advisor', 'seed.junior@futureshiftadvisory.test', User::TYPE_JUNIOR_ADVISOR, 30],
            'primary' => ['Seed Client Principal', 'seed.client.primary@futureshiftadvisory.test', User::TYPE_CLIENT_PRIMARY, 20],
            'team' => ['Seed Finance Lead', 'seed.client.team@futureshiftadvisory.test', User::TYPE_CLIENT_TEAM, 20],
            'buyer' => ['Seed Buyer Principal', 'seed.buyer.primary@futureshiftadvisory.test', User::TYPE_CLIENT_PRIMARY, 20],
            'analyst' => ['Seed Buyer Analyst', 'seed.buyer.analyst@futureshiftadvisory.test', User::TYPE_CLIENT_TEAM, 20],
            'entrepreneur' => ['Seed Founder', 'seed.entrepreneur@futureshiftadvisory.test', User::TYPE_ENTREPRENEUR, 20],
            'ideaValidationStart' => ['Seed Idea Validation Starter', 'seed.idea.start@futureshiftadvisory.test', User::TYPE_ENTREPRENEUR, 20],
            'ideaValidationReview' => ['Seed Idea Validation Review', 'seed.idea.review@futureshiftadvisory.test', User::TYPE_ENTREPRENEUR, 20],
            'broker' => ['Seed Broker Partner', 'seed.broker@futureshiftadvisory.test', User::TYPE_BROKER, 20],
            'coach' => ['Seed Coach Partner', 'seed.coach@futureshiftadvisory.test', User::TYPE_COACH, 20],
            'mentor' => ['Seed Entrepreneur Mentor', 'seed.mentor@futureshiftadvisory.test', User::TYPE_ENTREPRENEUR_MENTOR, 20],
            'npoPrimary' => ['Seed NPO Primary', 'seed.npo.primary@futureshiftadvisory.test', User::TYPE_CLIENT_PRIMARY, 20],
            'npoTreasurer' => ['Seed NPO Treasurer', 'seed.npo.treasurer@futureshiftadvisory.test', User::TYPE_CLIENT_TEAM, 20],
            'npoBoard' => ['Seed NPO Board Chair', 'seed.npo.board@futureshiftadvisory.test', User::TYPE_NPO_BOARD_MEMBER, 20],
            'socialEnterprise' => ['Seed Social Enterprise Lead', 'seed.social.enterprise@futureshiftadvisory.test', User::TYPE_CLIENT_PRIMARY, 20],
            'suspendedClient' => ['Seed Suspended Contact', 'seed.suspended@futureshiftadvisory.test', User::TYPE_CLIENT_PRIMARY, 10],
        ];

        foreach ($records as $key => [$name, $email, $type, $timeout]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'email_verified_at' => $this->now,
                'password' => Hash::make('password'),
                'user_type' => $type,
                'primary_role' => $type,
                'remember_token' => Str::random(10),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'mfa_enabled_at' => null,
                'mfa_method' => null,
                'last_password_set_at' => $this->now->copy()->subDays(7),
                'session_timeout_minutes' => $timeout,
                'suspended_at' => $key === 'suspendedClient' ? $this->now->copy()->subDays(2) : null,
                'suspended_reason' => $key === 'suspendedClient' ? 'Seeded suspension scenario' : null,
            ])->save();

            if (Role::query()->where('name', $type)->where('guard_name', 'web')->exists()) {
                $user->syncRoles([$type]);
            }

            $this->upsert('communication_preferences', ['user_id' => $user->getKey()], [
                'channel' => match ($key) {
                    'team', 'analyst' => 'email',
                    'broker', 'coach', 'mentor' => 'in_app',
                    default => 'both',
                },
                'frequency' => in_array($key, ['team', 'analyst'], true) ? 'daily_digest' : 'immediate',
                'timezone' => 'Pacific/Auckland',
            ]);

            DB::table('mfa_factors')
                ->where('user_id', $user->getKey())
                ->delete();

            $this->users[$key] = $user->refresh();
        }

        $termsVersionId = DB::table('terms_versions')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->value('id');

        if ($termsVersionId !== null) {
            foreach ($this->users as $key => $user) {
                $this->upsert('terms_acceptances', [
                    'user_id' => $user->getKey(),
                    'terms_version_id' => $termsVersionId,
                ], [
                    'accepted_at' => $this->now->copy()->subDays(6),
                    'declined_at' => null,
                    'expires_at' => null,
                    'reacceptance_notice_queued_at' => null,
                    'signed_pdf_path' => "seed/terms/{$key}-acceptance.pdf",
                    'signed_pdf_sha256_envelope' => hash('sha256', "seed-terms-{$key}"),
                    'signed_pdf_envelope_meta' => $this->json([
                        'algorithm' => 'sha256',
                        'fixture' => true,
                    ]),
                    'signed_pdf_byte_size' => 128_000,
                    'ip' => '127.0.0.1',
                    'user_agent' => 'FutureShift testing seed data',
                ]);
            }
        }
    }

    private function seedProposalTemplate(): void
    {
        $templateFile = $this->proposalTemplateFile();

        if ($templateFile === null) {
            return;
        }

        $this->upsert('templates', [
            'category' => Template::CATEGORY_PROPOSAL,
            'title' => 'FSA Proposal Template',
        ], [
            'body' => '',
            'structure' => $this->json([
                'source_kind' => 'uploaded_file',
                'sections' => [],
                'uploaded_file' => [
                    'stored_path' => $templateFile['path'],
                    'extension' => 'docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'original_name' => $templateFile['original_name'],
                    'byte_size' => $templateFile['byte_size'],
                    'sha256' => $templateFile['sha256'],
                    'uploaded_at' => $this->now->toIso8601String(),
                ],
                'layout' => [
                    'accent_color' => '#2f6f5e',
                    'accent_dark' => '#214f44',
                    'ink_color' => '#17211b',
                    'muted_color' => '#5d6b63',
                    'paper_color' => '#ffffff',
                ],
            ]),
            'source_reference' => 'seed:uploaded-proposal-template',
            'status' => Template::STATUS_ACTIVE,
            'version' => 1,
            'created_by_user_id' => $this->users['admin']->getKey(),
            'learning_update_implementation_id' => null,
        ]);
    }

    /**
     * @return array{path:string, original_name:string, byte_size:int, sha256:string}|null
     */
    private function proposalTemplateFile(): ?array
    {
        $disk = Storage::disk('secure_local');
        $uploadedPath = 'documents/template_file/2026/06/acd58311-4264-4c37-b90e-e15721087e71-fsa-proposal-template.docx';

        try {
            if ($disk->exists($uploadedPath)) {
                $contents = $disk->get($uploadedPath);

                if (is_string($contents)) {
                    return [
                        'path' => $uploadedPath,
                        'original_name' => 'fsa-proposal-template.docx',
                        'byte_size' => $disk->size($uploadedPath),
                        'sha256' => hash('sha256', $contents),
                    ];
                }
            }
        } catch (\Throwable) {
            // Fall through to a generated fixture when the local encrypted upload
            // was written with a different APP_KEY than the current environment.
        }

        if (! class_exists(\ZipArchive::class)) {
            return null;
        }

        $fixturePath = 'documents/template_file/seed/fsa-proposal-template.docx';
        $contents = $this->minimalProposalTemplateDocx();
        $disk->put($fixturePath, $contents);

        return [
            'path' => $fixturePath,
            'original_name' => 'fsa-proposal-template.docx',
            'byte_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ];
    }

    private function minimalProposalTemplateDocx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fsa-proposal-template-');

        if (! is_string($path)) {
            throw new \RuntimeException('Could not create temporary proposal template fixture.');
        }

        $zip = new \ZipArchive;

        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not open proposal template fixture archive.');
        }

        $zip->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>UPLOADED PROPOSAL TEMPLATE [Business Name] [Date]</w:t></w:r></w:p>
    <w:p><w:r><w:t>Valid until [Expiry Date]</w:t></w:r></w:p>
    <w:p><w:r><w:t>$[X,XXX] per month - [X]-month engagement</w:t></w:r></w:p>
    <w:p><w:r><w:t>Estimated ROI: [X]x return on advisory investment in year 1</w:t></w:r></w:p>
    <w:p><w:r><w:t>Based on total identified improvement opportunity PV of $[XXX,XXX]</w:t></w:r></w:p>
    <w:p><w:r><w:t>Prepared for [Client Name]</w:t></w:r></w:p>
    <w:p><w:r><w:t>1. Financial Health Assessment</w:t></w:r></w:p>
    <w:p><w:r><w:t>[Body text - Arial 9.5pt, Dark Grey. State the finding directly in the first sentence. Evidence follows. Every claim is referenced to the source data.]</w:t></w:r></w:p>
  </w:body>
</w:document>
XML);

        $zip->close();

        $contents = file_get_contents($path);
        @unlink($path);

        if (! is_string($contents)) {
            throw new \RuntimeException('Could not read proposal template fixture archive.');
        }

        return $contents;
    }

    private function seedProspectIntake(): void
    {
        $inviteId = $this->upsert('invite_tokens', ['token_hash' => hash('sha256', 'seed-invite-founder')], [
            'email' => 'prospect.founder@example.test',
            'target_role' => User::TYPE_ENTREPRENEUR,
            'target_user_type' => User::TYPE_ENTREPRENEUR,
            'expires_at' => $this->now->copy()->addDays(14),
            'accepted_at' => null,
            'issued_by_user_id' => $this->users['advisor']->getKey(),
            'accepted_by_user_id' => null,
        ]);

        $prospects = [
            [
                'name' => 'Casey New Venture',
                'email' => 'prospect.founder@example.test',
                'phone' => '+64 21 000 1001',
                'company' => 'Aroha Analytics',
                'engagement_interest' => EngagementType::ENTREPRENEUR_MODULE->value,
                'message' => 'Looking for structure before validating a data product for exporters.',
                'source' => 'public_contact_form',
                'status' => 'invited',
                'triage_outcome' => 'invited',
                'triage_notes' => 'Strong founder-market fit and clear New Zealand export angle.',
                'invite_token_id' => $inviteId,
            ],
            [
                'name' => 'Morgan Acquisition',
                'email' => 'prospect.buyer@example.test',
                'phone' => '+64 21 000 1002',
                'company' => 'Southern Lights Holdings',
                'engagement_interest' => EngagementType::DUE_DILIGENCE->value,
                'message' => 'Need acquisition due diligence support before exclusivity expires.',
                'source' => 'advisor_referral',
                'status' => 'new',
                'triage_outcome' => null,
                'triage_notes' => null,
                'invite_token_id' => null,
            ],
            [
                'name' => 'Riley Parked',
                'email' => 'prospect.parked@example.test',
                'phone' => '+64 21 000 1003',
                'company' => 'Parked Pilot Limited',
                'engagement_interest' => EngagementType::STANDARD_ADVISORY->value,
                'message' => 'Too early for a full advisory engagement but worth nurturing.',
                'source' => 'webinar',
                'status' => 'parked',
                'triage_outcome' => 'parked',
                'triage_notes' => 'Park until revenue evidence is available.',
                'invite_token_id' => null,
            ],
        ];

        foreach ($prospects as $prospect) {
            $dedupeKey = hash('sha256', strtolower($prospect['email']).'|'.$prospect['engagement_interest']);

            $this->upsert('prospect_leads', ['dedupe_key' => $dedupeKey], [
                'name' => $prospect['name'],
                'email' => $prospect['email'],
                'phone' => $prospect['phone'],
                'company' => $prospect['company'],
                'engagement_interest' => $prospect['engagement_interest'],
                'message' => $prospect['message'],
                'source' => $prospect['source'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'FutureShift testing seed data',
                'status' => $prospect['status'],
                'assigned_advisor_user_id' => $this->users['advisor']->getKey(),
                'payload_hash' => hash('sha256', $prospect['message']),
                'intake_payload' => $this->json([
                    'fixture' => true,
                    'interest' => $prospect['engagement_interest'],
                    'preferred_contact' => 'email',
                ]),
                'triage_outcome' => $prospect['triage_outcome'],
                'triage_notes' => $prospect['triage_notes'],
                'triaged_at' => $prospect['triage_outcome'] === null ? null : $this->now->copy()->subDays(2),
                'triaged_by_user_id' => $prospect['triage_outcome'] === null ? null : $this->users['advisor']->getKey(),
                'invite_token_id' => $prospect['invite_token_id'],
            ]);
        }
    }

    private function seedClients(): void
    {
        $this->clients['advisory'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000010'],
            [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'status' => ClientStatus::ACTIVE->value,
                'legal_name' => 'Harbour Hive Advisory Limited',
                'trading_name' => 'Harbour Hive',
                'entity_type' => 'NZ Limited Company',
                'address' => [
                    'line1' => '12 Quay Street',
                    'city' => 'Auckland',
                    'region' => 'Auckland',
                    'country' => 'NZ',
                ],
                'gst_registered' => true,
                'directors' => [
                    ['name' => 'Seed Client Principal', 'role' => 'Managing Director'],
                    ['name' => 'Seed Finance Lead', 'role' => 'Finance Lead'],
                ],
                'filing_status' => 'up_to_date',
                'data_quality' => Client::DATA_QUALITY_HIGH,
                'registry_sources' => [
                    'nzbn' => 'seeded',
                    'companies_office' => 'fixture',
                ],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['primary']->getKey(),
                'engagement_type_locked_at' => $this->now->copy()->subDays(10),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['profile', 'team', 'documents', 'questionnaire'],
                    'current_step' => 'advisor_review',
                    'offline_synced_at' => $this->now->copy()->subHour()->toIso8601String(),
                ],
            ],
        );

        $this->clients['dd'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000027'],
            [
                'engagement_type' => EngagementType::DUE_DILIGENCE->value,
                'status' => ClientStatus::ACTIVE->value,
                'legal_name' => 'Southern Lights Holdings Limited',
                'trading_name' => 'Southern Lights',
                'entity_type' => 'NZ Limited Company',
                'address' => [
                    'line1' => '8 Willis Street',
                    'city' => 'Wellington',
                    'region' => 'Wellington',
                    'country' => 'NZ',
                ],
                'gst_registered' => true,
                'directors' => [
                    ['name' => 'Seed Buyer Principal', 'role' => 'Director'],
                ],
                'filing_status' => 'up_to_date',
                'data_quality' => Client::DATA_QUALITY_MEDIUM,
                'registry_sources' => ['nzbn' => 'seeded'],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['buyer']->getKey(),
                'engagement_type_locked_at' => $this->now->copy()->subDays(7),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['profile', 'team', 'documents'],
                    'current_step' => 'dd_scoping',
                ],
            ],
        );

        $this->clients['postAcquisition'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000034'],
            [
                'engagement_type' => EngagementType::POST_ACQUISITION_ADVISORY->value,
                'status' => ClientStatus::ACTIVE->value,
                'legal_name' => 'Kauri Kitchens Group Limited',
                'trading_name' => 'Kauri Kitchens',
                'entity_type' => 'NZ Limited Company',
                'address' => [
                    'line1' => '44 Durham Street',
                    'city' => 'Christchurch',
                    'region' => 'Canterbury',
                    'country' => 'NZ',
                ],
                'gst_registered' => true,
                'directors' => [['name' => 'Seed Buyer Principal', 'role' => 'Director']],
                'filing_status' => 'up_to_date',
                'data_quality' => Client::DATA_QUALITY_MEDIUM,
                'registry_sources' => ['nzbn' => 'seeded'],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['buyer']->getKey(),
                'engagement_type_locked_at' => $this->now->copy()->subDays(3),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['welcome', 'identity', 'business-snapshot', 'goals', 'questionnaire'],
                    'current_step' => 6,
                ],
            ],
        );

        $this->clients['paused'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000041'],
            [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'status' => ClientStatus::PAUSED->value,
                'legal_name' => 'Pause and Pivot Limited',
                'trading_name' => 'Pause and Pivot',
                'entity_type' => 'NZ Limited Company',
                'address' => [
                    'line1' => '5 George Street',
                    'city' => 'Dunedin',
                    'region' => 'Otago',
                    'country' => 'NZ',
                ],
                'gst_registered' => false,
                'directors' => [['name' => 'Seed Suspended Contact', 'role' => 'Founder']],
                'filing_status' => 'extension_requested',
                'data_quality' => Client::DATA_QUALITY_LOW,
                'registry_sources' => ['nzbn' => 'seeded'],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['suspendedClient']->getKey(),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['profile'],
                    'current_step' => 'documents',
                    'pause_reason' => 'Awaiting financial statements',
                ],
            ],
        );

        $this->clients['offboarded'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000058'],
            [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'status' => ClientStatus::OFFBOARDED->value,
                'legal_name' => 'Legacy Loom Limited',
                'trading_name' => 'Legacy Loom',
                'entity_type' => 'NZ Limited Company',
                'address' => [
                    'line1' => '21 Devon Street',
                    'city' => 'New Plymouth',
                    'region' => 'Taranaki',
                    'country' => 'NZ',
                ],
                'gst_registered' => true,
                'directors' => [['name' => 'Seed Client Principal', 'role' => 'Director']],
                'filing_status' => 'up_to_date',
                'data_quality' => Client::DATA_QUALITY_HIGH,
                'registry_sources' => ['nzbn' => 'seeded'],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['primary']->getKey(),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['profile', 'team', 'documents', 'questionnaire', 'offboarding'],
                    'current_step' => 'closed',
                ],
            ],
        );

        $this->clients['suspended'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000065'],
            [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'status' => ClientStatus::SUSPENDED->value,
                'legal_name' => 'Deferred Due Care Limited',
                'trading_name' => 'Deferred Due Care',
                'entity_type' => 'NZ Limited Company',
                'address' => [
                    'line1' => '77 Trafalgar Street',
                    'city' => 'Nelson',
                    'region' => 'Tasman',
                    'country' => 'NZ',
                ],
                'gst_registered' => false,
                'directors' => [['name' => 'Seed Suspended Contact', 'role' => 'Director']],
                'filing_status' => 'overdue',
                'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
                'registry_sources' => ['nzbn' => 'seeded'],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['suspendedClient']->getKey(),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['profile'],
                    'current_step' => 'suspended',
                ],
            ],
        );

        $this->clients['npo'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000072'],
            [
                'engagement_type' => EngagementType::NPO->value,
                'status' => ClientStatus::ACTIVE->value,
                'legal_name' => 'Aroha Community Trust',
                'trading_name' => 'Aroha Community',
                'entity_type' => 'Registered Charity and Incorporated Society',
                'address' => [
                    'line1' => '18 Rimu Road',
                    'city' => 'Hamilton',
                    'region' => 'Waikato',
                    'country' => 'NZ',
                ],
                'gst_registered' => false,
                'directors' => [
                    ['name' => 'Seed NPO Board Chair', 'role' => 'Board chair'],
                    ['name' => 'Seed NPO Treasurer', 'role' => 'Treasurer'],
                ],
                'filing_status' => 'reregistration_in_progress',
                'data_quality' => Client::DATA_QUALITY_HIGH,
                'registry_sources' => [
                    'charities_services' => 'seeded',
                    'companies_office' => 'fixture',
                    'funding_register' => 'seeded',
                ],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['npoPrimary']->getKey(),
                'engagement_type_locked_at' => $this->now->copy()->subDays(24),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['profile', 'team', 'documents', 'questionnaire'],
                    'current_step' => 'advisor_review',
                    'npo_stream' => 'standard_npo',
                ],
            ],
        );

        $this->clients['socialEnterprise'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000089'],
            [
                'engagement_type' => EngagementType::NPO->value,
                'status' => ClientStatus::ACTIVE->value,
                'legal_name' => 'Tupu Community Trading Limited',
                'trading_name' => 'Tupu Trading',
                'entity_type' => 'Social Enterprise Registered Charity',
                'address' => [
                    'line1' => '6 Cuba Street',
                    'city' => 'Wellington',
                    'region' => 'Wellington',
                    'country' => 'NZ',
                ],
                'gst_registered' => true,
                'directors' => [
                    ['name' => 'Seed Social Enterprise Lead', 'role' => 'General Manager'],
                    ['name' => 'Seed NPO Board Chair', 'role' => 'Trustee'],
                ],
                'filing_status' => 'up_to_date',
                'data_quality' => Client::DATA_QUALITY_MEDIUM,
                'registry_sources' => [
                    'charities_services' => 'seeded',
                    'social_enterprise_profile' => 'fixture',
                ],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['socialEnterprise']->getKey(),
                'engagement_type_locked_at' => $this->now->copy()->subDays(16),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['profile', 'team', 'documents', 'questionnaire'],
                    'current_step' => 'impact_metrics',
                    'npo_stream' => 'social_enterprise',
                ],
            ],
        );

        $this->clients['websiteAudit'] = Client::query()->updateOrCreate(
            ['nzbn' => '9429000000133'],
            [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'status' => ClientStatus::ACTIVE->value,
                'legal_name' => 'Website Review Demo Limited',
                'trading_name' => 'Website Review Demo',
                'entity_type' => 'NZ Limited Company',
                'address' => [
                    'line1' => '31 Victoria Street',
                    'city' => 'Wellington',
                    'region' => 'Wellington',
                    'country' => 'NZ',
                ],
                'gst_registered' => true,
                'directors' => [
                    ['name' => 'Seed Client Principal', 'role' => 'Managing Director'],
                ],
                'filing_status' => 'up_to_date',
                'data_quality' => Client::DATA_QUALITY_HIGH,
                'registry_sources' => [
                    'nzbn' => 'seeded',
                    'website_audit_fixture' => true,
                ],
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'primary_contact_user_id' => $this->users['primary']->getKey(),
                'engagement_type_locked_at' => $this->now->copy()->subDays(5),
                'onboarding_wizard_state' => [
                    'completed_steps' => ['profile', 'team', 'documents', 'questionnaire'],
                    'current_step' => 'advisor_review',
                    'fixture' => 'website_audit_confirmation',
                ],
            ],
        );

        $this->seedPvWaterfallClients();
        $this->seedClientTeam();
        $this->seedConflictDeclarations();
    }

    private function seedPvWaterfallClients(): void
    {
        foreach ($this->pvWaterfallClientDefinitions() as $clientKey => $definition) {
            $this->clients[$clientKey] = Client::query()->updateOrCreate(
                ['nzbn' => $definition['nzbn']],
                [
                    'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                    'status' => ClientStatus::ACTIVE->value,
                    'legal_name' => $definition['legal_name'],
                    'trading_name' => $definition['trading_name'],
                    'entity_type' => 'NZ Limited Company',
                    'address' => $definition['address'],
                    'gst_registered' => true,
                    'directors' => $definition['directors'],
                    'filing_status' => 'up_to_date',
                    'data_quality' => $definition['data_quality'],
                    'registry_sources' => [
                        'nzbn' => 'seeded',
                        'pv_waterfall_fixture' => true,
                    ],
                    'created_by_user_id' => $this->users['advisor']->getKey(),
                    'primary_contact_user_id' => null,
                    'engagement_type_locked_at' => $this->now->copy()->subDays($definition['locked_days_ago']),
                    'onboarding_wizard_state' => [
                        'completed_steps' => ['profile', 'documents', 'questionnaire', 'analysis'],
                        'current_step' => 'pv_review',
                        'fixture' => 'pv_waterfall',
                    ],
                ],
            );
        }
    }

    private function seedClientAllocationTestData(): void
    {
        if (! Schema::hasTable('advisor_client_transfer_requests')) {
            return;
        }

        $this->upsert('advisor_client_transfer_requests', [
            'client_id' => $this->clients['websiteAudit']->getKey(),
            'requested_by_user_id' => $this->users['advisor']->getKey(),
            'target_advisor_user_id' => $this->users['transferAdvisor']->getKey(),
        ], [
            'reason' => 'Seeded review scenario: website and digital-channel experience is a better fit with the receiving advisor.',
            'status' => 'pending',
            'decision_reason' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'completed_at' => null,
        ]);
    }

    private function seedClientTeam(): void
    {
        $members = [
            ['advisory', 'advisor', 'lead_advisor', ['dashboard', 'documents', 'questionnaire', 'payments', 'reports']],
            ['advisory', 'junior', 'advisor', ['dashboard', 'documents', 'questionnaire']],
            ['advisory', 'primary', 'primary_contact', ['portal', 'documents', 'questionnaire', 'payments']],
            ['advisory', 'team', 'finance_contact', ['documents', 'payments', 'reports']],
            ['websiteAudit', 'advisor', 'lead_advisor', ['dashboard', 'documents', 'questionnaire', 'reports']],
            ['websiteAudit', 'primary', 'primary_contact', ['portal', 'documents', 'questionnaire', 'reports']],
            ['dd', 'advisor', 'lead_advisor', ['dashboard', 'documents', 'dd', 'reports']],
            ['dd', 'junior', 'advisor', ['documents', 'dd']],
            ['dd', 'buyer', 'primary_contact', ['portal', 'documents', 'dd']],
            ['dd', 'analyst', 'client_team', ['documents', 'dd']],
            ['postAcquisition', 'advisor', 'lead_advisor', ['dashboard', 'documents', 'post_acquisition']],
            ['postAcquisition', 'buyer', 'primary_contact', ['portal', 'documents', 'post_acquisition']],
            ['paused', 'advisor', 'lead_advisor', ['dashboard', 'documents']],
            ['paused', 'suspendedClient', 'primary_contact', ['portal', 'documents']],
            ['offboarded', 'advisor', 'lead_advisor', ['dashboard', 'reports']],
            ['offboarded', 'primary', 'primary_contact', ['portal', 'reports']],
            ['suspended', 'advisor', 'lead_advisor', ['dashboard']],
            ['suspended', 'suspendedClient', 'primary_contact', ['portal']],
            ['npo', 'advisor', 'lead_advisor', ['dashboard', 'documents', 'questionnaire', 'reports', 'npo', 'funding']],
            ['npo', 'junior', 'advisor', ['dashboard', 'documents', 'questionnaire', 'npo']],
            ['npo', 'npoPrimary', 'primary_contact', ['portal', 'documents', 'questionnaire', 'reports', 'npo']],
            ['npo', 'npoTreasurer', 'finance_contact', ['portal', 'documents', 'funding', 'reports', 'npo']],
            ['socialEnterprise', 'advisor', 'lead_advisor', ['dashboard', 'documents', 'questionnaire', 'reports', 'npo', 'social_enterprise']],
            ['socialEnterprise', 'socialEnterprise', 'primary_contact', ['portal', 'documents', 'questionnaire', 'reports', 'npo', 'social_enterprise']],
        ];

        foreach (array_keys($this->pvWaterfallClientDefinitions()) as $clientKey) {
            $members[] = [$clientKey, 'advisor', 'lead_advisor', ['dashboard', 'documents', 'questionnaire', 'reports', 'pv']];
        }

        foreach ($members as [$clientKey, $userKey, $role, $modules]) {
            $this->upsert('client_team', [
                'client_id' => $this->clients[$clientKey]->getKey(),
                'user_id' => $this->users[$userKey]->getKey(),
            ], [
                'role' => $role,
                'granted_modules' => $this->json($modules),
            ]);
        }
    }

    private function seedConflictDeclarations(): void
    {
        foreach (['advisory', 'dd', 'postAcquisition', 'npo', 'socialEnterprise', ...array_keys($this->pvWaterfallClientDefinitions())] as $clientKey) {
            $this->ids["conflict_{$clientKey}"] = $this->upsert('conflict_declarations', [
                'client_id' => $this->clients[$clientKey]->getKey(),
                'advisor_id' => $this->users['advisor']->getKey(),
            ], [
                'declaration' => $this->json([
                    'has_conflict' => $clientKey === 'dd',
                    'summary' => $clientKey === 'dd'
                        ? 'Advisor has prior sector familiarity but no financial interest in the target.'
                        : 'No known conflicts for this seeded engagement.',
                    'mitigation' => 'Reviewed by the seed super admin fixture.',
                ]),
                'declared_at' => $this->now->copy()->subDays(5),
            ]);
        }
    }

    private function seedServicePackagesAndActivationFlow(): void
    {
        if (! Schema::hasTable('service_rate_packages') || ! Schema::hasTable('service_activations')) {
            return;
        }

        $packageIds = [];

        foreach ($this->servicePackageFixtures() as $key => $fixture) {
            $packageIds[$key] = $this->upsert('service_rate_packages', [
                'service_type' => $fixture['service_type'],
                'package_name' => $fixture['package_name'],
            ], [
                'package_scope' => $fixture['package_scope'],
                'package_name' => $fixture['package_name'],
                'client_label' => $fixture['client_label'],
                'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
                'fixed_fee' => $fixture['fixed_fee'],
                'deposit_percent' => $fixture['deposit_percent'],
                'hourly_rate' => null,
                'retainer_amount' => null,
                'purchase_price_min' => $fixture['purchase_price_min'] ?? null,
                'purchase_price_max' => $fixture['purchase_price_max'] ?? null,
                'currency' => 'NZD',
                'scope_description' => $fixture['scope_description'],
                'is_active' => true,
                'effective_from' => $this->now->copy()->subDays(14),
                'effective_to' => null,
                'created_by_user_id' => $this->users['admin']->getKey(),
            ]);
        }

        $this->seedServiceActivationScenario(
            key: 'service_activation_dd_deposit_due',
            clientKey: 'postAcquisition',
            userKey: 'buyer',
            packageId: $packageIds['dd_1m_3m'] ?? null,
            serviceType: ServiceActivation::SERVICE_DUE_DILIGENCE,
            paymentStatus: ServiceActivation::PAYMENT_DEPOSIT_PENDING,
            intake: [
                'target_name' => 'Canterbury Precision Manufacturing',
                'vendor_name' => 'Private vendor',
                'industry' => 'Specialised manufacturing',
                'asking_price' => 1_850_000,
                'timing' => 'Heads of terms expected this month',
                'notes' => 'Seed scenario: card deposit still needs to be paid before the bank transfer balance can be confirmed.',
            ],
        );

        $this->seedServiceActivationScenario(
            key: 'service_activation_dd_balance_due',
            clientKey: 'dd',
            userKey: 'buyer',
            packageId: $packageIds['dd_300k_1m'] ?? null,
            serviceType: ServiceActivation::SERVICE_DUE_DILIGENCE,
            paymentStatus: ServiceActivation::PAYMENT_BALANCE_PENDING,
            intake: [
                'target_name' => 'Target Panel Limited',
                'vendor_name' => 'Seed vendor group',
                'industry' => 'Wholesale and trade supply',
                'asking_price' => 725_000,
                'timing' => 'Indicative offer accepted',
                'notes' => 'Seed scenario: card deposit paid; bank-transfer balance still due.',
            ],
            depositPaid: true,
        );

        $this->seedServiceActivationScenario(
            key: 'service_activation_entrepreneur_payment_due',
            clientKey: 'advisory',
            userKey: 'primary',
            packageId: $packageIds['entrepreneur_combo'] ?? null,
            serviceType: ServiceActivation::SERVICE_ENTREPRENEUR,
            paymentStatus: ServiceActivation::PAYMENT_PENDING,
            intake: [
                'idea_name' => 'HiveOps Advisory Companion',
                'industry' => 'Professional services technology',
                'customer' => 'Small advisory practices',
                'problem' => 'Founder-led advisory firms need a repeatable way to test demand before investing in buildout.',
                'timing' => 'Ready to test in the next quarter',
                'notes' => 'Seed scenario: full card payment required before Test new Business Idea opens.',
            ],
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function servicePackageFixtures(): array
    {
        return [
            'dd_under_300k' => [
                'service_type' => ServiceRatePackage::SERVICE_DUE_DILIGENCE,
                'package_scope' => ServiceRatePackage::SCOPE_DD_UNDER_300K,
                'package_name' => 'Purchase Price - below $300k',
                'client_label' => 'Purchase Price - below $300k',
                'fixed_fee' => 4500,
                'deposit_percent' => 100,
                'purchase_price_min' => 1,
                'purchase_price_max' => 300000,
                'scope_description' => 'Business purchase price under $300k.',
            ],
            'dd_300k_1m' => [
                'service_type' => ServiceRatePackage::SERVICE_DUE_DILIGENCE,
                'package_scope' => ServiceRatePackage::SCOPE_DD_300K_1M,
                'package_name' => 'Purchase price between $300k and $1m',
                'client_label' => 'Purchase price between $300k and $1m',
                'fixed_fee' => 8500,
                'deposit_percent' => 50,
                'purchase_price_min' => 300001,
                'purchase_price_max' => 1000000,
                'scope_description' => 'Business purchase price between $300k and $1m.',
            ],
            'dd_1m_3m' => [
                'service_type' => ServiceRatePackage::SERVICE_DUE_DILIGENCE,
                'package_scope' => ServiceRatePackage::SCOPE_DD_1M_3M,
                'package_name' => 'Purchase price between $1m and $3m',
                'client_label' => 'Purchase price between $1m and $3m',
                'fixed_fee' => 14500,
                'deposit_percent' => 25,
                'purchase_price_min' => 1000001,
                'purchase_price_max' => 3000000,
                'scope_description' => 'Business purchase price is between $1m and $3m.',
            ],
            'entrepreneur_combo' => [
                'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
                'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
                'package_name' => 'Bundle - Idea + Business Plan + Budget',
                'client_label' => 'Bundle - Idea + Business Plan + Budget',
                'fixed_fee' => 4450,
                'deposit_percent' => 100,
                'scope_description' => 'Platform validation, graded plan, budget/runway and revision.',
            ],
            'entrepreneur_plan_budget' => [
                'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
                'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
                'package_name' => 'Full plan + assessment + runway',
                'client_label' => 'Full plan + assessment + runway',
                'fixed_fee' => 3450,
                'deposit_percent' => 100,
                'scope_description' => 'Business plan workspace, budget/runway builder, advisor assessment, and revision round. Up to 14 advisor hours.',
            ],
            'entrepreneur_idea_validation' => [
                'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
                'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
                'package_name' => 'Idea Validation Sprint',
                'client_label' => 'Idea Validation Sprint',
                'fixed_fee' => 1650,
                'deposit_percent' => 100,
                'scope_description' => 'Platform idea validation, AI-supported viability review, advisor gate feedback. Up to 6 advisor hours.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $intake
     */
    private function seedServiceActivationScenario(
        string $key,
        string $clientKey,
        string $userKey,
        string|int|null $packageId,
        string $serviceType,
        string $paymentStatus,
        array $intake,
        bool $depositPaid = false,
    ): void {
        if ($packageId === null || ! isset($this->clients[$clientKey], $this->users[$userKey])) {
            return;
        }

        $package = ServiceRatePackage::query()->find($packageId);

        if (! $package instanceof ServiceRatePackage) {
            return;
        }

        $snapshot = $package->snapshot();
        $depositReference = $depositPaid ? "seed-card-{$key}" : null;
        $depositPaidAt = $depositPaid ? $this->now->copy()->subDays(1) : null;

        $this->ids[$key] = $this->upsert('service_activations', [
            'client_id' => $this->clients[$clientKey]->getKey(),
            'service_type' => $serviceType,
            'client_label' => $serviceType === ServiceActivation::SERVICE_DUE_DILIGENCE
                ? 'Explore buying a business'
                : 'Test new Business Idea',
        ], [
            'requested_by_user_id' => $this->users[$userKey]->getKey(),
            'advisor_id' => $this->users['advisor']->getKey(),
            'approved_by_user_id' => $this->users['advisor']->getKey(),
            'service_rate_package_id' => $package->getKey(),
            'status' => ServiceActivation::STATUS_PACKAGE_SELECTED,
            'intake' => $this->json($intake),
            'selected_package_snapshot' => $this->json($snapshot),
            'payment_status' => $paymentStatus,
            'payment_completed_at' => null,
            'payment_completed_by_user_id' => null,
            'payment_reference' => null,
            'deposit_paid_at' => $depositPaidAt,
            'deposit_paid_by_user_id' => $depositPaid ? $this->users[$userKey]->getKey() : null,
            'deposit_reference' => $depositReference,
            'balance_received_at' => null,
            'balance_received_by_user_id' => null,
            'balance_reference' => null,
            'accepted_by_user_id' => null,
            'accepted_at' => null,
            'acceptance_text' => null,
            'terms_reference' => null,
            'related_dd_engagement_id' => null,
            'related_entrepreneur_profile_id' => null,
            'client_message_thread_id' => null,
            'closed_at' => null,
            'cancelled_at' => null,
            'metadata' => $this->json([
                'fixture' => true,
                'fixture_key' => $key,
                'pricing_source' => 'testing_seed_data',
                'payment_required_before_workspace_access' => true,
                'balance_required_before_workspace_access' => $paymentStatus === ServiceActivation::PAYMENT_BALANCE_PENDING,
            ]),
        ]);
    }

    private function seedClientDocumentsAndQuestionnaires(): void
    {
        $this->ids['doc_financials'] = $this->document(
            key: 'advisory-financial-statements',
            client: $this->clients['advisory'],
            category: 'financial_statement',
            filename: 'harbour-hive-financial-statements.pdf',
            uploader: $this->users['primary'],
            scannerResult: 'clean',
            expiresAt: $this->now->copy()->addDays(29),
            size: 420_000,
        );
        $this->ids['doc_website_audit_financials'] = $this->document(
            key: 'website-audit-financial-statements',
            client: $this->clients['websiteAudit'],
            category: 'financial_statement',
            filename: 'website-review-demo-financial-statements.pdf',
            uploader: $this->users['primary'],
            scannerResult: 'clean',
            expiresAt: $this->now->copy()->addDays(60),
            size: 280_000,
        );
        $this->ids['doc_contract'] = $this->document(
            key: 'advisory-key-supplier-contract',
            client: $this->clients['advisory'],
            category: 'contract',
            filename: 'key-supplier-contract.pdf',
            uploader: $this->users['team'],
            scannerResult: 'clean',
            expiresAt: null,
            size: 260_000,
        );
        $this->ids['doc_insurance_expired'] = $this->document(
            key: 'advisory-expired-insurance',
            client: $this->clients['advisory'],
            category: 'insurance',
            filename: 'expired-liability-certificate.pdf',
            uploader: $this->users['team'],
            scannerResult: 'clean',
            expiresAt: $this->now->copy()->subDays(3),
            size: 180_000,
        );
        $this->ids['doc_dd_target'] = $this->document(
            key: 'dd-target-management-accounts',
            client: $this->clients['dd'],
            category: 'dd_financials',
            filename: 'target-management-accounts.xlsx',
            uploader: $this->users['buyer'],
            scannerResult: 'clean',
            expiresAt: null,
            size: 330_000,
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->ids['doc_dd_contracts'] = $this->document(
            key: 'dd-target-customer-contracts',
            client: $this->clients['dd'],
            category: 'dd_contracts',
            filename: 'top-customer-contracts.pdf',
            uploader: $this->users['analyst'],
            scannerResult: 'pending',
            expiresAt: null,
            size: 510_000,
        );
        $this->ids['doc_voice'] = $this->document(
            key: 'advisory-discovery-call-audio',
            client: $this->clients['advisory'],
            category: 'voice_note',
            filename: 'discovery-call.m4a',
            uploader: $this->users['advisor'],
            scannerResult: 'clean',
            expiresAt: null,
            size: 1_800_000,
            mimeType: 'audio/mp4',
        );

        $standard = $this->seedQuestionnaireResponse(
            client: $this->clients['advisory'],
            set: QuestionnaireSet::STANDARD_ADVISORY,
            submittedBy: $this->users['primary'],
            attachedDocumentId: (string) $this->ids['doc_financials'],
        );
        $this->ids['advisory_response'] = $standard['response_id'];

        $websiteAudit = $this->seedQuestionnaireResponse(
            client: $this->clients['websiteAudit'],
            set: QuestionnaireSet::STANDARD_ADVISORY,
            submittedBy: $this->users['primary'],
            attachedDocumentId: (string) $this->ids['doc_website_audit_financials'],
            answerOverrides: [
                'Describe the business in plain English.' => 'Website Review Demo Limited provides practical business advisory services. Its current website is https://example.com.',
            ],
        );
        $this->ids['website_audit_response'] = $websiteAudit['response_id'];

        $gap = $this->seedQuestionnaireResponse(
            client: $this->clients['postAcquisition'],
            set: QuestionnaireSet::POST_ACQUISITION_GAP,
            submittedBy: $this->users['buyer'],
            attachedDocumentId: (string) $this->ids['doc_contract'],
        );
        $this->ids['post_acquisition_response'] = $gap['response_id'];

        $this->ids['verification_financials'] = $this->verification(
            documentId: (string) $this->ids['doc_financials'],
            context: 'financials-questionnaire',
            client: $this->clients['advisory'],
            claim: 'Annual revenue is supported by the uploaded financial statements.',
            outcome: 'verified',
            confidence: 0.94,
            questionnaireResponseId: (string) $standard['response_id'],
            questionnaireAnswerId: $standard['file_answer_id'],
            questionnaireQuestionId: $standard['file_question_id'],
            questionPrompt: $standard['file_question_prompt'],
        );
        $this->ids['verification_insurance'] = $this->verification(
            documentId: (string) $this->ids['doc_insurance_expired'],
            context: 'expired-insurance',
            client: $this->clients['advisory'],
            claim: 'Public liability insurance is current.',
            outcome: 'discrepancy',
            confidence: 0.89,
            explanation: 'The certificate expiry date is in the past.',
        );
        $this->verification(
            documentId: (string) $this->ids['doc_dd_contracts'],
            context: 'dd-contracts-pending',
            client: $this->clients['dd'],
            claim: 'Top customer contracts have been uploaded for review.',
            outcome: 'pending',
            confidence: null,
        );
    }

    private function seedFinancialsAndAnalysis(): void
    {
        $this->upsert('economic_indicators', [
            'indicator' => 'nz_ocr',
            'period_date' => '2026-05-01',
            'source' => 'seed_rbnz',
        ], [
            'label' => 'NZ Official Cash Rate',
            'value' => 4.7500,
            'unit' => 'percent',
            'source_badge' => 'seeded',
            'degraded' => false,
            'correlation_id' => null,
            'fetched_at' => $this->now->copy()->subDays(2),
            'payload' => $this->json(['fixture' => true]),
        ]);

        $this->upsert('economic_indicators', [
            'indicator' => EconomicIndicator::CPI_ANNUAL,
            'period_date' => '2026-05-01',
            'source' => 'seed_stats_nz',
        ], [
            'label' => 'NZ CPI annual inflation',
            'value' => 2.7000,
            'unit' => 'percent',
            'source_badge' => 'seeded',
            'degraded' => false,
            'correlation_id' => null,
            'fetched_at' => $this->now->copy()->subDays(2),
            'payload' => $this->json(['fixture' => true, 'used_by' => 'strategic_budget_seed']),
        ]);

        $this->upsert('economic_indicators', [
            'indicator' => EconomicIndicator::COMPANY_TAX_RATE,
            'period_date' => '2026-04-01',
            'source' => 'seed_ird',
        ], [
            'label' => 'NZ company tax rate',
            'value' => 28.0000,
            'unit' => 'percent',
            'source_badge' => 'seeded',
            'degraded' => false,
            'correlation_id' => null,
            'fetched_at' => $this->now->copy()->subDays(2),
            'payload' => $this->json(['fixture' => true, 'used_by' => 'strategic_budget_seed']),
        ]);

        $this->ids['exchange_usd'] = $this->upsert('exchange_rates', [
            'base_currency' => 'USD',
            'quote_currency' => 'NZD',
            'rate_date' => '2026-05-20',
            'source' => 'seed_exchange',
        ], [
            'rate' => 1.65000000,
            'source_badge' => 'seeded',
            'degraded' => false,
            'correlation_id' => null,
            'fetched_at' => $this->now->copy()->subDays(2),
            'payload' => $this->json(['fixture' => true]),
        ]);

        $this->upsert('valuation_multiples', ['record_hash' => hash('sha256', 'seed-saas-ebitda-2026q2')], [
            'industry_code' => 'M7000',
            'industry_label' => 'Professional, Scientific and Technical Services',
            'metric' => 'ebitda',
            'multiple_low' => 3.20,
            'multiple_mid' => 4.60,
            'multiple_high' => 6.10,
            'source' => 'seed_market_set',
            'source_badge' => 'seeded',
            'degraded' => false,
            'correlation_id' => null,
            'quarter' => '2026Q2',
            'fetched_at' => $this->now->copy()->subDays(2),
            'superseded_at' => null,
            'payload' => $this->json(['fixture' => true]),
        ]);

        $this->ids['accounting_connection'] = $this->upsert('accounting_connections', [
            'client_id' => $this->clients['advisory']->getKey(),
            'provider' => 'xero',
        ], [
            'external_tenant_id' => 'seed-xero-harbour-hive',
            'status' => 'connected',
            'token_envelope' => encrypt('seed-xero-token'),
            'token_envelope_meta' => $this->json(['fixture' => true, 'algorithm' => 'local']),
            'scopes' => $this->json([
                'accounting.transactions.read',
                'accounting.reports.balancesheet.read',
                'accounting.reports.profitandloss.read',
                'accounting.reports.banksummary.read',
            ]),
            'connected_by_user_id' => $this->users['primary']->getKey(),
            'connected_at' => $this->now->copy()->subDays(21),
            'revoked_by_user_id' => null,
            'revoked_at' => null,
            'last_snapshot_at' => $this->now->copy()->subDay(),
        ]);

        $previousSnapshotId = $this->firstOrInsert('financial_snapshots', [
            'client_id' => $this->clients['advisory']->getKey(),
            'accounting_connection_id' => $this->ids['accounting_connection'],
            'period_end' => '2026-03-31',
            'source' => 'seeded_xero',
        ], [
            'provider' => 'xero',
            'period_start' => '2026-03-01',
            'source_badge' => 'seeded',
            'degraded' => false,
            'correlation_id' => null,
            'profit_and_loss' => $this->json(['revenue' => 245000, 'gross_profit' => 142000, 'ebitda' => 38000]),
            'balance_sheet' => $this->json(['cash' => 96000, 'receivables' => 41000, 'liabilities' => 73000]),
            'cash_flow' => $this->json(['operating' => 28000, 'investing' => -7000, 'financing' => -4000]),
            'metrics' => $this->json(['gross_margin' => 0.58, 'debtor_days' => 42, 'cash_runway_months' => 5.8]),
            'pulled_at' => $this->now->copy()->subDays(32),
        ]);

        $currentSnapshotId = $this->firstOrInsert('financial_snapshots', [
            'client_id' => $this->clients['advisory']->getKey(),
            'accounting_connection_id' => $this->ids['accounting_connection'],
            'period_end' => '2026-04-30',
            'source' => 'seeded_xero',
        ], [
            'provider' => 'xero',
            'period_start' => '2026-04-01',
            'source_badge' => 'seeded',
            'degraded' => false,
            'correlation_id' => null,
            'profit_and_loss' => $this->json(['revenue' => 226000, 'gross_profit' => 124000, 'ebitda' => 29000]),
            'balance_sheet' => $this->json(['cash' => 79000, 'receivables' => 56000, 'liabilities' => 82000]),
            'cash_flow' => $this->json(['operating' => 11000, 'investing' => -9000, 'financing' => -5000]),
            'metrics' => $this->json(['gross_margin' => 0.55, 'debtor_days' => 58, 'cash_runway_months' => 4.2]),
            'pulled_at' => $this->now->copy()->subDay(),
        ]);

        $this->upsert('financial_alerts', ['alert_key' => 'seed-harbour-hive-debtor-days'], [
            'client_id' => $this->clients['advisory']->getKey(),
            'accounting_connection_id' => $this->ids['accounting_connection'],
            'previous_snapshot_id' => $previousSnapshotId,
            'current_snapshot_id' => $currentSnapshotId,
            'category' => 'working_capital',
            'severity' => 'medium',
            'metric' => 'debtor_days',
            'headline' => 'Debtor days are stretching beyond target',
            'detail' => 'Debtor days increased from 42 to 58 month on month.',
            'previous_value' => 42,
            'current_value' => 58,
            'change_amount' => 16,
            'change_percent' => 38.1,
            'citation' => $this->json(['snapshot_ids' => [$previousSnapshotId, $currentSnapshotId]]),
            'surfaced_at' => $this->now->copy()->subHours(20),
            'notified_at' => $this->now->copy()->subHours(19),
        ]);

        $this->ids['analysis_run'] = $this->upsert('analysis_runs', [
            'client_id' => $this->clients['advisory']->getKey(),
            'module' => 'swot',
            'prompt_version' => 'testing-v1',
        ], [
            'status' => 'completed',
            'framework_lenses' => $this->json(['financial', 'operational', 'people', 'risk']),
            'data_quality_snapshot' => $this->json(['score' => 82, 'label' => 'high']),
            'ai_model' => 'seeded-analysis-model',
            'prompt_hash' => hash('sha256', 'testing-v1'),
            'tokens_in' => 8_240,
            'tokens_out' => 2_940,
            'started_at' => $this->now->copy()->subDays(4)->subMinutes(12),
            'completed_at' => $this->now->copy()->subDays(4),
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $this->ids['finding_cash'] = $this->upsert('analysis_findings', [
            'analysis_run_id' => $this->ids['analysis_run'],
            'title' => 'Working-capital drag is slowing growth execution',
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'lens' => 'diagnostic',
            'severity' => 'medium',
            'body' => 'Receivables have increased faster than revenue, creating avoidable cash pressure.',
            'attributions' => $this->json([
                ['type' => 'financial_snapshot', 'id' => $currentSnapshotId],
                ['type' => 'document', 'id' => $this->ids['doc_financials']],
            ]),
            'document_support' => 'supported',
            'uncertainty' => 'medium',
            'data_quality_disclaimer' => 'Seeded diagnostic based on a limited but consistent fixture set.',
            'bias_signals' => $this->json(['overweight_recent_month' => false]),
            'pv_link_id' => null,
        ]);

        $this->ids['finding_people'] = $this->upsert('analysis_findings', [
            'analysis_run_id' => $this->ids['analysis_run'],
            'title' => 'Key-person dependency is concentrated in dispatch',
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'lens' => 'diagnostic',
            'severity' => 'high',
            'body' => 'Dispatch knowledge sits with one operator and is not documented in the operating system.',
            'attributions' => $this->json([
                ['type' => 'questionnaire_response', 'id' => $this->ids['advisory_response']],
            ]),
            'document_support' => 'partial',
            'uncertainty' => 'medium',
            'data_quality_disclaimer' => 'Seeded finding for workflow and red-flag testing.',
            'bias_signals' => $this->json(['single_source_dependency' => true]),
            'pv_link_id' => null,
        ]);

        $this->upsert('analysis_feedback', [
            'analysis_finding_id' => $this->ids['finding_cash'],
            'advisor_user_id' => $this->users['advisor']->getKey(),
            'decision' => 'accepted',
        ], [
            'rating' => 5,
            'corrected_body' => null,
            'note' => 'Useful and supported by the seeded Xero snapshots.',
        ], timestamps: false);

        $this->ids['pv_improvement'] = $this->upsert('pv_calculations', [
            'client_id' => $this->clients['advisory']->getKey(),
            'type' => 'improvement_opportunity',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ], [
            'discount_method' => 'advisor_configured',
            'discount_rate' => 0.115000,
            'discount_rate_rationale' => 'Seeded SME advisory hurdle rate with execution risk overlay.',
            'inputs' => $this->json(['annual_benefit' => 68000, 'duration_years' => 3]),
            'result' => $this->json(['present_value' => 165000, 'annualised_impact' => 68000]),
            'as_at' => $this->now->copy()->subDays(4),
            'source_attributions' => $this->json([
                ['type' => 'analysis_finding', 'id' => $this->ids['finding_cash']],
            ]),
        ]);

        $this->ids['pv_risk'] = $this->upsert('pv_calculations', [
            'client_id' => $this->clients['advisory']->getKey(),
            'type' => 'risk_cost',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ], [
            'discount_method' => 'advisor_configured',
            'discount_rate' => 0.130000,
            'discount_rate_rationale' => 'Seeded key-person risk discount rate.',
            'inputs' => $this->json(['financial_impact' => 120000, 'probability' => 0.28, 'duration_years' => 2]),
            'result' => $this->json(['present_value' => 58000, 'expected_annual_cost' => 33600]),
            'as_at' => $this->now->copy()->subDays(4),
            'source_attributions' => $this->json([
                ['type' => 'analysis_finding', 'id' => $this->ids['finding_people']],
            ]),
        ]);

        $this->ids['pv_valuation'] = $this->upsert('pv_calculations', [
            'client_id' => $this->clients['advisory']->getKey(),
            'type' => 'business_valuation',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ], [
            'discount_method' => 'advisor_configured',
            'discount_rate' => 0.145000,
            'discount_rate_rationale' => 'Seeded valuation scenario for advisory testing.',
            'inputs' => $this->json(['ebitda' => 456000, 'growth_rate' => 0.06, 'terminal_multiple' => 4.6]),
            'result' => $this->json(['low' => 1620000, 'mid' => 1880000, 'high' => 2160000]),
            'as_at' => $this->now->copy()->subDays(3),
            'source_attributions' => $this->json([
                ['type' => 'valuation_multiple', 'record_hash' => hash('sha256', 'seed-saas-ebitda-2026q2')],
            ]),
        ]);

        $this->ids['business_valuation'] = $this->upsert('business_valuations', [
            'client_id' => $this->clients['advisory']->getKey(),
            'pv_calculation_id' => $this->ids['pv_valuation'],
        ], [
            'sde_value' => $this->json(['low' => 1500000, 'mid' => 1780000, 'high' => 2040000]),
            'ebitda_value' => $this->json(['low' => 1580000, 'mid' => 1910000, 'high' => 2250000]),
            'dcf_value' => $this->json(['low' => 1620000, 'mid' => 1880000, 'high' => 2160000]),
            'reconciled_low' => 1_580_000,
            'reconciled_mid' => 1_880_000,
            'reconciled_high' => 2_160_000,
            'adjustments' => $this->json(['owner_salary_normalisation' => 45000]),
            'data_quality_disclaimer' => 'Seeded valuation for testing only.',
            'source_attributions' => $this->json([
                ['type' => 'pv_calculation', 'id' => $this->ids['pv_valuation']],
            ]),
            'as_at' => $this->now->copy()->subDays(3),
        ]);

        $this->ids['opportunity_cash'] = $this->upsert('improvement_opportunities', [
            'client_id' => $this->clients['advisory']->getKey(),
            'title' => 'Tighten debtor follow-up cadence',
        ], [
            'analysis_finding_id' => $this->ids['finding_cash'],
            'pv_calculation_id' => $this->ids['pv_improvement'],
            'annual_benefit' => 68_000,
            'duration_years' => 3,
            'pv_of_impact' => 165_000,
            'rank' => 1,
            'source_attributions' => $this->json([
                ['type' => 'financial_alert', 'alert_key' => 'seed-harbour-hive-debtor-days'],
            ]),
        ]);

        $this->ids['pv_succession_target'] = $this->upsert('pv_calculations', [
            'client_id' => $this->clients['advisory']->getKey(),
            'type' => 'goal_target',
            'discount_rate_rationale' => 'Seeded succession target exit value.',
        ], [
            'discount_method' => 'advisor_configured',
            'discount_rate' => 0.125000,
            'inputs' => $this->json(['target_exit_annual_cash_flow' => 640000, 'duration_years' => 5]),
            'result' => $this->json(['present_value' => 2_650_000]),
            'as_at' => $this->now->copy()->subDays(3),
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'source_attributions' => $this->json([
                ['type' => 'client_goal', 'id' => 'seed-advisory-succession-target'],
            ]),
        ]);

        $this->ids['succession_plan'] = $this->upsert('succession_plans', [
            'client_id' => $this->clients['advisory']->getKey(),
            'target_exit_pv_calculation_id' => $this->ids['pv_succession_target'],
        ], [
            'analysis_run_id' => null,
            'exit_readiness_score' => 6,
            'options' => $this->json([
                ['name' => 'Trade sale', 'fit_score' => 7, 'rationale' => 'Best fit once debtor cadence and owner dependency are reduced.'],
                ['name' => 'Management buy-out', 'fit_score' => 5, 'rationale' => 'Possible if second-tier leadership depth improves.'],
                ['name' => 'Family transfer', 'fit_score' => 3, 'rationale' => 'No active family successor is recorded in the seeded profile.'],
            ]),
            'owner_dependency_plan' => $this->json([
                'actions' => [
                    'Document dispatch handover and debtor escalation rules.',
                    'Nominate a second operational lead before sale preparation.',
                ],
            ]),
            'target_exit_pv' => 2_650_000,
            'owner_readiness_is_primary_constraint' => true,
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $this->ids['risk_people'] = $this->upsert('risk_costs', [
            'client_id' => $this->clients['advisory']->getKey(),
            'title' => 'Dispatch key-person dependency',
        ], [
            'analysis_finding_id' => $this->ids['finding_people'],
            'pv_calculation_id' => $this->ids['pv_risk'],
            'financial_impact' => 120_000,
            'probability' => 0.2800,
            'duration_years' => 2,
            'statutory_penalty_range' => $this->json(['low' => 0, 'high' => 0]),
            'applied_impact' => 120_000,
            'annual_expected_cost' => 33_600,
            'pv_of_cost' => 58_000,
            'rank' => 1,
            'source_attributions' => $this->json([
                ['type' => 'analysis_finding', 'id' => $this->ids['finding_people']],
            ]),
        ]);

        $this->ids['red_flag_people'] = $this->upsert('red_flags', [
            'source_type' => 'analysis_finding',
            'source_key' => (string) $this->ids['finding_people'],
            'client_id' => $this->clients['advisory']->getKey(),
        ], [
            'analysis_finding_id' => $this->ids['finding_people'],
            'category' => 'people',
            'severity' => 'high',
            'headline' => 'Dispatch continuity is fragile',
            'detail' => 'Only one person can currently run dispatch without external support.',
            'surfaced_at' => $this->now->copy()->subDays(4),
            'acknowledged_at' => $this->now->copy()->subDays(3),
            'acknowledged_by_user_id' => $this->users['advisor']->getKey(),
            'resolved_at' => null,
        ]);

        $this->seedPvWaterfallPortfolioFixtures();
    }

    private function seedPvWaterfallPortfolioFixtures(): void
    {
        foreach ($this->pvWaterfallClientDefinitions() as $clientKey => $definition) {
            $client = $this->clients[$clientKey];
            $analysisRunId = $this->upsert('analysis_runs', [
                'client_id' => $client->getKey(),
                'module' => 'financial',
                'prompt_version' => "testing-pv-waterfall-{$clientKey}",
            ], [
                'status' => 'completed',
                'framework_lenses' => $this->json(['descriptive', 'diagnostic', 'prescriptive']),
                'data_quality_snapshot' => $this->json([
                    'score' => $definition['analysis_quality_score'],
                    'label' => $definition['data_quality'],
                    'fixture' => 'pv_waterfall',
                ]),
                'ai_model' => 'seeded-pv-waterfall-model',
                'prompt_hash' => hash('sha256', "testing-pv-waterfall-{$clientKey}"),
                'tokens_in' => 4_800 + (int) $definition['analysis_quality_score'],
                'tokens_out' => 1_600 + (count($definition['improvements']) * 120),
                'started_at' => $this->stableTimestamp($definition['analysis_started_at']),
                'completed_at' => $this->stableTimestamp($definition['analysis_completed_at']),
                'created_by_user_id' => $this->users['advisor']->getKey(),
            ]);

            $valuation = $definition['valuation'];
            $valuationCalculationId = $this->seedPvCalculation(
                client: $client,
                type: 'business_valuation',
                fixtureKey: "{$clientKey}-valuation",
                discountRate: $valuation['discount_rate'],
                rationale: "Seeded PV waterfall baseline valuation for {$definition['legal_name']}.",
                inputs: [
                    'normalised_ebitda' => $valuation['normalised_ebitda'],
                    'growth_rate' => $valuation['growth_rate'],
                    'terminal_multiple' => $valuation['terminal_multiple'],
                ],
                result: [
                    'low' => $valuation['low'],
                    'mid' => $valuation['mid'],
                    'high' => $valuation['high'],
                ],
                asAt: $this->stableTimestamp($valuation['as_at']),
                sourceAttributions: [
                    ['claim' => 'Seeded PV waterfall baseline valuation.', 'source_reference' => "testing_seed:{$clientKey}:valuation"],
                ],
            );

            $this->ids["pv_waterfall_{$clientKey}_valuation"] = $valuationCalculationId;
            $this->ids["business_valuation_{$clientKey}"] = $this->upsert('business_valuations', [
                'client_id' => $client->getKey(),
                'pv_calculation_id' => $valuationCalculationId,
            ], [
                'sde_value' => $this->json($this->valuationBand($valuation, 0.94)),
                'ebitda_value' => $this->json($this->valuationBand($valuation, 1.00)),
                'dcf_value' => $this->json($this->valuationBand($valuation, 1.03)),
                'reconciled_low' => $valuation['low'],
                'reconciled_mid' => $valuation['mid'],
                'reconciled_high' => $valuation['high'],
                'adjustments' => $this->json($valuation['adjustments']),
                'data_quality_disclaimer' => 'Seeded valuation for PV waterfall dashboard testing only.',
                'source_attributions' => $this->json([
                    ['type' => 'pv_calculation', 'id' => $valuationCalculationId],
                    ['claim' => 'Fixture creates varied current PV bands for advisor dashboard testing.', 'source_reference' => "testing_seed:{$clientKey}:business_valuation"],
                ]),
                'as_at' => $this->stableTimestamp($valuation['as_at']),
            ]);

            foreach ($definition['improvements'] as $rank => $improvement) {
                $findingId = $this->seedPvWaterfallFinding(
                    analysisRunId: $analysisRunId,
                    client: $client,
                    title: $improvement['finding_title'],
                    body: $improvement['body'],
                    lens: 'prescriptive',
                    severity: $improvement['severity'],
                    sourceReference: "testing_seed:{$clientKey}:improvement:".($rank + 1),
                );
                $calculationId = $this->seedPvCalculation(
                    client: $client,
                    type: 'improvement_opportunity',
                    fixtureKey: "{$clientKey}-improvement-".($rank + 1),
                    discountRate: $improvement['discount_rate'],
                    rationale: "Seeded PV waterfall improvement {$improvement['title']} for {$definition['legal_name']}.",
                    inputs: [
                        'annual_benefit' => $improvement['annual_benefit'],
                        'duration_years' => $improvement['duration_years'],
                    ],
                    result: [
                        'present_value' => $improvement['pv'],
                        'annualised_impact' => $improvement['annual_benefit'],
                    ],
                    asAt: $this->stableTimestamp($improvement['as_at']),
                    sourceAttributions: [
                        ['type' => 'analysis_finding', 'id' => $findingId],
                    ],
                );
                $opportunityId = $this->upsert('improvement_opportunities', [
                    'client_id' => $client->getKey(),
                    'title' => $improvement['title'],
                ], [
                    'analysis_finding_id' => $findingId,
                    'pv_calculation_id' => $calculationId,
                    'annual_benefit' => $improvement['annual_benefit'],
                    'duration_years' => $improvement['duration_years'],
                    'pv_of_impact' => $improvement['pv'],
                    'rank' => $rank + 1,
                    'source_attributions' => $this->json([
                        ['type' => 'analysis_finding', 'id' => $findingId],
                        ['claim' => 'Seeded recommendation supports PV waterfall dashboard testing.', 'source_reference' => "testing_seed:{$clientKey}:improvement:".($rank + 1)],
                    ]),
                ]);

                $this->linkFindingToPvItem($findingId, $opportunityId);
            }

            foreach ($definition['risks'] as $rank => $risk) {
                $findingId = $this->seedPvWaterfallFinding(
                    analysisRunId: $analysisRunId,
                    client: $client,
                    title: $risk['finding_title'],
                    body: $risk['body'],
                    lens: 'diagnostic',
                    severity: $risk['severity'],
                    sourceReference: "testing_seed:{$clientKey}:risk:".($rank + 1),
                );
                $calculationId = $this->seedPvCalculation(
                    client: $client,
                    type: 'risk_cost',
                    fixtureKey: "{$clientKey}-risk-".($rank + 1),
                    discountRate: $risk['discount_rate'],
                    rationale: "Seeded PV waterfall risk {$risk['title']} for {$definition['legal_name']}.",
                    inputs: [
                        'financial_impact' => $risk['financial_impact'],
                        'probability' => $risk['probability'],
                        'duration_years' => $risk['duration_years'],
                    ],
                    result: [
                        'present_value' => $risk['pv'],
                        'expected_annual_cost' => $risk['annual_expected_cost'],
                    ],
                    asAt: $this->stableTimestamp($risk['as_at']),
                    sourceAttributions: [
                        ['type' => 'analysis_finding', 'id' => $findingId],
                    ],
                );
                $riskId = $this->upsert('risk_costs', [
                    'client_id' => $client->getKey(),
                    'title' => $risk['title'],
                ], [
                    'analysis_finding_id' => $findingId,
                    'pv_calculation_id' => $calculationId,
                    'financial_impact' => $risk['financial_impact'],
                    'probability' => $risk['probability'],
                    'duration_years' => $risk['duration_years'],
                    'statutory_penalty_range' => $this->json($risk['statutory_penalty_range']),
                    'applied_impact' => $risk['applied_impact'],
                    'annual_expected_cost' => $risk['annual_expected_cost'],
                    'pv_of_cost' => $risk['pv'],
                    'rank' => $rank + 1,
                    'source_attributions' => $this->json([
                        ['type' => 'analysis_finding', 'id' => $findingId],
                        ['claim' => 'Seeded risk cost supports PV waterfall dashboard testing.', 'source_reference' => "testing_seed:{$clientKey}:risk:".($rank + 1)],
                    ]),
                ]);

                $this->linkFindingToPvItem($findingId, $riskId);
            }
        }
    }

    private function seedIntegrationEfficiencyService(): void
    {
        $advisor = $this->users['advisor'];
        $client = $this->clients['advisory'];
        $bands = [
            ['S', 'inhouse', 3500, 4500, 5500],
            ['M', 'inhouse', 6500, 8000, 9500],
            ['L', 'inhouse', 12000, 15000, 18000],
            ['XL', 'inhouse', 45000, 45000, 45000],
            ['S', 'lowcode', 3000, 4000, 5000],
            ['M', 'lowcode', 5500, 7000, 8500],
            ['L', 'lowcode', 9500, 12000, 15000],
            ['XL', 'lowcode', 35000, 35000, 35000],
            ['S', 'partner', 4500, 5500, 6500],
            ['M', 'partner', 7500, 9000, 11000],
            ['L', 'partner', 14000, 17000, 21000],
            ['XL', 'partner', 50000, 50000, 50000],
            ['S', 'mixed', 4000, 5000, 6000],
            ['M', 'mixed', 7000, 8500, 10000],
            ['L', 'mixed', 13000, 16000, 19000],
            ['XL', 'mixed', 47000, 47000, 47000],
        ];

        $hostingPricing = IntegrationFeeBand::defaultHostingPricing();
        foreach ($bands as [$band, $deliveryMode, $low, $mid, $high]) {
            IntegrationFeeBand::query()->updateOrCreate([
                'complexity_band' => $band,
                'delivery_mode' => $deliveryMode,
            ], [
                'fee_low' => $low,
                'fee_mid' => $mid,
                'fee_high' => $high,
                'currency' => 'NZD',
                'scope_description' => IntegrationFeeBand::defaultScopeDescriptionFor($band),
                'hosting_monthly_cost' => $hostingPricing['monthly_cost'],
                'hosting_markup_percent' => $hostingPricing['markup_percent'],
                'is_active' => true,
                'updated_by_user_id' => $advisor->getKey(),
            ]);
        }

        $scope = IntegrationScope::query()
            ->where('client_id', $client->getKey())
            ->first();

        if (! $scope instanceof IntegrationScope) {
            $scope = app(IntegrationScopeService::class)->create($client, [
                'systems' => [
                    [
                        'id' => 'xero',
                        'name' => 'Xero',
                        'vendor' => 'Xero',
                        'role' => 'Accounting and invoice ledger',
                        'api_quality' => 'rest_public',
                        'auth' => 'oauth',
                        'monthly_records' => 2800,
                        'confidence' => 'known',
                        'source' => 'manual',
                    ],
                    [
                        'id' => 'field-service',
                        'name' => 'Field Service Board',
                        'vendor' => 'Legacy vendor',
                        'role' => 'Job completion and time capture',
                        'api_quality' => 'none',
                        'auth' => 'none',
                        'monthly_records' => 12500,
                        'confidence' => 'estimate',
                        'source' => 'manual',
                    ],
                    [
                        'id' => 'crm',
                        'name' => 'Client CRM',
                        'vendor' => 'HubSpot',
                        'role' => 'Customer and sales record',
                        'api_quality' => 'rest_partner',
                        'auth' => 'oauth',
                        'monthly_records' => 8400,
                        'confidence' => 'estimate',
                        'source' => 'manual',
                    ],
                ],
                'tasks' => [
                    [
                        'id' => 'invoice-rekeying',
                        'description' => 'Re-key completed field jobs into Xero invoices',
                        'system_ids' => ['field-service', 'xero'],
                        'minutes_per_occurrence' => 14,
                        'occurrences_per' => 'day',
                        'people_count' => 2,
                        'hourly_cost' => 52,
                        'confidence' => 'known',
                        'source' => 'manual',
                    ],
                    [
                        'id' => 'crm-status',
                        'description' => 'Re-key job progress into the client CRM',
                        'system_ids' => ['field-service', 'crm'],
                        'minutes_per_occurrence' => 9,
                        'occurrences_per' => 'day',
                        'people_count' => 2,
                        'hourly_cost' => 48,
                        'confidence' => 'estimate',
                        'source' => 'manual',
                    ],
                ],
                'connections' => [
                    [
                        'id' => 'field-to-xero',
                        'from_system' => 'field-service',
                        'to_system' => 'xero',
                        'direction' => 'one_way',
                        'transform_complexity' => 'med',
                        'task_ids' => ['invoice-rekeying'],
                        'confidence' => 'estimate',
                        'source' => 'manual',
                    ],
                    [
                        'id' => 'crm-to-field',
                        'from_system' => 'crm',
                        'to_system' => 'field-service',
                        'direction' => 'two_way',
                        'transform_complexity' => 'high',
                        'task_ids' => ['crm-status'],
                        'confidence' => 'estimate',
                        'source' => 'manual',
                    ],
                ],
                'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
                'capture_percent' => 80,
                'savings_horizon_years' => 3,
                'discount_rate_percent' => 12,
                'source_document_ids' => [],
            ], $advisor);
        }

        $scope = $this->seedIntegrationQuoteSourcePlan($scope, $client, $advisor);

        $calculation = FeeCalculation::query()
            ->where('client_id', $client->getKey())
            ->where('integration_scope_id', $scope->getKey())
            ->where('method', FeeMethod::Integration)
            ->first();

        if (! $calculation instanceof FeeCalculation) {
            $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::Integration, [
                'integration_scope_id' => $scope->getKey(),
            ], [
                'created_by_user_id' => $advisor->getKey(),
            ]);
        }

        $package = ServiceRatePackage::query()->updateOrCreate([
            'service_type' => ServiceActivation::SERVICE_INTEGRATION_SCOPING,
            'package_name' => 'Integration Scoping Workshop',
        ], [
            'client_label' => 'Systems & Integration Efficiency Scoping',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 1200,
            'hourly_rate' => null,
            'retainer_amount' => null,
            'purchase_price_min' => null,
            'purchase_price_max' => null,
            'currency' => 'NZD',
            'scope_description' => 'Advisor-led systems inventory, duplicate-entry analysis, integration complexity assessment, and Quote Pack.',
            'is_active' => true,
            'effective_from' => $this->now,
            'effective_to' => null,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $activationId = $this->upsert('service_activations', [
            'client_id' => $client->getKey(),
            'service_type' => ServiceActivation::SERVICE_INTEGRATION_SCOPING,
        ], [
            'requested_by_user_id' => $advisor->getKey(),
            'advisor_id' => $advisor->getKey(),
            'approved_by_user_id' => $advisor->getKey(),
            'service_rate_package_id' => $package->getKey(),
            'client_label' => 'Systems integration scoping',
            'status' => ServiceActivation::STATUS_ACTIVE,
            'intake' => $this->json([]),
            'selected_package_snapshot' => $this->json($package->snapshot()),
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'payment_completed_at' => $this->now->copy()->subDays(10),
            'payment_completed_by_user_id' => $this->users['primary']->getKey(),
            'payment_reference' => 'seed-integration-scoping-paid',
            'deposit_paid_at' => $this->now->copy()->subDays(10),
            'deposit_paid_by_user_id' => $this->users['primary']->getKey(),
            'deposit_reference' => 'seed-integration-scoping-paid',
            'balance_received_at' => null,
            'balance_received_by_user_id' => null,
            'balance_reference' => null,
            'accepted_by_user_id' => $this->users['primary']->getKey(),
            'accepted_at' => $this->now->copy()->subDays(10),
            'acceptance_text' => 'Seeded advisor offer and paid scoping package consent.',
            'terms_reference' => $this->json(['source' => 'testing_seed_data']),
            'related_dd_engagement_id' => null,
            'related_entrepreneur_profile_id' => null,
            'client_message_thread_id' => null,
            'closed_at' => null,
            'cancelled_at' => null,
            'metadata' => $this->json(['fixture' => true, 'source' => 'advisor_offer']),
        ]);
        $creditId = $this->upsert('billing_adjustments', [
            'source_service_activation_id' => $activationId,
        ], [
            'client_id' => $client->getKey(),
            'type' => BillingAdjustment::TYPE_SCOPING_FEE_CREDIT,
            'source_payment_reference' => 'seed-integration-scoping-paid',
            'amount' => 1200,
            'currency' => 'NZD',
            'status' => BillingAdjustment::STATUS_AVAILABLE,
            'applied_to_proposal_id' => null,
            'applied_at' => null,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $scope->forceFill(['scoping_credit_adjustment_id' => $creditId])->save();

        $this->ids['integration_scope'] = $scope->getKey();
        $this->ids['integration_fee_calculation'] = $calculation->getKey();
        $this->ids['integration_scoping_activation'] = $activationId;
        $this->ids['integration_scoping_credit'] = $creditId;

        $this->seedWebsiteAuditIntegrationProposal($advisor);
    }

    private function seedWebsiteAuditIntegrationProposal(User $advisor): void
    {
        $client = $this->clients['websiteAudit'];
        $scope = IntegrationScope::query()
            ->where('client_id', $client->getKey())
            ->first();

        if (! $scope instanceof IntegrationScope) {
            $scope = app(IntegrationScopeService::class)->create($client, [
                'systems' => [
                    [
                        'id' => 'website',
                        'name' => 'Public website',
                        'vendor' => 'Website platform',
                        'role' => 'Lead capture and service information',
                        'api_quality' => 'rest_public',
                        'auth' => 'oauth',
                        'monthly_records' => 900,
                        'confidence' => 'known',
                        'source' => 'manual',
                    ],
                    [
                        'id' => 'crm',
                        'name' => 'Client CRM',
                        'vendor' => 'CRM provider',
                        'role' => 'Prospect and enquiry management',
                        'api_quality' => 'rest_public',
                        'auth' => 'oauth',
                        'monthly_records' => 650,
                        'confidence' => 'known',
                        'source' => 'manual',
                    ],
                ],
                'tasks' => [[
                    'id' => 'website-lead-entry',
                    'description' => 'Copy website enquiries into the client CRM',
                    'system_ids' => ['website', 'crm'],
                    'minutes_per_occurrence' => 8,
                    'occurrences_per' => 'week',
                    'people_count' => 1,
                    'hourly_cost' => 55,
                    'confidence' => 'known',
                    'source' => 'manual',
                ]],
                'connections' => [[
                    'id' => 'website-to-crm',
                    'from_system' => 'website',
                    'to_system' => 'crm',
                    'direction' => 'one_way',
                    'transform_complexity' => 'low',
                    'task_ids' => ['website-lead-entry'],
                    'confidence' => 'known',
                    'source' => 'manual',
                ]],
                'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
                'capture_percent' => 80,
                'savings_horizon_years' => 3,
                'discount_rate_percent' => 12,
                'fsa_hosting_enabled' => true,
            ], $advisor);
        }

        $calculation = FeeCalculation::query()
            ->where('client_id', $client->getKey())
            ->where('integration_scope_id', $scope->getKey())
            ->where('method', FeeMethod::Integration)
            ->first();

        if (! $calculation instanceof FeeCalculation) {
            $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::Integration, [
                'integration_scope_id' => $scope->getKey(),
            ], [
                'created_by_user_id' => $advisor->getKey(),
            ]);
        }

        $proposal = Proposal::query()
            ->where('client_id', $client->getKey())
            ->where('fee_calculation_id', $calculation->getKey())
            ->first();

        if (! $proposal instanceof Proposal) {
            $proposal = app(ProposalBuilder::class)->generate($client, $calculation, [
                'scope' => [
                    'summary' => 'Website-to-CRM integration and FSA managed hosting proposal for Website Review Demo Limited.',
                    'included' => [
                        'Website enquiry to CRM connection',
                        'Field mapping, configuration, testing, and handover',
                        'FSA managed hosting while the service is active',
                    ],
                    'excluded' => [
                        'Third-party software subscriptions and vendor charges',
                        'Additional integrations or data migration not included in the agreed scope',
                    ],
                ],
            ], [
                'created_by_user_id' => $advisor->getKey(),
            ]);

            app(ProposalBuilder::class)->release($proposal, $advisor, 30);
        }

        $this->ids['website_audit_integration_scope'] = $scope->getKey();
        $this->ids['website_audit_integration_fee_calculation'] = $calculation->getKey();
        $this->ids['website_audit_integration_proposal'] = $proposal->getKey();
    }

    private function seedIntegrationQuoteSourcePlan(IntegrationScope $scope, Client $client, User $advisor): IntegrationScope
    {
        $storedPath = 'seed/documents/integration-external-implementation-plan.txt';
        $planText = implode("\n", [
            'System: Xero; API: rest_public; auth: oauth; monthly records: 2800',
            'System: Field Service Board; API: none; auth: none; monthly records: 12500',
            'System: Client CRM; API: rest_partner; auth: oauth; monthly records: 8400',
            'Task: Re-key completed field jobs into Xero invoices; 14 minutes; 2 people; day; $52/hour',
            'Connection: Field Service Board -> Xero; one way; med',
            'Connection: Client CRM -> Field Service Board; two way; high',
        ]);
        $documentId = $this->document(
            key: 'integration-external-implementation-plan.txt',
            client: $client,
            category: Document::CATEGORY_PLAN_ATTACHMENT,
            filename: 'Harbour Hive external implementation plan.txt',
            uploader: $advisor,
            scannerResult: Document::SCANNER_CLEAN,
            expiresAt: null,
            size: strlen($planText),
            mimeType: 'text/plain',
        );
        if (! is_string($documentId)) {
            return $scope;
        }

        Storage::disk('secure_local')->put($storedPath, $planText);
        DB::table('documents')->where('id', $documentId)->update([
            'byte_size' => strlen($planText),
            'sha256' => hash('sha256', $planText),
            'updated_at' => $this->now,
        ]);

        $verificationId = $this->verification(
            documentId: $documentId,
            context: 'integration-quote-source-plan',
            client: $client,
            claim: 'Implementation plan describing the client systems, manual processes, and requested connections, used to scope an integration quote.',
            outcome: 'verified',
            confidence: 0.95,
            explanation: 'The verified seed plan supports the systems, duplicate-entry tasks, and connections in the integration quote.',
        );
        if (! is_string($verificationId)) {
            return $scope;
        }

        $rows = [
            [
                'id' => '019f5a00-0001-7000-8000-000000000001',
                'type' => 'system',
                'name' => 'Field Service Board',
                'vendor' => 'Legacy vendor',
                'role' => 'Job completion and time capture',
                'api_quality' => 'none',
                'auth' => 'none',
                'monthly_records' => 12500,
                'confidence' => 'estimate',
                'source' => 'document',
                'source_reference' => "document:{$documentId}:line:2",
                'claim' => 'System: Field Service Board; API: none; auth: none; monthly records: 12500',
                'review_status' => 'confirmed',
            ],
            [
                'id' => '019f5a00-0002-7000-8000-000000000002',
                'type' => 'task',
                'description' => 'Re-key completed field jobs into Xero invoices',
                'minutes_per_occurrence' => 14,
                'occurrences_per' => 'day',
                'people_count' => 2,
                'hourly_cost' => 52,
                'confidence' => 'known',
                'source' => 'document',
                'source_reference' => "document:{$documentId}:line:4",
                'claim' => 'Task: Re-key completed field jobs into Xero invoices; 14 minutes; 2 people; day; $52/hour',
                'review_status' => 'confirmed',
            ],
            [
                'id' => '019f5a00-0003-7000-8000-000000000003',
                'type' => 'connection',
                'from_system' => 'field-service',
                'to_system' => 'xero',
                'direction' => 'one_way',
                'transform_complexity' => 'med',
                'confidence' => 'estimate',
                'source' => 'document',
                'source_reference' => "document:{$documentId}:line:5",
                'claim' => 'Connection: Field Service Board -> Xero; one way; med',
                'review_status' => 'confirmed',
            ],
            [
                'id' => '019f5a00-0004-7000-8000-000000000004',
                'type' => 'connection',
                'from_system' => 'crm',
                'to_system' => 'field-service',
                'direction' => 'two_way',
                'transform_complexity' => 'high',
                'confidence' => 'estimate',
                'source' => 'document',
                'source_reference' => "document:{$documentId}:line:6",
                'claim' => 'Connection: Client CRM -> Field Service Board; two way; high',
                'review_status' => 'confirmed',
            ],
        ];
        $extraction = QuoteSourceExtraction::query()->firstOrCreate([
            'client_id' => $client->getKey(),
            'scopeable_type' => IntegrationScope::class,
            'scopeable_id' => $scope->getKey(),
            'description_text' => 'Verified external implementation plan supplied before integration quote preparation.',
        ], [
            'description_captured_at' => $this->now->copy()->subDays(4),
            'status' => QuoteSourceExtraction::STATUS_EXTRACTED,
            'extracted_rows' => $rows,
            'confirmed_row_ids' => array_column($rows, 'id'),
            'extracted_at' => $this->now->copy()->subDays(4),
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $extraction->forceFill([
            'status' => QuoteSourceExtraction::STATUS_EXTRACTED,
            'blocked_reason' => null,
            'extracted_rows' => $rows,
            'confirmed_row_ids' => array_column($rows, 'id'),
            'extracted_at' => $this->now->copy()->subDays(4),
        ])->save();

        QuoteSourceExtractionDocument::query()->updateOrCreate([
            'quote_source_extraction_id' => $extraction->getKey(),
            'document_id' => $documentId,
        ], [
            'document_verification_id' => $verificationId,
            'verification_outcome_at_use' => 'verified',
        ]);

        $sourceDocumentIds = array_values(array_unique([
            ...((array) $scope->source_document_ids),
            $documentId,
        ]));
        if ($sourceDocumentIds === (array) $scope->source_document_ids) {
            return $scope;
        }

        return app(IntegrationScopeService::class)->update($scope, [
            'source_document_ids' => $sourceDocumentIds,
        ], $advisor);
    }

    private function seedEntrepreneurJourney(): void
    {
        $profileId = $this->upsert('entrepreneur_profiles', ['email' => $this->users['entrepreneur']->email], [
            'user_id' => $this->users['entrepreneur']->getKey(),
            'assigned_advisor_id' => $this->users['advisor']->getKey(),
            'invite_token_id' => null,
            'name' => 'Aroha Analytics',
            'stage' => EntrepreneurStage::ADVISORY_READY->value,
            'concept_summary' => 'Forecasting toolkit for New Zealand exporters managing demand and currency risk.',
        ]);
        $this->ids['entrepreneur_profile'] = $profileId;

        $this->ids['doc_pitch_deck'] = $this->document(
            key: 'entrepreneur-pitch-deck',
            client: null,
            category: 'pitch_deck',
            filename: 'aroha-analytics-pitch-deck.pdf',
            uploader: $this->users['entrepreneur'],
            scannerResult: 'clean',
            expiresAt: null,
            size: 740_000,
            entrepreneurProfileId: (string) $profileId,
        );

        $this->ids['readiness_assessment'] = $this->upsert('readiness_assessments', [
            'entrepreneur_profile_id' => $profileId,
            'assessed_at' => $this->stableTimestamp('2026-05-14 09:00:00'),
        ], [
            'responses' => $this->json([
                'time_commitment' => 'high',
                'support_network' => 'medium',
                'financial_runway_months' => 9,
            ]),
            'score' => 84.50,
            'outcome' => 'ready',
            'personal_barriers' => $this->json(['needs_customer_interviews' => true]),
            'assessed_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $this->ids['idea_validation'] = $this->upsert('idea_validations', [
            'entrepreneur_profile_id' => $profileId,
            'evaluated_at' => $this->stableTimestamp('2026-05-15 10:00:00'),
        ], [
            'problem' => 'Exporters lack a practical way to connect demand forecasting with currency risk.',
            'target_customer' => 'Small and mid-market New Zealand exporters selling into Australia and the US.',
            'solution' => 'A lightweight dashboard that blends sales pipeline, shipment dates, and FX exposure.',
            'value_proposition' => 'Make pricing and hedging decisions earlier with advisor-friendly evidence.',
            'demand_signal' => 'Six founder interviews and two letters of intent from export operators.',
            'revenue_model' => 'Subscription plus advisor implementation package.',
            'ai_evaluation' => $this->json(['score' => 82, 'summary' => 'Promising niche with clear validation steps.']),
            'viability_alerts' => $this->json(['data_access' => 'Confirm accounting and logistics integrations.']),
            'evaluated_by_user_id' => $this->users['advisor']->getKey(),
            'advisor_gate_passed_at' => $this->now->copy()->subDays(6),
            'advisor_gate_passed_by_user_id' => $this->users['advisor']->getKey(),
            'advisor_gate_note' => 'Proceed to business plan build with customer proof as the main dependency.',
        ]);

        $planId = $this->upsert('business_plans', [
            'entrepreneur_profile_id' => $profileId,
            'title' => 'Aroha Analytics Founder Plan',
        ], [
            'client_id' => null,
            'dd_engagement_id' => null,
            'source_type' => 'entrepreneur_module',
            'status' => 'completed',
            'current_phase' => 5,
            'founding_advisory_payload' => $this->json([
                'mentor_user_id' => $this->users['mentor']->getKey(),
                'primary_outcome' => 'advisory_ready',
            ]),
            'created_by_user_id' => $this->users['entrepreneur']->getKey(),
            'completed_at' => $this->now->copy()->subDays(2),
            'living_plan_next_update_at' => $this->now->copy()->addDays(28),
            'living_plan_last_prompted_at' => $this->now->copy()->subDays(3),
            'living_plan_last_assessed_at' => $this->now->copy()->subDays(2),
            'living_plan_divergence_flags' => $this->json(['customer_interview_count_changed' => true]),
        ]);
        $this->ids['entrepreneur_plan'] = $planId;

        $phases = [
            ['foundation', 'Foundation and personal readiness', 1, 'completed'],
            ['idea_validation', 'Idea validation', 2, 'completed'],
            ['market', 'Market and customer evidence', 3, 'completed'],
            ['model', 'Business model', 4, 'completed'],
            ['launch', 'Launch plan', 5, 'completed'],
        ];

        foreach ($phases as [$key, $title, $position, $status]) {
            $phaseId = $this->upsert('plan_phases', [
                'business_plan_id' => $planId,
                'key' => $key,
            ], [
                'title' => $title,
                'position' => $position,
                'depends_on' => $position === 1 ? null : $this->json([$phases[$position - 2][0]]),
                'status' => $status,
            ]);

            $this->ids["entrepreneur_phase_{$key}"] = $phaseId;
        }

        $sections = [
            ['problem', 'Problem', 'Exporters are making margin decisions without a single view of demand, fulfilment, and FX risk.', 'idea_validation'],
            ['customers', 'Customer Evidence', 'The plan includes interviews, letters of intent, and a pilot shortlist.', 'market'],
            ['model', 'Revenue Model', 'Subscription revenue with optional advisor implementation support.', 'model'],
            ['launch', 'Launch Milestones', 'Pilot with two exporters, publish advisor dashboard, and convert the first annual plans.', 'launch'],
        ];

        foreach ($sections as [$key, $title, $body, $phaseKey]) {
            $sectionId = $this->upsert('plan_sections', [
                'business_plan_id' => $planId,
                'key' => $key,
            ], [
                'plan_phase_id' => $this->ids["entrepreneur_phase_{$phaseKey}"],
                'title' => $title,
                'body' => $body,
                'source_type' => 'founder_input',
                'source_analysis_finding_id' => null,
                'completeness_status' => 'complete',
                'metadata' => $this->json(['fixture' => true]),
            ]);

            if ($key === 'customers') {
                $this->ids['entrepreneur_customer_section'] = $sectionId;
            }
        }

        $ratingFrameworkId = DB::table('rating_frameworks')
            ->where('production_ready', true)
            ->whereNull('industry_variant')
            ->orderByDesc('version')
            ->value('id');

        if ($ratingFrameworkId !== null) {
            $assessmentScores = DB::table('rating_criteria')
                ->where('rating_framework_id', $ratingFrameworkId)
                ->orderBy('number')
                ->get(['id', 'number', 'name', 'weight'])
                ->map(fn (object $criterion): array => [
                    'criterion_id' => (string) $criterion->id,
                    'criterion_number' => (int) $criterion->number,
                    'criterion_name' => (string) $criterion->name,
                    'score' => 82,
                    'weight' => (float) $criterion->weight,
                    'rationale' => 'Seeded first-pass score uses canonical criterion rows for testing.',
                    'attributions' => [
                        [
                            'claim' => 'Seeded assessment score derived from the demo business plan.',
                            'source_reference' => 'business_plan:'.$planId,
                        ],
                    ],
                    'score_source' => 'seed_fixture',
                ])
                ->values()
                ->all();

            $this->ids['plan_assessment'] = $this->upsert('plan_assessments', [
                'business_plan_id' => $planId,
                'round' => 1,
            ], [
                'rating_framework_id' => $ratingFrameworkId,
                'ai_scores' => $this->json($assessmentScores),
                'advisor_scores' => $this->json([]),
                'mentor_notes' => $this->json([
                    ['mentor_user_id' => $this->users['mentor']->getKey(), 'note' => 'Tighten integration assumptions before launch.'],
                ]),
                'document_support' => $this->json([
                    ['document_id' => $this->ids['doc_pitch_deck'], 'support' => 'partial'],
                ]),
                'overall_grade' => 'strong',
                'concept_pv_calculation_id' => null,
                'finalised_at' => $this->now->copy()->subDays(2),
                'finalised_by_user_id' => $this->users['advisor']->getKey(),
            ]);
        }

        $this->ids['plan_revision'] = $this->upsert('plan_revisions', [
            'business_plan_id' => $planId,
            'round' => 2,
        ], [
            'submitted_at' => $this->now->copy()->subDay(),
            'progress_comparison' => $this->json([
                'changed_sections' => ['customers', 'launch'],
                'improved_score' => 6.5,
            ]),
            'submitted_by_user_id' => $this->users['entrepreneur']->getKey(),
        ]);

        $this->ids['advisory_readiness_signal'] = $this->upsert('advisory_readiness_signals', [
            'entrepreneur_profile_id' => $profileId,
        ], [
            'business_plan_id' => $planId,
            'plan_assessment_id' => $this->ids['plan_assessment'] ?? null,
            'score' => 86.25,
            'surfaced_at' => $this->now->copy()->subDay(),
            'advisor_notified_at' => $this->now->copy()->subDay()->addMinutes(5),
        ]);

        $this->verification(
            documentId: (string) $this->ids['doc_pitch_deck'],
            context: 'pitch-deck-customer-evidence',
            client: null,
            claim: 'Pitch deck includes two letters of intent and six customer interviews.',
            outcome: 'verified',
            confidence: 0.91,
            explanation: 'Seeded verification for entrepreneur plan document support.',
            entrepreneurProfileId: (string) $profileId,
            planSectionId: (string) ($this->ids['entrepreneur_customer_section'] ?? ''),
        );
    }

    private function seedIdeaValidationTestScenarios(): void
    {
        $startProfileId = $this->upsert('entrepreneur_profiles', [
            'email' => $this->users['ideaValidationStart']->email,
        ], [
            'user_id' => $this->users['ideaValidationStart']->getKey(),
            'client_id' => null,
            'assigned_advisor_id' => $this->users['advisor']->getKey(),
            'invite_token_id' => null,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'name' => 'Seed Idea Validation Starter',
            'stage' => EntrepreneurStage::IDEA_VALIDATION->value,
            'concept_summary' => 'A new mobile app concept ready for a founder to complete the idea validation form.',
            'gamification_on' => true,
        ]);
        $this->ids['idea_validation_start_profile'] = $startProfileId;

        $reviewProfileId = $this->upsert('entrepreneur_profiles', [
            'email' => $this->users['ideaValidationReview']->email,
        ], [
            'user_id' => $this->users['ideaValidationReview']->getKey(),
            'client_id' => null,
            'assigned_advisor_id' => $this->users['advisor']->getKey(),
            'invite_token_id' => null,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'name' => 'Seed Idea Validation Review',
            'stage' => EntrepreneurStage::IDEA_VALIDATION->value,
            'concept_summary' => 'A submitted clinic appointment app concept awaiting an advisor gate decision.',
            'gamification_on' => true,
        ]);
        $this->ids['idea_validation_review_profile'] = $reviewProfileId;

        $this->ids['idea_validation_review'] = $this->upsert('idea_validations', [
            'entrepreneur_profile_id' => $reviewProfileId,
            'evaluated_at' => $this->stableTimestamp('2026-07-16 10:30:00'),
        ], [
            'revision_number' => 1,
            'previous_validation_id' => null,
            'problem' => 'Independent clinics lose appointments because patients cannot quickly see availability, join a cancellation list, or receive timely reminders.',
            'target_customer' => 'Owner-managed New Zealand allied-health clinics with three to fifteen practitioners and recurring appointment demand.',
            'solution' => 'A clinic app that fills cancelled appointment slots through an opt-in patient waitlist, automated reminders, and a simple staff dashboard.',
            'value_proposition' => 'Help small clinics recover missed revenue and give patients faster access to care without replacing their existing practice system.',
            'demand_signal' => 'Eight clinic owners described cancellations as a weekly issue, and three have agreed to test a no-cost prototype with their patients.',
            'revenue_model' => 'Monthly subscription per clinic location with a setup fee for waitlist messaging, onboarding, and staff training.',
            'ai_evaluation' => $this->json([
                'summary' => 'The problem is specific and early demand is credible. Before approval, confirm patient consent requirements, integration feasibility, and whether clinic staff will adopt a separate workflow.',
                'model' => 'seeded-idea-review',
                'prompt_id' => 'entrepreneur.idea_validation',
                'prompt_hash' => hash('sha256', 'seeded-idea-validation-review'),
                'uncertainty' => 'medium',
                'past_plan_pattern' => [
                    'source_reference' => 'past_plan_patterns:health',
                    'cohort' => 2,
                    'industry' => 'health',
                    'note' => 'Comparable health-service plans show adoption and practice-system integration as the main commercial risks.',
                ],
                'validation_evidence_loop' => [
                    'status' => 'experiments_recorded',
                    'source_reference' => 'idea_validation:experiments:founder_supplied',
                    'experiment_count' => 1,
                    'completed_experiment_count' => 1,
                    'experiments' => [[
                        'name' => 'Clinic owner discovery interviews',
                        'hypothesis' => 'Clinic owners will pay to recover cancellation revenue without changing their core practice software.',
                        'evidence' => 'Eight interviews and three prototype commitments.',
                        'result' => 'Validated for initial pilot clinics.',
                        'next_step' => 'Run a four-week patient waitlist pilot with one clinic.',
                        'status' => 'completed',
                    ]],
                ],
                'metadata' => [
                    'advisor_gate_status' => 'advisor_review',
                    'fixture' => true,
                ],
            ]),
            'viability_alerts' => $this->json([
                [
                    'severity' => 'informational',
                    'type' => 'integration_feasibility',
                    'message' => 'Confirm the first practice-management integration and patient consent approach before committing to a build timeline.',
                    'blocking' => false,
                ],
            ]),
            'evaluated_by_user_id' => $this->users['ideaValidationReview']->getKey(),
            'advisor_gate_passed_at' => null,
            'advisor_gate_passed_by_user_id' => null,
            'advisor_gate_note' => null,
            'recalled_at' => null,
            'recalled_by_user_id' => null,
        ]);
    }

    private function seedGoalsProposalsAndPayments(): void
    {
        $this->ids['goal_cashflow'] = $this->upsert('goals', [
            'client_id' => $this->clients['advisory']->getKey(),
            'title' => 'Lift cash resilience before peak season',
        ], [
            'description' => 'Reduce debtor drag and document operating cover before the next demand spike.',
            'pv_target_calculation_id' => $this->ids['pv_improvement'],
            'pv_target' => 165_000,
            'status' => 'active',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $this->ids['milestone_debtors'] = $this->upsert('milestones', [
            'goal_id' => $this->ids['goal_cashflow'],
            'title' => 'Implement debtor cadence and escalation rules',
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'recommendation_ref' => 'seed-rec-working-capital',
            'pv_of_impact_calculation_id' => $this->ids['pv_improvement'],
            'pv_of_impact' => 110_000,
            'due_date' => $this->now->copy()->addDays(30)->toDateString(),
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        $this->ids['action_debtor_policy'] = $this->upsert('milestone_actions', [
            'milestone_id' => $this->ids['milestone_debtors'],
            'title' => 'Draft debtor follow-up policy',
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'call_log_id' => null,
            'owner_user_id' => $this->users['team']->getKey(),
            'due_date' => $this->now->copy()->addDays(10)->toDateString(),
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $this->ids['proof_debtors'] = $this->upsert('proof_of_completion', [
            'milestone_id' => $this->ids['milestone_debtors'],
            'document_id' => $this->ids['doc_contract'],
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'document_verification_id' => $this->ids['verification_financials'],
            'status' => 'approved',
            'reviewed_at' => $this->now->copy()->subDay(),
        ]);

        $this->ids['fee_calculation'] = $this->upsert('fee_calculations', [
            'client_id' => $this->clients['advisory']->getKey(),
            'method' => 'outcome_based',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ], [
            'inputs' => $this->json([
                'base_fee' => 18000,
                'pv_linked_component' => 0.08,
                'risk_adjustment' => 0.95,
            ]),
            'suggested_low' => 18_000,
            'suggested_mid' => 24_000,
            'suggested_high' => 31_000,
            'improvement_pv_total' => 165_000,
            'risk_cost_pv_total' => 58_000,
            'roi_ratio' => 6.8750,
            'justification' => $this->json([
                'summary' => 'Seeded fee scenario tied to high confidence PV opportunity.',
            ]),
        ]);

        $signedAt = $this->now->copy()->subDays(4)->addMinutes(10);
        $signatureEvidencePath = 'seed/proposals/harbour-hive-signature.pdf';

        $this->ids['proposal'] = $this->upsert('proposals', [
            'client_id' => $this->clients['advisory']->getKey(),
            'fee_calculation_id' => $this->ids['fee_calculation'],
            'version' => 1,
        ], [
            'status' => 'signed',
            'scope' => $this->json([
                'modules' => ['working_capital', 'operational_resilience', 'reporting'],
                'term_months' => 6,
            ]),
            'services' => $this->json([
                ['name' => 'Monthly advisor review', 'cadence' => 'monthly'],
                ['name' => 'Implementation check-ins', 'cadence' => 'fortnightly'],
            ]),
            'pv_summary' => $this->json([
                'improvement_pv_total' => 165000,
                'risk_cost_pv_total' => 58000,
            ]),
            'roi_ratio' => 6.8750,
            'acceptance_terms' => $this->json([
                'payment' => 'monthly_card',
                'collection_day' => 1,
                'cancellation_notice_days' => 30,
            ]),
            'pdf_path' => 'seed/proposals/harbour-hive-v1.pdf',
            'pdf_byte_size' => 240_000,
            'released_at' => $this->now->copy()->subDays(6),
            'released_by_user_id' => $this->users['advisor']->getKey(),
            'expires_at' => $this->now->copy()->addDays(24),
            'recalled_at' => null,
            'recalled_by_user_id' => null,
            'expired_at' => null,
            'renewed_from_proposal_id' => null,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'awaiting_signature_at' => $this->now->copy()->subDays(5),
            'signed_at' => $signedAt,
            'signed_by_user_id' => $this->users['primary']->getKey(),
            'signature_evidence_path' => $signatureEvidencePath,
            'signature_evidence_sha256_envelope' => null,
            'signature_envelope_meta' => null,
            'signature_evidence_byte_size' => null,
        ]);

        $this->ids['proposal_consent'] = $this->upsert('consents', [
            'proposal_id' => $this->ids['proposal'],
            'type' => 'payment_authority',
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'election' => 'granted',
            'evidence' => $this->json(['proposal_id' => $this->ids['proposal'], 'ip' => '127.0.0.1']),
            'captured_by_user_id' => $this->users['primary']->getKey(),
            'revoked_by_user_id' => null,
            'captured_at' => $this->now->copy()->subDays(4),
            'revoked_at' => null,
        ]);

        $this->ids['proposal_insurance_consent'] = $this->upsert('consents', [
            'proposal_id' => $this->ids['proposal'],
            'type' => Consent::TYPE_INSURANCE_REFERRAL,
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'election' => Consent::ELECTION_OPT_IN,
            'evidence' => $this->json([
                'source' => 'proposal_signoff',
                'step' => ProposalSignoffStep::STEP_INSURANCE_CONSENT,
                'fixture' => true,
            ]),
            'captured_by_user_id' => $this->users['primary']->getKey(),
            'revoked_by_user_id' => null,
            'captured_at' => $this->now->copy()->subDays(5)->addMinutes(10),
            'revoked_at' => null,
        ]);

        $this->ids['proposal_coach_consent'] = $this->upsert('consents', [
            'proposal_id' => $this->ids['proposal'],
            'type' => Consent::TYPE_COACH_REFERRAL,
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'election' => Consent::ELECTION_OPT_OUT,
            'evidence' => $this->json([
                'source' => 'proposal_signoff',
                'step' => ProposalSignoffStep::STEP_COACH_CONSENT,
                'fixture' => true,
            ]),
            'captured_by_user_id' => $this->users['primary']->getKey(),
            'revoked_by_user_id' => null,
            'captured_at' => $this->now->copy()->subDays(5)->addMinutes(20),
            'revoked_at' => null,
        ]);

        DB::table('proposal_signoff_steps')
            ->where('proposal_id', $this->ids['proposal'])
            ->whereIn('step', ['released', 'client_signed', 'payment_authorised'])
            ->delete();

        foreach ([
            ProposalSignoffStep::STEP_REVIEW => [
                'completed_by_user_id' => $this->users['primary']->getKey(),
                'completed_at' => $this->now->copy()->subDays(5)->addMinutes(5),
                'payload' => ['reviewed' => true, 'fixture' => true],
            ],
            ProposalSignoffStep::STEP_INSURANCE_CONSENT => [
                'completed_by_user_id' => $this->users['primary']->getKey(),
                'completed_at' => $this->now->copy()->subDays(5)->addMinutes(10),
                'payload' => [
                    'type' => Consent::TYPE_INSURANCE_REFERRAL,
                    'election' => Consent::ELECTION_OPT_IN,
                    'consent_id' => $this->ids['proposal_insurance_consent'],
                    'fixture' => true,
                ],
            ],
            ProposalSignoffStep::STEP_COACH_CONSENT => [
                'completed_by_user_id' => $this->users['primary']->getKey(),
                'completed_at' => $this->now->copy()->subDays(5)->addMinutes(20),
                'payload' => [
                    'type' => Consent::TYPE_COACH_REFERRAL,
                    'election' => Consent::ELECTION_OPT_OUT,
                    'consent_id' => $this->ids['proposal_coach_consent'],
                    'fixture' => true,
                ],
            ],
            ProposalSignoffStep::STEP_PAYMENT_METHOD => [
                'completed_by_user_id' => $this->users['primary']->getKey(),
                'completed_at' => $this->now->copy()->subDays(5)->addMinutes(30),
                'payload' => [
                    'type' => PaymentAuthority::TYPE_CARD,
                    'gateway' => PaymentAuthority::GATEWAY_STRIPE,
                    'collection_day' => 1,
                    'fixture' => true,
                ],
            ],
        ] as $step => $data) {
            $this->upsert('proposal_signoff_steps', [
                'proposal_id' => $this->ids['proposal'],
                'step' => $step,
            ], [
                'client_id' => $this->clients['advisory']->getKey(),
                'completed_by_user_id' => $data['completed_by_user_id'],
                'completed_at' => $data['completed_at'],
                'payload' => $this->json($data['payload']),
            ]);
        }

        $this->ids['payment_authority'] = $this->upsert('payment_authorities', [
            'client_id' => $this->clients['advisory']->getKey(),
            'proposal_id' => $this->ids['proposal'],
            'type' => PaymentAuthority::TYPE_CARD,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
        ], [
            'gateway_customer_ref' => 'cus_seed_harbour_hive',
            'gateway_token_envelope' => app(KeyEnvelope::class)->encrypt(json_encode([
                'token' => 'pm_seed_harbour_hive',
                'customer_ref' => 'cus_seed_harbour_hive',
                'metadata' => [
                    'gateway' => PaymentAuthority::GATEWAY_STRIPE,
                    'fixture' => true,
                    'type' => PaymentAuthority::TYPE_CARD,
                    'collection_day' => 1,
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'status' => PaymentAuthority::STATUS_ACTIVE,
            'authorised_by_user_id' => $this->users['primary']->getKey(),
            'authorised_at' => $this->now->copy()->subDays(4),
            'revoked_at' => null,
        ]);

        foreach ([
            ProposalSignoffStep::STEP_AUTHORITY => [
                'completed_at' => $this->now->copy()->subDays(4),
                'payload' => [
                    'type' => PaymentAuthority::TYPE_CARD,
                    'gateway' => PaymentAuthority::GATEWAY_STRIPE,
                    'collection_day' => 1,
                    'payment_authority_id' => $this->ids['payment_authority'],
                    'gateway_customer_ref' => 'cus_seed_harbour_hive',
                    'fixture' => true,
                ],
            ],
            ProposalSignoffStep::STEP_SIGNATURE => [
                'completed_at' => $signedAt,
                'payload' => [
                    'signature_name' => 'Seed Client Principal',
                    'signed_by_user_id' => $this->users['primary']->getKey(),
                    'signature_evidence_path' => $signatureEvidencePath,
                    'collection_day' => 1,
                    'identity_verification' => [
                        'password_verified_at' => $signedAt->toIso8601String(),
                        'mfa_required' => false,
                        'mfa_verified_at' => null,
                        'mfa_method' => null,
                    ],
                    'fixture' => true,
                ],
            ],
            ProposalSignoffStep::STEP_CONFIRMATION => [
                'completed_at' => $this->now->copy()->subDays(4)->addMinutes(15),
                'payload' => ['confirmed' => true, 'fixture' => true],
            ],
        ] as $step => $data) {
            $this->upsert('proposal_signoff_steps', [
                'proposal_id' => $this->ids['proposal'],
                'step' => $step,
            ], [
                'client_id' => $this->clients['advisory']->getKey(),
                'completed_by_user_id' => $this->users['primary']->getKey(),
                'completed_at' => $data['completed_at'],
                'payload' => $this->json($data['payload']),
            ]);
        }

        $this->writeSignedProposalFixture(
            Proposal::query()->findOrFail($this->ids['proposal']),
            $this->users['primary'],
            'Seed Client Principal',
            $signedAt,
            $signatureEvidencePath,
            [
                'ip' => '127.0.0.1',
                'user_agent' => 'FutureShift testing seed data',
                'identity_verification' => [
                    'password_verified_at' => $signedAt->toIso8601String(),
                    'mfa_required' => false,
                    'mfa_verified_at' => null,
                    'mfa_method' => null,
                ],
            ],
        );

        $this->ids['payment_schedule'] = $this->upsert('payment_schedules', [
            'client_id' => $this->clients['advisory']->getKey(),
            'proposal_id' => $this->ids['proposal'],
            'cadence' => PaymentSchedule::CADENCE_MONTHLY_RETAINER,
        ], [
            'payment_authority_id' => $this->ids['payment_authority'],
            'amount' => 4_000,
            'currency' => 'NZD',
            'collection_day' => 1,
            'next_run_at' => $this->now->copy()->addMonth()->startOfDay()->setDay(1),
            'status' => 'active',
            'revoked_at' => null,
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $this->ids['payment'] = $this->upsert('payments', [
            'payment_schedule_id' => $this->ids['payment_schedule'],
            'attempt' => 1,
        ], [
            'client_id' => $this->clients['advisory']->getKey(),
            'payment_authority_id' => $this->ids['payment_authority'],
            'amount' => 4_000,
            'currency' => 'NZD',
            'gateway' => 'stripe',
            'gateway_ref' => 'pi_seed_harbour_hive_001',
            'status' => 'succeeded',
            'failover_from' => null,
            'failed_reason' => null,
            'processed_at' => $this->now->copy()->subDays(3),
        ]);

        $this->ids['receipt'] = $this->upsert('receipts', ['payment_id' => $this->ids['payment']], [
            'client_id' => $this->clients['advisory']->getKey(),
            'receipt_path' => 'seed/receipts/harbour-hive-001.pdf',
            'receipt_sha256_envelope' => hash('sha256', 'seed-receipt-harbour-hive-001'),
            'receipt_envelope_meta' => $this->json(['fixture' => true]),
            'receipt_byte_size' => 96_000,
            'generated_at' => $this->now->copy()->subDays(3)->addMinutes(5),
        ]);
    }

    private function seedNpoModuleData(): void
    {
        $this->seedNpoEngagements();
        $this->seedNpoDocumentsAndQuestionnaires();
        $this->seedNpoGovernanceAndHealth();
        $this->seedNpoFundingValueAndReports();
    }

    private function seedNpoEngagements(): void
    {
        $this->ids['npo_governance_engagement'] = $this->upsert('npo_engagements', [
            'client_id' => $this->clients['npo']->getKey(),
            'sub_type' => NpoEngagementSubType::GovernanceReview->value,
        ], [
            'legal_structure' => NpoLegalStructure::RegisteredCharityAndIncorporatedSociety->value,
            'tiriti_mode' => NpoTiritiMode::Standalone->value,
            'tiriti_decision_guide' => $this->json([
                'governance_obligation' => true,
                'mana_whenua_relationship' => true,
                'tiriti_outcomes' => true,
            ]),
            'social_enterprise' => false,
            'social_enterprise_type' => null,
            'commercial_weight' => null,
            'mission_weight' => null,
            'isa_2022_reregistered' => false,
            'converted_from_npo_engagement_id' => null,
            'conversion_status' => NpoConversionStatus::Converted->value,
            'conversion_decline_reason' => null,
            'report_delivered_at' => $this->now->copy()->subDays(21),
            'reengagement_due_at' => $this->now->copy()->addMonthsNoOverflow(11)->toDateString(),
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'updated_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $this->ids['npo_standard_engagement'] = $this->upsert('npo_engagements', [
            'client_id' => $this->clients['npo']->getKey(),
            'sub_type' => NpoEngagementSubType::StandardNpo->value,
        ], [
            'legal_structure' => NpoLegalStructure::RegisteredCharityAndIncorporatedSociety->value,
            'tiriti_mode' => NpoTiritiMode::Woven->value,
            'tiriti_decision_guide' => $this->json([
                'governance_obligation' => true,
                'mana_whenua_relationship' => true,
                'tiriti_outcomes' => true,
            ]),
            'social_enterprise' => false,
            'social_enterprise_type' => null,
            'commercial_weight' => null,
            'mission_weight' => null,
            'isa_2022_reregistered' => false,
            'converted_from_npo_engagement_id' => $this->ids['npo_governance_engagement'],
            'conversion_status' => null,
            'conversion_decline_reason' => null,
            'report_delivered_at' => null,
            'reengagement_due_at' => $this->now->copy()->addYear()->toDateString(),
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'updated_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $this->ids['social_enterprise_engagement'] = $this->upsert('npo_engagements', [
            'client_id' => $this->clients['socialEnterprise']->getKey(),
            'sub_type' => NpoEngagementSubType::SocialEnterprise->value,
        ], [
            'legal_structure' => NpoLegalStructure::SocialEnterpriseRegisteredCharity->value,
            'tiriti_mode' => NpoTiritiMode::Woven->value,
            'tiriti_decision_guide' => $this->json([
                'governance_obligation' => true,
                'mana_whenua_relationship' => false,
                'tiriti_outcomes' => true,
            ]),
            'social_enterprise' => true,
            'social_enterprise_type' => NpoSocialEnterpriseType::CrossSubsidy->value,
            'commercial_weight' => NpoSocialEnterpriseType::CrossSubsidy->commercialWeight(),
            'mission_weight' => NpoSocialEnterpriseType::CrossSubsidy->missionWeight(),
            'isa_2022_reregistered' => true,
            'converted_from_npo_engagement_id' => null,
            'conversion_status' => null,
            'conversion_decline_reason' => null,
            'report_delivered_at' => null,
            'reengagement_due_at' => $this->now->copy()->addMonthsNoOverflow(10)->toDateString(),
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'updated_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        foreach ([
            ['npo', 'npo_standard_engagement', 'npoBoard', false],
            ['npo', 'npo_standard_engagement', 'npoTreasurer', true],
            ['socialEnterprise', 'social_enterprise_engagement', 'npoBoard', false],
        ] as [$clientKey, $engagementKey, $userKey, $treasurer]) {
            $this->upsert('npo_board_members', [
                'npo_engagement_id' => $this->ids[$engagementKey],
                'user_id' => $this->users[$userKey]->getKey(),
            ], [
                'client_id' => $this->clients[$clientKey]->getKey(),
                'treasurer' => $treasurer,
                'active' => true,
                'revoked_at' => null,
                'created_by_user_id' => $this->users['advisor']->getKey(),
                'revoked_by_user_id' => null,
            ]);
        }
    }

    private function seedNpoDocumentsAndQuestionnaires(): void
    {
        $this->ids['doc_npo_constitution'] = $this->document(
            key: 'npo-current-constitution',
            client: $this->clients['npo'],
            category: Document::CATEGORY_NPO_BOARD_RECORD,
            filename: 'aroha-community-constitution.pdf',
            uploader: $this->users['npoPrimary'],
            scannerResult: Document::SCANNER_CLEAN,
            expiresAt: $this->now->copy()->addMonthsNoOverflow(10),
            size: 310_000,
            npoEngagementId: (string) $this->ids['npo_standard_engagement'],
        );
        $this->ids['doc_npo_board_minutes'] = $this->document(
            key: 'npo-board-minutes-may',
            client: $this->clients['npo'],
            category: Document::CATEGORY_NPO_MEETING_MINUTES,
            filename: 'aroha-board-minutes-may-2026.pdf',
            uploader: $this->users['npoTreasurer'],
            scannerResult: Document::SCANNER_CLEAN,
            expiresAt: null,
            size: 190_000,
            npoEngagementId: (string) $this->ids['npo_standard_engagement'],
        );
        $this->ids['doc_npo_financials'] = $this->document(
            key: 'npo-management-accounts-april',
            client: $this->clients['npo'],
            category: Document::CATEGORY_FINANCIAL_STATEMENT,
            filename: 'aroha-management-accounts-april-2026.pdf',
            uploader: $this->users['npoTreasurer'],
            scannerResult: Document::SCANNER_CLEAN,
            expiresAt: null,
            size: 380_000,
            npoEngagementId: (string) $this->ids['npo_standard_engagement'],
        );
        $this->ids['doc_npo_funding_agreement'] = $this->document(
            key: 'npo-community-wellbeing-fund-agreement',
            client: $this->clients['npo'],
            category: Document::CATEGORY_COMPLIANCE_DOC,
            filename: 'community-wellbeing-fund-agreement.pdf',
            uploader: $this->users['npoPrimary'],
            scannerResult: Document::SCANNER_CLEAN,
            expiresAt: $this->now->copy()->addDays(60),
            size: 240_000,
            npoEngagementId: (string) $this->ids['npo_standard_engagement'],
        );
        $this->ids['doc_npo_governance_pack'] = $this->document(
            key: 'npo-governance-review-pack',
            client: $this->clients['npo'],
            category: Document::CATEGORY_NPO_BOARD_RECORD,
            filename: 'aroha-governance-review-evidence-pack.pdf',
            uploader: $this->users['npoBoard'],
            scannerResult: Document::SCANNER_CLEAN,
            expiresAt: null,
            size: 520_000,
            npoEngagementId: (string) $this->ids['npo_governance_engagement'],
        );
        $this->ids['doc_social_enterprise_impact'] = $this->document(
            key: 'social-enterprise-impact-dashboard',
            client: $this->clients['socialEnterprise'],
            category: Document::CATEGORY_NPO_BOARD_RECORD,
            filename: 'tupu-impact-dashboard.pdf',
            uploader: $this->users['socialEnterprise'],
            scannerResult: Document::SCANNER_CLEAN,
            expiresAt: null,
            size: 275_000,
            npoEngagementId: (string) $this->ids['social_enterprise_engagement'],
        );

        $governance = $this->seedQuestionnaireResponse(
            client: $this->clients['npo'],
            set: QuestionnaireSet::GOVERNANCE_REVIEW,
            submittedBy: $this->users['npoBoard'],
            attachedDocumentId: (string) $this->ids['doc_npo_governance_pack'],
            npoEngagementId: (string) $this->ids['npo_governance_engagement'],
        );
        $this->ids['npo_governance_response'] = $governance['response_id'];

        $standard = $this->seedQuestionnaireResponse(
            client: $this->clients['npo'],
            set: QuestionnaireSet::STANDARD_NPO,
            submittedBy: $this->users['npoPrimary'],
            attachedDocumentId: (string) $this->ids['doc_npo_constitution'],
            npoEngagementId: (string) $this->ids['npo_standard_engagement'],
        );
        $this->ids['npo_standard_response'] = $standard['response_id'];

        $social = $this->seedQuestionnaireResponse(
            client: $this->clients['socialEnterprise'],
            set: QuestionnaireSet::STANDARD_NPO,
            submittedBy: $this->users['socialEnterprise'],
            attachedDocumentId: (string) $this->ids['doc_social_enterprise_impact'],
            npoEngagementId: (string) $this->ids['social_enterprise_engagement'],
        );
        $this->ids['social_enterprise_response'] = $social['response_id'];

        $this->ids['verification_npo_constitution'] = $this->verification(
            documentId: (string) $this->ids['doc_npo_constitution'],
            context: 'npo-constitution-current',
            client: $this->clients['npo'],
            claim: 'The governing document is uploaded and available for advisor review.',
            outcome: 'verified',
            confidence: 0.92,
            questionnaireResponseId: (string) $standard['response_id'],
            questionnaireAnswerId: $standard['file_answer_id'],
            questionnaireQuestionId: $standard['file_question_id'],
            questionPrompt: $standard['file_question_prompt'],
        );
        $this->verification(
            documentId: (string) $this->ids['doc_npo_funding_agreement'],
            context: 'npo-funding-reporting-deadline',
            client: $this->clients['npo'],
            claim: 'The funder agreement includes a reporting deadline inside the next quarter.',
            outcome: 'verified',
            confidence: 0.88,
            explanation: 'Seeded agreement fixture supports the reporting deadline used by funder alerts.',
        );
    }

    private function seedNpoGovernanceAndHealth(): void
    {
        foreach ([
            'constitution-reregistration' => [
                'category' => 'constitution_compliance',
                'severity' => 'high',
                'title' => 'Constitution update needs board sign-off before re-registration',
                'body' => 'The evidence pack shows the Incorporated Societies Act 2022 re-registration work is in progress but not yet complete.',
            ],
            'conflicts-register' => [
                'category' => 'board_controls',
                'severity' => 'medium',
                'title' => 'Conflicts register is present but not reviewed every meeting',
                'body' => 'Board minutes cite annual interest declarations, while the governance questionnaire says conflict checks are not standing agenda items.',
            ],
            'financial-delegations' => [
                'category' => 'financial_oversight',
                'severity' => 'medium',
                'title' => 'Financial delegations need clearer two-person approval thresholds',
                'body' => 'The treasurer pack includes approvals but does not show a written delegation matrix for grant-restricted spend.',
            ],
        ] as $key => $finding) {
            $this->upsert('governance_review_findings', [
                'client_id' => $this->clients['npo']->getKey(),
                'npo_engagement_id' => $this->ids['npo_governance_engagement'],
                'finding_key' => "seed-{$key}",
            ], [
                'category' => $finding['category'],
                'severity' => $finding['severity'],
                'title' => $finding['title'],
                'body' => $finding['body'],
                'criteria' => $this->json([
                    'legal_structure' => NpoLegalStructure::RegisteredCharityAndIncorporatedSociety->value,
                    'fixture' => true,
                ]),
                'evidence' => $this->json([
                    ['document_id' => $this->ids['doc_npo_governance_pack'], 'claim' => 'Governance evidence pack reviewed.'],
                    ['questionnaire_response_id' => $this->ids['npo_governance_response']],
                ]),
                'attributions' => $this->json([
                    ['type' => 'document', 'id' => $this->ids['doc_npo_governance_pack']],
                    ['type' => 'questionnaire_response', 'id' => $this->ids['npo_governance_response']],
                ]),
                'uncertainty' => 'medium',
                'ai_payload' => $this->json(['model' => 'seeded-governance-review', 'fixture' => true]),
                'status' => 'reviewed',
                'advisor_notes' => 'Seeded reviewed finding for NPO module testing.',
                'reviewed_at' => $this->now->copy()->subDays(14),
                'reviewed_by_user_id' => $this->users['advisor']->getKey(),
            ]);
        }

        $this->upsert('npo_dimension_scores', [
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'assessment_batch_id' => $this->stableUuid('seed-npo-governance-prepopulation-'.$this->ids['npo_standard_engagement']),
            'dimension_number' => 3,
        ], [
            'client_id' => $this->clients['npo']->getKey(),
            'dimension_key' => 'governance_compliance',
            'dimension_label' => 'Governance and compliance',
            'tiriti_mode' => NpoTiritiMode::Woven->value,
            'score' => 60,
            'advisor_weight' => 22,
            'weighted_score' => 13.20,
            'health_score' => null,
            'findings' => $this->json([[
                'title' => 'Governance review pre-populated the governance dimension.',
                'severity' => 'medium',
                'attributions' => [['type' => 'npo_engagement', 'id' => $this->ids['npo_governance_engagement']]],
            ]]),
            'mode_b_criteria_contributions' => $this->json(['[TIRITI] governance obligations and board accountability']),
            'source_attributions' => $this->json([['type' => 'npo_engagement', 'id' => $this->ids['npo_governance_engagement']]]),
            'scoring_context' => $this->json([
                'fixture' => true,
                'prepopulation' => [
                    'source_npo_engagement_id' => $this->ids['npo_governance_engagement'],
                    'finding_count' => 3,
                ],
            ]),
            'source' => NpoDimensionScore::SOURCE_GOVERNANCE_REVIEW_PREPOPULATION,
            'source_npo_engagement_id' => $this->ids['npo_governance_engagement'],
            'captured_at' => $this->now->copy()->subDays(6),
        ]);

        $this->seedNpoDimensionBatch(
            client: $this->clients['npo'],
            npoEngagementId: (string) $this->ids['npo_standard_engagement'],
            mode: NpoTiritiMode::Woven,
            scores: [
                'mission_strategy' => 78,
                'service_operations' => 73,
                'governance_compliance' => 62,
                'financial_sustainability' => 68,
                'people_capability' => 74,
                'impact_measurement' => 66,
                'funding_resilience' => 58,
            ],
            findings: [
                'governance_compliance' => [[
                    'title' => 'Re-registration remains the critical governance dependency.',
                    'severity' => 'high',
                    'attributions' => [['type' => 'governance_review_finding', 'key' => 'seed-constitution-reregistration']],
                ]],
                'funding_resilience' => [[
                    'title' => 'Anchor funder concentration needs renewal planning.',
                    'severity' => 'medium',
                    'attributions' => [['type' => 'client_funder_record', 'key' => 'Community Wellbeing Fund']],
                ]],
            ],
            capturedAt: $this->now->copy()->subDays(5),
            batchKey: 'seed-npo-standard-health',
        );

        $this->seedNpoDimensionBatch(
            client: $this->clients['socialEnterprise'],
            npoEngagementId: (string) $this->ids['social_enterprise_engagement'],
            mode: NpoTiritiMode::Woven,
            scores: [
                'mission_strategy' => 72,
                'service_operations' => 76,
                'governance_compliance' => 69,
                'financial_sustainability' => 64,
                'people_capability' => 71,
                'impact_measurement' => 83,
                'funding_resilience' => 57,
            ],
            findings: [
                'financial_sustainability' => [[
                    'title' => 'Cross-subsidy margin is improving but still sensitive to training cohort fill rates.',
                    'severity' => 'medium',
                    'attributions' => [['type' => 'document', 'id' => $this->ids['doc_social_enterprise_impact']]],
                ]],
                'impact_measurement' => [[
                    'title' => 'Impact dashboard is board-ready and funder-facing.',
                    'severity' => 'low',
                    'attributions' => [['type' => 'document', 'id' => $this->ids['doc_social_enterprise_impact']]],
                ]],
            ],
            capturedAt: $this->now->copy()->subDays(4),
            batchKey: 'seed-social-enterprise-health',
        );

        $this->upsert('npo_compliance_alerts', [
            'client_id' => $this->clients['npo']->getKey(),
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'type' => NpoComplianceAlert::TYPE_ISA_2022_REREGISTRATION_MISSING,
        ], [
            'severity' => NpoComplianceAlert::SEVERITY_CRITICAL,
            'message' => 'Incorporated Societies Act 2022 re-registration is not yet complete.',
            'source' => 'governance_review',
            'metadata' => $this->json([
                'finding_key' => 'seed-constitution-reregistration',
                'blocks_analysis' => true,
            ]),
            'triggered_at' => $this->now->copy()->subDays(14),
            'acknowledged_at' => null,
            'acknowledged_by_user_id' => null,
            'resolved_at' => null,
        ]);
        $this->upsert('npo_compliance_alerts', [
            'client_id' => $this->clients['npo']->getKey(),
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'type' => 'charities_return_due',
        ], [
            'severity' => 'high',
            'message' => 'Charities Services annual return is due inside the next quarter.',
            'source' => 'charities_services_fixture',
            'metadata' => $this->json(['due_on' => $this->now->copy()->addDays(54)->toDateString()]),
            'triggered_at' => $this->now->copy()->subDays(2),
            'acknowledged_at' => $this->now->copy()->subDay(),
            'acknowledged_by_user_id' => $this->users['advisor']->getKey(),
            'resolved_at' => null,
        ]);
    }

    private function seedNpoFundingValueAndReports(): void
    {
        $learningUpdateId = $this->upsert('learning_updates', [
            'layer_id' => LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES,
            'summary' => 'Seeded NPO funder registry baseline',
        ], [
            'source' => $this->json(['type' => 'testing_seed', 'fixture' => true]),
            'proposed_change' => $this->json(['action' => 'seed_funder_registry']),
            'impact_scope' => $this->json(['surface' => 'npo_funder_registry']),
            'clients_affected' => 2,
            'magnitude' => 'low',
            'confidence' => 0.9000,
            'evidence' => $this->json(['source' => 'seed fixture']),
            'effective_date' => $this->now->copy()->subDays(30),
            'pre_implementation_notice_at' => null,
            'review_due_at' => $this->now->copy()->addMonthsNoOverflow(3),
            'status' => LearningUpdate::STATUS_APPROVED,
            'decided_by_user_id' => $this->users['admin']->getKey(),
            'decided_at' => $this->now->copy()->subDays(29),
            'rollback_id' => null,
        ]);
        $this->ids['npo_funder_learning_update'] = $learningUpdateId;

        foreach ([
            'community_wellbeing' => [
                'name' => 'Seed Community Wellbeing Fund',
                'type' => Funder::TYPE_PHILANTHROPIC,
                'windows' => [['opens' => $this->now->copy()->addDays(60)->toDateString(), 'closes' => $this->now->copy()->addDays(90)->toDateString()]],
                'criteria' => ['region' => 'Waikato', 'focus' => ['youth', 'community wellbeing']],
                'requirements' => ['six_month_report' => true, 'impact_metrics' => ['participants', 'volunteer_hours']],
                'renewal' => ['renewal_weight' => 0.72, 'relationship_signal' => 'warm'],
            ],
            'council' => [
                'name' => 'Seed Auckland Council Community Grants',
                'type' => Funder::TYPE_GOVERNMENT,
                'windows' => [['opens' => $this->now->copy()->subDays(1)->toDateString(), 'closes' => $this->now->copy()->addDays(21)->toDateString()]],
                'criteria' => ['region' => 'Auckland', 'requires_charity_registration' => true],
                'requirements' => ['annual_report' => true, 'receipts' => true],
                'renewal' => ['renewal_weight' => 0.55, 'relationship_signal' => 'standard'],
            ],
            'impact_enterprise' => [
                'name' => 'Seed Impact Enterprise Foundation',
                'type' => Funder::TYPE_COMMUNITY,
                'windows' => [['opens' => $this->now->copy()->addDays(18)->toDateString(), 'closes' => $this->now->copy()->addDays(48)->toDateString()]],
                'criteria' => ['focus' => ['employment pathways', 'social enterprise'], 'earned_income_required' => true],
                'requirements' => ['quarterly_dashboard' => true, 'case_studies' => true],
                'renewal' => ['renewal_weight' => 0.64, 'relationship_signal' => 'developing'],
            ],
        ] as $key => $funder) {
            $this->ids["funder_{$key}"] = $this->upsert('funders', ['name' => $funder['name']], [
                'type' => $funder['type'],
                'funding_windows' => $this->json($funder['windows']),
                'criteria' => $this->json($funder['criteria']),
                'reporting_requirements' => $this->json($funder['requirements']),
                'renewal_intelligence' => $this->json($funder['renewal']),
                'last_verified_at' => $this->now->copy()->subDays(11),
                'source_learning_update_id' => $learningUpdateId,
            ]);
        }

        $this->ids['npo_funder_record_community'] = $this->upsert('client_funder_records', [
            'client_id' => $this->clients['npo']->getKey(),
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'funder_id' => $this->ids['funder_community_wellbeing'],
            'grant_name' => 'Community wellbeing backbone grant',
        ], [
            'grant_amount' => 120_000,
            'currency' => 'NZD',
            'period_start' => $this->now->copy()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(),
            'period_end' => $this->now->copy()->addMonthsNoOverflow(10)->endOfMonth()->toDateString(),
            'conditions' => $this->json(['restricted_to' => 'youth outreach', 'six_month_report' => true]),
            'reporting_deadline' => $this->now->copy()->addDays(30)->toDateString(),
            'next_application_window_opens_at' => $this->now->copy()->addDays(60)->toDateString(),
            'next_application_window_closes_at' => $this->now->copy()->addDays(90)->toDateString(),
            'grant_expiry_at' => $this->now->copy()->addDays(60)->toDateString(),
            'renewal_probability' => 72,
            'notes' => 'Anchor funder with report due and renewal window coming up.',
            'history' => $this->json([
                ['event' => 'grant_awarded', 'at' => $this->now->copy()->subMonthsNoOverflow(2)->toDateString()],
                ['event' => 'advisor_reviewed_conditions', 'at' => $this->now->copy()->subDays(8)->toDateString()],
            ]),
        ]);
        $this->ids['npo_funder_record_council'] = $this->upsert('client_funder_records', [
            'client_id' => $this->clients['npo']->getKey(),
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'funder_id' => $this->ids['funder_council'],
            'grant_name' => 'Local activation grant',
        ], [
            'grant_amount' => 45_000,
            'currency' => 'NZD',
            'period_start' => $this->now->copy()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => $this->now->copy()->addMonthsNoOverflow(5)->endOfMonth()->toDateString(),
            'conditions' => $this->json(['receipts_required' => true, 'restricted_to' => 'community events']),
            'reporting_deadline' => $this->now->copy()->addDays(7)->toDateString(),
            'next_application_window_opens_at' => $this->now->copy()->subDay()->toDateString(),
            'next_application_window_closes_at' => $this->now->copy()->addDays(21)->toDateString(),
            'grant_expiry_at' => $this->now->copy()->addMonthsNoOverflow(5)->toDateString(),
            'renewal_probability' => 58,
            'notes' => 'Short-cycle grant useful for alert and deadline testing.',
            'history' => $this->json([['event' => 'grant_record_seeded']]),
        ]);
        $this->ids['social_funder_record_impact'] = $this->upsert('client_funder_records', [
            'client_id' => $this->clients['socialEnterprise']->getKey(),
            'npo_engagement_id' => $this->ids['social_enterprise_engagement'],
            'funder_id' => $this->ids['funder_impact_enterprise'],
            'grant_name' => 'Employment pathway growth grant',
        ], [
            'grant_amount' => 85_000,
            'currency' => 'NZD',
            'period_start' => $this->now->copy()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => $this->now->copy()->addYear()->endOfMonth()->toDateString(),
            'conditions' => $this->json(['employment_outcomes' => true, 'earned_income_reporting' => true]),
            'reporting_deadline' => $this->now->copy()->addDays(45)->toDateString(),
            'next_application_window_opens_at' => $this->now->copy()->addDays(18)->toDateString(),
            'next_application_window_closes_at' => $this->now->copy()->addDays(48)->toDateString(),
            'grant_expiry_at' => $this->now->copy()->addYear()->toDateString(),
            'renewal_probability' => 64,
            'notes' => 'Social enterprise grant connected to dual-impact reporting.',
            'history' => $this->json([['event' => 'impact_dashboard_shared']]),
        ]);

        foreach ([
            ['npo_funder_record_community', ClientFunderAlert::TYPE_REPORT_DUE_30, ClientFunderAlert::SEVERITY_MEDIUM, 'Funder report due in 30 days.', $this->now->copy()->addDays(30)],
            ['npo_funder_record_community', ClientFunderAlert::TYPE_GRANT_EXPIRY_60, ClientFunderAlert::SEVERITY_HIGH, 'Grant expires in 60 days.', $this->now->copy()->addDays(60)],
            ['npo_funder_record_council', ClientFunderAlert::TYPE_REPORT_DUE_7, ClientFunderAlert::SEVERITY_HIGH, 'Funder report due in 7 days.', $this->now->copy()->addDays(7)],
            ['npo_funder_record_council', ClientFunderAlert::TYPE_APPLICATION_WINDOW_OPEN, ClientFunderAlert::SEVERITY_HIGH, 'Funder application window is open.', $this->now->copy()->subDay()],
        ] as [$recordKey, $type, $severity, $message, $dueOn]) {
            $recordId = (string) $this->ids[$recordKey];
            $clientId = DB::table('client_funder_records')->where('id', $recordId)->value('client_id');
            $this->upsert('client_funder_alerts', [
                'alert_key' => "{$recordId}:{$type}:{$dueOn->toDateString()}",
            ], [
                'client_id' => $clientId,
                'client_funder_record_id' => $recordId,
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
                'due_on' => $dueOn->toDateString(),
                'triggered_at' => $this->now->copy()->subHours(3),
                'resolved_at' => null,
                'metadata' => $this->json(['fixture' => true, 'record_key' => $recordKey]),
            ]);
        }

        $this->ids['npo_accounting_connection'] = $this->upsert('accounting_connections', [
            'client_id' => $this->clients['npo']->getKey(),
            'provider' => 'manual_npo',
        ], [
            'external_tenant_id' => 'seed-manual-aroha',
            'status' => 'connected',
            'token_envelope' => encrypt('seed-manual-npo-token'),
            'token_envelope_meta' => $this->json(['fixture' => true]),
            'scopes' => $this->json(['manual.financials.read']),
            'connected_by_user_id' => $this->users['npoTreasurer']->getKey(),
            'connected_at' => $this->now->copy()->subDays(18),
            'revoked_by_user_id' => null,
            'revoked_at' => null,
            'last_snapshot_at' => $this->now->copy()->subDay(),
        ]);
        $this->firstOrInsert('financial_snapshots', [
            'client_id' => $this->clients['npo']->getKey(),
            'accounting_connection_id' => $this->ids['npo_accounting_connection'],
            'period_end' => '2026-04-30',
            'source' => 'seeded_npo_manual',
        ], [
            'provider' => 'manual_npo',
            'period_start' => '2026-04-01',
            'source_badge' => 'seeded',
            'degraded' => false,
            'correlation_id' => null,
            'profit_and_loss' => $this->json(['revenue' => 420000, 'grant_revenue' => 310000, 'programme_expenditure' => 230000, 'surplus' => 18000]),
            'balance_sheet' => $this->json(['cash' => 118000, 'restricted_cash' => 62000, 'unrestricted_reserves' => 96000, 'liabilities' => 41000]),
            'cash_flow' => $this->json(['operating' => 22000, 'grant_receipts' => 125000, 'programme_spend' => -74000]),
            'metrics' => $this->json(['months_unrestricted_reserves' => 4.8, 'beneficiaries_served' => 520, 'monthly_opex' => 20000]),
            'pulled_at' => $this->now->copy()->subDay(),
        ]);

        $this->ids['npo_value_cost_per_beneficiary'] = $this->upsert('npo_value_calculations', [
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'type' => NpoValueCalculation::TYPE_COST_PER_BENEFICIARY,
            'calculated_at' => $this->stableTimestamp('2026-05-23 09:00:00'),
        ], [
            'client_id' => $this->clients['npo']->getKey(),
            'dimension_number' => 4,
            'programme_type' => 'community_services',
            'size_band' => 'medium',
            'rating' => 'watch',
            'projection_mid' => 28_000,
            'projection_low' => 23_800,
            'projection_high' => 32_200,
            'inputs' => $this->json(['programme_expenditure' => 230000, 'beneficiary_count' => 520, 'programme_type' => 'community_services']),
            'result' => $this->json([
                'cost_per_beneficiary' => 442.31,
                'benchmark_cost_per_beneficiary' => 388.00,
                'variance_to_benchmark' => 54.31,
                'rating' => 'watch',
                'mission_framing' => 'Efficiency improvement is framed as capacity to serve more whanau, not surplus extraction.',
                'projections' => [[
                    'key' => 'annual_reinvestment_capacity',
                    'label' => 'Annual reinvestment capacity',
                    'mid' => 28_000,
                    'low' => 23_800,
                    'high' => 32_200,
                    'unit' => 'nzd',
                    'uncertainty' => ['rate' => 0.15, 'basis' => '+/-15% seed uncertainty range'],
                ]],
            ]),
            'benchmark_config' => $this->json(['source_reference' => 'seed-layer-36-community-services-medium', 'cost_per_beneficiary' => 388.00]),
            'source_attributions' => $this->json([
                ['claim' => 'Programme expenditure came from seeded NPO financial snapshot.', 'source_reference' => 'financial_snapshots:seeded_npo_manual'],
            ]),
            'stable_assumption_disclosure' => 'Projection keeps programme scope, beneficiary demand, and delivery cost base stable; every projection carries a +/-15% uncertainty range.',
        ]);
        $this->ids['npo_value_funding_risk'] = $this->upsert('npo_value_calculations', [
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'type' => NpoValueCalculation::TYPE_FUNDING_RISK,
            'calculated_at' => $this->stableTimestamp('2026-05-23 10:00:00'),
        ], [
            'client_id' => $this->clients['npo']->getKey(),
            'dimension_number' => 2,
            'programme_type' => null,
            'size_band' => null,
            'rating' => 'high',
            'projection_mid' => 92_000,
            'projection_low' => 78_200,
            'projection_high' => 105_800,
            'inputs' => $this->json(['annual_revenue' => 420000, 'largest_funder_amount' => 120000, 'unrestricted_reserves' => 96000]),
            'result' => $this->json([
                'rating' => 'high',
                'concentration' => ['largest_funder_ratio' => 0.2857, 'largest_funder_name' => 'Seed Community Wellbeing Fund'],
                'runway' => ['months' => 4.8, 'rating' => 'watch'],
                'mission_framing' => 'Funding risk value estimates mission delivery capacity exposed through renewal uncertainty and reserve runway pressure.',
                'projections' => [[
                    'key' => 'risk_exposure',
                    'label' => 'Funding risk value',
                    'mid' => 92_000,
                    'low' => 78_200,
                    'high' => 105_800,
                    'unit' => 'nzd',
                    'uncertainty' => ['rate' => 0.15, 'basis' => '+/-15% seed uncertainty range'],
                ]],
            ]),
            'benchmark_config' => $this->json(['source_reference' => 'seed-layer-37-funding-thresholds', 'largest_funder_watch' => 0.25]),
            'source_attributions' => $this->json([
                ['claim' => 'Funder concentration came from seeded client funder records.', 'source_reference' => 'client_funder_records:seed'],
            ]),
            'stable_assumption_disclosure' => 'Projection keeps current revenue, unrestricted reserves, operating cost base, and renewal probabilities stable; every projection carries a +/-15% uncertainty range.',
        ]);

        $this->ids['social_scorecard'] = $this->upsert('npo_social_enterprise_scorecards', [
            'npo_engagement_id' => $this->ids['social_enterprise_engagement'],
            'calculated_at' => $this->stableTimestamp('2026-05-24 09:00:00'),
        ], [
            'client_id' => $this->clients['socialEnterprise']->getKey(),
            'commercial_score' => 68,
            'mission_score' => 70,
            'commercial_weight' => NpoSocialEnterpriseType::CrossSubsidy->commercialWeight(),
            'mission_weight' => NpoSocialEnterpriseType::CrossSubsidy->missionWeight(),
            'blended_score' => 69.20,
            'commercial_axes' => $this->json([
                ['axis' => 'margin_resilience', 'score' => 62],
                ['axis' => 'earned_income_pipeline', 'score' => 74],
            ]),
            'mission_axes' => $this->json([
                ['dimension' => 'impact_measurement', 'score' => 83],
                ['dimension' => 'funding_resilience', 'score' => 57],
            ]),
            'source_attributions' => $this->json([
                ['claim' => 'Commercial score uses seeded business-health axes.', 'source_reference' => 'seed-social-enterprise-commercial'],
                ['claim' => 'Mission score uses seeded NPO health batch.', 'source_reference' => 'npo_dimension_scores:seed-social-enterprise-health'],
            ]),
        ]);
        $this->upsert('npo_tension_analyses', [
            'npo_social_enterprise_scorecard_id' => $this->ids['social_scorecard'],
            'generated_at' => $this->stableTimestamp('2026-05-24 10:00:00'),
        ], [
            'client_id' => $this->clients['socialEnterprise']->getKey(),
            'npo_engagement_id' => $this->ids['social_enterprise_engagement'],
            'review_status' => NpoTensionAnalysis::REVIEW_REVIEWED,
            'tensions' => $this->json([
                [
                    'type' => NpoTensionAnalysis::TYPE_REVENUE_VS_ACCESS,
                    'title' => 'Training fee growth may reduce access for priority cohorts',
                    'commercial_implication' => 'Higher fee recovery improves trading margin.',
                    'mission_implication' => 'Priority participants may need scholarships to keep access equitable.',
                    'strategic_options' => ['ring-fenced scholarship pool', 'tiered pricing', 'funder-backed places'],
                    'advisor_recommended_path' => 'Keep tiered pricing and track access outcomes quarterly.',
                    'data_points' => [
                        ['label' => 'Commercial score', 'value' => 68, 'source_reference' => 'npo_social_enterprise_scorecards:seed'],
                    ],
                ],
            ]),
            'ai_response' => $this->json(['model' => 'seeded-social-enterprise-analysis', 'fixture' => true]),
            'source_attributions' => $this->json([
                ['claim' => 'Tension analysis uses seeded scorecard and impact dashboard.', 'source_reference' => 'doc_social_enterprise_impact'],
            ]),
            'reviewed_by_user_id' => $this->users['advisor']->getKey(),
            'reviewed_at' => $this->now->copy()->subDays(2),
        ]);

        foreach ([
            ['npo', 'npo_standard_engagement', 'people_reached', 'People reached through youth outreach', 520, 'people', 575.00],
            ['npo', 'npo_standard_engagement', 'volunteer_hours', 'Volunteer hours contributed', 1840, 'hours', null],
            ['npo', 'npo_standard_engagement', 'wellbeing_sessions', 'Wellbeing sessions delivered', 96, 'sessions', 104.00],
            ['socialEnterprise', 'social_enterprise_engagement', 'training_placements', 'Training placements completed', 34, 'placements', 39.00],
        ] as [$clientKey, $engagementKey, $metricKey, $label, $value, $unit, $platformValue]) {
            $this->upsert('npo_impact_metrics', [
                'npo_engagement_id' => $this->ids[$engagementKey],
                'metric_key' => $metricKey,
                'period_end' => '2026-04-30',
            ], [
                'client_id' => $this->clients[$clientKey]->getKey(),
                'metric_label' => $label,
                'value' => $value,
                'unit' => $unit,
                'platform_value' => $platformValue,
                'period_start' => '2026-04-01',
                'source' => 'testing_seed',
                'notes' => 'Seeded impact metric for NPO portal and report testing.',
                'entered_by_user_id' => $clientKey === 'npo' ? $this->users['npoPrimary']->getKey() : $this->users['socialEnterprise']->getKey(),
            ]);
        }

        $this->ids['npo_goal_funder_readiness'] = $this->upsert('goals', [
            'client_id' => $this->clients['npo']->getKey(),
            'title' => 'Strengthen funder readiness and board assurance',
        ], [
            'description' => 'Close governance and reporting gaps before the next funder renewal cycle.',
            'pv_target_calculation_id' => null,
            'pv_target' => 92_000,
            'status' => 'active',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ]);
        $this->ids['npo_milestone_accountability_pack'] = $this->upsert('milestones', [
            'goal_id' => $this->ids['npo_goal_funder_readiness'],
            'title' => 'Complete six-month accountability pack',
        ], [
            'client_id' => $this->clients['npo']->getKey(),
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'recommendation_ref' => 'seed-npo-funder-accountability',
            'pv_of_impact_calculation_id' => null,
            'pv_of_impact' => 42_000,
            'due_date' => $this->now->copy()->addDays(21)->toDateString(),
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
        $this->upsert('milestone_actions', [
            'milestone_id' => $this->ids['npo_milestone_accountability_pack'],
            'title' => 'Attach outcomes dashboard and treasurer sign-off',
        ], [
            'client_id' => $this->clients['npo']->getKey(),
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'call_log_id' => null,
            'owner_user_id' => $this->users['npoTreasurer']->getKey(),
            'due_date' => $this->now->copy()->addDays(10)->toDateString(),
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $this->ids['npo_fee_governance_review'] = $this->upsert('fee_calculations', [
            'client_id' => $this->clients['npo']->getKey(),
            'npo_engagement_id' => $this->ids['npo_governance_engagement'],
            'method' => 'governance_review',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ], [
            'inputs' => $this->json(['review_scope' => 'board_pack_constitution_financial_controls', 'charity_and_society' => true]),
            'suggested_low' => 4_800,
            'suggested_mid' => 6_400,
            'suggested_high' => 8_200,
            'improvement_pv_total' => 0,
            'risk_cost_pv_total' => 0,
            'roi_ratio' => 0,
            'justification' => $this->json(['summary' => 'Fixed-fee governance review seed scenario.']),
        ]);
        $this->ids['npo_proposal_governance_review'] = $this->upsert('proposals', [
            'client_id' => $this->clients['npo']->getKey(),
            'fee_calculation_id' => $this->ids['npo_fee_governance_review'],
            'version' => 1,
        ], [
            'npo_engagement_id' => $this->ids['npo_governance_engagement'],
            'status' => 'released',
            'scope' => $this->json(['modules' => ['governance_review', 'constitution', 'board_controls'], 'term_weeks' => 4]),
            'services' => $this->json([
                ['name' => 'Governance evidence review', 'cadence' => 'one_off'],
                ['name' => 'Board findings session', 'cadence' => 'one_off'],
            ]),
            'pv_summary' => $this->json(['mission_risk_reduction' => true]),
            'roi_ratio' => 0,
            'acceptance_terms' => $this->json(['payment' => 'invoice', 'valid_days' => 14]),
            'pdf_path' => 'seed/proposals/aroha-governance-review-v1.pdf',
            'pdf_byte_size' => 170_000,
            'released_at' => $this->now->copy()->subDays(25),
            'released_by_user_id' => $this->users['advisor']->getKey(),
            'expires_at' => $this->now->copy()->addDays(7),
            'recalled_at' => null,
            'recalled_by_user_id' => null,
            'expired_at' => null,
            'renewed_from_proposal_id' => null,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'awaiting_signature_at' => null,
            'signed_at' => null,
            'signed_by_user_id' => null,
            'signature_evidence_path' => null,
            'signature_evidence_sha256_envelope' => null,
            'signature_envelope_meta' => null,
            'signature_evidence_byte_size' => null,
        ]);

        $governanceReportId = $this->seedNpoReport(
            idKey: 'npo_governance_report',
            client: $this->clients['npo'],
            npoEngagementId: (string) $this->ids['npo_governance_engagement'],
            type: ReportType::GovernanceReview,
            title: 'Aroha Community Trust Governance Review',
            sections: [
                ['key' => 'executive_summary', 'title' => 'Executive summary', 'body' => 'Governance review is complete with re-registration, conflicts, and delegation actions surfaced.'],
                ['key' => 'findings', 'title' => 'Findings', 'body' => 'Three reviewed findings are linked to questionnaire and document evidence.'],
            ],
            reviewStatus: 'reviewed',
            metadata: ['fixture' => true, 'source' => 'testing_seed'],
        );
        $healthReportId = $this->seedNpoReport(
            idKey: 'npo_health_report',
            client: $this->clients['npo'],
            npoEngagementId: (string) $this->ids['npo_standard_engagement'],
            type: ReportType::NpoHealth,
            title: 'Aroha Community Trust NPO Health Report',
            sections: [
                ['key' => 'health_score', 'title' => 'NPO health score', 'body' => 'Latest seeded health batch shows funding resilience and governance compliance as priority dimensions.'],
                ['key' => 'dimension_actions', 'title' => 'Dimension actions', 'body' => 'Actions focus on funder reporting, re-registration, and impact measurement cadence.'],
            ],
            reviewStatus: 'reviewed',
            metadata: ['fixture' => true, 'health_score_source' => 'npo_dimension_scores'],
        );
        $funderReportId = $this->seedNpoReport(
            idKey: 'npo_funder_accountability_report',
            client: $this->clients['npo'],
            npoEngagementId: (string) $this->ids['npo_standard_engagement'],
            type: ReportType::FunderAccountability,
            title: 'Community Wellbeing Fund Accountability Report',
            sections: [
                ['key' => 'funding_conditions', 'title' => 'Funding conditions', 'body' => 'The report summarises restricted-funding conditions and due impact metrics.'],
                ['key' => 'impact_metrics', 'title' => 'Impact metrics', 'body' => 'People reached: 520 people. Volunteer hours contributed: 1840 hours.'],
            ],
            reviewStatus: 'reviewed',
            metadata: ['fixture' => true, 'client_funder_record_id' => $this->ids['npo_funder_record_community']],
        );
        $this->ids['npo_report_governance'] = $governanceReportId;
        $this->ids['npo_report_health'] = $healthReportId;
        $this->ids['npo_report_funder'] = $funderReportId;
        $this->ids['social_report_dual'] = $this->seedNpoReport(
            idKey: 'social_enterprise_dual_report',
            client: $this->clients['socialEnterprise'],
            npoEngagementId: (string) $this->ids['social_enterprise_engagement'],
            type: ReportType::SocialEnterpriseDual,
            title: 'Tupu Trading Dual Impact Report',
            sections: [
                ['key' => 'dual_score', 'title' => 'Dual impact score', 'body' => 'Commercial score 68 and mission score 70 produce a blended seeded score of 69.2.'],
                ['key' => 'tensions', 'title' => 'Strategic tensions', 'body' => 'Fee growth should be balanced with funded access for priority training cohorts.'],
            ],
            reviewStatus: 'reviewed',
            metadata: ['fixture' => true, 'scorecard_id' => $this->ids['social_scorecard']],
        );

        $this->ids['npo_funder_report_link'] = $this->upsert('npo_funder_report_links', [
            'client_id' => $this->clients['npo']->getKey(),
            'npo_engagement_id' => $this->ids['npo_standard_engagement'],
            'guest_email' => 'programme.officer@example.test',
            'report_id' => $funderReportId,
        ], [
            'client_funder_record_id' => $this->ids['npo_funder_record_community'],
            'status' => 'approved',
            'token_hash' => hash('sha256', 'seed-npo-funder-report-token'),
            'requested_by_user_id' => $this->users['npoPrimary']->getKey(),
            'approved_by_user_id' => $this->users['advisor']->getKey(),
            'declined_by_user_id' => null,
            'revoked_by_user_id' => null,
            'approved_at' => $this->now->copy()->subDay(),
            'declined_at' => null,
            'decline_reason' => null,
            'expires_at' => $this->now->copy()->addDays(20),
            'revoked_at' => null,
            'last_used_at' => $this->now->copy()->subHours(6),
        ]);
        $this->upsert('npo_funder_report_sessions', [
            'npo_funder_report_link_id' => $this->ids['npo_funder_report_link'],
            'report_id' => $funderReportId,
            'accessed_at' => $this->stableTimestamp('2026-05-26 09:00:00'),
        ], [
            'client_id' => $this->clients['npo']->getKey(),
            'metadata' => $this->json(['fixture' => true, 'ip' => '127.0.0.1']),
        ], timestamps: false);

        $threadId = $this->upsert('message_threads', [
            'client_id' => $this->clients['npo']->getKey(),
            'subject' => 'NPO accountability pack next steps',
        ], [
            'entrepreneur_profile_id' => null,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'last_activity_at' => $this->now->copy()->subHours(2),
        ]);
        foreach (['advisor', 'npoPrimary', 'npoTreasurer'] as $userKey) {
            $this->upsert('message_thread_participants', [
                'thread_id' => $threadId,
                'user_id' => $this->users[$userKey]->getKey(),
            ], [
                'last_read_at' => $userKey === 'advisor' ? $this->now->copy()->subHour() : null,
            ]);
        }
        $this->upsert('messages', [
            'logical_message_key' => 'seed-npo-accountability-001',
            'channel' => 'in_app',
        ], [
            'thread_id' => $threadId,
            'sender_user_id' => $this->users['advisor']->getKey(),
            'body' => 'The funder accountability report is reviewed. Please attach the treasurer sign-off before we share the next impact update.',
            'attachments' => $this->json([
                ['type' => 'report', 'id' => $funderReportId],
                ['type' => 'document', 'id' => $this->ids['doc_npo_funding_agreement']],
            ]),
            'delivery_state' => 'sent',
            'channel_decision' => $this->json(['selected' => 'in_app', 'reason' => 'npo_client_portal']),
            'email_subject' => null,
            'email_recipients' => null,
            'sent_at' => $this->now->copy()->subHours(2),
        ]);
    }

    /**
     * @param  array<int|string, int>  $scores
     * @param  array<int|string, array<int, array<string, mixed>>>  $findings
     */
    private function seedNpoDimensionBatch(
        Client $client,
        string $npoEngagementId,
        NpoTiritiMode $mode,
        array $scores,
        array $findings,
        CarbonInterface $capturedAt,
        string $batchKey,
    ): void {
        $definitions = $this->npoDimensionDefinitions($mode);
        $healthScore = (int) round(collect($definitions)->sum(function (array $definition) use ($scores): float {
            $score = (int) ($scores[$definition['number']] ?? $scores[$definition['key']] ?? 0);

            return $score * $definition['weight'] / 100;
        }));
        $batchId = $this->stableUuid($batchKey.'-'.$npoEngagementId);

        foreach ($definitions as $definition) {
            $score = (int) ($scores[$definition['number']] ?? $scores[$definition['key']] ?? 0);
            $dimensionFindings = array_values($findings[$definition['number']] ?? $findings[$definition['key']] ?? []);

            $this->upsert('npo_dimension_scores', [
                'npo_engagement_id' => $npoEngagementId,
                'assessment_batch_id' => $batchId,
                'dimension_number' => $definition['number'],
            ], [
                'client_id' => $client->getKey(),
                'dimension_key' => $definition['key'],
                'dimension_label' => $definition['label'],
                'tiriti_mode' => $mode->value,
                'score' => max(0, min(100, $score)),
                'advisor_weight' => $definition['weight'],
                'weighted_score' => round(max(0, min(100, $score)) * $definition['weight'] / 100, 2),
                'health_score' => max(0, min(100, $healthScore)),
                'findings' => $this->json($dimensionFindings),
                'mode_b_criteria_contributions' => $this->jsonOrNull($definition['mode_b_contributions']),
                'source_attributions' => $this->json($this->npoFindingAttributions($dimensionFindings)),
                'scoring_context' => $this->json([
                    'fixture' => true,
                    'social_weighting' => [
                        'social_enterprise' => $client->getKey() === $this->clients['socialEnterprise']->getKey(),
                    ],
                ]),
                'source' => NpoDimensionScore::SOURCE_ADVISOR_ASSESSMENT,
                'source_npo_engagement_id' => null,
                'captured_at' => $capturedAt,
            ]);
        }
    }

    /**
     * @return array<int, array{number:int,key:string,label:string,weight:int,mode_b_contributions:?array<int, string>}>
     */
    private function npoDimensionDefinitions(NpoTiritiMode $mode): array
    {
        $labels = [
            1 => ['key' => 'mission_strategy', 'label' => 'Mission and strategy'],
            2 => ['key' => 'service_operations', 'label' => 'Service delivery and operations'],
            3 => ['key' => 'governance_compliance', 'label' => 'Governance and compliance'],
            4 => ['key' => 'financial_sustainability', 'label' => 'Financial sustainability'],
            5 => ['key' => 'people_capability', 'label' => 'People and capability'],
            6 => ['key' => 'impact_measurement', 'label' => 'Impact measurement'],
            7 => ['key' => 'funding_resilience', 'label' => 'Funding resilience'],
            8 => ['key' => 'te_tiriti', 'label' => 'Te Tiriti'],
        ];
        $weights = $mode === NpoTiritiMode::Standalone
            ? [1 => 10, 2 => 10, 3 => 20, 4 => 15, 5 => 10, 6 => 10, 7 => 15, 8 => 10]
            : [1 => 12, 2 => 11, 3 => 22, 4 => 16, 5 => 11, 6 => 11, 7 => 17];
        $modeB = $mode === NpoTiritiMode::Woven ? [
            1 => ['[TIRITI] purpose and partnership alignment'],
            3 => ['[TIRITI] governance obligations and board accountability'],
            6 => ['[TIRITI] equity and outcomes evidence'],
            7 => ['[TIRITI] funder obligations and restricted funding impacts'],
        ] : [];

        return collect($weights)
            ->map(fn (int $weight, int $number): array => [
                'number' => $number,
                'key' => $labels[$number]['key'],
                'label' => $labels[$number]['label'],
                'weight' => $weight,
                'mode_b_contributions' => $modeB[$number] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function npoFindingAttributions(array $findings): array
    {
        return collect($findings)
            ->flatMap(fn (array $finding): array => collect($finding['attributions'] ?? [])->all())
            ->filter(fn (mixed $attribution): bool => is_array($attribution))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{key:string,title:string,body:string,lens?:string,document_support?:string,document_support_note?:string,data_quality_note?:string,metadata?:array<string, mixed>,attributions?:array<int, array<string, mixed>>}>  $sections
     * @param  array<string, mixed>  $metadata
     */
    private function seedNpoReport(
        string $idKey,
        Client $client,
        string $npoEngagementId,
        ReportType $type,
        string $title,
        array $sections,
        string $reviewStatus,
        array $metadata,
    ): string|int|null {
        $reportId = $this->upsert('reports', [
            'client_id' => $client->getKey(),
            'type' => $type->value,
            'title' => $title,
        ], [
            'npo_engagement_id' => $npoEngagementId,
            'pdf_path' => "seed/reports/{$idKey}.pdf",
            'pdf_byte_size' => 220_000,
            'pptx_path' => null,
            'pptx_byte_size' => null,
            'generated_by_user_id' => $this->users['advisor']->getKey(),
            'generated_at' => $this->now->copy()->subDays(2),
            'metadata' => $this->json([
                ...$metadata,
                'npo_engagement_id' => $npoEngagementId,
            ]),
            'review_status' => $reviewStatus,
            'reviewed_by_user_id' => $reviewStatus === 'reviewed' ? $this->users['advisor']->getKey() : null,
            'reviewed_at' => $reviewStatus === 'reviewed' ? $this->now->copy()->subDay() : null,
        ]);

        foreach ($sections as $position => $section) {
            $this->upsert('report_sections', [
                'report_id' => $reportId,
                'key' => $section['key'],
            ], [
                'client_id' => $client->getKey(),
                'entrepreneur_profile_id' => null,
                'title' => $section['title'],
                'body' => $section['body'],
                'position' => $position + 1,
                'lens' => $section['lens'] ?? 'diagnostic',
                'attributions' => $this->json($section['attributions'] ?? [['type' => 'npo_engagement', 'id' => $npoEngagementId]]),
                'document_support' => $section['document_support'] ?? 'supported',
                'document_support_note' => $section['document_support_note'] ?? 'Seeded NPO report section with fixture evidence.',
                'data_quality_note' => $section['data_quality_note'] ?? 'Testing seed data; not a production advice record.',
                'metadata' => $this->json($section['metadata'] ?? ['fixture' => true]),
            ]);
        }

        return $reportId;
    }

    private function seedEngagementTouchpoints(): void
    {
        $threadId = $this->upsert('message_threads', [
            'client_id' => $this->clients['advisory']->getKey(),
            'subject' => 'Testing seed advisory follow-up',
        ], [
            'entrepreneur_profile_id' => null,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'last_activity_at' => $this->now->copy()->subHours(4),
        ]);

        foreach (['advisor', 'primary', 'team'] as $userKey) {
            $this->upsert('message_thread_participants', [
                'thread_id' => $threadId,
                'user_id' => $this->users[$userKey]->getKey(),
            ], [
                'last_read_at' => $userKey === 'advisor' ? $this->now->copy()->subHours(3) : null,
            ]);
        }

        $messageId = $this->upsert('messages', [
            'logical_message_key' => 'seed-advisory-follow-up-001',
            'channel' => 'in_app',
        ], [
            'thread_id' => $threadId,
            'sender_user_id' => $this->users['advisor']->getKey(),
            'body' => 'I have added the working-capital actions and attached the proposal for review.',
            'attachments' => $this->json([
                ['type' => 'proposal', 'id' => $this->ids['proposal']],
                ['type' => 'document', 'id' => $this->ids['doc_financials']],
            ]),
            'delivery_state' => 'sent',
            'channel_decision' => $this->json(['selected' => 'in_app', 'reason' => 'recipient_prefers_both']),
            'email_subject' => null,
            'email_recipients' => null,
            'sent_at' => $this->now->copy()->subHours(4),
        ]);
        $this->ids['message_follow_up'] = $messageId;

        $checkinId = $this->upsert('wellbeing_checkins', [
            'client_id' => $this->clients['advisory']->getKey(),
            'user_id' => $this->users['primary']->getKey(),
            'period_start' => $this->now->copy()->startOfMonth()->toDateString(),
        ], [
            'business_confidence' => 6,
            'personal_coping' => 4,
            'notes' => 'Cash pressure is manageable but owner workload is high.',
            'submitted_at' => $this->now->copy()->subDays(5),
        ]);

        $signalId = $this->upsert('coaching_signals', [
            'client_id' => $this->clients['advisory']->getKey(),
            'signal_type' => 'owner_strain',
            'trigger_checkin_id' => $checkinId,
        ], [
            'user_id' => $this->users['primary']->getKey(),
            'entrepreneur_profile_id' => null,
            'severity' => 'advisor_attention',
            'status' => 'detected',
            'evidence' => $this->json(['business_confidence' => 6, 'personal_coping' => 4]),
            'generated_at' => $this->now->copy()->subDays(5)->addMinutes(2),
        ]);
        $this->ids['coaching_signal'] = $signalId;

        $this->upsert('coach_referral_suggestions', ['coaching_signal_id' => $signalId], [
            'client_id' => $this->clients['advisory']->getKey(),
            'suggested_specialisation' => 'founder_resilience',
            'threshold_ref' => 'wellbeing.personal_coping.low',
            'rationale' => 'Personal coping score is below the seeded advisory threshold.',
            'evidence' => $this->json(['checkin_id' => $checkinId]),
            'status' => 'reviewed',
            'surfaced_at' => $this->now->copy()->subDays(5)->addMinutes(3),
            'reviewed_by_user_id' => $this->users['advisor']->getKey(),
            'reviewed_at' => $this->now->copy()->subDays(4),
        ]);

        $voiceNoteId = $this->upsert('voice_notes', [
            'client_id' => $this->clients['advisory']->getKey(),
            'document_id' => $this->ids['doc_voice'],
        ], [
            'uploaded_by_user_id' => $this->users['advisor']->getKey(),
            'original_filename' => 'discovery-call.m4a',
            'mime_type' => 'audio/mp4',
            'duration_seconds' => 1840,
            'transcription_text' => 'Seeded transcript covering debtor pressure, dispatch dependency, and proposal next steps.',
            'transcription_metadata' => $this->json(['provider' => 'seeded', 'confidence' => 0.97]),
            'summary_text' => 'Owner wants debtor discipline and dispatch cover resolved first.',
            'summary_payload' => $this->json(['themes' => ['cashflow', 'operations', 'people']]),
            'status' => 'summarized',
            'transcribed_at' => $this->now->copy()->subDays(6),
            'summarized_at' => $this->now->copy()->subDays(6)->addMinutes(6),
        ]);

        $callLogId = $this->upsert('call_logs', [
            'client_id' => $this->clients['advisory']->getKey(),
            'title' => 'Seed discovery call',
        ], [
            'voice_note_id' => $voiceNoteId,
            'advisor_user_id' => $this->users['advisor']->getKey(),
            'channel' => 'video_call',
            'occurred_at' => $this->now->copy()->subDays(6),
            'transcript' => 'Seeded transcript covering debtor pressure and dispatch continuity.',
            'summary' => 'Prioritise debtor cadence, dispatch SOP, and insurance renewal.',
            'action_items' => $this->json([
                ['title' => 'Draft debtor follow-up policy', 'owner_user_id' => $this->users['team']->getKey()],
                ['title' => 'Renew liability insurance', 'owner_user_id' => $this->users['primary']->getKey()],
            ]),
        ]);

        DB::table('milestone_actions')
            ->where('id', $this->ids['action_debtor_policy'])
            ->update([
                'call_log_id' => $callLogId,
                'updated_at' => $this->now,
            ]);

        $this->upsert('testimonials', [
            'client_id' => $this->clients['advisory']->getKey(),
            'source_type' => 'nps',
        ], [
            'source_score' => 9,
            'quote' => 'The advisory roadmap turned our vague pressure points into clear weekly action.',
            'marketing_consent' => true,
            'display_mode' => 'named_company',
            'display_name' => 'Harbour Hive',
            'status' => 'approved',
            'requested_by_user_id' => $this->users['advisor']->getKey(),
            'submitted_by_user_id' => $this->users['primary']->getKey(),
            'requested_at' => $this->now->copy()->subDays(2),
            'consented_at' => $this->now->copy()->subDay(),
            'declined_at' => null,
        ]);

        $this->ids['meeting'] = $this->upsert('meetings', [
            'client_id' => $this->clients['advisory']->getKey(),
            'external_ref' => 'seed-meeting-harbour-hive-review',
        ], [
            'title' => 'Implementation review',
            'scheduled_at' => $this->now->copy()->addDays(7),
            'location' => null,
            'link' => 'https://meet.example.test/seed-harbour-hive',
            'attendees' => $this->json([
                $this->users['advisor']->email,
                $this->users['primary']->email,
                $this->users['team']->email,
            ]),
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $this->upsert('pre_meeting_briefs', ['meeting_id' => $this->ids['meeting']], [
            'client_id' => $this->clients['advisory']->getKey(),
            'meeting_at' => $this->now->copy()->addDays(7),
            'body' => 'Review debtor policy, insurance renewal, and dispatch SOP progress.',
            'red_flag_ids' => $this->json([$this->ids['red_flag_people']]),
            'generated_at' => $this->now,
            'reviewed_by_user_id' => $this->users['advisor']->getKey(),
            'reviewed_at' => $this->now,
            'sent_at' => null,
        ]);

        $this->upsert('industry_briefings', [
            'client_id' => $this->clients['advisory']->getKey(),
            'period' => $this->now->copy()->startOfMonth()->toDateString(),
        ], [
            'body' => 'Seeded sector briefing: demand remains steady, labour availability is uneven, and working-capital discipline matters.',
            'sources' => $this->json([
                ['label' => 'Seed market note', 'url' => 'https://example.test/market-note'],
            ]),
            'status' => 'sent',
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'reviewed_by_user_id' => $this->users['advisor']->getKey(),
            'reviewed_at' => $this->now->copy()->subDay(),
            'sent_at' => $this->now->copy()->subDay()->addMinutes(10),
        ]);

        $this->firstOrInsert('funnel_events', [
            'flow' => 'questionnaire',
            'step' => 'submit',
            'client_id' => $this->clients['advisory']->getKey(),
            'user_id' => $this->users['primary']->getKey(),
        ], [
            'entered_at' => $this->now->copy()->subDays(8),
            'completed_at' => $this->now->copy()->subDays(7),
            'abandoned' => false,
        ]);

        $this->upsert('practice_health_snapshots', [
            'scope' => 'advisor',
            'advisor_user_id' => $this->users['advisor']->getKey(),
            'generated_at' => $this->stableTimestamp('2026-05-22 08:00:00'),
        ], [
            'client_ids' => $this->json(array_map(
                static fn (Client $client): string => (string) $client->getKey(),
                $this->clients,
            )),
            'metrics' => $this->json([
                'active_clients' => 3,
                'paused_clients' => 1,
                'overdue_documents' => 1,
                'payment_success_rate' => 1.0,
            ]),
        ]);

        $this->upsert('offboarding_records', [
            'client_id' => $this->clients['offboarded']->getKey(),
            'triggered_at' => $this->stableTimestamp('2026-05-01 12:00:00'),
        ], [
            'triggered_by_user_id' => $this->users['advisor']->getKey(),
            'status' => 'completed',
            'final_report_path' => 'seed/offboarding/legacy-loom/final-report.pdf',
            'engagement_summary_path' => 'seed/offboarding/legacy-loom/summary.pdf',
            'handover_path' => 'seed/offboarding/legacy-loom/handover.pdf',
            'exit_interview_path' => 'seed/offboarding/legacy-loom/exit-interview.pdf',
            'privacy_notice_path' => 'seed/offboarding/legacy-loom/privacy-notice.pdf',
            'reengagement_due' => $this->now->copy()->addMonths(3),
            'reengagement_reminder_sent_at' => null,
            'advisor_capacity_released' => true,
            'advisor_capacity_before' => 14,
            'advisor_capacity_after' => 15,
            'advisor_capacity_delta' => 1,
            'metadata' => $this->json(['fixture' => true, 'reason' => 'Engagement completed']),
        ]);
    }

    private function seedPanelAndReferralData(): void
    {
        $brokerMemberId = $this->upsert('panel_members', [
            'user_id' => $this->users['broker']->getKey(),
            'panel_type' => 'broker',
        ], [
            'invite_token_id' => null,
            'status' => 'approved',
            'application' => $this->json([
                'company' => 'Seed Broker Partners',
                'regions' => ['Auckland', 'Wellington'],
                'specialties' => ['succession', 'capital_raise'],
            ]),
            'fsp_number' => 'FSP100001',
            'fsp_status' => 'current',
            'fsp_last_checked_at' => $this->now->copy()->subDays(15),
            'coach_specialisations' => null,
            'coach_profile' => null,
            'professional_memberships' => null,
            'coach_vetting' => null,
            'coach_vetted_by_user_id' => null,
            'coach_vetted_at' => null,
            'approved_by_user_id' => $this->users['admin']->getKey(),
            'applied_at' => $this->now->copy()->subDays(30),
            'approved_at' => $this->now->copy()->subDays(24),
            'suspended_at' => null,
        ]);

        $coachMemberId = $this->upsert('panel_members', [
            'user_id' => $this->users['coach']->getKey(),
            'panel_type' => 'coach',
        ], [
            'invite_token_id' => null,
            'status' => 'approved',
            'application' => $this->json([
                'company' => 'Seed Coaching Studio',
                'regions' => ['Remote', 'Auckland'],
            ]),
            'fsp_number' => null,
            'fsp_status' => null,
            'fsp_last_checked_at' => null,
            'coach_specialisations' => $this->json(['founder_resilience', 'leadership_rhythm']),
            'coach_profile' => $this->json([
                'bio' => 'Works with SME owners on resilience and execution habits.',
                'delivery_modes' => ['video', 'in_person'],
            ]),
            'professional_memberships' => $this->json(['ICF Associate']),
            'coach_vetting' => $this->json(['references_checked' => true, 'insurance_checked' => true]),
            'coach_vetted_by_user_id' => $this->users['admin']->getKey(),
            'coach_vetted_at' => $this->now->copy()->subDays(20),
            'approved_by_user_id' => $this->users['admin']->getKey(),
            'applied_at' => $this->now->copy()->subDays(28),
            'approved_at' => $this->now->copy()->subDays(20),
            'suspended_at' => null,
        ]);

        foreach ([
            ['member_id' => $brokerMemberId, 'status' => 'signed', 'path' => 'seed/panels/broker-agreement.pdf', 'user' => 'broker'],
            ['member_id' => $coachMemberId, 'status' => 'signed', 'path' => 'seed/panels/coach-agreement.pdf', 'user' => 'coach'],
        ] as $agreement) {
            $this->upsert('panel_agreements', ['panel_member_id' => $agreement['member_id']], [
                'status' => $agreement['status'],
                'terms' => $this->json(['commission_basis' => 'fixed', 'privacy_terms' => 'seeded']),
                'pdf_path' => $agreement['path'],
                'pdf_sha256_envelope' => hash('sha256', $agreement['path']),
                'pdf_envelope_meta' => $this->json(['fixture' => true]),
                'pdf_byte_size' => 180_000,
                'signed_by_user_id' => $this->users[$agreement['user']]->getKey(),
                'generated_at' => $this->now->copy()->subDays(21),
                'signed_at' => $this->now->copy()->subDays(20),
            ]);
        }

        $authorisationId = $this->upsert('coach_referral_authorisations', [
            'client_id' => $this->clients['advisory']->getKey(),
            'authorised_by_user_id' => $this->users['primary']->getKey(),
            'staff_name' => 'Seed Client Principal',
        ], [
            'staff_email' => $this->users['primary']->email,
            'purpose' => 'Founder resilience coaching following low coping score.',
            'payload' => $this->json(['coaching_signal_id' => $this->ids['coaching_signal']]),
            'granted_at' => $this->now->copy()->subDays(3),
            'revoked_at' => null,
        ]);

        $brokerReferralId = $this->upsert('referrals', [
            'client_id' => $this->clients['advisory']->getKey(),
            'panel_member_id' => $brokerMemberId,
            'referral_type' => 'succession_broker_intro',
        ], [
            'entrepreneur_profile_id' => null,
            'conflict_declaration_id' => $this->ids['conflict_advisory'],
            'consent_id' => $this->ids['proposal_consent'],
            'panel_type' => 'broker',
            'stage' => 'sent',
            'payload' => $this->json([
                'reason' => 'Optional succession market sounding for future planning.',
                'client_contact' => $this->users['primary']->email,
            ]),
            'coach_specialisation' => null,
            'referred_subject_type' => 'client',
            'coach_referral_authorisation_id' => null,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'sent_at' => $this->now->copy()->subDays(2),
            'closed_at' => null,
        ]);

        $coachReferralId = $this->upsert('referrals', [
            'client_id' => $this->clients['advisory']->getKey(),
            'panel_member_id' => $coachMemberId,
            'referral_type' => 'wellbeing_coach_intro',
        ], [
            'entrepreneur_profile_id' => null,
            'conflict_declaration_id' => $this->ids['conflict_advisory'],
            'consent_id' => $this->ids['proposal_consent'],
            'panel_type' => 'coach',
            'stage' => 'accepted',
            'payload' => $this->json([
                'reason' => 'Owner coping score triggered coaching suggestion.',
                'signal_id' => $this->ids['coaching_signal'],
            ]),
            'coach_specialisation' => 'founder_resilience',
            'referred_subject_type' => 'client_primary',
            'coach_referral_authorisation_id' => $authorisationId,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'sent_at' => $this->now->copy()->subDays(2),
            'closed_at' => null,
        ]);

        foreach ([
            [$brokerReferralId, 'Sharing the seeded succession context and preferred introduction timing.'],
            [$coachReferralId, 'Client has authorised a founder-resilience coaching introduction.'],
        ] as [$referralId, $body]) {
            $this->upsert('referral_messages', [
                'referral_id' => $referralId,
                'body' => $body,
            ], [
                'client_id' => $this->clients['advisory']->getKey(),
                'sender_user_id' => $this->users['advisor']->getKey(),
                'sent_at' => $this->now->copy()->subDays(2)->addMinutes(10),
            ]);
        }

        $this->upsert('reverse_referrals', [
            'panel_member_id' => $brokerMemberId,
            'email' => 'reverse.prospect@example.test',
        ], [
            'target_type' => 'prospect',
            'name' => 'Reverse Referral Prospect',
            'company' => 'Inbound Manufacturing Limited',
            'payload' => $this->json(['interest' => EngagementType::STANDARD_ADVISORY->value]),
            'prospect_lead_id' => DB::table('prospect_leads')->where('email', 'prospect.buyer@example.test')->value('id'),
            'entrepreneur_profile_id' => null,
            'submitted_at' => $this->now->copy()->subDay(),
        ]);
    }

    private function seedDueDiligenceJourney(): void
    {
        $engagementId = $this->upsert('dd_engagements', [
            'client_id' => $this->clients['dd']->getKey(),
            'target_name' => 'Kauri Kitchens Group Limited',
        ], [
            'target_details' => $this->json([
                'nzbn' => $this->clients['postAcquisition']->nzbn,
                'sector' => 'Food manufacturing and retail fitout',
                'headline_price_nzd' => 2_400_000,
                'asking_price' => 2_400_000,
                'gst_going_concern_zero_rating' => false,
                'working_capital_adjustment_nzd' => 95_000,
                'working_capital_peg' => 'Completion accounts should peg normal working capital at NZD 320,000.',
                'vendor_finance' => 'Vendor finance is available for 10 percent of purchase price subject to security.',
                'earnout' => 'Earn-out can protect the buyer against top-customer churn over the first 12 months.',
                'holidays_act' => [
                    'underpaid_hours' => 420,
                    'hourly_rate' => 36,
                    'buffer_rate' => 0.15,
                ],
            ]),
            'status' => 'in_progress',
            'recommendation' => 'proceed_with_conditions',
            'conflict_declaration_id' => $this->ids['conflict_dd'],
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'disclaimer_acknowledged_at' => $this->now->copy()->subDays(7),
        ]);
        $this->ids['dd_engagement'] = $engagementId;

        $guestLinkId = $this->upsert('dd_guest_links', ['token_hash' => hash('sha256', 'seed-dd-guest-link')], [
            'client_id' => $this->clients['dd']->getKey(),
            'dd_engagement_id' => $engagementId,
            'workstream' => 'financial',
            'folder' => 'management-accounts',
            'guest_email' => 'vendor.cfo@example.test',
            'max_uploads' => 10,
            'upload_count' => 2,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'revoked_by_user_id' => null,
            'expires_at' => $this->now->copy()->addDays(10),
            'revoked_at' => null,
            'last_used_at' => $this->now->copy()->subDay(),
        ]);

        foreach ([
            ['financial', 'management-accounts', $this->ids['doc_dd_target'], 'guest_upload'],
            ['commercial', 'customer-contracts', $this->ids['doc_dd_contracts'], 'client_upload'],
        ] as [$workstream, $folder, $documentId, $source]) {
            $itemId = $this->upsert('dd_data_room_items', [
                'dd_engagement_id' => $engagementId,
                'document_id' => $documentId,
            ], [
                'client_id' => $this->clients['dd']->getKey(),
                'workstream' => $workstream,
                'folder' => $folder,
                'artifact_type' => 'dd_artifact',
                'source' => $source,
                'dd_guest_link_id' => $source === 'guest_upload' ? $guestLinkId : null,
                'guest_name' => $source === 'guest_upload' ? 'Vendor CFO' : null,
                'guest_email' => $source === 'guest_upload' ? 'vendor.cfo@example.test' : null,
                'metadata' => $this->json(['fixture' => true, 'confidentiality' => 'restricted']),
            ]);

            $this->ids["dd_item_{$workstream}"] = $itemId;
        }

        $ddAnalysisRunId = $this->upsert('analysis_runs', [
            'client_id' => $this->clients['dd']->getKey(),
            'module' => 'dd_workstream',
            'prompt_version' => 'testing-dd-v1',
        ], [
            'status' => 'completed',
            'framework_lenses' => $this->json(['financial', 'commercial', 'legal', 'integration']),
            'data_quality_snapshot' => $this->json(['score' => 71, 'label' => 'medium']),
            'ai_model' => 'seeded-analysis-model',
            'prompt_hash' => hash('sha256', 'testing-dd-v1'),
            'tokens_in' => 11_400,
            'tokens_out' => 3_600,
            'started_at' => $this->now->copy()->subDays(2)->subMinutes(20),
            'completed_at' => $this->now->copy()->subDays(2),
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ]);

        $ddFindingId = $this->upsert('analysis_findings', [
            'analysis_run_id' => $ddAnalysisRunId,
            'title' => 'Revenue concentration requires purchase price protection',
        ], [
            'client_id' => $this->clients['dd']->getKey(),
            'lens' => 'diagnostic',
            'severity' => 'high',
            'body' => 'Two customers represent 46 percent of trailing revenue in the seeded data room.',
            'attributions' => $this->json([
                ['type' => 'dd_data_room_item', 'id' => $this->ids['dd_item_commercial']],
            ]),
            'document_support' => 'partial',
            'uncertainty' => 'medium',
            'data_quality_disclaimer' => 'Seeded data room is intentionally incomplete.',
            'bias_signals' => $this->json(['seller_supplied_data' => true]),
            'pv_link_id' => null,
        ]);
        $this->ids['dd_finding'] = $ddFindingId;

        foreach ([
            ['financial', 'completed', [$this->ids['dd_item_financial']]],
            ['commercial', 'needs_review', [$this->ids['dd_item_commercial']]],
            ['legal', 'pending', []],
            ['integration', 'paused', []],
        ] as [$workstream, $status, $itemIds]) {
            $this->upsert('dd_workstreams', [
                'dd_engagement_id' => $engagementId,
                'workstream' => $workstream,
            ], [
                'client_id' => $this->clients['dd']->getKey(),
                'status' => $status,
                'analysis_run_id' => in_array($workstream, ['financial', 'commercial'], true) ? $ddAnalysisRunId : null,
                'data_room_item_ids' => $this->json($itemIds),
                'verification_weight' => count($itemIds) * 50,
                'nz_checks' => $this->json(['ird_gst' => 'pending', 'companies_office' => 'matched']),
                'paused_reason' => $workstream === 'integration' ? 'Awaiting vendor transition plan.' : null,
                'ran_by_user_id' => $status === 'pending' ? null : $this->users['advisor']->getKey(),
                'ran_at' => $status === 'pending' ? null : $this->now->copy()->subDays(2),
            ]);
        }

        $ddPvId = $this->upsert('pv_calculations', [
            'client_id' => $this->clients['dd']->getKey(),
            'type' => 'business_valuation',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ], [
            'discount_method' => 'advisor_configured',
            'discount_rate' => 0.160000,
            'discount_rate_rationale' => 'Seeded DD valuation risk rate.',
            'inputs' => $this->json(['normalised_ebitda' => 520000, 'target_multiple' => 4.2]),
            'result' => $this->json(['low' => 1820000, 'mid' => 2180000, 'high' => 2520000]),
            'as_at' => $this->now->copy()->subDays(2),
            'source_attributions' => $this->json([
                ['type' => 'document', 'id' => $this->ids['doc_dd_target']],
            ]),
        ]);

        $ddBusinessValuationId = $this->upsert('business_valuations', [
            'client_id' => $this->clients['dd']->getKey(),
            'pv_calculation_id' => $ddPvId,
        ], [
            'sde_value' => $this->json(['low' => 1700000, 'mid' => 2100000, 'high' => 2400000]),
            'ebitda_value' => $this->json(['low' => 1820000, 'mid' => 2180000, 'high' => 2520000]),
            'dcf_value' => $this->json(['low' => 1760000, 'mid' => 2140000, 'high' => 2490000]),
            'reconciled_low' => 1_760_000,
            'reconciled_mid' => 2_140_000,
            'reconciled_high' => 2_490_000,
            'adjustments' => $this->json(['customer_concentration_discount' => 180000]),
            'data_quality_disclaimer' => 'Seeded DD valuation with concentration discount.',
            'source_attributions' => $this->json([
                ['type' => 'pv_calculation', 'id' => $ddPvId],
            ]),
            'as_at' => $this->now->copy()->subDays(2),
        ]);

        $this->ids['dd_valuation'] = $this->upsert('dd_valuations', [
            'dd_engagement_id' => $engagementId,
            'business_valuation_id' => $ddBusinessValuationId,
        ], [
            'client_id' => $this->clients['dd']->getKey(),
            'pv_calculation_id' => $ddPvId,
            'source_currency' => 'NZD',
            'normalised_currency' => 'NZD',
            'exchange_rate_id' => null,
            'source_to_nzd_rate' => 1,
            'rate_timestamp' => null,
            'normalised_values' => $this->json(['low' => 1760000, 'mid' => 2140000, 'high' => 2490000]),
            'sensitivity' => $this->json([
                ['case' => 'base', 'value' => 2140000],
                ['case' => 'customer_loss', 'value' => 1810000],
            ]),
            'buyer_position' => $this->json(['recommended_offer' => 2050000, 'holdback' => 250000]),
            'source_attributions' => $this->json([
                ['type' => 'business_valuation', 'id' => $ddBusinessValuationId],
            ]),
            'as_at' => $this->now->copy()->subDays(2),
        ]);

        $riskRegisterId = $this->upsert('dd_risk_register', [
            'dd_engagement_id' => $engagementId,
            'title' => 'Customer concentration holdback',
        ], [
            'client_id' => $this->clients['dd']->getKey(),
            'analysis_finding_id' => $ddFindingId,
            'risk_cost_id' => null,
            'risk_level' => 'high',
            'category' => 'commercial',
            'body' => 'Top-two customer concentration should be handled through price protection or earnout.',
            'financial_impact' => 320_000,
            'probability' => 0.3600,
            'pv_of_cost' => 115_000,
            'price_adjustment_nzd' => 250_000,
            'rank' => 1,
            'status' => 'open',
            'source_attributions' => $this->json([
                ['type' => 'analysis_finding', 'id' => $ddFindingId],
            ]),
        ]);

        foreach ([
            [1, 'Day 1', 'Confirm customer communication plan and retention owner.', 'Buyer CEO', 'high'],
            [30, 'First 30 days', 'Reconcile management accounts to bank and GST filings.', 'Finance Lead', 'high'],
            [90, 'First 90 days', 'Move customer concentration monitoring into monthly board pack.', 'Advisor', 'medium'],
        ] as [$day, $phase, $action, $owner, $priority]) {
            $this->upsert('dd_integration_plans', [
                'dd_engagement_id' => $engagementId,
                'day' => $day,
                'action' => $action,
            ], [
                'client_id' => $this->clients['dd']->getKey(),
                'dd_risk_register_id' => $day === 1 ? $riskRegisterId : null,
                'phase' => $phase,
                'owner' => $owner,
                'priority' => $priority,
                'status' => 'pending',
                'metadata' => $this->json(['fixture' => true]),
            ]);
        }

        $reportId = $this->upsert('reports', [
            'client_id' => $this->clients['dd']->getKey(),
            'type' => 'due_diligence',
            'title' => 'Kauri Kitchens Due Diligence Report',
        ], [
            'pdf_path' => 'seed/reports/kauri-kitchens-dd-report.pdf',
            'pdf_byte_size' => 680_000,
            'pptx_path' => 'seed/reports/kauri-kitchens-dd-report.pptx',
            'pptx_byte_size' => 920_000,
            'generated_by_user_id' => $this->users['advisor']->getKey(),
            'generated_at' => $this->now->copy()->subDay(),
            'metadata' => $this->json(['dd_engagement_id' => $engagementId]),
            'review_status' => 'reviewed',
            'reviewed_by_user_id' => $this->users['advisor']->getKey(),
            'reviewed_at' => $this->now->copy()->subDay()->addHour(),
        ]);
        $this->ids['dd_report'] = $reportId;

        foreach ([
            ['executive_summary', 'Executive Summary', 'Proceed with conditions and a concentration holdback.', 1, 'commercial'],
            ['valuation', 'Valuation View', 'Seeded valuation supports an offer below headline price.', 2, 'financial'],
            ['integration', 'Integration Plan', 'Focus first on customer communication and finance reconciliation.', 3, 'integration'],
        ] as [$key, $title, $body, $position, $lens]) {
            $this->upsert('report_sections', [
                'report_id' => $reportId,
                'key' => $key,
            ], [
                'client_id' => $this->clients['dd']->getKey(),
                'title' => $title,
                'body' => $body,
                'position' => $position,
                'lens' => $lens,
                'attributions' => $this->json([
                    ['type' => 'dd_engagement', 'id' => $engagementId],
                ]),
                'document_support' => 'partial',
                'document_support_note' => 'Seeded report section for testing.',
                'data_quality_note' => 'Data room contains enough evidence for UI and workflow tests.',
                'metadata' => $this->json(['fixture' => true]),
            ]);
        }

        $ddPlanId = $this->upsert('business_plans', [
            'dd_engagement_id' => $engagementId,
            'title' => 'Kauri Kitchens Post-close Integration Plan',
        ], [
            'client_id' => $this->clients['postAcquisition']->getKey(),
            'entrepreneur_profile_id' => null,
            'source_type' => 'post_acquisition',
            'status' => 'active',
            'current_phase' => 1,
            'founding_advisory_payload' => null,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'completed_at' => null,
            'living_plan_next_update_at' => $this->now->copy()->addDays(14),
            'living_plan_last_prompted_at' => null,
            'living_plan_last_assessed_at' => null,
            'living_plan_divergence_flags' => null,
        ]);

        $postAcquisitionGapReportId = $this->upsert('reports', [
            'client_id' => $this->clients['postAcquisition']->getKey(),
            'type' => ReportType::PostAcquisitionGap->value,
            'title' => 'Kauri Kitchens Post-acquisition Gap Report',
        ], [
            'pdf_path' => 'seed/reports/kauri-kitchens-post-acquisition-gap-report.pdf',
            'pdf_byte_size' => 410_000,
            'pptx_path' => null,
            'pptx_byte_size' => null,
            'generated_by_user_id' => $this->users['advisor']->getKey(),
            'generated_at' => $this->now->copy()->subHours(8),
            'metadata' => $this->json([
                'dd_engagement_id' => $engagementId,
                'business_plan_id' => $ddPlanId,
                'dd_pv_baseline' => 2_140_000,
                'fixture' => true,
            ]),
            'review_status' => 'not_required',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ]);

        foreach ([
            ['handoff_summary', 'Handoff summary', 'DD handoff identifies customer concentration, working-capital true-up, and post-close operating cadence gaps.', 1],
            ['dd_gaps', 'DD gaps requiring advisory attention', 'Resolve customer assignment consent, completion accounts, and revenue concentration risks during the first 100 days.', 2],
            ['plan_comparison', 'DD to business-plan gap comparison', 'The acquisition plan covers target context and financial range; funding structure and handover controls remain pending.', 3],
        ] as [$key, $title, $body, $position]) {
            $this->upsert('report_sections', [
                'report_id' => $postAcquisitionGapReportId,
                'key' => $key,
            ], [
                'client_id' => $this->clients['postAcquisition']->getKey(),
                'title' => $title,
                'body' => $body,
                'position' => $position,
                'lens' => 'diagnostic',
                'attributions' => $this->json([
                    ['type' => 'post_acquisition_migration', 'dd_engagement_id' => $engagementId],
                ]),
                'document_support' => 'partial',
                'document_support_note' => 'Seeded post-acquisition gap report section for testing.',
                'data_quality_note' => 'Testing seed data; advisor should review before client advice is issued.',
                'metadata' => $this->json(['fixture' => true]),
            ]);
        }

        $this->upsert('post_acquisition_migrations', ['dd_engagement_id' => $engagementId], [
            'buyer_client_id' => $this->clients['dd']->getKey(),
            'advisory_client_id' => $this->clients['postAcquisition']->getKey(),
            'business_plan_id' => $ddPlanId,
            'dd_report_id' => $reportId,
            'gap_questionnaire_response_id' => $this->ids['post_acquisition_response'],
            'proposal_id' => null,
            'migrated_document_ids' => $this->json([
                $this->ids['doc_dd_target'],
                $this->ids['doc_dd_contracts'],
            ]),
            'dd_pv_baseline' => 2_140_000,
            'status' => 'created',
            'metadata' => $this->json([
                'fixture' => true,
                'source' => 'testing_seed_data',
                'post_acquisition_gap_report_id' => $postAcquisitionGapReportId,
            ]),
            'migrated_by_user_id' => $this->users['advisor']->getKey(),
            'migrated_at' => $this->now->copy()->subHours(12),
        ]);
    }

    private function seedOutcomeFollowUpFixtures(): void
    {
        if (! Schema::hasTable('outcome_follow_ups')) {
            return;
        }

        if (isset($this->ids['plan_assessment'], $this->ids['entrepreneur_profile'])) {
            $this->upsert('outcome_follow_ups', [
                'plan_assessment_id' => $this->ids['plan_assessment'],
                'cadence_month' => 6,
            ], [
                'client_id' => null,
                'entrepreneur_profile_id' => $this->ids['entrepreneur_profile'],
                'dd_engagement_id' => null,
                'service_activation_id' => null,
                'conversion_outcome_id' => null,
                'dd_outcome_record_id' => null,
                'responded_by_user_id' => null,
                'subject_type' => OutcomeFollowUp::SUBJECT_ENTREPRENEUR,
                'status' => OutcomeFollowUp::STATUS_PENDING,
                'engagement_completed_at' => $this->now->copy()->subMonthsNoOverflow(6)->subDays(2),
                'due_at' => $this->now->copy()->subDay(),
                'completed_at' => null,
                'response_payload' => null,
                'outcome_signal' => null,
            ]);
        }

        if (isset($this->ids['dd_engagement'])) {
            $this->upsert('outcome_follow_ups', [
                'dd_engagement_id' => $this->ids['dd_engagement'],
                'cadence_month' => 6,
            ], [
                'client_id' => $this->clients['dd']->getKey(),
                'entrepreneur_profile_id' => null,
                'plan_assessment_id' => null,
                'service_activation_id' => null,
                'conversion_outcome_id' => null,
                'dd_outcome_record_id' => null,
                'responded_by_user_id' => null,
                'subject_type' => OutcomeFollowUp::SUBJECT_DUE_DILIGENCE,
                'status' => OutcomeFollowUp::STATUS_PENDING,
                'engagement_completed_at' => $this->now->copy()->subMonthsNoOverflow(6)->subDays(2),
                'due_at' => $this->now->copy()->subDay(),
                'completed_at' => null,
                'response_payload' => null,
                'outcome_signal' => null,
            ]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function pvWaterfallClientDefinitions(): array
    {
        return [
            'pvMicro' => [
                'nzbn' => '9429000000096',
                'legal_name' => 'Bay Micro Tools Limited',
                'trading_name' => 'Bay Micro Tools',
                'address' => ['line1' => '9 Dive Crescent', 'city' => 'Tauranga', 'region' => 'Bay of Plenty', 'country' => 'NZ'],
                'directors' => [['name' => 'Mia Roberts', 'role' => 'Founder']],
                'data_quality' => Client::DATA_QUALITY_MEDIUM,
                'locked_days_ago' => 9,
                'analysis_quality_score' => 68,
                'analysis_started_at' => '2026-05-16 09:00:00',
                'analysis_completed_at' => '2026-05-16 09:18:00',
                'valuation' => [
                    'low' => 135_000,
                    'mid' => 180_000,
                    'high' => 230_000,
                    'normalised_ebitda' => 52_000,
                    'growth_rate' => 0.025,
                    'terminal_multiple' => 3.3,
                    'discount_rate' => 0.165000,
                    'as_at' => '2026-05-16 09:30:00',
                    'adjustments' => ['owner_salary_normalisation' => 12_000, 'micro_business_discount' => 18_000],
                ],
                'improvements' => [
                    [
                        'title' => 'Quote follow-up automation',
                        'finding_title' => 'Manual quoting is leaking small-ticket margin',
                        'body' => 'Quote follow-up is founder-managed and inconsistent, creating avoidable conversion leakage.',
                        'severity' => 'medium',
                        'annual_benefit' => 18_000,
                        'duration_years' => 2,
                        'pv' => 31_000,
                        'discount_rate' => 0.165000,
                        'as_at' => '2026-05-16 10:00:00',
                    ],
                    [
                        'title' => 'Standardise supplier re-order points',
                        'finding_title' => 'Supplier ordering is creating preventable stock gaps',
                        'body' => 'Re-order points are held in spreadsheets rather than a repeatable replenishment workflow.',
                        'severity' => 'low',
                        'annual_benefit' => 9_000,
                        'duration_years' => 2,
                        'pv' => 15_000,
                        'discount_rate' => 0.165000,
                        'as_at' => '2026-05-16 10:06:00',
                    ],
                ],
                'risks' => [
                    [
                        'title' => 'Single supplier continuity exposure',
                        'finding_title' => 'Supplier continuity depends on one distributor',
                        'body' => 'A single distributor provides the highest margin product line with no tested alternative.',
                        'severity' => 'medium',
                        'financial_impact' => 45_000,
                        'probability' => 0.2200,
                        'duration_years' => 2,
                        'applied_impact' => 45_000,
                        'annual_expected_cost' => 9_900,
                        'pv' => 17_000,
                        'discount_rate' => 0.175000,
                        'statutory_penalty_range' => ['low' => 0, 'high' => 0],
                        'as_at' => '2026-05-16 10:12:00',
                    ],
                ],
            ],
            'pvMid' => [
                'nzbn' => '9429000000102',
                'legal_name' => 'Cobalt Manufacturing Limited',
                'trading_name' => 'Cobalt Manufacturing',
                'address' => ['line1' => '31 Port Road', 'city' => 'Napier', 'region' => 'Hawke\'s Bay', 'country' => 'NZ'],
                'directors' => [['name' => 'Noah Chen', 'role' => 'Managing Director']],
                'data_quality' => Client::DATA_QUALITY_HIGH,
                'locked_days_ago' => 12,
                'analysis_quality_score' => 77,
                'analysis_started_at' => '2026-05-17 08:45:00',
                'analysis_completed_at' => '2026-05-17 09:22:00',
                'valuation' => [
                    'low' => 820_000,
                    'mid' => 950_000,
                    'high' => 1_120_000,
                    'normalised_ebitda' => 238_000,
                    'growth_rate' => 0.040,
                    'terminal_multiple' => 4.0,
                    'discount_rate' => 0.140000,
                    'as_at' => '2026-05-17 09:35:00',
                    'adjustments' => ['customer_concentration_discount' => 65_000, 'maintainable_margin_normalisation' => 42_000],
                ],
                'improvements' => [
                    [
                        'title' => 'Shift production scheduling to weekly constraint planning',
                        'finding_title' => 'Production constraints are not sequenced against gross margin',
                        'body' => 'Scheduling is optimised for available labour rather than margin contribution and bottleneck throughput.',
                        'severity' => 'high',
                        'annual_benefit' => 120_000,
                        'duration_years' => 3,
                        'pv' => 285_000,
                        'discount_rate' => 0.140000,
                        'as_at' => '2026-05-17 10:00:00',
                    ],
                    [
                        'title' => 'Introduce preventative maintenance cadence',
                        'finding_title' => 'Maintenance spend is reactive and downtime-heavy',
                        'body' => 'Unplanned downtime is materially higher than the target operating rhythm for the production line.',
                        'severity' => 'medium',
                        'annual_benefit' => 45_000,
                        'duration_years' => 3,
                        'pv' => 108_000,
                        'discount_rate' => 0.140000,
                        'as_at' => '2026-05-17 10:05:00',
                    ],
                ],
                'risks' => [
                    [
                        'title' => 'Machine breakdown exposure',
                        'finding_title' => 'Aging plant creates a meaningful delivery risk',
                        'body' => 'The highest-volume machine has no standby plan and is outside the preferred service interval.',
                        'severity' => 'high',
                        'financial_impact' => 180_000,
                        'probability' => 0.3500,
                        'duration_years' => 3,
                        'applied_impact' => 180_000,
                        'annual_expected_cost' => 63_000,
                        'pv' => 155_000,
                        'discount_rate' => 0.150000,
                        'statutory_penalty_range' => ['low' => 0, 'high' => 0],
                        'as_at' => '2026-05-17 10:10:00',
                    ],
                    [
                        'title' => 'Product compliance rework risk',
                        'finding_title' => 'Compliance evidence is not retained at batch level',
                        'body' => 'Batch evidence is inconsistently retained, increasing the cost of responding to product queries.',
                        'severity' => 'medium',
                        'financial_impact' => 80_000,
                        'probability' => 0.2000,
                        'duration_years' => 3,
                        'applied_impact' => 80_000,
                        'annual_expected_cost' => 16_000,
                        'pv' => 39_000,
                        'discount_rate' => 0.150000,
                        'statutory_penalty_range' => ['low' => 0, 'high' => 15_000],
                        'as_at' => '2026-05-17 10:15:00',
                    ],
                ],
            ],
            'pvGrowth' => [
                'nzbn' => '9429000000119',
                'legal_name' => 'Kowhai Export Co Limited',
                'trading_name' => 'Kowhai Export Co',
                'address' => ['line1' => '22 Durham Lane', 'city' => 'Christchurch', 'region' => 'Canterbury', 'country' => 'NZ'],
                'directors' => [['name' => 'Amelia Singh', 'role' => 'Chief Executive']],
                'data_quality' => Client::DATA_QUALITY_HIGH,
                'locked_days_ago' => 15,
                'analysis_quality_score' => 84,
                'analysis_started_at' => '2026-05-18 11:00:00',
                'analysis_completed_at' => '2026-05-18 11:42:00',
                'valuation' => [
                    'low' => 2_650_000,
                    'mid' => 3_200_000,
                    'high' => 3_860_000,
                    'normalised_ebitda' => 710_000,
                    'growth_rate' => 0.070,
                    'terminal_multiple' => 4.5,
                    'discount_rate' => 0.128000,
                    'as_at' => '2026-05-18 12:00:00',
                    'adjustments' => ['fx_volatility_discount' => 120_000, 'export_pipeline_premium' => 180_000],
                ],
                'improvements' => [
                    [
                        'title' => 'Convert distributor demand into rolling forecast',
                        'finding_title' => 'Export demand signals are not converted into a rolling forecast',
                        'body' => 'Distributor updates arrive monthly but are not translated into inventory and cash planning.',
                        'severity' => 'high',
                        'annual_benefit' => 280_000,
                        'duration_years' => 4,
                        'pv' => 875_000,
                        'discount_rate' => 0.128000,
                        'as_at' => '2026-05-18 12:20:00',
                    ],
                    [
                        'title' => 'Add landed-margin dashboard by market',
                        'finding_title' => 'Landed-margin reporting is too slow for export pricing',
                        'body' => 'Freight and FX movements are visible after month end rather than before pricing decisions.',
                        'severity' => 'medium',
                        'annual_benefit' => 95_000,
                        'duration_years' => 3,
                        'pv' => 230_000,
                        'discount_rate' => 0.132000,
                        'as_at' => '2026-05-18 12:25:00',
                    ],
                    [
                        'title' => 'Tighten receivables cadence for offshore accounts',
                        'finding_title' => 'Offshore receivables are drifting beyond agreed terms',
                        'body' => 'Collections timing has widened as export volumes increased, lifting working-capital pressure.',
                        'severity' => 'medium',
                        'annual_benefit' => 60_000,
                        'duration_years' => 2,
                        'pv' => 105_000,
                        'discount_rate' => 0.132000,
                        'as_at' => '2026-05-18 12:30:00',
                    ],
                ],
                'risks' => [
                    [
                        'title' => 'FX margin compression risk',
                        'finding_title' => 'Unhedged USD exposure can erase export margin',
                        'body' => 'USD receivables and NZD input costs are not hedged against short-term movements.',
                        'severity' => 'high',
                        'financial_impact' => 650_000,
                        'probability' => 0.1800,
                        'duration_years' => 4,
                        'applied_impact' => 650_000,
                        'annual_expected_cost' => 117_000,
                        'pv' => 360_000,
                        'discount_rate' => 0.145000,
                        'statutory_penalty_range' => ['low' => 0, 'high' => 0],
                        'as_at' => '2026-05-18 12:35:00',
                    ],
                    [
                        'title' => 'Distributor concentration exposure',
                        'finding_title' => 'Two distributors represent most export revenue',
                        'body' => 'The top two distributors account for the majority of offshore revenue without replacement plans.',
                        'severity' => 'high',
                        'financial_impact' => 420_000,
                        'probability' => 0.2200,
                        'duration_years' => 3,
                        'applied_impact' => 420_000,
                        'annual_expected_cost' => 92_400,
                        'pv' => 245_000,
                        'discount_rate' => 0.145000,
                        'statutory_penalty_range' => ['low' => 0, 'high' => 0],
                        'as_at' => '2026-05-18 12:40:00',
                    ],
                ],
            ],
            'pvEnterprise' => [
                'nzbn' => '9429000000126',
                'legal_name' => 'Summit SaaS Limited',
                'trading_name' => 'Summit SaaS',
                'address' => ['line1' => '4 Madden Street', 'city' => 'Auckland', 'region' => 'Auckland', 'country' => 'NZ'],
                'directors' => [['name' => 'Ethan Williams', 'role' => 'Chief Executive']],
                'data_quality' => Client::DATA_QUALITY_HIGH,
                'locked_days_ago' => 18,
                'analysis_quality_score' => 91,
                'analysis_started_at' => '2026-05-19 13:00:00',
                'analysis_completed_at' => '2026-05-19 14:10:00',
                'valuation' => [
                    'low' => 7_900_000,
                    'mid' => 9_500_000,
                    'high' => 11_400_000,
                    'normalised_ebitda' => 1_850_000,
                    'growth_rate' => 0.110,
                    'terminal_multiple' => 5.2,
                    'discount_rate' => 0.118000,
                    'as_at' => '2026-05-19 14:30:00',
                    'adjustments' => ['recurring_revenue_premium' => 650_000, 'enterprise_churn_discount' => 240_000],
                ],
                'improvements' => [
                    ['title' => 'Self-serve onboarding conversion lift', 'finding_title' => 'Enterprise onboarding is carrying repeatable work manually', 'body' => 'Implementation work repeats across customers and delays revenue activation.', 'severity' => 'high', 'annual_benefit' => 420_000, 'duration_years' => 5, 'pv' => 1_520_000, 'discount_rate' => 0.118000, 'as_at' => '2026-05-19 15:00:00'],
                    ['title' => 'Enterprise retention playbook', 'finding_title' => 'Retention interventions are reactive rather than cohort-led', 'body' => 'Health signals exist but are not converted into proactive retention motions.', 'severity' => 'high', 'annual_benefit' => 260_000, 'duration_years' => 4, 'pv' => 820_000, 'discount_rate' => 0.118000, 'as_at' => '2026-05-19 15:05:00'],
                    ['title' => 'Pricing packaging refresh', 'finding_title' => 'Packaging no longer matches high-value customer usage', 'body' => 'The current price book under-recovers from customers with heavier support and integration needs.', 'severity' => 'high', 'annual_benefit' => 190_000, 'duration_years' => 4, 'pv' => 610_000, 'discount_rate' => 0.120000, 'as_at' => '2026-05-19 15:10:00'],
                    ['title' => 'Infrastructure rightsizing programme', 'finding_title' => 'Cloud cost allocation is not linked to product margin', 'body' => 'Gross margin by module is masked by pooled infrastructure spend.', 'severity' => 'medium', 'annual_benefit' => 120_000, 'duration_years' => 3, 'pv' => 285_000, 'discount_rate' => 0.120000, 'as_at' => '2026-05-19 15:15:00'],
                    ['title' => 'Partner channel enablement', 'finding_title' => 'Partner leads are not moving through a measurable channel motion', 'body' => 'Partners generate interest but receive inconsistent enablement and follow-up.', 'severity' => 'medium', 'annual_benefit' => 150_000, 'duration_years' => 3, 'pv' => 350_000, 'discount_rate' => 0.120000, 'as_at' => '2026-05-19 15:20:00'],
                    ['title' => 'RevOps forecast hygiene', 'finding_title' => 'Revenue forecast confidence is reduced by stage drift', 'body' => 'Pipeline stages are updated late and do not reflect buying milestones.', 'severity' => 'medium', 'annual_benefit' => 95_000, 'duration_years' => 3, 'pv' => 220_000, 'discount_rate' => 0.122000, 'as_at' => '2026-05-19 15:25:00'],
                    ['title' => 'AI support triage', 'finding_title' => 'Support triage consumes senior product time', 'body' => 'Senior product staff are resolving low-complexity tickets that could be routed earlier.', 'severity' => 'medium', 'annual_benefit' => 75_000, 'duration_years' => 3, 'pv' => 174_000, 'discount_rate' => 0.122000, 'as_at' => '2026-05-19 15:30:00'],
                    ['title' => 'Security assurance automation', 'finding_title' => 'Security questionnaire work slows enterprise deals', 'body' => 'Security responses are recreated deal-by-deal rather than reusable from governed evidence.', 'severity' => 'medium', 'annual_benefit' => 65_000, 'duration_years' => 3, 'pv' => 151_000, 'discount_rate' => 0.122000, 'as_at' => '2026-05-19 15:35:00'],
                    ['title' => 'Customer education academy', 'finding_title' => 'Customer education is informal and success-manager dependent', 'body' => 'Training material is uneven across cohorts and increases ongoing success load.', 'severity' => 'low', 'annual_benefit' => 55_000, 'duration_years' => 3, 'pv' => 128_000, 'discount_rate' => 0.122000, 'as_at' => '2026-05-19 15:40:00'],
                ],
                'risks' => [
                    ['title' => 'Platform outage exposure', 'finding_title' => 'Availability controls lag enterprise contract expectations', 'body' => 'Enterprise contracts introduce service credits and reputational exposure if availability dips.', 'severity' => 'high', 'financial_impact' => 1_450_000, 'probability' => 0.1800, 'duration_years' => 4, 'applied_impact' => 1_450_000, 'annual_expected_cost' => 261_000, 'pv' => 650_000, 'discount_rate' => 0.135000, 'statutory_penalty_range' => ['low' => 0, 'high' => 0], 'as_at' => '2026-05-19 16:00:00'],
                    ['title' => 'Privacy incident exposure', 'finding_title' => 'Privacy evidence is fragmented across tooling', 'body' => 'Privacy controls are present but not assembled into a single auditable response pack.', 'severity' => 'high', 'financial_impact' => 900_000, 'probability' => 0.2000, 'duration_years' => 4, 'applied_impact' => 900_000, 'annual_expected_cost' => 180_000, 'pv' => 430_000, 'discount_rate' => 0.140000, 'statutory_penalty_range' => ['low' => 20_000, 'high' => 120_000], 'as_at' => '2026-05-19 16:05:00'],
                    ['title' => 'Key architect dependency', 'finding_title' => 'Architecture decisions rely on one senior engineer', 'body' => 'Critical architecture knowledge is concentrated in one person with limited succession cover.', 'severity' => 'medium', 'financial_impact' => 620_000, 'probability' => 0.1600, 'duration_years' => 3, 'applied_impact' => 620_000, 'annual_expected_cost' => 99_200, 'pv' => 310_000, 'discount_rate' => 0.138000, 'statutory_penalty_range' => ['low' => 0, 'high' => 0], 'as_at' => '2026-05-19 16:10:00'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $valuation
     * @return array{low:float, mid:float, high:float}
     */
    private function valuationBand(array $valuation, float $multiplier): array
    {
        return [
            'low' => round((float) $valuation['low'] * $multiplier, 2),
            'mid' => round((float) $valuation['mid'] * $multiplier, 2),
            'high' => round((float) $valuation['high'] * $multiplier, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $result
     * @param  array<int, array<string, mixed>>  $sourceAttributions
     */
    private function seedPvCalculation(
        Client $client,
        string $type,
        string $fixtureKey,
        float $discountRate,
        string $rationale,
        array $inputs,
        array $result,
        CarbonInterface $asAt,
        array $sourceAttributions,
    ): string|int|null {
        return $this->upsert('pv_calculations', [
            'client_id' => $client->getKey(),
            'type' => $type,
            'discount_rate_rationale' => $rationale,
        ], [
            'discount_method' => 'advisor_configured',
            'discount_rate' => $discountRate,
            'inputs' => $this->json(['fixture_key' => $fixtureKey, ...$inputs]),
            'result' => $this->json($result),
            'as_at' => $asAt,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'source_attributions' => $this->json($sourceAttributions),
        ]);
    }

    private function seedPvWaterfallFinding(
        string|int|null $analysisRunId,
        Client $client,
        string $title,
        string $body,
        string $lens,
        string $severity,
        string $sourceReference,
    ): string|int|null {
        return $this->upsert('analysis_findings', [
            'analysis_run_id' => $analysisRunId,
            'title' => $title,
        ], [
            'client_id' => $client->getKey(),
            'lens' => $lens,
            'severity' => $severity,
            'body' => $body,
            'attributions' => $this->json([
                ['claim' => 'Seeded PV waterfall analysis finding.', 'source_reference' => $sourceReference],
            ]),
            'document_support' => 'supported',
            'uncertainty' => 'medium',
            'data_quality_disclaimer' => 'Seeded finding for PV waterfall dashboard testing only.',
            'bias_signals' => $this->json(['fixture' => 'pv_waterfall']),
            'pv_link_id' => null,
        ]);
    }

    private function linkFindingToPvItem(string|int|null $findingId, string|int|null $pvLinkId): void
    {
        if ($findingId === null || $pvLinkId === null) {
            return;
        }

        DB::table('analysis_findings')
            ->where('id', $findingId)
            ->update([
                'pv_link_id' => $pvLinkId,
                'updated_at' => $this->now,
            ]);
    }

    private function seedStrategicPlanTestData(): void
    {
        $ddAcquisitionPlanId = $this->seedDdAcquisitionBusinessPlan();
        $this->ids['dd_acquisition_plan'] = $ddAcquisitionPlanId;

        $this->ids['doc_post_acquisition_financials'] = $this->document(
            key: 'post-acquisition-management-accounts',
            client: $this->clients['postAcquisition'],
            category: Document::CATEGORY_FINANCIAL_STATEMENT,
            filename: 'kauri-kitchens-management-accounts-may-2026.xlsx',
            uploader: $this->users['buyer'],
            scannerResult: Document::SCANNER_CLEAN,
            expiresAt: null,
            size: 345_000,
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $ddProposalId = $this->seedSignedStrategicProposal(
            clientKey: 'dd',
            idPrefix: 'dd-strategic-advice',
            method: 'outcome_based',
            suggestedMid: 36_000,
            termMonths: 12,
            services: [
                'Acquisition decision support',
                'Completion accounts review',
                'First 100-day advisory cadence',
            ],
        );
        $this->ids['dd_strategic_proposal'] = $ddProposalId;

        $postAcquisitionProposalId = $this->seedSignedStrategicProposal(
            clientKey: 'postAcquisition',
            idPrefix: 'post-acquisition-strategic-advice',
            method: 'outcome_based',
            suggestedMid: 48_000,
            termMonths: 18,
            services: [
                'Post-acquisition operating cadence',
                'Working-capital controls',
                'Management reporting reset',
            ],
        );
        $this->ids['post_acquisition_strategic_proposal'] = $postAcquisitionProposalId;

        $advisoryBudget = $this->seedStrategicBudgetScenario(
            clientKey: 'advisory',
            scenario: 'advisory',
            businessPlanId: null,
            proposalId: $this->ids['proposal'],
            state: StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
        );
        $this->ids['strategic_budget_advisory'] = $advisoryBudget->getKey();

        $ddBudget = $this->seedStrategicBudgetScenario(
            clientKey: 'dd',
            scenario: 'due_diligence',
            businessPlanId: $ddAcquisitionPlanId,
            proposalId: $ddProposalId,
            state: StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
        );
        $this->ids['strategic_budget_dd'] = $ddBudget->getKey();

        $postAcquisitionBudget = $this->seedStrategicBudgetScenario(
            clientKey: 'postAcquisition',
            scenario: 'post_acquisition',
            businessPlanId: null,
            proposalId: $postAcquisitionProposalId,
            state: StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
        );
        $this->ids['strategic_budget_post_acquisition'] = $postAcquisitionBudget->getKey();

        $npoBudget = $this->seedStrategicBudgetScenario(
            clientKey: 'npo',
            scenario: 'npo',
            businessPlanId: null,
            proposalId: null,
            state: StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
        );
        $this->ids['strategic_budget_npo'] = $npoBudget->getKey();

        $this->seedStrategicPlanScenario(
            key: 'advisory_deployed',
            clientKey: 'advisory',
            proposalId: $this->ids['proposal'],
            budgetId: $advisoryBudget->getKey(),
            status: StrategicPlan::STATUS_DEPLOYED,
            summary: "Harbour Hive's accepted advisory proposal is now deployed into practical milestones. The first focus is debtor cadence, operating cover, and management reporting evidence.",
            sections: [
                ['outcomes', 'Target outcomes', 'Lift cash resilience before peak season, reduce debtor drag, and create a repeatable monthly evidence pack for advisor review.'],
                ['priorities', 'Action priorities', 'Start with receivables discipline, then connect inventory controls and weekly cash visibility before the next trading peak.'],
                ['milestones', 'Milestone approach', 'Milestone due dates are set from the agreed start date and owned by the client, advisor, or both.'],
                ['budget', 'Budget and affordability', 'Use the approved Business Plan & Budget snapshot and GST-exclusive accepted proposal payment terms.'],
                ['governance', 'Review rhythm', 'Fortnightly check-ins for the first six weeks, then monthly evidence and progress review.'],
            ],
            milestones: [
                ['Strategic plan review meeting', 'Advisor and client confirmed the proposal, success measures, and first evidence actions.', StrategicPlanMilestone::OWNER_JOINT, 7, StrategicPlanMilestone::STATUS_COMPLETED, 100, 'Meeting notes accepted and first actions loaded.'],
                ['Debtor cadence live in weekly operations', 'Client embeds follow-up rules, escalation thresholds, and disputed invoice review.', StrategicPlanMilestone::OWNER_CLIENT, 21, StrategicPlanMilestone::STATUS_IN_PROGRESS, 65, 'Draft debtor cadence is in use for two customer cohorts.'],
                ['Advisor validates cash dashboard', 'Advisor reviews the cash dashboard against uploaded financials and management accounts.', StrategicPlanMilestone::OWNER_ADVISOR, 30, StrategicPlanMilestone::STATUS_PENDING, 0, null],
                ['Peak-season funding review', 'Joint review of cash runway, funding buffer, and proposal affordability before the peak period.', StrategicPlanMilestone::OWNER_JOINT, 45, StrategicPlanMilestone::STATUS_PENDING, 0, null],
            ],
            deployedAt: $this->now->copy()->subDays(9),
        );

        $this->seedStrategicPlanScenario(
            key: 'dd_draft',
            clientKey: 'dd',
            proposalId: $ddProposalId,
            budgetId: $ddBudget->getKey(),
            status: StrategicPlan::STATUS_DRAFT,
            summary: 'Southern Lights has accepted the DD advisory proposal. This draft strategic plan is ready for advisor review before it is shared with the buyer portal.',
            sections: [
                ['outcomes', 'Target outcomes', 'Protect purchase price, complete conditions precedent, and convert DD findings into first 100-day operating controls.'],
                ['priorities', 'Action priorities', 'Confirm customer concentration protection, completion accounts process, funding buffer, and handover owner before settlement.'],
                ['milestones', 'Milestone approach', 'Advisor reviews milestone ownership with the buyer before the plan starts.'],
                ['budget', 'Budget and affordability', 'Use the approved DD Business Plan & Budget and accepted advisory proposal over 12 months.'],
                ['governance', 'Review rhythm', 'Weekly until settlement, then fortnightly for the first 90 days.'],
            ],
            milestones: [
                ['Review DD strategic plan with buyer', 'Advisor explains how DD findings become post-acceptance milestones.', StrategicPlanMilestone::OWNER_JOINT, 7, StrategicPlanMilestone::STATUS_PENDING, 0, null],
                ['Confirm completion accounts evidence', 'Buyer provides source files and advisor confirms assumptions before settlement.', StrategicPlanMilestone::OWNER_CLIENT, 14, StrategicPlanMilestone::STATUS_PENDING, 0, null],
                ['Negotiate customer concentration protection', 'Advisor helps finalise holdback or earnout treatment for top customer risk.', StrategicPlanMilestone::OWNER_ADVISOR, 21, StrategicPlanMilestone::STATUS_PENDING, 0, null],
                ['First 100-day handover controls', 'Joint delivery of operating cadence, weekly cash review, and customer communication plan.', StrategicPlanMilestone::OWNER_JOINT, 45, StrategicPlanMilestone::STATUS_PENDING, 0, null],
            ],
            deployedAt: null,
        );

        $this->removeStrategicPlanForProposal($postAcquisitionProposalId);
    }

    private function seedDdAcquisitionBusinessPlan(): string|int|null
    {
        $planId = $this->upsert('business_plans', [
            'dd_engagement_id' => $this->ids['dd_engagement'],
            'title' => 'Kauri Kitchens Acquisition Plan',
        ], [
            'client_id' => $this->clients['dd']->getKey(),
            'entrepreneur_profile_id' => null,
            'source_type' => BusinessPlan::SOURCE_DUE_DILIGENCE,
            'status' => BusinessPlan::STATUS_FOUNDING,
            'current_phase' => 5,
            'founding_advisory_payload' => $this->json([
                'source' => 'seeded_dd_acquisition_plan',
                'target_name' => 'Kauri Kitchens Group Limited',
            ]),
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'completed_at' => $this->now->copy()->subDays(2),
            'living_plan_next_update_at' => $this->now->copy()->addDays(21),
            'living_plan_last_prompted_at' => $this->now->copy()->subDays(3),
            'living_plan_last_assessed_at' => $this->now->copy()->subDays(2),
            'living_plan_divergence_flags' => $this->json(['dd_customer_concentration_changed' => true]),
        ]);

        $phases = [
            ['foundation', 'Foundation', 1],
            ['market', 'Market', 2],
            ['strategy', 'Strategy', 3],
            ['legal_operations', 'Legal & Operations', 4],
            ['financial', 'Financial', 5],
        ];

        foreach ($phases as [$key, $title, $position]) {
            $this->ids["dd_acquisition_phase_{$key}"] = $this->upsert('plan_phases', [
                'business_plan_id' => $planId,
                'key' => $key,
            ], [
                'title' => $title,
                'position' => $position,
                'depends_on' => $position === 1 ? null : $this->json([$phases[$position - 2][0]]),
                'status' => 'completed',
            ]);
        }

        foreach ([
            ['foundation', 'dd-foundation-target', 'Target context from DD', 'Kauri Kitchens is the named target. DD status is proceed with conditions, subject to customer concentration and completion accounts protection.', 'target_context'],
            ['foundation', 'client-foundation-acquisition-thesis', 'Buyer acquisition thesis', 'The acquisition should proceed if price protection, customer assignment consent, and post-settlement working-capital controls are confirmed.', 'acquisition_thesis'],
            ['market', 'client-market-market-position', 'Customer and market position', 'The target has a loyal customer base, but two customers represent 46 percent of trailing revenue and require retention planning.', 'market_position'],
            ['strategy', 'dd-strategy-integration', 'First 100-day operating plan', 'First 100 days focus on customer communication, finance reconciliation, staff handover, and weekly cash visibility.', 'first_100_days'],
            ['legal_operations', 'client-legal_operations-handover-risks', 'Legal, people, and operational handover risks', 'Open risks include customer assignment, key-person dependency in dispatch, and completion account evidence.', 'handover_risks'],
            ['financial', 'dd-valuation-summary', 'Valuation and purchase-price range', 'Valuation range is NZD 1.76m to NZD 2.49m with a reconciled midpoint of NZD 2.14m after concentration discount.', 'valuation_price_range'],
            ['financial', 'client-financial-funding-structure', 'Funding and deal-structure assumptions', 'Funding plan assumes bank debt plus owner contribution, with a completion-account true-up and 90-day cash buffer.', 'funding_structure'],
        ] as [$phaseKey, $key, $title, $body, $requirementKey]) {
            $this->upsert('plan_sections', [
                'business_plan_id' => $planId,
                'key' => $key,
            ], [
                'plan_phase_id' => $this->ids["dd_acquisition_phase_{$phaseKey}"],
                'title' => $title,
                'body' => $body,
                'attached_document_ids' => $this->json([
                    $this->ids['doc_dd_target'],
                    $this->ids['doc_dd_contracts'],
                ]),
                'source_type' => BusinessPlan::SOURCE_DUE_DILIGENCE,
                'source_analysis_finding_id' => $requirementKey === 'market_position' ? $this->ids['dd_finding'] : null,
                'completeness_status' => 'complete',
                'metadata' => $this->json([
                    'fixture' => true,
                    'requirement_key' => $requirementKey,
                    'source' => 'seeded_dd_plan',
                ]),
                'predictive_score' => $this->json([
                    'confidence' => 0.78,
                    'source' => 'seeded',
                ]),
            ]);
        }

        return $planId;
    }

    private function seedSignedStrategicProposal(
        string $clientKey,
        string $idPrefix,
        string $method,
        int $suggestedMid,
        int $termMonths,
        array $services,
        ?string $npoEngagementId = null,
    ): string|int|null {
        $client = $this->clients[$clientKey];
        $signer = $this->portalActorForClient($clientKey);
        $monthly = round($suggestedMid / max(1, $termMonths), 2);
        $feeId = $this->upsert('fee_calculations', [
            'client_id' => $client->getKey(),
            'method' => $method,
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ], [
            'npo_engagement_id' => $npoEngagementId,
            'inputs' => $this->json([
                'source' => 'strategic_plan_testing_seed',
                'term_months' => $termMonths,
                'monthly_retainer_fee' => $monthly,
            ]),
            'suggested_low' => round($suggestedMid * 0.85, 2),
            'suggested_mid' => $suggestedMid,
            'suggested_high' => round($suggestedMid * 1.2, 2),
            'improvement_pv_total' => round($suggestedMid * 4.8, 2),
            'risk_cost_pv_total' => round($suggestedMid * 1.4, 2),
            'roi_ratio' => 4.8000,
            'justification' => $this->json([
                'summary' => 'Seeded accepted proposal for strategic plan workflow testing.',
                'retainer' => [
                    'months' => $termMonths,
                    'monthly_fee' => $monthly,
                ],
            ]),
        ]);

        $signedAt = $this->now->copy()->subDays(match ($clientKey) {
            'dd' => 2,
            'postAcquisition' => 1,
            default => 3,
        });
        $signatureEvidencePath = "seed/proposals/{$idPrefix}-signed-proposal.pdf";

        $proposalId = $this->upsert('proposals', [
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $feeId,
            'version' => 1,
        ], [
            'npo_engagement_id' => $npoEngagementId,
            'status' => 'signed',
            'scope' => $this->json([
                'summary' => 'Accepted strategic advisory proposal seeded for strategic plan testing.',
                'modules' => ['business_plan_budget', 'proposal_acceptance', 'strategic_plan'],
                'term_months' => $termMonths,
            ]),
            'services' => $this->json(collect($services)
                ->map(fn (string $service): array => ['name' => $service, 'cadence' => 'monthly'])
                ->values()
                ->all()),
            'pv_summary' => $this->json([
                'fee_suggested_mid' => $suggestedMid,
                'monthly_retainer_fee' => $monthly,
                'strategic_plan_fixture' => true,
            ]),
            'roi_ratio' => 4.8000,
            'acceptance_terms' => $this->json([
                'payment' => 'monthly_card',
                'term_months' => $termMonths,
                'collection_day' => 1,
                'cancellation_notice_days' => 30,
            ]),
            'pdf_path' => "seed/proposals/{$idPrefix}.pdf",
            'pdf_byte_size' => 180_000,
            'released_at' => $signedAt->copy()->subDay(),
            'released_by_user_id' => $this->users['advisor']->getKey(),
            'expires_at' => $signedAt->copy()->addDays(26),
            'recalled_at' => null,
            'recalled_by_user_id' => null,
            'expired_at' => null,
            'renewed_from_proposal_id' => null,
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'awaiting_signature_at' => $signedAt->copy()->subHours(3),
            'signed_at' => $signedAt,
            'signed_by_user_id' => $signer->getKey(),
            'signature_evidence_path' => $signatureEvidencePath,
            'signature_evidence_sha256_envelope' => null,
            'signature_envelope_meta' => null,
            'signature_evidence_byte_size' => null,
        ]);

        $this->seedSignedProposalSteps($proposalId, $clientKey, $signedAt);
        $this->writeSignedProposalFixture(
            Proposal::query()->findOrFail($proposalId),
            $signer,
            $signer->name,
            $signedAt,
            $signatureEvidencePath,
            [
                'ip' => '127.0.0.1',
                'user_agent' => 'FutureShift testing seed data',
                'identity_verification' => [
                    'password_verified_at' => $signedAt->toIso8601String(),
                    'mfa_required' => false,
                    'mfa_verified_at' => null,
                    'mfa_method' => null,
                ],
            ],
        );

        return $proposalId;
    }

    private function seedStrategicBudgetScenario(
        string $clientKey,
        string $scenario,
        string|int|null $businessPlanId,
        string|int|null $proposalId,
        string $state,
    ): StrategicBudget {
        $budgetService = app(StrategicBudgetService::class);
        $client = $this->clients[$clientKey];
        $businessPlan = $businessPlanId === null
            ? null
            : BusinessPlan::query()->whereKey($businessPlanId)->first();
        $clientActor = $this->portalActorForClient($clientKey);
        $advisor = $this->users['advisor'];

        $budget = $budgetService->ensureForClient($client, $businessPlan);
        $budget = $budgetService->update($budget, $this->strategicBudgetPayload($scenario), $clientActor);
        $budget = $budgetService->updateAdvisorGoals($budget, $this->advisorGoalsForBudgetScenario($scenario), $advisor);

        $canSubmit = $budget->isUnlocked()
            && collect((array) ($budget->business_plan_sections ?? []))
                ->filter(fn (mixed $section): bool => is_array($section) && trim((string) ($section['answer'] ?? '')) !== '')
                ->count() === 8;
        $submitted = false;
        if ($canSubmit && in_array($state, [
            StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
            StrategicBudget::STATUS_ADVISOR_APPROVED,
            StrategicBudget::STATUS_USED_IN_PROPOSAL,
            StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
        ], true)) {
            $budget = $budgetService->submit($budget, $clientActor);
            $submitted = true;
        }

        $approved = false;
        if ($submitted && in_array($state, [
            StrategicBudget::STATUS_ADVISOR_APPROVED,
            StrategicBudget::STATUS_USED_IN_PROPOSAL,
            StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
        ], true)) {
            $budget = $budgetService->approve($budget, $advisor);
            $approved = true;
        }

        if ($approved && in_array($state, [
            StrategicBudget::STATUS_USED_IN_PROPOSAL,
            StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
        ], true) && $proposalId !== null) {
            $proposal = Proposal::query()->whereKey($proposalId)->first();

            if ($proposal instanceof Proposal) {
                $budget = $budgetService->markUsedInProposal($budget, $proposal, $advisor);
            }
        }

        if ($approved && $state === StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT) {
            $budget->forceFill([
                'status' => StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
                'proposal_id' => $proposalId,
                'accepted_snapshot_at' => $this->now->copy()->subDay(),
            ])->save();
        }

        return $budget->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function strategicBudgetPayload(string $scenario): array
    {
        return [
            'business_plan_sections' => $this->businessPlanSectionAnswers($scenario),
            'horizon_months' => match ($scenario) {
                'due_diligence', 'post_acquisition' => 24,
                default => 12,
            },
            'expected_runway_months' => match ($scenario) {
                'npo' => 6,
                'due_diligence' => 9,
                'post_acquisition' => 8,
                default => 4,
            },
            'assumptions' => [
                'revenue_growth_percent' => match ($scenario) {
                    'npo' => 5,
                    'due_diligence' => 9,
                    'post_acquisition' => 11,
                    default => 8,
                },
                'cost_inflation_percent' => 2.7,
                'target_gross_profit_percent' => match ($scenario) {
                    'npo' => 0,
                    'due_diligence' => 52,
                    'post_acquisition' => 56,
                    default => 58,
                },
                'target_net_profit_before_tax_percent' => match ($scenario) {
                    'npo' => 0,
                    default => 14,
                },
                'target_net_profit_after_tax_percent' => match ($scenario) {
                    'npo' => 0,
                    default => 10,
                },
            ],
            'implementation_costs' => $this->budgetRows($scenario, 'implementation'),
            'monthly_fixed_costs' => $this->budgetRows($scenario, 'monthly'),
            'future_costs' => $this->futureBudgetRows($scenario),
            'revenue_forecast' => $this->budgetRows($scenario, 'revenue'),
            'funding_sources' => $this->budgetRows($scenario, 'funding'),
            'funding_scenarios' => $this->fundingScenarioRows($scenario),
        ];
    }

    /**
     * @return array<int, array{key:string,title:string,answer:string}>
     */
    private function businessPlanSectionAnswers(string $scenario): array
    {
        $titles = [
            'goals' => 'Goals',
            'current_position' => 'Current position',
            'market_customers' => 'Market / customers',
            'operations' => 'Operations',
            'risks' => 'Risks',
            'swot' => 'SWOT',
            'action_priorities' => 'Action priorities',
            'evidence_documents' => 'Evidence / documents',
        ];
        $answers = [
            'advisory' => [
                'goals' => 'Improve cash resilience, reduce debtor drag, and make monthly management reporting decision-ready.',
                'current_position' => 'Harbour Hive has solid demand but cash is being trapped in receivables and unmanaged operating rhythms.',
                'market_customers' => 'Core customers are recurring service buyers. Demand remains steady, with sensitivity to delayed decisions and payment cycles.',
                'operations' => 'Operations need tighter debtor escalation, inventory visibility, and weekly cash review ownership.',
                'risks' => 'Main risks are cash squeeze, slow decision cadence, and evidence gaps in the management pack.',
                'swot' => 'Strength: loyal customer base. Weakness: debtor discipline. Opportunity: funding-ready evidence pack. Threat: peak-season cash pressure.',
                'action_priorities' => 'Implement debtor cadence, validate weekly cash dashboard, and run a peak-season funding review.',
                'evidence_documents' => 'Financial statements, supplier contract, expired insurance flag, and advisor PV findings support the plan.',
            ],
            'due_diligence' => [
                'goals' => 'Protect purchase price, complete deal conditions, and convert DD findings into first 100-day actions.',
                'current_position' => 'Southern Lights is reviewing Kauri Kitchens with a proceed-with-conditions DD recommendation.',
                'market_customers' => 'The target has concentrated customer revenue and needs a retention plan before settlement.',
                'operations' => 'Post-settlement operations require customer communication, management-account reconciliation, and handover controls.',
                'risks' => 'Customer concentration, completion accounts, and dispatch key-person dependency are the highest priority risks.',
                'swot' => 'Strength: defensible local customer base. Weakness: concentration and undocumented systems. Opportunity: margin and reporting uplift. Threat: customer churn after settlement.',
                'action_priorities' => 'Confirm price protection, complete funding assumptions, and assign first 100-day operating owners.',
                'evidence_documents' => 'Management accounts, customer contracts, DD workstream findings, valuation, and acquisition plan sections support the budget.',
            ],
            'post_acquisition' => [
                'goals' => 'Stabilise the acquired business, preserve customer revenue, and establish a repeatable management rhythm.',
                'current_position' => 'Kauri Kitchens is entering post-acquisition advisory with DD gaps around reporting, customer concentration, and handover.',
                'market_customers' => 'The customer base is valuable but retention depends on communication, pricing discipline, and visible service continuity.',
                'operations' => 'Operations need weekly leadership cadence, finance reconciliation, and documented dispatch knowledge.',
                'risks' => 'Integration drift, cash surprises, and informal operational knowledge are the main execution risks.',
                'swot' => 'Strength: established operating base. Weakness: reporting lag. Opportunity: first 100-day control reset. Threat: inherited concentration risk.',
                'action_priorities' => 'Deploy handover controls, install weekly cash reporting, and review pricing and margin by customer segment.',
                'evidence_documents' => 'Post-acquisition financials, DD report, gap report, and integration plan actions support the advisory budget.',
            ],
            'npo' => [
                'goals' => 'Improve funding confidence, governance evidence, and programme sustainability for the next operating cycle.',
                'current_position' => 'Aroha Community has committed programmes and active governance work, but reporting rhythm and funding accountability need tightening.',
                'market_customers' => 'Beneficiaries, funders, and community partners rely on stable service delivery and credible impact evidence.',
                'operations' => 'Programme delivery needs clearer volunteer capacity, treasurer sign-off, and board reporting cadence.',
                'risks' => 'Funding concentration, reporting deadlines, governance re-registration, and service continuity are the key risks.',
                'swot' => 'Strength: strong community trust. Weakness: manual reporting. Opportunity: better funder accountability. Threat: funding deadline pressure.',
                'action_priorities' => 'Complete funder accountability pack, confirm programme budget lines, and prepare governance evidence for board review.',
                'evidence_documents' => 'Management accounts, constitution, board minutes, and funding agreement support the operating plan.',
            ],
        ][$scenario];

        return collect($titles)
            ->map(fn (string $title, string $key): array => [
                'key' => $key,
                'title' => $title,
                'answer' => $answers[$key],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function budgetRows(string $scenario, string $group): array
    {
        $rows = [
            'advisory' => [
                'implementation' => [
                    ['Debtor cadence setup', 6_500, 1, 'estimate'],
                    ['Cash dashboard configuration', 4_200, 1, 'known'],
                ],
                'monthly' => [
                    ['Advisor implementation cadence', 2_800, 1, 'known'],
                    ['Reporting administration time', 650, 1, 'estimate'],
                ],
                'revenue' => [
                    ['Margin retained through debtor improvement', 18_500, 1, 'estimate', 1, 1.5, 72],
                    ['Peak-season delivery uplift', 9_000, 1, 'guess', 4, 2.0, 58],
                ],
                'funding' => [
                    ['Working capital reserve', 30_000, 1, 'known'],
                ],
            ],
            'due_diligence' => [
                'implementation' => [
                    ['Completion accounts support', 9_500, 1, 'estimate'],
                    ['Customer retention workstream', 7_500, 1, 'estimate'],
                ],
                'monthly' => [
                    ['DD advisory cadence', 3_000, 1, 'known'],
                    ['Post-settlement reporting setup', 1_200, 1, 'estimate'],
                ],
                'revenue' => [
                    ['Protected acquisition earnings', 42_000, 1, 'estimate', 2, 0.8, 52],
                    ['Customer retention upside', 12_000, 1, 'guess', 5, 1.2, 55],
                ],
                'funding' => [
                    ['Buyer contribution', 180_000, 1, 'known'],
                    ['Bank acquisition facility', 620_000, 1, 'estimate'],
                ],
            ],
            'post_acquisition' => [
                'implementation' => [
                    ['Handover control reset', 11_000, 1, 'estimate'],
                    ['Management reporting pack', 6_800, 1, 'known'],
                ],
                'monthly' => [
                    ['Post-acquisition advisory retainer', 3_200, 1, 'known'],
                    ['Systems and reporting support', 950, 1, 'estimate'],
                ],
                'revenue' => [
                    ['Retained customer revenue', 38_000, 1, 'estimate', 1, 1.2, 56],
                    ['Pricing discipline uplift', 8_500, 1, 'guess', 4, 1.0, 62],
                ],
                'funding' => [
                    ['Integration cash buffer', 75_000, 1, 'known'],
                ],
            ],
            'npo' => [
                'implementation' => [
                    ['Funder accountability pack', 3_800, 1, 'estimate'],
                    ['Board reporting refresh', 2_400, 1, 'known'],
                ],
                'monthly' => [
                    ['Programme reporting support', 850, 1, 'estimate'],
                    ['Volunteer coordination administration', 600, 1, 'guess'],
                ],
                'revenue' => [
                    ['Committed programme funding', 18_000, 1, 'known', 1, 0.0, null],
                    ['Community grant pipeline', 6_500, 1, 'estimate', 4, 0.0, null],
                ],
                'funding' => [
                    ['Confirmed grant funding reserve', 22_000, 1, 'known'],
                ],
            ],
        ][$scenario][$group];

        return collect($rows)
            ->map(function (array $row): array {
                return [
                    'label' => $row[0],
                    'amount' => $row[1],
                    'quantity' => $row[2],
                    'confidence' => $row[3],
                    ...($row[4] ?? null ? ['month' => $row[4]] : []),
                    ...($row[5] ?? null ? ['monthly_growth_percent' => $row[5]] : []),
                    ...($row[6] ?? null ? ['gross_profit_percent' => $row[6]] : []),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function futureBudgetRows(string $scenario): array
    {
        return match ($scenario) {
            'due_diligence' => [
                ['label' => 'Earnout validation support', 'amount' => 8_000, 'quantity' => 1, 'year' => 2, 'recurring' => false, 'confidence' => 'estimate'],
            ],
            'post_acquisition' => [
                ['label' => 'System integration phase two', 'amount' => 14_000, 'quantity' => 1, 'year' => 2, 'recurring' => false, 'confidence' => 'guess'],
            ],
            'npo' => [
                ['label' => 'Impact reporting upgrade', 'amount' => 5_500, 'quantity' => 1, 'year' => 2, 'recurring' => false, 'confidence' => 'estimate'],
            ],
            default => [
                ['label' => 'Management reporting phase two', 'amount' => 7_500, 'quantity' => 1, 'year' => 2, 'recurring' => false, 'confidence' => 'estimate'],
            ],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fundingScenarioRows(string $scenario): array
    {
        return match ($scenario) {
            'due_diligence' => [
                ['name' => 'Bank plus buyer contribution', 'type' => 'bank_loan', 'amount' => 620_000, 'year' => 1, 'interest_rate_percent' => 8.2, 'term_years' => 5, 'interest_only_months' => 6, 'confidence' => 'estimate'],
            ],
            'post_acquisition' => [
                ['name' => 'Integration working capital facility', 'type' => 'bank_loan', 'amount' => 150_000, 'year' => 1, 'interest_rate_percent' => 8.0, 'term_years' => 3, 'interest_only_months' => 3, 'confidence' => 'estimate'],
            ],
            default => [],
        };
    }

    /**
     * @return array<int, array{title:string,measure:string}>
     */
    private function advisorGoalsForBudgetScenario(string $scenario): array
    {
        return match ($scenario) {
            'due_diligence' => [
                ['title' => 'Protect downside before settlement', 'measure' => 'Holdback or earnout treatment agreed before signing.'],
                ['title' => 'Convert DD into operating cadence', 'measure' => 'First 100-day controls ready before proposal sign-off.'],
            ],
            'post_acquisition' => [
                ['title' => 'Stabilise first 90 days', 'measure' => 'Weekly reporting pack and risk register reviewed with advisor.'],
            ],
            'npo' => [
                ['title' => 'Improve funder accountability', 'measure' => 'Board-approved reporting pack ready before next grant deadline.'],
            ],
            default => [
                ['title' => 'Make cash resilience measurable', 'measure' => 'Weekly cash dashboard and debtor cadence operating by first review.'],
            ],
        };
    }

    private function seedStrategicPlanScenario(
        string $key,
        string $clientKey,
        string|int|null $proposalId,
        string|int|null $budgetId,
        string $status,
        string $summary,
        array $sections,
        array $milestones,
        ?CarbonInterface $deployedAt,
    ): void {
        $client = $this->clients[$clientKey];
        $planId = $this->upsert('strategic_plans', [
            'proposal_id' => $proposalId,
        ], [
            'client_id' => $client->getKey(),
            'strategic_budget_id' => $budgetId,
            'title' => 'Strategic Plan - '.($client->trading_name ?: $client->legal_name),
            'status' => $status,
            'summary' => $summary,
            'sections' => $this->json(collect($sections)
                ->map(fn (array $section): array => [
                    'key' => $section[0],
                    'title' => $section[1],
                    'body' => $section[2],
                ])
                ->values()
                ->all()),
            'generated_at' => $this->now->copy()->subDays($status === StrategicPlan::STATUS_DEPLOYED ? 10 : 1),
            'generated_by_user_id' => $this->users['advisor']->getKey(),
            'deployed_at' => $deployedAt,
            'deployed_by_user_id' => $deployedAt instanceof CarbonInterface ? $this->users['advisor']->getKey() : null,
        ]);

        $this->ids["strategic_plan_{$key}"] = $planId;

        DB::table('strategic_plan_milestones')
            ->where('strategic_plan_id', $planId)
            ->delete();

        foreach ($milestones as [$title, $description, $owner, $offset, $milestoneStatus, $progress, $evidenceNotes]) {
            $completed = $milestoneStatus === StrategicPlanMilestone::STATUS_COMPLETED;

            $this->upsert('strategic_plan_milestones', [
                'strategic_plan_id' => $planId,
                'title' => $title,
            ], [
                'client_id' => $client->getKey(),
                'description' => $description,
                'owner' => $owner,
                'due_offset_days' => $offset,
                'due_date' => $deployedAt instanceof CarbonInterface
                    ? $deployedAt->copy()->addDays($offset)->toDateString()
                    : null,
                'status' => $milestoneStatus,
                'progress_percent' => $completed ? 100 : $progress,
                'evidence_notes' => $evidenceNotes,
                'advisor_notes' => $owner === StrategicPlanMilestone::OWNER_ADVISOR
                    ? 'Seeded advisor-owned milestone for strategic plan testing.'
                    : null,
                'completed_at' => $completed && $deployedAt instanceof CarbonInterface
                    ? $deployedAt->copy()->addDays(min(6, $offset))
                    : null,
            ]);
        }
    }

    private function removeStrategicPlanForProposal(string|int|null $proposalId): void
    {
        if ($proposalId === null) {
            return;
        }

        $planIds = DB::table('strategic_plans')
            ->where('proposal_id', $proposalId)
            ->pluck('id')
            ->all();

        if ($planIds === []) {
            return;
        }

        DB::table('strategic_plan_milestones')
            ->whereIn('strategic_plan_id', $planIds)
            ->delete();
        DB::table('strategic_plans')
            ->whereIn('id', $planIds)
            ->delete();
    }

    private function seedSignedProposalSteps(string|int|null $proposalId, string $clientKey, CarbonInterface $signedAt): void
    {
        if ($proposalId === null) {
            return;
        }

        foreach ([
            ProposalSignoffStep::STEP_REVIEW => ['completed_at' => $signedAt->copy()->subHours(3), 'payload' => ['reviewed' => true]],
            ProposalSignoffStep::STEP_PAYMENT_METHOD => ['completed_at' => $signedAt->copy()->subHours(2), 'payload' => ['type' => PaymentAuthority::TYPE_CARD, 'gateway' => PaymentAuthority::GATEWAY_STRIPE, 'collection_day' => 1]],
            ProposalSignoffStep::STEP_SIGNATURE => ['completed_at' => $signedAt, 'payload' => ['signature_name' => $this->portalActorForClient($clientKey)->name, 'accepted' => true]],
            ProposalSignoffStep::STEP_CONFIRMATION => ['completed_at' => $signedAt->copy()->addMinutes(5), 'payload' => ['confirmed' => true]],
        ] as $step => $data) {
            $this->upsert('proposal_signoff_steps', [
                'proposal_id' => $proposalId,
                'step' => $step,
            ], [
                'client_id' => $this->clients[$clientKey]->getKey(),
                'completed_by_user_id' => $this->portalActorForClient($clientKey)->getKey(),
                'completed_at' => $data['completed_at'],
                'payload' => $this->json(['fixture' => true, ...$data['payload']]),
            ]);
        }
    }

    private function portalActorForClient(string $clientKey): User
    {
        return match ($clientKey) {
            'dd', 'postAcquisition' => $this->users['buyer'],
            'npo' => $this->users['npoPrimary'],
            'socialEnterprise' => $this->users['socialEnterprise'],
            default => $this->users['primary'],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeSignedProposalFixture(
        Proposal $proposal,
        User $signedBy,
        string $typedName,
        CarbonInterface $signedAt,
        string $path,
        array $payload,
    ): void {
        $pdf = app(SignedProposalEvidenceRenderer::class)->renderPdf(
            $proposal->refresh()->load(['client.primaryContact', 'feeCalculation', 'consents', 'createdBy', 'signoffSteps']),
            $signedBy,
            $typedName,
            $payload,
            $signedAt,
        );
        $hashEnvelope = app(KeyEnvelope::class)->encrypt(hash('sha256', $pdf));

        Storage::disk('secure_local')->put($path, $pdf);

        DB::table('proposals')
            ->where('id', $proposal->getKey())
            ->update([
                'signature_evidence_path' => $path,
                'signature_evidence_sha256_envelope' => $hashEnvelope,
                'signature_envelope_meta' => $this->json(app(KeyEnvelope::class)->inspect($hashEnvelope)),
                'signature_evidence_byte_size' => strlen($pdf),
                'updated_at' => now(),
            ]);
    }

    private function seedBulkCommunicationsAndExpiryReminders(): void
    {
        $bulkId = $this->upsert('bulk_communications', ['template_key' => 'testing-quarterly-update'], [
            'title' => 'Testing Quarterly Update',
            'subject' => 'Quarterly advisory update for testing',
            'body' => 'This seeded message exercises bulk communication scheduling, delivery, and metrics.',
            'audience_type' => 'selected_clients',
            'selected_client_ids' => $this->json([
                $this->clients['advisory']->getKey(),
                $this->clients['dd']->getKey(),
            ]),
            'status' => 'sent',
            'scheduled_at' => $this->now->copy()->subHours(6),
            'sent_at' => $this->now->copy()->subHours(5),
            'created_by_user_id' => $this->users['advisor']->getKey(),
            'metrics' => $this->json([
                'recipients' => 3,
                'sent' => 3,
                'opened' => 1,
                'skipped' => 0,
            ]),
        ]);

        foreach ([
            ['client' => 'advisory', 'user' => 'primary', 'status' => 'opened'],
            ['client' => 'advisory', 'user' => 'team', 'status' => 'sent'],
            ['client' => 'dd', 'user' => 'buyer', 'status' => 'sent'],
        ] as $recipient) {
            $this->upsert('bulk_communication_recipients', [
                'bulk_communication_id' => $bulkId,
                'client_id' => $this->clients[$recipient['client']]->getKey(),
                'user_id' => $this->users[$recipient['user']]->getKey(),
            ], [
                'message_id' => $recipient['client'] === 'advisory' ? $this->ids['message_follow_up'] : null,
                'channel' => 'email',
                'preference_channel' => $recipient['user'] === 'team' ? 'email' : 'both',
                'preference_frequency' => $recipient['user'] === 'team' ? 'daily_digest' : 'immediate',
                'status' => $recipient['status'],
                'skipped_reason' => null,
                'open_token' => hash('sha256', 'bulk-'.$recipient['client'].'-'.$recipient['user']),
                'sent_at' => $this->now->copy()->subHours(5),
                'opened_at' => $recipient['status'] === 'opened' ? $this->now->copy()->subHours(4) : null,
                'delivery_metadata' => $this->json(['provider' => 'seeded', 'message_id' => 'seeded-email-id']),
            ]);
        }

        foreach ([
            ['document' => 'doc_financials', 'user' => 'primary', 'type' => 'expires_soon'],
            ['document' => 'doc_insurance_expired', 'user' => 'team', 'type' => 'expired'],
        ] as $reminder) {
            $document = DB::table('documents')->where('id', $this->ids[$reminder['document']])->first();

            if ($document === null) {
                continue;
            }

            $this->upsert('document_expiry_reminders', [
                'document_id' => $document->id,
                'user_id' => $this->users[$reminder['user']]->getKey(),
                'reminder_type' => $reminder['type'],
            ], [
                'client_id' => $document->client_id,
                'expires_at_snapshot' => $document->expires_at,
                'triggered_at' => $this->now->copy()->subHours(2),
                'metadata' => $this->json([
                    'fixture' => true,
                    'original_filename' => $document->original_filename,
                ]),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $answerOverrides
     * @return array{response_id:string|int|null,file_answer_id:?string,file_question_id:?string,file_question_prompt:?string}
     */
    private function seedQuestionnaireResponse(
        Client $client,
        QuestionnaireSet $set,
        User $submittedBy,
        string $attachedDocumentId,
        ?string $npoEngagementId = null,
        array $answerOverrides = [],
    ): array {
        $questionnaire = Questionnaire::query()
            ->forSet($set)
            ->published()
            ->with('sections.questions')
            ->firstOrFail();

        $responseKey = [
            'client_id' => $client->getKey(),
            'questionnaire_id' => $questionnaire->getKey(),
        ];
        $responseValues = [
            'submitted_at' => $this->now->copy()->subDays(7),
            'submitted_by_user_id' => $submittedBy->getKey(),
        ];

        if ($npoEngagementId !== null && Schema::hasColumn('questionnaire_responses', 'npo_engagement_id')) {
            $responseKey['npo_engagement_id'] = $npoEngagementId;
            $responseValues['npo_engagement_id'] = $npoEngagementId;
        }

        $responseId = $this->upsert('questionnaire_responses', $responseKey, $responseValues);

        $fileAnswerId = null;
        $fileQuestionId = null;
        $fileQuestionPrompt = null;

        foreach ($questionnaire->sections as $section) {
            foreach ($section->questions as $question) {
                $type = is_string($question->type) ? $question->type : $question->type->value;
                $attachedDocumentIds = [];
                $value = $answerOverrides[$question->prompt] ?? match ($type) {
                    'text' => 'Seeded response for '.$section->title,
                    'long-text' => 'Seeded long-form response with enough detail for analysis, reports, and document verification tests.',
                    'number' => 14,
                    'currency' => 1_250_000,
                    'date' => $this->now->copy()->addDays(90)->toDateString(),
                    'single-select', 'likert' => $this->firstOptionValue($question->options),
                    'multi-select' => $this->firstOptionValues($question->options, 2),
                    'file-attach' => null,
                    default => null,
                };

                if ($type === 'file-attach') {
                    $attachedDocumentIds = [$attachedDocumentId];
                    $fileQuestionId = (string) $question->getKey();
                    $fileQuestionPrompt = $question->prompt;
                }

                $answerId = $this->upsert('questionnaire_answers', [
                    'response_id' => $responseId,
                    'question_id' => $question->getKey(),
                ], [
                    'value' => $this->jsonOrNull($value),
                    'attached_document_ids' => $this->json($attachedDocumentIds),
                ]);

                if ($type === 'file-attach') {
                    $fileAnswerId = (string) $answerId;
                }
            }
        }

        return [
            'response_id' => $responseId,
            'file_answer_id' => $fileAnswerId,
            'file_question_id' => $fileQuestionId,
            'file_question_prompt' => $fileQuestionPrompt,
        ];
    }

    private function document(
        string $key,
        ?Client $client,
        string $category,
        string $filename,
        User $uploader,
        string $scannerResult,
        ?CarbonInterface $expiresAt,
        int $size,
        string $mimeType = 'application/pdf',
        ?string $entrepreneurProfileId = null,
        ?string $npoEngagementId = null,
    ): string|int|null {
        $storedPath = "seed/documents/{$key}";
        $disk = Storage::disk('secure_local');
        $contents = $this->fixtureDocumentContent($filename, $key, $mimeType);

        // Seed fixtures must be rewritten so their encrypted envelope matches the current APP_KEY.
        if (! $disk->put($storedPath, $contents)) {
            throw new \RuntimeException("Unable to write seeded document [{$key}].");
        }

        $values = [
            'client_id' => $client?->getKey(),
            'entrepreneur_profile_id' => $entrepreneurProfileId,
            'category' => $category,
            'original_filename' => $filename,
            'byte_size' => $size,
            'mime_type' => $mimeType,
            'sha256' => hash('sha256', "seed-document-{$key}"),
            'uploaded_by_user_id' => $uploader->getKey(),
            'scanner_result' => $scannerResult,
            'scanner_payload' => $this->json([
                'fixture' => true,
                'result' => $scannerResult,
                'engine' => 'seed-scanner',
            ]),
            'expires_at' => $expiresAt,
        ];

        if ($npoEngagementId !== null && Schema::hasColumn('documents', 'npo_engagement_id')) {
            $values['npo_engagement_id'] = $npoEngagementId;
        }

        return $this->upsert('documents', ['stored_path' => $storedPath], $values);
    }

    private function fixtureDocumentContent(string $filename, string $key, string $mimeType): string
    {
        $label = "Future Shift Advisory seed document: {$filename}";

        if ($mimeType !== 'application/pdf') {
            return "{$label}\nFixture key: {$key}\n";
        }

        $stream = "BT /F1 12 Tf 72 720 Td ({$this->pdfText($label)}) Tj 0 -18 Td (Fixture key: {$this->pdfText($key)}) Tj ET";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length '.strlen($stream)." >> stream\n{$stream}\nendstream endobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= "{$object}\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private function pdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($value));
    }

    private function verification(
        string $documentId,
        string $context,
        ?Client $client,
        string $claim,
        string $outcome,
        ?float $confidence,
        ?string $explanation = null,
        ?string $questionnaireResponseId = null,
        ?string $questionnaireAnswerId = null,
        ?string $questionnaireQuestionId = null,
        ?string $questionPrompt = null,
        ?string $entrepreneurProfileId = null,
        ?string $planSectionId = null,
    ): string|int|null {
        return $this->upsert('document_verifications', [
            'document_id' => $documentId,
            'context_hash' => hash('sha256', "seed-verification-{$context}"),
        ], [
            'client_id' => $client?->getKey(),
            'entrepreneur_profile_id' => $entrepreneurProfileId,
            'questionnaire_response_id' => $questionnaireResponseId,
            'questionnaire_answer_id' => $questionnaireAnswerId,
            'questionnaire_question_id' => $questionnaireQuestionId,
            'plan_section_id' => $planSectionId === '' ? null : $planSectionId,
            'claim_source' => $questionnaireAnswerId === null ? 'seed_fixture' : 'questionnaire_answer',
            'question_prompt' => $questionPrompt,
            'claim_text' => $claim,
            'outcome' => $outcome,
            'confidence' => $confidence,
            'explanation' => $explanation ?? "Seeded {$outcome} verification.",
            'client_explanation' => $outcome === 'discrepancy' ? 'The uploaded evidence does not match the claim.' : null,
            'source_payload' => $this->json(['fixture' => true, 'context' => $context]),
            'ai_payload' => $this->json(['model' => 'seeded-verifier', 'fixture' => true]),
            'prompt_version' => 'seed-v1',
            'prompt_hash' => hash('sha256', 'seed-v1'),
            'verified_at' => $outcome === 'pending' ? null : $this->now->copy()->subDay(),
            'resolved_at' => $outcome === 'discrepancy' ? null : $this->now->copy()->subDay(),
            'resolved_by_user_id' => $outcome === 'pending' ? null : $this->users['advisor']->getKey(),
            'resolution_note' => $outcome === 'pending' ? null : 'Seeded verification resolved for testing.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    private function upsert(string $table, array $key, array $values, bool $timestamps = true): string|int|null
    {
        $existing = $this->matching($table, $key)->first();

        if ($existing !== null) {
            $update = $values;

            if ($timestamps) {
                $update['updated_at'] = $this->now;
            }

            if ($update !== []) {
                $this->matching($table, $key)->update($update);
            }

            return $existing->id ?? null;
        }

        $insert = [...$key, ...$values];

        if ($timestamps) {
            $insert['created_at'] = $this->now;
            $insert['updated_at'] = $this->now;
        }

        DB::table($table)->insert($insert);

        return $this->matching($table, $key)->value('id');
    }

    /**
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    private function firstOrInsert(string $table, array $key, array $values, bool $timestamps = true): string|int|null
    {
        $existing = $this->matching($table, $key)->first();

        if ($existing !== null) {
            return $existing->id ?? null;
        }

        $insert = [...$key, ...$values];

        if ($timestamps) {
            $insert['created_at'] = $this->now;
            $insert['updated_at'] = $this->now;
        }

        DB::table($table)->insert($insert);

        return $this->matching($table, $key)->value('id');
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function matching(string $table, array $key): Builder
    {
        $query = DB::table($table);

        foreach ($key as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function jsonOrNull(mixed $value): ?string
    {
        return $value === null ? null : $this->json($value);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $options
     */
    private function firstOptionValue(?array $options): ?string
    {
        $first = $options[0]['value'] ?? null;

        return is_scalar($first) ? (string) $first : null;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $options
     * @return array<int, string>
     */
    private function firstOptionValues(?array $options, int $count): array
    {
        return collect($options ?? [])
            ->pluck('value')
            ->filter(static fn (mixed $value): bool => is_scalar($value))
            ->map(static fn (mixed $value): string => (string) $value)
            ->take($count)
            ->values()
            ->all();
    }

    private function stableTimestamp(string $value): CarbonInterface
    {
        return Carbon::parse($value, config('app.timezone'));
    }

    private function stableUuid(string $value): string
    {
        $hash = md5($value);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
