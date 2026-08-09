<?php

declare(strict_types=1);

namespace App\Services\Surveys;

use App\Enums\ReportType;
use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyStatus;
use App\Enums\SurveyType;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\PlanAssessment;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
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
        $this->ensureType($survey, SurveyType::GeneralExperience);
        $snapshot = $this->clientDeliverables($client);

        return $this->createAssignment($survey, $actor, $snapshot, $dueAt, [
            'client_id' => $client->getKey(),
            'entrepreneur_profile_id' => null,
        ]);
    }

    public function activateForEntrepreneur(EntrepreneurProfile $profile, Survey $survey, User $actor, ?CarbonInterface $dueAt = null): SurveyAssignment
    {
        $this->ensurePublished($survey);
        $this->ensureType($survey, SurveyType::GeneralExperience);
        $snapshot = $this->entrepreneurDeliverables($profile);

        return $this->createAssignment($survey, $actor, $snapshot, $dueAt, [
            'client_id' => null,
            'entrepreneur_profile_id' => $profile->getKey(),
        ]);
    }

    public function activateForEntrepreneurService(
        EntrepreneurProfile $profile,
        Survey $survey,
        User $actor,
        ?CarbonInterface $dueAt = null,
        bool $replaceOpen = false,
    ): SurveyAssignment {
        $this->ensurePublished($survey);
        $this->ensureType($survey, SurveyType::ServiceImprovement);

        $serviceSnapshot = $this->completedEntrepreneurServiceSnapshot($profile);

        if ($serviceSnapshot === null) {
            throw ValidationException::withMessages([
                'entrepreneur_profile' => 'A service survey can be issued after an Idea Validation gate is approved or once this entrepreneur is advisory ready or launched.',
            ]);
        }

        $subject = [
            'client_id' => null,
            'entrepreneur_profile_id' => $profile->getKey(),
            'service_activation_id' => null,
            'service_snapshot' => $serviceSnapshot,
        ];

        return DB::transaction(function () use ($actor, $dueAt, $profile, $replaceOpen, $subject, $survey): SurveyAssignment {
            $openAssignments = SurveyAssignment::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->whereNull('service_activation_id')
                ->whereNotNull('service_snapshot')
                ->whereIn('status', SurveyAssignmentStatus::activeValues())
                ->whereHas('survey', fn ($query) => $query->where('type', SurveyType::ServiceImprovement->value))
                ->lockForUpdate()
                ->get();

            if ($openAssignments->isNotEmpty() && ! $replaceOpen) {
                throw ValidationException::withMessages([
                    'entrepreneur_profile' => 'This entrepreneur already has an open service survey.',
                ]);
            }

            $this->replaceOpenAssignments($openAssignments, $actor, [
                'replacement_survey_id' => $survey->getKey(),
                'replacement_reason' => 'latest_entrepreneur_service_survey_reissued',
            ]);

            return $this->createAssignmentRecord($survey, $actor, [], $dueAt, $subject);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function completedEntrepreneurServiceSnapshot(EntrepreneurProfile $profile): ?array
    {
        $packageLabel = ServiceRatePackage::packageScopeLabel(
            ServiceRatePackage::normaliseEntrepreneurScope(
                (string) ($profile->intended_package_scope ?? ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO),
            ),
        );

        $ideaValidation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNotNull('advisor_gate_passed_at')
            ->latest('advisor_gate_passed_at')
            ->first();

        if ($ideaValidation instanceof IdeaValidation) {
            return [
                'source' => 'idea_validation',
                'idea_validation_id' => (string) $ideaValidation->getKey(),
                'service_label' => 'Idea Validation',
                'package_label' => $packageLabel,
                'completed_at' => $ideaValidation->advisor_gate_passed_at?->toIso8601String(),
            ];
        }

        if (! in_array($profile->currentStage()->value, ['advisory_ready', 'launched'], true)) {
            return null;
        }

        return [
            'source' => 'entrepreneur_profile',
            'service_label' => 'Entrepreneur advisory service',
            'package_label' => $packageLabel,
            'completed_at' => $profile->updated_at?->toIso8601String(),
        ];
    }

    public function activateForService(
        ServiceActivation $serviceActivation,
        Survey $survey,
        User $actor,
        ?CarbonInterface $dueAt = null,
        bool $replaceOpen = false,
    ): SurveyAssignment {
        $this->ensurePublished($survey);
        $this->ensureType($survey, SurveyType::ServiceImprovement);

        if ($serviceActivation->status !== ServiceActivation::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'service_activation' => 'A service survey can only be issued after the service is closed.',
            ]);
        }

        $serviceActivation->loadMissing(['client', 'package']);
        $client = $serviceActivation->client;

        if (! $client instanceof Client) {
            throw ValidationException::withMessages([
                'service_activation' => 'The closed service must have a client before a survey can be issued.',
            ]);
        }

        $packageSnapshot = is_array($serviceActivation->selected_package_snapshot)
            ? $serviceActivation->selected_package_snapshot
            : [];
        $packageLabel = data_get($packageSnapshot, 'client_label')
            ?? data_get($packageSnapshot, 'package_name')
            ?? $serviceActivation->package?->package_name;

        $subject = [
            'client_id' => $client->getKey(),
            'entrepreneur_profile_id' => null,
            'service_activation_id' => $serviceActivation->getKey(),
            'service_snapshot' => [
                'service_activation_id' => (string) $serviceActivation->getKey(),
                'service_type' => $serviceActivation->service_type,
                'service_label' => $serviceActivation->clientLabel(),
                'package_label' => is_string($packageLabel) ? $packageLabel : null,
                'closed_at' => $serviceActivation->closed_at?->toIso8601String(),
            ],
        ];

        return DB::transaction(function () use ($actor, $dueAt, $replaceOpen, $serviceActivation, $subject, $survey): SurveyAssignment {
            $openAssignments = SurveyAssignment::query()
                ->where('service_activation_id', $serviceActivation->getKey())
                ->whereIn('status', SurveyAssignmentStatus::activeValues())
                ->lockForUpdate()
                ->get();

            if ($openAssignments->isNotEmpty() && ! $replaceOpen) {
                throw ValidationException::withMessages([
                    'service_activation' => 'This service already has an open survey.',
                ]);
            }

            $this->replaceOpenAssignments($openAssignments, $actor, [
                'replacement_survey_id' => $survey->getKey(),
                'replacement_reason' => 'latest_service_survey_reissued',
            ]);

            return $this->createAssignmentRecord($survey, $actor, [], $dueAt, $subject);
        });
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
     * @param  array{client_id:string|null,entrepreneur_profile_id:string|null,service_activation_id?:string|null,service_snapshot?:array<string, mixed>|null}  $subject
     */
    private function createAssignment(Survey $survey, User $actor, array $snapshot, ?CarbonInterface $dueAt, array $subject): SurveyAssignment
    {
        return DB::transaction(function () use ($actor, $dueAt, $snapshot, $subject, $survey): SurveyAssignment {
            return $this->createAssignmentRecord($survey, $actor, $snapshot, $dueAt, $subject);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     * @param  array{client_id:string|null,entrepreneur_profile_id:string|null,service_activation_id?:string|null,service_snapshot?:array<string, mixed>|null}  $subject
     */
    private function createAssignmentRecord(Survey $survey, User $actor, array $snapshot, ?CarbonInterface $dueAt, array $subject): SurveyAssignment
    {
        $assignment = SurveyAssignment::query()->create([
            'survey_id' => $survey->getKey(),
            'client_id' => $subject['client_id'],
            'entrepreneur_profile_id' => $subject['entrepreneur_profile_id'],
            'status' => SurveyAssignmentStatus::Pending->value,
            'activated_by_user_id' => $actor->getKey(),
            'activated_at' => now(),
            'due_at' => $dueAt,
            'deliverable_snapshot' => $snapshot,
            'service_activation_id' => $subject['service_activation_id'] ?? null,
            'service_snapshot' => $subject['service_snapshot'] ?? null,
        ]);

        $this->audit->record('survey_assignment.activated', subject: $assignment, actor: $actor, after: [
            'survey_assignment_id' => $assignment->getKey(),
            'survey_id' => $survey->getKey(),
            'client_id' => $subject['client_id'],
            'entrepreneur_profile_id' => $subject['entrepreneur_profile_id'],
            'deliverable_count' => count($snapshot),
            'service_activation_id' => $subject['service_activation_id'] ?? null,
            'due_at' => $dueAt?->toIso8601String(),
        ]);

        return $assignment;
    }

    /**
     * @param  iterable<int, SurveyAssignment>  $assignments
     * @param  array<string, mixed>  $after
     */
    private function replaceOpenAssignments(iterable $assignments, User $actor, array $after): void
    {
        foreach ($assignments as $assignment) {
            if (! $assignment->isActive()) {
                continue;
            }

            $this->context->withSystemContext(function () use ($assignment): void {
                $assignment->forceFill([
                    'status' => SurveyAssignmentStatus::Cancelled->value,
                ])->save();
            });

            $this->audit->record('survey_assignment.replaced', subject: $assignment, actor: $actor, after: [
                ...$after,
                'survey_assignment_id' => $assignment->getKey(),
                'survey_id' => $assignment->survey_id,
                'client_id' => $assignment->client_id,
                'entrepreneur_profile_id' => $assignment->entrepreneur_profile_id,
                'service_activation_id' => $assignment->service_activation_id,
            ]);
        }
    }

    private function ensurePublished(Survey $survey): void
    {
        if ($survey->status !== SurveyStatus::Published) {
            throw ValidationException::withMessages([
                'survey_id' => 'Only published surveys can be activated.',
            ]);
        }
    }

    private function ensureType(Survey $survey, SurveyType $type): void
    {
        if ($survey->type !== $type) {
            throw ValidationException::withMessages([
                'survey_id' => sprintf('This action requires a %s survey.', $type->label()),
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
