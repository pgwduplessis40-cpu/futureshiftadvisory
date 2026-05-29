<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Enums\QuestionnaireSet;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\DdValuation;
use App\Models\DdWorkstream;
use App\Models\Document;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Dd\Workstreams\DdWorkstreamRunner;
use App\Services\Reports\ReportComposer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DdAdviceReportGenerator
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly DdWorkstreamRunner $workstreams,
    ) {}

    public function generateIfReadyForClient(Client $client, ?User $actor = null): ?Report
    {
        $engagement = DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();

        if (! $engagement instanceof DdEngagement) {
            return null;
        }

        return $this->generateIfReady($engagement, $actor);
    }

    public function generateIfReady(DdEngagement $engagement, ?User $actor = null, bool $returnCurrent = false): ?Report
    {
        $engagement->loadMissing('client');

        $response = $this->latestDdResponse($engagement);
        if (! $response instanceof QuestionnaireResponse) {
            return $this->waiting($engagement, $actor, 'dd_questionnaire_not_submitted');
        }

        $this->syncQuestionnaireDocuments($engagement, $response, $actor);

        $latestEvidenceAt = $engagement->dataRoomItems()->latest()->value('created_at');
        if ($latestEvidenceAt === null) {
            return $this->waiting($engagement, $actor, 'dd_data_room_empty');
        }

        $this->runWorkstreamsIfNeeded($engagement, $actor, $response->submitted_at, $latestEvidenceAt);
        if (! $this->workstreamsComplete($engagement)) {
            return $this->waiting($engagement, $actor, 'dd_workstreams_incomplete');
        }

        $latestWorkstreamAt = $engagement->workstreams()->latest('ran_at')->value('ran_at');

        $valuation = $engagement->valuations()->latest('as_at')->latest()->first();
        if (! $valuation instanceof DdValuation) {
            $valuation = $this->calculateValuationIfPossible($engagement, $actor);
        }

        if (! $valuation instanceof DdValuation) {
            return $this->waiting($engagement, $actor, 'dd_valuation_inputs_missing');
        }

        $currentReport = $this->latestCurrentReport($engagement, $response->submitted_at, $latestEvidenceAt, $valuation->as_at, $latestWorkstreamAt);
        if ($currentReport instanceof Report) {
            return $returnCurrent ? $currentReport : null;
        }

        return app(ReportComposer::class)->composeDueDiligence($engagement, $actor);
    }

    private function latestDdResponse(DdEngagement $engagement): ?QuestionnaireResponse
    {
        return QuestionnaireResponse::query()
            ->where('client_id', $engagement->client_id)
            ->whereNotNull('submitted_at')
            ->whereHas('questionnaire', fn ($query) => $query->forSet(QuestionnaireSet::DUE_DILIGENCE))
            ->latest('submitted_at')
            ->first();
    }

    private function syncQuestionnaireDocuments(DdEngagement $engagement, QuestionnaireResponse $response, ?User $actor): void
    {
        $response->loadMissing('answers.question.section');

        /** @var QuestionnaireAnswer $answer */
        foreach ($response->answers as $answer) {
            $documentIds = $answer->attached_document_ids ?? [];
            if ($documentIds === []) {
                continue;
            }

            foreach ($documentIds as $documentId) {
                $document = Document::query()
                    ->whereKey((string) $documentId)
                    ->where('client_id', $engagement->client_id)
                    ->first();

                if (! $document instanceof Document) {
                    continue;
                }

                DdDataRoomItem::query()->updateOrCreate(
                    [
                        'dd_engagement_id' => $engagement->getKey(),
                        'document_id' => $document->getKey(),
                    ],
                    [
                        'client_id' => $engagement->client_id,
                        'workstream' => $this->workstreamForAnswer($answer),
                        'folder' => $this->folderForAnswer($answer),
                        'artifact_type' => DdDataRoomItem::ARTIFACT_TYPE,
                        'source' => DdDataRoomItem::SOURCE_CLIENT_UPLOAD,
                        'dd_guest_link_id' => null,
                        'guest_name' => null,
                        'guest_email' => null,
                        'metadata' => [
                            'source' => 'dd_questionnaire_attachment',
                            'questionnaire_response_id' => $response->getKey(),
                            'questionnaire_answer_id' => $answer->getKey(),
                            'questionnaire_question_id' => $answer->question_id,
                            'question_prompt' => $answer->question?->prompt,
                            'synced_by_user_id' => $actor?->getKey(),
                        ],
                    ],
                );
            }
        }
    }

    private function runWorkstreamsIfNeeded(DdEngagement $engagement, ?User $actor, ?CarbonInterface $questionnaireSubmittedAt, mixed $latestEvidenceAt): void
    {
        $latestInputAt = $this->latestCarbon([$questionnaireSubmittedAt, $latestEvidenceAt]);
        $latestRunAt = $this->latestCarbon([$engagement->workstreams()->latest('ran_at')->value('ran_at')]);
        $completedCount = $engagement->workstreams()
            ->where('status', DdWorkstream::STATUS_COMPLETED)
            ->count();

        if (
            $completedCount >= count(DataRoom::WORKSTREAMS)
            && $latestRunAt instanceof Carbon
            && $latestInputAt instanceof Carbon
            && $latestRunAt->greaterThanOrEqualTo($latestInputAt)
        ) {
            return;
        }

        $this->workstreams->runAll($engagement, $actor);
    }

    private function workstreamsComplete(DdEngagement $engagement): bool
    {
        return $engagement->workstreams()
            ->where('status', DdWorkstream::STATUS_COMPLETED)
            ->count() >= count(DataRoom::WORKSTREAMS);
    }

    private function calculateValuationIfPossible(DdEngagement $engagement, ?User $actor): ?DdValuation
    {
        $targetDetails = $engagement->target_details ?? [];
        $financials = is_array($targetDetails['valuation_financials'] ?? null)
            ? $targetDetails['valuation_financials']
            : [];

        if ($financials === []) {
            return null;
        }

        try {
            return app(Valuation::class)->calculate($engagement, $actor);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function latestCurrentReport(
        DdEngagement $engagement,
        ?CarbonInterface $questionnaireSubmittedAt,
        mixed $latestEvidenceAt,
        ?CarbonInterface $valuationAt,
        mixed $latestWorkstreamAt,
    ): ?Report {
        $latestInputAt = $this->latestCarbon([$questionnaireSubmittedAt, $latestEvidenceAt, $valuationAt, $latestWorkstreamAt]);

        if (! $latestInputAt instanceof Carbon) {
            return null;
        }

        $report = Report::query()
            ->where('client_id', $engagement->client_id)
            ->where('type', ReportType::DueDiligence)
            ->latest('generated_at')
            ->get()
            ->first(fn (Report $report): bool => (string) data_get($report->metadata, 'dd_engagement_id') === (string) $engagement->getKey());

        if (! $report instanceof Report || ! $report->generated_at instanceof CarbonInterface) {
            return null;
        }

        $hasPurchaseRange = $report->sections()->where('key', 'dd_purchase_price_range')->exists();

        $generatedAt = Carbon::parse($report->generated_at)->startOfSecond();
        $inputAt = $latestInputAt->copy()->startOfSecond();

        return $hasPurchaseRange && $generatedAt->greaterThanOrEqualTo($inputAt)
            ? $report
            : null;
    }

    /**
     * @param  array<int, mixed>  $dates
     */
    private function latestCarbon(array $dates): ?Carbon
    {
        $latest = null;
        foreach ($dates as $date) {
            if ($date === null) {
                continue;
            }

            $candidate = $date instanceof Carbon ? $date : Carbon::parse($date);
            if (! $latest instanceof Carbon || $candidate->greaterThan($latest)) {
                $latest = $candidate;
            }
        }

        return $latest;
    }

    private function workstreamForAnswer(QuestionnaireAnswer $answer): string
    {
        $text = Str::of(implode(' ', [
            $answer->question?->section?->title,
            $answer->question?->prompt,
            is_scalar($answer->value) ? (string) $answer->value : json_encode($answer->value),
        ]))->lower()->toString();

        $matches = [
            'tax' => ['tax', 'gst', 'ird'],
            'valuation' => ['valuation', 'purchase price', 'enterprise value', 'dcf', 'multiple', 'price'],
            'financial' => ['financial', 'revenue', 'ebitda', 'cash flow', 'working capital', 'debt', 'margin'],
            'legal' => ['legal', 'contract', 'lease', 'ownership', 'ip', 'litigation', 'licence', 'license'],
            'commercial_market' => ['customer', 'market', 'competitor', 'sales', 'pipeline', 'supplier'],
            'operational' => ['operation', 'system', 'process', 'inventory', 'technology', 'data privacy', 'it '],
            'hr_people' => ['employee', 'staff', 'people', 'hr', 'contractor'],
            'nz_regulatory' => ['regulatory', 'compliance', 'worksafe', 'privacy', 'nzbn', 'consent'],
        ];

        foreach ($matches as $workstream => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $workstream;
                }
            }
        }

        return 'financial';
    }

    private function folderForAnswer(QuestionnaireAnswer $answer): string
    {
        $section = (string) ($answer->question?->section?->title ?? 'questionnaire');
        $folder = Str::slug($section, '_');

        return $folder === '' ? 'questionnaire' : Str::limit($folder, 160, '');
    }

    private function waiting(DdEngagement $engagement, ?User $actor, string $reason): ?Report
    {
        $this->audit->record('dd.advice_report_waiting', subject: $engagement, actor: $actor, after: [
            'dd_engagement_id' => $engagement->getKey(),
            'reason' => $reason,
        ]);

        return null;
    }
}
