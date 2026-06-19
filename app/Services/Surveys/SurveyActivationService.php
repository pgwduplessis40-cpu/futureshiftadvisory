<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Enums\ReportType;
use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyStatus;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\Report;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SurveyActivationService
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function activateForClient(Client $client, Survey $survey, User $actor, ?CarbonInterface $dueAt = null): SurveyAssignment
    {
        $this->ensurePublished($survey);
        $snapshot = $this->clientDeliverables($client);

        return $this->createAssignment($survey, $actor, $snapshot, $dueAt, [
            'client_id' => $client->getKey(),
            'entrepreneur_profile_id' => null,
        ]);
    }

    public function activateForEntrepreneur(EntrepreneurProfile $profile, Survey $survey, User $actor, ?CarbonInterface $dueAt = null): SurveyAssignment
    {
        $this->ensurePublished($survey);
        $snapshot = $this->entrepreneurDeliverables($profile);

        return $this->createAssignment($survey, $actor, $snapshot, $dueAt, [
            'client_id' => null,
            'entrepreneur_profile_id' => $profile->getKey(),
        ]);
    }

    public function cancel(SurveyAssignment $assignment, User $actor): SurveyAssignment
    {
        return DB::transaction(function () use ($actor, $assignment): SurveyAssignment {
            /** @var SurveyAssignment $locked */
            $locked = SurveyAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages([
                    'assignment' => 'Only pending or in-progress surveys can be cancelled.',
                ]);
            }

            $this->context->withSystemContext(function () use ($locked): void {
                $locked->forceFill([
                    'status' => SurveyAssignmentStatus::Cancelled->value,
                ])->save();
            });

            $this->audit->record('survey_assignment.cancelled', subject: $locked, actor: $actor, after: [
                'survey_assignment_id' => $locked->getKey(),
                'survey_id' => $locked->survey_id,
                'client_id' => $locked->client_id,
                'entrepreneur_profile_id' => $locked->entrepreneur_profile_id,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     * @param  array{client_id:string|null,entrepreneur_profile_id:string|null}  $subject
     */
    private function createAssignment(Survey $survey, User $actor, array $snapshot, ?CarbonInterface $dueAt, array $subject): SurveyAssignment
    {
        return DB::transaction(function () use ($actor, $dueAt, $snapshot, $subject, $survey): SurveyAssignment {
            $assignment = SurveyAssignment::query()->create([
                'survey_id' => $survey->getKey(),
                'client_id' => $subject['client_id'],
                'entrepreneur_profile_id' => $subject['entrepreneur_profile_id'],
                'status' => SurveyAssignmentStatus::Pending->value,
                'activated_by_user_id' => $actor->getKey(),
                'activated_at' => now(),
                'due_at' => $dueAt,
                'deliverable_snapshot' => $snapshot,
            ]);

            $this->audit->record('survey_assignment.activated', subject: $assignment, actor: $actor, after: [
                'survey_assignment_id' => $assignment->getKey(),
                'survey_id' => $survey->getKey(),
                'client_id' => $subject['client_id'],
                'entrepreneur_profile_id' => $subject['entrepreneur_profile_id'],
                'deliverable_count' => count($snapshot),
                'due_at' => $dueAt?->toIso8601String(),
            ]);

            return $assignment;
        });
    }

    private function ensurePublished(Survey $survey): void
    {
        if ($survey->status !== SurveyStatus::Published) {
            throw ValidationException::withMessages([
                'survey_id' => 'Only published surveys can be activated.',
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clientDeliverables(Client $client): array
    {
        $reports = Report::query()
            ->where('client_id', $client->getKey())
            ->whereIn('review_status', ['not_required', 'reviewed'])
            ->latest('generated_at')
            ->limit(10)
            ->get()
            ->map(fn (Report $report): array => [
                'source_type' => 'report',
                'source_id' => (string) $report->getKey(),
                'title' => $report->title,
                'label' => $report->type instanceof ReportType ? $report->type->label() : (string) $report->type,
                'delivered_at' => $report->generated_at?->toIso8601String(),
            ]);

        $documents = Document::query()
            ->visibleToClients()
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Document $document): array => [
                'source_type' => 'document',
                'source_id' => (string) $document->getKey(),
                'title' => $document->original_filename,
                'label' => str((string) $document->category)->replace('_', ' ')->title()->toString(),
                'delivered_at' => $document->created_at?->toIso8601String(),
            ]);

        return $reports
            ->concat($documents)
            ->sortByDesc('delivered_at')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entrepreneurDeliverables(EntrepreneurProfile $profile): array
    {
        $reports = Report::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('type', ReportType::EntrepreneurAssessment->value)
            ->latest('generated_at')
            ->limit(10)
            ->get()
            ->map(fn (Report $report): array => [
                'source_type' => 'report',
                'source_id' => (string) $report->getKey(),
                'title' => $report->title,
                'label' => $report->type instanceof ReportType ? $report->type->label() : 'Entrepreneur assessment report',
                'delivered_at' => $report->generated_at?->toIso8601String(),
            ]);

        $documents = Document::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('scanner_result', Document::SCANNER_CLEAN)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Document $document): array => [
                'source_type' => 'document',
                'source_id' => (string) $document->getKey(),
                'title' => $document->original_filename,
                'label' => str((string) $document->category)->replace('_', ' ')->title()->toString(),
                'delivered_at' => $document->created_at?->toIso8601String(),
            ]);

        $assessments = PlanAssessment::query()
            ->whereNotNull('finalised_at')
            ->whereHas('businessPlan', fn ($query) => $query->where('entrepreneur_profile_id', $profile->getKey()))
            ->with('businessPlan')
            ->latest('finalised_at')
            ->limit(10)
            ->get()
            ->map(fn (PlanAssessment $assessment): array => [
                'source_type' => 'plan_assessment',
                'source_id' => (string) $assessment->getKey(),
                'title' => sprintf('%s assessment round %s', $assessment->businessPlan instanceof BusinessPlan ? $assessment->businessPlan->title : 'Business plan', $assessment->round),
                'label' => $assessment->overall_grade,
                'delivered_at' => $assessment->finalised_at?->toIso8601String(),
            ]);

        return $reports
            ->concat($documents)
            ->concat($assessments)
            ->sortByDesc('delivered_at')
            ->values()
            ->all();
    }
}
