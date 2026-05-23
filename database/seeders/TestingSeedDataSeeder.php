<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\Questionnaire;
use App\Models\User;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            NzResourceSeeder::class,
            RatingFrameworkSeeder::class,
            FoundingRatingFrameworkValuesSeeder::class,
            UserSeeder::class,
        ]);

        app(RequestContext::class)->apply('system', []);

        DB::transaction(function (): void {
            $this->seedUsers();
            $this->seedProspectIntake();
            $this->seedClients();
            $this->seedClientDocumentsAndQuestionnaires();
            $this->seedFinancialsAndAnalysis();
            $this->seedEntrepreneurJourney();
            $this->seedGoalsProposalsAndPayments();
            $this->seedEngagementTouchpoints();
            $this->seedPanelAndReferralData();
            $this->seedDueDiligenceJourney();
            $this->seedBulkCommunicationsAndExpiryReminders();
        });
    }

    private function seedUsers(): void
    {
        $records = [
            'admin' => ['Seed Super Admin', 'seed.admin@futureshiftadvisory.test', User::TYPE_SUPER_ADMIN, 45],
            'advisor' => ['Seed Lead Advisor', 'seed.advisor@futureshiftadvisory.test', User::TYPE_ADVISOR, 30],
            'junior' => ['Seed Junior Advisor', 'seed.junior@futureshiftadvisory.test', User::TYPE_JUNIOR_ADVISOR, 30],
            'primary' => ['Seed Client Principal', 'seed.client.primary@futureshiftadvisory.test', User::TYPE_CLIENT_PRIMARY, 20],
            'team' => ['Seed Finance Lead', 'seed.client.team@futureshiftadvisory.test', User::TYPE_CLIENT_TEAM, 20],
            'buyer' => ['Seed Buyer Principal', 'seed.buyer.primary@futureshiftadvisory.test', User::TYPE_CLIENT_PRIMARY, 20],
            'analyst' => ['Seed Buyer Analyst', 'seed.buyer.analyst@futureshiftadvisory.test', User::TYPE_CLIENT_TEAM, 20],
            'entrepreneur' => ['Seed Founder', 'seed.entrepreneur@futureshiftadvisory.test', User::TYPE_ENTREPRENEUR, 20],
            'broker' => ['Seed Broker Partner', 'seed.broker@futureshiftadvisory.test', User::TYPE_BROKER, 20],
            'coach' => ['Seed Coach Partner', 'seed.coach@futureshiftadvisory.test', User::TYPE_COACH, 20],
            'mentor' => ['Seed Entrepreneur Mentor', 'seed.mentor@futureshiftadvisory.test', User::TYPE_ENTREPRENEUR_MENTOR, 20],
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
                'two_factor_secret' => encrypt("testing-secret-{$key}"),
                'two_factor_recovery_codes' => encrypt(json_encode(["testing-recovery-{$key}"], JSON_THROW_ON_ERROR)),
                'two_factor_confirmed_at' => $this->now,
                'mfa_enabled_at' => $this->now,
                'mfa_method' => User::MFA_METHOD_TOTP,
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

            $this->upsert('mfa_factors', ['user_id' => $user->getKey(), 'type' => 'totp'], [
                'label' => 'Testing authenticator',
                'secret_envelope' => encrypt("testing-factor-{$key}"),
                'recovery_codes_envelope' => encrypt(json_encode(["testing-factor-recovery-{$key}"], JSON_THROW_ON_ERROR)),
                'confirmed_at' => $this->now,
                'last_used_at' => $this->now->copy()->subDay(),
            ]);

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
                    'completed_steps' => ['profile', 'questionnaire'],
                    'current_step' => 'integration_plan',
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

        $this->seedClientTeam();
        $this->seedConflictDeclarations();
    }

    private function seedClientTeam(): void
    {
        $members = [
            ['advisory', 'advisor', 'lead_advisor', ['dashboard', 'documents', 'questionnaire', 'payments', 'reports']],
            ['advisory', 'junior', 'advisor', ['dashboard', 'documents', 'questionnaire']],
            ['advisory', 'primary', 'primary_contact', ['portal', 'documents', 'questionnaire', 'payments']],
            ['advisory', 'team', 'finance_contact', ['documents', 'payments', 'reports']],
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
        ];

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
        foreach (['advisory', 'dd', 'postAcquisition'] as $clientKey) {
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
            'scopes' => $this->json(['accounting.transactions.read', 'accounting.reports.read']),
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
            'module' => 'strategic_diagnostic',
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
            'lens' => 'financial',
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
            'lens' => 'people',
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
            'discount_method' => 'risk_adjusted',
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
            'discount_method' => 'risk_adjusted',
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
            'discount_method' => 'dcf',
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
            $this->ids['plan_assessment'] = $this->upsert('plan_assessments', [
                'business_plan_id' => $planId,
                'round' => 1,
            ], [
                'rating_framework_id' => $ratingFrameworkId,
                'ai_scores' => $this->json([
                    'problem' => 8.6,
                    'market' => 8.1,
                    'evidence' => 7.9,
                    'execution' => 8.4,
                ]),
                'advisor_scores' => $this->json(['overall' => 8.2, 'note' => 'Good evidence depth for a first plan.']),
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
            'method' => 'pv_linked',
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
            'acceptance_terms' => $this->json(['payment' => 'monthly_card', 'cancellation_notice_days' => 30]),
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
            'signed_at' => $this->now->copy()->subDays(4),
            'signed_by_user_id' => $this->users['primary']->getKey(),
            'signature_evidence_path' => 'seed/proposals/harbour-hive-signature.json',
            'signature_evidence_sha256_envelope' => hash('sha256', 'seed-proposal-signature'),
            'signature_envelope_meta' => $this->json(['fixture' => true]),
            'signature_evidence_byte_size' => 9_600,
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

        foreach (['released', 'client_signed', 'payment_authorised'] as $index => $step) {
            $this->upsert('proposal_signoff_steps', [
                'proposal_id' => $this->ids['proposal'],
                'step' => $step,
            ], [
                'client_id' => $this->clients['advisory']->getKey(),
                'completed_by_user_id' => $step === 'released' ? $this->users['advisor']->getKey() : $this->users['primary']->getKey(),
                'completed_at' => $this->now->copy()->subDays(6 - $index),
                'payload' => $this->json(['fixture' => true, 'order' => $index + 1]),
            ]);
        }

        $this->ids['payment_authority'] = $this->upsert('payment_authorities', [
            'client_id' => $this->clients['advisory']->getKey(),
            'proposal_id' => $this->ids['proposal'],
            'type' => 'card',
            'gateway' => 'stripe',
        ], [
            'gateway_customer_ref' => 'cus_seed_harbour_hive',
            'gateway_token_envelope' => encrypt('pm_seed_harbour_hive'),
            'status' => 'active',
            'authorised_by_user_id' => $this->users['primary']->getKey(),
            'authorised_at' => $this->now->copy()->subDays(4),
            'revoked_at' => null,
        ]);

        $this->ids['payment_schedule'] = $this->upsert('payment_schedules', [
            'client_id' => $this->clients['advisory']->getKey(),
            'proposal_id' => $this->ids['proposal'],
            'cadence' => 'monthly',
        ], [
            'payment_authority_id' => $this->ids['payment_authority'],
            'amount' => 4_000,
            'currency' => 'NZD',
            'next_run_at' => $this->now->copy()->addMonth()->startOfDay(),
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
            'module' => 'due_diligence',
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
            'lens' => 'commercial',
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
            'type' => 'dd_valuation',
            'created_by_user_id' => $this->users['advisor']->getKey(),
        ], [
            'discount_method' => 'dcf',
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
            'type' => 'dd_report',
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
            'metadata' => $this->json(['fixture' => true, 'source' => 'testing_seed_data']),
            'migrated_by_user_id' => $this->users['advisor']->getKey(),
            'migrated_at' => $this->now->copy()->subHours(12),
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
     * @return array{response_id:string|int|null,file_answer_id:?string,file_question_id:?string,file_question_prompt:?string}
     */
    private function seedQuestionnaireResponse(
        Client $client,
        QuestionnaireSet $set,
        User $submittedBy,
        string $attachedDocumentId,
    ): array {
        $questionnaire = Questionnaire::query()
            ->forSet($set)
            ->published()
            ->with('sections.questions')
            ->firstOrFail();

        $responseId = $this->upsert('questionnaire_responses', [
            'client_id' => $client->getKey(),
            'questionnaire_id' => $questionnaire->getKey(),
        ], [
            'submitted_at' => $this->now->copy()->subDays(7),
            'submitted_by_user_id' => $submittedBy->getKey(),
        ]);

        $fileAnswerId = null;
        $fileQuestionId = null;
        $fileQuestionPrompt = null;

        foreach ($questionnaire->sections as $section) {
            foreach ($section->questions as $question) {
                $type = is_string($question->type) ? $question->type : $question->type->value;
                $attachedDocumentIds = [];
                $value = match ($type) {
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
    ): string|int|null {
        return $this->upsert('documents', ['stored_path' => "seed/documents/{$key}"], [
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
        ]);
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
}
