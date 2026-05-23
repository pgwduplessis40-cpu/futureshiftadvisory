<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\QuestionnaireSet;
use App\Enums\ReportType;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\DdRiskRegisterItem;
use App\Models\DdValuation;
use App\Models\Document;
use App\Models\FeeCalculation;
use App\Models\PostAcquisitionMigration;
use App\Models\Proposal;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Proposals\ProposalBuilder;
use App\Services\Reports\ReportComposer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PostAcquisition
{
    public function __construct(
        private readonly ReportComposer $reports,
        private readonly ProposalBuilder $proposals,
        private readonly AuditWriter $audit,
    ) {}

    public function convert(DdEngagement $engagement, User $actor): PostAcquisitionMigration
    {
        $engagement->refresh()->loadMissing('client.teamMembers');

        if ($engagement->status !== DdEngagement::STATUS_ACQUISITION_PROCEEDING) {
            throw new InvalidArgumentException('Post-acquisition conversion requires the DD engagement to be marked acquisition proceeding.');
        }

        $existing = PostAcquisitionMigration::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->first();

        if ($existing instanceof PostAcquisitionMigration) {
            return $existing->refresh()->load(['advisoryClient', 'gapQuestionnaireResponse.answers', 'proposal.feeCalculation']);
        }

        return DB::transaction(function () use ($engagement, $actor): PostAcquisitionMigration {
            $report = $this->latestDdReport($engagement) ?? $this->reports->composeDueDiligence($engagement, $actor);
            $plan = $this->foundingPlan($engagement);
            $advisoryClient = $this->createAdvisoryClient($engagement, $actor);
            $this->copyTeam($engagement, $advisoryClient, $actor);
            $migratedDocumentIds = $this->migrateDocuments($engagement, $advisoryClient, $actor);
            [$gapResponse, $remainingQuestions, $prefillPayload] = $this->createGapQuestionnaireResponse(
                engagement: $engagement,
                advisoryClient: $advisoryClient,
                migratedDocumentIds: $migratedDocumentIds,
                actor: $actor,
            );
            $proposal = $this->createProposal($engagement, $advisoryClient, $report, $actor);

            $migration = PostAcquisitionMigration::query()->create([
                'dd_engagement_id' => $engagement->getKey(),
                'buyer_client_id' => $engagement->client_id,
                'advisory_client_id' => $advisoryClient->getKey(),
                'business_plan_id' => $plan?->getKey(),
                'dd_report_id' => $report->getKey(),
                'gap_questionnaire_response_id' => $gapResponse->getKey(),
                'proposal_id' => $proposal->getKey(),
                'migrated_document_ids' => $migratedDocumentIds,
                'dd_pv_baseline' => $this->ddPvBaseline($engagement),
                'status' => PostAcquisitionMigration::STATUS_CREATED,
                'metadata' => [
                    'source_label' => 'Sourced from DD',
                    'gap_prefill_payload' => $prefillPayload,
                    'gap_questions_remaining' => $remainingQuestions,
                    'target_details' => $engagement->target_details ?? [],
                ],
                'migrated_by_user_id' => $actor->getKey(),
                'migrated_at' => now(),
            ]);

            $this->audit->record('dd.post_acquisition_created', subject: $migration, actor: $actor, after: [
                'dd_engagement_id' => $engagement->getKey(),
                'advisory_client_id' => $advisoryClient->getKey(),
                'migrated_document_count' => count($migratedDocumentIds),
                'proposal_id' => $proposal->getKey(),
                'dd_pv_baseline' => $migration->dd_pv_baseline,
            ]);

            return $migration->refresh()->load(['advisoryClient', 'gapQuestionnaireResponse.answers', 'proposal.feeCalculation']);
        });
    }

    private function latestDdReport(DdEngagement $engagement): ?Report
    {
        return Report::query()
            ->where('client_id', $engagement->client_id)
            ->where('type', ReportType::DueDiligence)
            ->latest('generated_at')
            ->get()
            ->first(fn (Report $report): bool => (string) data_get($report->metadata, 'dd_engagement_id') === (string) $engagement->getKey());
    }

    private function foundingPlan(DdEngagement $engagement): ?BusinessPlan
    {
        return BusinessPlan::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('status', BusinessPlan::STATUS_FOUNDING)
            ->latest()
            ->first();
    }

    private function createAdvisoryClient(DdEngagement $engagement, User $actor): Client
    {
        $details = $engagement->target_details ?? [];

        return Client::query()->create([
            'engagement_type' => EngagementType::POST_ACQUISITION_ADVISORY,
            'nzbn' => $details['nzbn'] ?? null,
            'legal_name' => $engagement->target_name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'registry_sources' => [
                'source' => 'due_diligence',
                'source_label' => 'Sourced from DD',
                'dd_engagement_id' => $engagement->getKey(),
                'target_details' => $details,
            ],
            'created_by_user_id' => $actor->getKey(),
            'primary_contact_user_id' => $engagement->client->primary_contact_user_id,
            'engagement_type_locked_at' => now(),
        ]);
    }

    private function copyTeam(DdEngagement $engagement, Client $advisoryClient, User $actor): void
    {
        $members = $engagement->client->teamMembers
            ->map(fn (ClientTeamMember $member): array => [
                'user_id' => $member->user_id,
                'role' => $member->role,
            ])
            ->push([
                'user_id' => $actor->getKey(),
                'role' => 'lead_advisor',
            ])
            ->unique('user_id')
            ->values();

        foreach ($members as $member) {
            ClientTeamMember::query()->create([
                'client_id' => $advisoryClient->getKey(),
                'user_id' => $member['user_id'],
                'role' => $member['role'],
                'granted_modules' => [EngagementType::POST_ACQUISITION_ADVISORY->value],
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function migrateDocuments(DdEngagement $engagement, Client $advisoryClient, User $actor): array
    {
        return DdDataRoomItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->with('document')
            ->get()
            ->map(function (DdDataRoomItem $item) use ($engagement, $advisoryClient, $actor): ?string {
                $document = $item->document;

                if (! $document instanceof Document) {
                    return null;
                }

                $payload = is_array($document->scanner_payload) ? $document->scanner_payload : [];
                $migrated = Document::query()->create([
                    'client_id' => $advisoryClient->getKey(),
                    'category' => $document->category,
                    'original_filename' => 'Sourced from DD - '.$document->original_filename,
                    'stored_path' => sprintf(
                        'post-acquisition/%s/%s-%s',
                        $advisoryClient->getKey(),
                        $document->getKey(),
                        $document->original_filename,
                    ),
                    'byte_size' => $document->byte_size,
                    'mime_type' => $document->mime_type,
                    'sha256' => $document->sha256,
                    'uploaded_by_user_id' => $actor->getKey(),
                    'scanner_result' => $document->scanner_result,
                    'scanner_payload' => [
                        ...$payload,
                        'source_label' => 'Sourced from DD',
                        'source_dd_engagement_id' => $engagement->getKey(),
                        'source_document_id' => $document->getKey(),
                        'source_stored_path' => $document->stored_path,
                        'workstream' => $item->workstream,
                    ],
                    'expires_at' => $document->expires_at,
                ]);

                return (string) $migrated->getKey();
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $migratedDocumentIds
     * @return array{0: QuestionnaireResponse, 1: array<int, string>, 2: array<string, mixed>}
     */
    private function createGapQuestionnaireResponse(
        DdEngagement $engagement,
        Client $advisoryClient,
        array $migratedDocumentIds,
        User $actor,
    ): array {
        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::POST_ACQUISITION_GAP)
            ->published()
            ->with('sections.questions')
            ->firstOrFail();
        $questions = $questionnaire->sections
            ->flatMap(fn ($section): Collection => $section->questions)
            ->values();

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $advisoryClient->getKey(),
            'questionnaire_id' => $questionnaire->getKey(),
            'submitted_at' => null,
            'submitted_by_user_id' => null,
        ]);

        $riskSummary = DdRiskRegisterItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->orderBy('rank')
            ->limit(5)
            ->get()
            ->map(fn (DdRiskRegisterItem $risk): string => sprintf('#%d %s - %s', $risk->rank, str_replace('_', ' ', $risk->risk_level), $risk->title))
            ->implode("\n");
        $prefills = [
            'Confirm acquired business details from DD.' => sprintf(
                'Acquired business: %s. NZBN: %s. Industry: %s.',
                $engagement->target_name,
                (string) data_get($engagement->target_details, 'nzbn', 'not supplied'),
                (string) data_get($engagement->target_details, 'industry', 'not supplied'),
            ),
            'Review inherited due diligence risks.' => $riskSummary !== '' ? $riskSummary : 'No ranked DD risks were present at handoff.',
            'Confirm migrated DD document set.' => sprintf('%d DD document(s) migrated with the "Sourced from DD" label.', count($migratedDocumentIds)),
        ];
        $answeredQuestionIds = [];

        foreach ($questions as $question) {
            if (! $question instanceof QuestionnaireQuestion || ! array_key_exists($question->prompt, $prefills)) {
                continue;
            }

            $response->answers()->create([
                'question_id' => $question->getKey(),
                'value' => [
                    'prefilled' => true,
                    'source' => 'due_diligence',
                    'text' => $prefills[$question->prompt],
                ],
                'attached_document_ids' => $migratedDocumentIds,
            ]);
            $answeredQuestionIds[] = (string) $question->getKey();
        }

        return [
            $response->refresh()->load('answers'),
            $questions
                ->reject(fn (QuestionnaireQuestion $question): bool => in_array((string) $question->getKey(), $answeredQuestionIds, true))
                ->pluck('id')
                ->values()
                ->all(),
            $prefills,
        ];
    }

    private function createProposal(DdEngagement $engagement, Client $advisoryClient, Report $report, User $actor): Proposal
    {
        $baseline = $this->ddPvBaseline($engagement);
        $riskCost = round((float) DdRiskRegisterItem::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->sum('pv_of_cost'), 2);
        $mid = round(max(3500.0, ($baseline * 0.0127) + ($riskCost * 0.041)), 2);
        $fee = FeeCalculation::query()->create([
            'client_id' => $advisoryClient->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => [
                'source' => 'due_diligence',
                'dd_engagement_id' => $engagement->getKey(),
                'dd_report_id' => $report->getKey(),
                'dd_pv_baseline' => $baseline,
                'risk_cost_pv_total' => $riskCost,
                'precision_basis' => 'DD valuation midpoint plus PV-ranked risk register.',
            ],
            'suggested_low' => round($mid * 0.92, 2),
            'suggested_mid' => $mid,
            'suggested_high' => round($mid * 1.08, 2),
            'improvement_pv_total' => $baseline,
            'risk_cost_pv_total' => $riskCost,
            'roi_ratio' => $mid > 0 ? round($baseline / $mid, 4) : 0,
            'justification' => [
                'method' => FeeMethod::OutcomeBased->value,
                'basis' => 'Post-acquisition outcome fee generated from DD-derived PV baseline and risk register.',
                'dd_report_id' => $report->getKey(),
                'dd_pv_baseline' => $baseline,
                'unusually_precise' => true,
            ],
            'created_by_user_id' => $actor->getKey(),
        ]);

        return $this->proposals->generate($advisoryClient, $fee, [
            'scope' => [
                'summary' => sprintf(
                    'Post-acquisition advisory proposal for %s using DD-derived PV baseline NZD %s.',
                    $engagement->target_name,
                    number_format($baseline, 0),
                ),
                'included' => [
                    '100-day post-acquisition integration advisory',
                    'Post-acquisition gap questionnaire completion',
                    'DD-derived PV baseline and risk-register review',
                ],
                'excluded' => [
                    'Legal, tax, lending, investment, or accounting advice by FSA',
                ],
            ],
            'services' => [[
                'name' => 'Post-acquisition integration advisory',
                'fee_method' => FeeMethod::OutcomeBased->value,
                'dd_pv_baseline' => $baseline,
                'dd_report_id' => $report->getKey(),
            ]],
        ], [
            'created_by_user_id' => $actor->getKey(),
        ]);
    }

    private function ddPvBaseline(DdEngagement $engagement): float
    {
        $valuation = DdValuation::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->latest('as_at')
            ->latest()
            ->first();
        $mid = data_get($valuation?->normalised_values, 'reconciled.mid');

        return is_numeric($mid) ? round((float) $mid, 2) : 0.0;
    }
}
