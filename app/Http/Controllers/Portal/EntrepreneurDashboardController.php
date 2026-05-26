<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurDashboardController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);

        $profile = EntrepreneurProfile::query()
            ->with([
                'assignedAdvisor',
                'businessPlans.assessments.ratingFramework.criteria',
                'advisoryReadinessSignals.planAssessment.ratingFramework.criteria',
            ])
            ->where('user_id', $user->getKey())
            ->first();
        $latestPlan = $profile?->businessPlans
            ->sortByDesc('updated_at')
            ->first();
        $latestAssessment = $latestPlan?->assessments
            ->sortByDesc('round')
            ->first();
        $latestSignal = $profile?->advisoryReadinessSignals
            ->sortByDesc('surfaced_at')
            ->first();
        $latestAssessmentPayload = $latestAssessment ? $this->assessmentPayload($latestAssessment) : null;

        return Inertia::render('portal/entrepreneur/Dashboard', [
            'profile' => $profile ? [
                'id' => $profile->id,
                'name' => $profile->name,
                'email' => $profile->email,
                'stage' => $profile->stage instanceof EntrepreneurStage
                    ? $profile->stage->value
                    : (string) $profile->stage,
                'stage_label' => $profile->stage instanceof EntrepreneurStage
                    ? $profile->stage->label()
                    : EntrepreneurStage::from((string) $profile->stage)->label(),
                'concept_summary' => $profile->concept_summary,
                'assigned_advisor' => $profile->assignedAdvisor ? [
                    'id' => $profile->assignedAdvisor->id,
                    'name' => $profile->assignedAdvisor->name,
                    'email' => $profile->assignedAdvisor->email,
                ] : null,
                'latest_plan' => $latestPlan instanceof BusinessPlan ? [
                    'id' => $latestPlan->id,
                    'status' => $latestPlan->status,
                    'assessment_count' => $latestPlan->assessments->count(),
                    'completed_assessment_count' => $latestPlan->assessments
                        ->whereNotNull('finalised_at')
                        ->count(),
                    'latest_grade' => $latestPlan->assessments->sortByDesc('round')->first()?->overall_grade,
                    'latest_assessment' => $latestAssessmentPayload ? [
                        'id' => $latestAssessmentPayload['id'],
                        'round' => $latestAssessmentPayload['round'],
                        'status' => $latestAssessmentPayload['status'],
                        'overall_grade' => $latestAssessmentPayload['overall_grade'],
                        'weighted_score' => $latestAssessmentPayload['weighted_score'],
                        'url' => route('portal.entrepreneur.assessments.show', $latestAssessment, absolute: false),
                    ] : null,
                    'living_plan_next_update_at' => $latestPlan->living_plan_next_update_at?->toIso8601String(),
                    'living_plan_divergence_flags' => $latestPlan->living_plan_divergence_flags,
                ] : null,
                'advisory_readiness_signal' => $latestSignal instanceof AdvisoryReadinessSignal
                    ? $this->advisoryReadinessSignalPayload($latestSignal)
                    : null,
                'latest_documents' => $this->latestDocuments($profile),
                'message_summary' => $this->messageSummary($profile, $user),
            ] : null,
            'messagesUrl' => route('portal.messages.index', absolute: false),
            'documentUploadUrl' => route('portal.documents.store', absolute: false),
            'notificationsUrl' => route('notifications.index', absolute: false),
            'settingsUrl' => route('profile.edit', absolute: false),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestDocuments(EntrepreneurProfile $profile): array
    {
        return Document::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'scanner_result' => $document->scanner_result,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'url' => route('portal.documents.show', $document, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageSummary(EntrepreneurProfile $profile, User $user): array
    {
        $threadIds = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->pluck('id');

        $participantRows = MessageThreadParticipant::query()
            ->whereIn('thread_id', $threadIds)
            ->where('user_id', $user->getKey())
            ->get(['thread_id', 'last_read_at']);

        $unread = $participantRows->sum(function (MessageThreadParticipant $participant) use ($user): int {
            $query = Message::query()
                ->where('thread_id', $participant->thread_id)
                ->where('sender_user_id', '!=', $user->getKey());

            if ($participant->last_read_at !== null) {
                $query->where('sent_at', '>', $participant->last_read_at);
            }

            return $query->count();
        });

        return [
            'threads_count' => $threadIds->count(),
            'unread_count' => (int) $unread,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function advisoryReadinessSignalPayload(AdvisoryReadinessSignal $signal): array
    {
        $assessment = $signal->planAssessment;
        $assessmentPayload = $assessment ? $this->assessmentPayload($assessment) : null;

        return [
            'score' => $signal->score,
            'surfaced_at' => $signal->surfaced_at?->toIso8601String(),
            'threshold' => $assessmentPayload['threshold'] ?? null,
            'grade' => $assessmentPayload['overall_grade'] ?? null,
            'explanation' => $assessmentPayload['explanation'] ?? 'This score reflects the current evidence-backed advisory readiness signal.',
            'assessment_url' => $assessment
                ? route('portal.entrepreneur.assessments.show', $assessment, absolute: false)
                : null,
            'criteria' => $assessmentPayload['criteria'] ?? [],
        ];
    }
}
