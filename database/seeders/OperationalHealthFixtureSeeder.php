<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\Template;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

final class OperationalHealthFixtureSeeder extends Seeder
{
    public function run(RequestContext $requestContext): void
    {
        $this->call(DdSpecificQuestionnaireV2Seeder::class);

        $requestContext->withSystemContext(function (): void {
            DB::transaction(function (): void {
                $admin = $this->monitorUser('super_admin_email', User::TYPE_SUPER_ADMIN, 'Operational Health Admin');
                $clientUser = $this->monitorUser('client_email', User::TYPE_CLIENT_PRIMARY, 'Operational Health Client');
                $ddUser = $this->monitorUser('dd_client_email', User::TYPE_CLIENT_PRIMARY, 'Operational Health DD Client');
                $entrepreneurUser = $this->monitorUser('entrepreneur_email', User::TYPE_ENTREPRENEUR, 'Operational Health Entrepreneur');

                foreach ([$admin, $clientUser, $ddUser, $entrepreneurUser] as $user) {
                    $this->acceptLatestTerms($user);
                }

                $standardClient = $this->client(
                    legalName: 'Operational Health Standard Advisory Fixture',
                    tradingName: 'Operational Health Standard',
                    engagementType: EngagementType::STANDARD_ADVISORY,
                    primaryContact: $clientUser,
                    admin: $admin,
                );
                $this->attachToClient($standardClient, $clientUser, 'primary_contact', [
                    'portal',
                    EngagementType::STANDARD_ADVISORY->value,
                ]);
                $this->attachToClient($standardClient, $admin, 'lead_advisor', [
                    'portal',
                    EngagementType::STANDARD_ADVISORY->value,
                ]);
                $this->document(
                    path: 'operational-health/client-documents/client-portal-fixture.pdf',
                    originalName: 'operational-health-client-document.pdf',
                    label: 'Operational health client portal document',
                    category: Document::CATEGORY_FINANCIAL_STATEMENT,
                    uploader: $clientUser,
                    client: $standardClient,
                );

                $ddClient = $this->client(
                    legalName: 'Operational Health Due Diligence Fixture',
                    tradingName: 'Operational Health DD',
                    engagementType: EngagementType::DUE_DILIGENCE,
                    primaryContact: $ddUser,
                    admin: $admin,
                );
                $this->attachToClient($ddClient, $ddUser, 'primary_contact', [
                    'portal',
                    EngagementType::DUE_DILIGENCE->value,
                ]);
                $this->attachToClient($ddClient, $admin, 'lead_advisor', [
                    'portal',
                    EngagementType::DUE_DILIGENCE->value,
                ]);
                $ddEngagement = $this->ddEngagement($ddClient, $admin);
                $this->ddPlanBudgetActivation($ddClient, $ddEngagement, $ddUser, $admin);
                $this->ddDecisionReport($ddClient, $admin);

                $this->entrepreneurProfile($entrepreneurUser, $admin);
                $this->template($admin);
            });
        });
    }

    private function monitorUser(string $emailKey, string $role, string $name): User
    {
        $email = $this->configuredEmail($emailKey);
        $user = User::query()->firstOrNew(['email' => $email]);

        $updates = [
            'name' => $user->exists ? ($user->name ?: $name) : $name,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'user_type' => $role,
            'primary_role' => $role,
            'mfa_enabled_at' => $user->mfa_enabled_at ?? now(),
            'mfa_method' => $user->mfa_method ?: User::MFA_METHOD_TOTP,
            'two_factor_secret' => $user->two_factor_secret ?? encrypt('operational-health-'.$emailKey),
            'two_factor_recovery_codes' => $user->two_factor_recovery_codes ?? encrypt(json_encode([Str::random(32)])),
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at ?? now(),
            'last_password_set_at' => $user->last_password_set_at ?? now(),
            'suspended_at' => null,
            'suspended_reason' => null,
            'deactivation_requested_at' => null,
            'deactivation_requested_reason' => null,
        ];

        if (! $user->exists) {
            $updates['password'] = Hash::make(Str::random(48));
        }

        $user->forceFill($updates)->save();
        $this->assignRole($user, $role);

        return $user->refresh();
    }

    private function configuredEmail(string $key): string
    {
        $email = config("operational_health.users.{$key}");

        return is_string($email) && trim($email) !== ''
            ? Str::lower(trim($email))
            : "operational-health-{$key}@futureshiftadvisory.test";
    }

    private function assignRole(User $user, string $role): void
    {
        if (! Schema::hasTable(config('permission.table_names.roles', 'roles'))) {
            return;
        }

        if (! Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
            return;
        }

        $user->syncRoles([$role]);
    }

    private function acceptLatestTerms(User $user): void
    {
        if (! Schema::hasTable('terms_versions') || ! Schema::hasTable('terms_acceptances')) {
            return;
        }

        $version = TermsVersion::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $version instanceof TermsVersion) {
            return;
        }

        $values = [
            'accepted_at' => now(),
            'declined_at' => null,
            'expires_at' => null,
            'reacceptance_notice_queued_at' => null,
            'ip' => '127.0.0.1',
            'user_agent' => 'FutureShift operational health fixture seeder',
        ];

        if (Schema::hasColumn('terms_acceptances', 'signed_pdf_path')) {
            $values['signed_pdf_path'] = null;
        }

        if (Schema::hasColumn('terms_acceptances', 'signed_pdf_sha256_envelope')) {
            $values['signed_pdf_sha256_envelope'] = null;
            $values['signed_pdf_envelope_meta'] = null;
            $values['signed_pdf_byte_size'] = null;
        }

        TermsAcceptance::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'terms_version_id' => $version->getKey(),
            ],
            $values,
        );
    }

    private function client(
        string $legalName,
        string $tradingName,
        EngagementType $engagementType,
        User $primaryContact,
        User $admin,
    ): Client {
        return Client::query()->updateOrCreate(
            ['legal_name' => $legalName],
            [
                'engagement_type' => $engagementType->value,
                'trading_name' => $tradingName,
                'entity_type' => 'company',
                'address' => [
                    'country' => 'NZ',
                    'region' => 'Operational Health',
                ],
                'gst_registered' => false,
                'directors' => [],
                'filing_status' => 'monitor_fixture',
                'data_quality' => Client::DATA_QUALITY_LOW,
                'registry_sources' => [
                    'source' => 'operational_health_fixture',
                    'purpose' => 'Synthetic authenticated workflow monitoring.',
                ],
                'created_by_user_id' => $admin->getKey(),
                'primary_contact_user_id' => $primaryContact->getKey(),
                'status' => ClientStatus::ACTIVE->value,
            ],
        );
    }

    /**
     * @param  array<int, string>  $modules
     */
    private function attachToClient(Client $client, User $user, string $role, array $modules): void
    {
        ClientTeamMember::query()->updateOrCreate(
            [
                'client_id' => $client->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'role' => $role,
                'granted_modules' => $modules,
            ],
        );
    }

    private function ddEngagement(Client $client, User $admin): DdEngagement
    {
        $conflict = ConflictDeclaration::query()->updateOrCreate(
            [
                'client_id' => $client->getKey(),
                'advisor_id' => $admin->getKey(),
            ],
            [
                'declaration' => [
                    'known_conflicts' => false,
                    'summary' => 'Operational health fixture only.',
                    'source' => 'operational_health_fixture',
                ],
                'declared_at' => now(),
            ],
        );

        return DdEngagement::query()->updateOrCreate(
            [
                'client_id' => $client->getKey(),
                'target_name' => 'Operational Health Target Ltd',
            ],
            [
                'target_details' => [
                    'sector' => 'Monitoring',
                    'purpose' => 'Synthetic due-diligence preview fixture.',
                ],
                'status' => DdEngagement::STATUS_IN_PROGRESS,
                'recommendation' => null,
                'conflict_declaration_id' => $conflict->getKey(),
                'created_by_user_id' => $admin->getKey(),
                'disclaimer_acknowledged_at' => now(),
            ],
        );
    }

    private function ddPlanBudgetActivation(
        Client $client,
        DdEngagement $engagement,
        User $clientUser,
        User $admin,
    ): ServiceActivation {
        $package = ServiceRatePackage::query()->updateOrCreate(
            [
                'service_type' => ServiceRatePackage::SERVICE_DD_PLAN_BUDGET,
                'package_name' => 'Operational Health DD + Business Plan & Budget',
            ],
            [
                'package_scope' => ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON,
                'client_label' => 'DD + Business Plan & Budget',
                'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
                'fixed_fee' => 2400,
                'deposit_percent' => 100,
                'purchase_price_min' => null,
                'purchase_price_max' => null,
                'currency' => 'NZD',
                'scope_description' => 'Operational health fixture for active DD Business Plan & Budget access.',
                'is_active' => true,
                'effective_from' => now(),
                'effective_to' => null,
                'created_by_user_id' => $admin->getKey(),
            ],
        );
        $snapshot = $package->snapshot();
        $snapshot['quote_context'] = [
            'plan_budget_fixed_fee' => 2400,
            'amount_due_for_this_activation' => 0,
            'combined_fixed_fee' => 2400,
            'source' => 'operational_health_fixture',
        ];

        return ServiceActivation::query()->updateOrCreate(
            [
                'client_id' => $client->getKey(),
                'service_type' => ServiceActivation::SERVICE_DD_PLAN_BUDGET,
            ],
            [
                'requested_by_user_id' => $clientUser->getKey(),
                'advisor_id' => $admin->getKey(),
                'approved_by_user_id' => $admin->getKey(),
                'client_label' => 'DD + Business Plan & Budget',
                'service_rate_package_id' => $package->getKey(),
                'status' => ServiceActivation::STATUS_ACTIVE,
                'intake' => [
                    'target_name' => $engagement->target_name,
                    'asking_price' => 750000,
                    'source' => 'operational_health_fixture',
                ],
                'selected_package_snapshot' => $snapshot,
                'accepted_by_user_id' => $clientUser->getKey(),
                'accepted_at' => now(),
                'acceptance_text' => 'Operational health fixture acceptance for DD Business Plan & Budget access.',
                'terms_reference' => [
                    'source' => 'operational_health_fixture',
                ],
                'related_dd_engagement_id' => $engagement->getKey(),
                'payment_status' => ServiceActivation::PAYMENT_PAID,
                'payment_completed_at' => now(),
                'metadata' => [
                    'source' => 'operational_health_fixture',
                    'monitor_only' => true,
                ],
            ],
        );
    }

    private function ddDecisionReport(Client $client, User $admin): Report
    {
        $path = 'operational-health/reports/dd-decision-report.pdf';
        $contents = $this->fixturePdf('Operational health DD decision report', $path);
        $disk = Storage::disk('secure_local');

        if (! $disk->put($path, $contents)) {
            throw new \RuntimeException("Unable to write operational health fixture report [{$path}].");
        }

        return Report::query()->updateOrCreate(
            [
                'client_id' => $client->getKey(),
                'type' => ReportType::AcquisitionGoNoGo->value,
                'title' => 'Operational Health DD Decision Report',
            ],
            [
                'pdf_path' => $path,
                'pdf_byte_size' => strlen($contents),
                'pptx_path' => null,
                'pptx_byte_size' => null,
                'generated_by_user_id' => $admin->getKey(),
                'generated_at' => now(),
                'render_status' => Report::RENDER_STATUS_RENDERED,
                'render_failed_at' => null,
                'render_error' => null,
                'review_status' => 'reviewed',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $admin->getKey(),
                'metadata' => [
                    'source' => 'operational_health_fixture',
                    'buyer_decision_readiness' => [
                        'ready' => true,
                        'label' => 'Buyer decision-ready',
                        'decision_label' => 'Renegotiate with conditions',
                        'decision_headline' => 'The DD decision report gives the buyer enough evidence to decide whether to buy, renegotiate, pause, or walk away.',
                        'decision_status' => 'renegotiate',
                        'confidence' => 'high',
                        'confidence_reason' => 'Synthetic monitor evidence is sufficient for routed smoke testing.',
                        'recommendation' => DdEngagement::RECOMMENDATION_RENEGOTIATE,
                        'recommendation_rationale' => 'Proceed only if price protection and completion-account conditions remain in the purchase agreement.',
                        'completed_workstreams' => 8,
                        'required_workstreams' => 8,
                        'evidence_item_count' => 2,
                        'finding_count' => 3,
                        'verified_finding_count' => 3,
                        'flagged_finding_count' => 0,
                        'material_risk_count' => 1,
                        'deal_killer_risk_count' => 0,
                        'major_risk_count' => 1,
                        'total_risk_count' => 2,
                        'price_adjustment_nzd' => 25000,
                        'valuation_midpoint_nzd' => 725000,
                        'gates' => [
                            [
                                'key' => 'workstream_coverage',
                                'label' => 'All DD workstreams assessed',
                                'passed' => true,
                                'detail' => '8 of 8 workstreams are complete.',
                            ],
                            [
                                'key' => 'client_decision',
                                'label' => 'Buy / renegotiate / walk-away position is explicit',
                                'passed' => true,
                                'detail' => 'Renegotiate with conditions.',
                            ],
                        ],
                        'blockers' => [],
                        'decision_questions' => [
                            [
                                'question' => 'Can the buyer make an informed decision?',
                                'answer' => 'Yes, subject to independent legal and accounting advice.',
                                'status' => 'met',
                            ],
                        ],
                    ],
                    'advisor_client_reply' => [
                        'status' => 'feedback_saved',
                        'advisor_feedback' => 'Operational health DD feedback fixture.',
                        'proposed_reply' => 'Operational health DD client reply fixture.',
                        'saved_at' => now()->toIso8601String(),
                        'saved_by_user_id' => $admin->getKey(),
                        'sent_at' => null,
                        'sent_by_user_id' => null,
                        'client_message_thread_id' => null,
                        'client_message_id' => null,
                    ],
                ],
            ],
        );
    }

    private function entrepreneurProfile(User $entrepreneur, User $admin): EntrepreneurProfile
    {
        return EntrepreneurProfile::query()->updateOrCreate(
            ['email' => $entrepreneur->email],
            [
                'user_id' => $entrepreneur->getKey(),
                'assigned_advisor_id' => $admin->getKey(),
                'invite_token_id' => null,
                'name' => $entrepreneur->name,
                'stage' => EntrepreneurStage::BUILDING_PHASE_1->value,
                'concept_summary' => 'Operational health fixture profile for business plan preview monitoring.',
                'intended_service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
                'intended_package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET,
            ],
        );
    }

    private function document(
        string $path,
        string $originalName,
        string $label,
        string $category,
        User $uploader,
        ?Client $client = null,
    ): Document {
        $contents = $this->fixturePdf($label, $path);
        $disk = Storage::disk('secure_local');

        if (! $disk->put($path, $contents)) {
            throw new \RuntimeException("Unable to write operational health fixture document [{$path}].");
        }

        return Document::query()->updateOrCreate(
            ['stored_path' => $path],
            [
                'client_id' => $client?->getKey(),
                'entrepreneur_profile_id' => null,
                'category' => $category,
                'original_filename' => $originalName,
                'byte_size' => strlen($contents),
                'mime_type' => 'application/pdf',
                'sha256' => hash('sha256', $contents),
                'uploaded_by_user_id' => $uploader->getKey(),
                'scanner_result' => Document::SCANNER_CLEAN,
                'scanner_payload' => [
                    'fixture' => true,
                    'result' => Document::SCANNER_CLEAN,
                    'engine' => 'operational-health-seeder',
                ],
                'expires_at' => null,
            ],
        );
    }

    private function template(User $admin): Template
    {
        $document = $this->document(
            path: 'operational-health/templates/template-preview.pdf',
            originalName: 'operational-health-template-preview.pdf',
            label: 'Operational health advisor template preview',
            category: Document::CATEGORY_TEMPLATE_FILE,
            uploader: $admin,
        );

        return Template::query()->updateOrCreate(
            ['source_reference' => 'operational-health:template-preview'],
            [
                'category' => Template::CATEGORY_REPORT,
                'title' => 'Operational Health Template Preview',
                'body' => '',
                'structure' => [
                    'source_kind' => 'uploaded_file',
                    'sections' => [],
                    'uploaded_file' => [
                        'document_id' => (string) $document->getKey(),
                        'stored_path' => $document->stored_path,
                        'original_name' => $document->original_filename,
                        'mime_type' => $document->mime_type,
                        'extension' => 'pdf',
                        'byte_size' => $document->byte_size,
                        'sha256' => $document->sha256,
                        'scanner_result' => $document->scanner_result,
                        'uploaded_at' => now()->toIso8601String(),
                    ],
                ],
                'status' => Template::STATUS_ARCHIVED,
                'version' => 1,
                'created_by_user_id' => $admin->getKey(),
                'learning_update_implementation_id' => null,
            ],
        );
    }

    private function fixturePdf(string $label, string $key): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], "{$label} {$key}");
        $stream = "BT /F1 12 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj',
            '4 0 obj << /Length '.strlen($stream)." >> stream\n{$stream}\nendstream endobj",
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
