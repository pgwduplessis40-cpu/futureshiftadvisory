<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\ClientLeavePeriod;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\Meeting;
use App\Models\MessageThread;
use App\Models\Milestone;
use App\Models\MilestoneAction;
use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\PlanAssessment;
use App\Models\Proposal;
use App\Models\ReadinessAssessment;
use App\Models\Referral;
use App\Models\ReferralMessage;
use App\Models\Report;
use App\Models\ReverseReferral;
use App\Models\StrategicPlanMilestone;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Calendar\ClientAvailabilityCalendar;
use App\Services\Calendar\PublicHolidayCalendar;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Portal\ClientPortalResolver;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class CalendarController extends Controller
{
    public function __construct(
        private readonly PublicHolidayCalendar $publicHolidays,
        private readonly ClientAvailabilityCalendar $availability,
    ) {}

    public function __invoke(
        Request $request,
        ClientPortalResolver $clients,
        EntrepreneurInviteReconciler $entrepreneurInvites,
    ): Response|RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (in_array($user->user_type, [User::TYPE_SUPER_ADMIN, User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true)) {
            return to_route('advisor.calendar.index');
        }

        [$title, $subtitle, $events, $extra] = match ($user->user_type) {
            User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM, User::TYPE_NPO_BOARD_MEMBER => $this->clientCalendar($clients->resolveFor($request)),
            User::TYPE_ENTREPRENEUR => $this->entrepreneurCalendar($user, $entrepreneurInvites),
            User::TYPE_ENTREPRENEUR_MENTOR => $this->mentorCalendar($user),
            User::TYPE_BROKER => $this->panelCalendar($user, PanelMember::TYPE_BROKER),
            User::TYPE_COACH => $this->panelCalendar($user, PanelMember::TYPE_COACH),
            default => $this->emptyCalendar('Calendar', 'Dated activity for this portal will appear here.'),
        };

        return Inertia::render('calendar/Index', [
            'title' => $title,
            'subtitle' => $subtitle,
            'events' => $this->sortedEvents($events),
            'emptyState' => 'No dated activity is available for this portal yet.',
            'leavePeriods' => [],
            'leaveStoreUrl' => null,
            'canManageLeavePeriods' => false,
            ...$extra,
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, array<string, mixed>>, 3: array<string, mixed>}
     */
    private function clientCalendar(Client $client): array
    {
        $events = [];
        $rangeStart = now()->subMonths(3);
        $rangeEnd = now()->addMonths(9);

        Meeting::query()
            ->where('client_id', $client->getKey())
            ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
            ->orderBy('scheduled_at')
            ->limit(120)
            ->get()
            ->each(function (Meeting $meeting) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "meeting:{$meeting->id}",
                    title: $meeting->title,
                    startsAt: $meeting->scheduled_at,
                    kind: 'meeting',
                    status: $meeting->status ?? Meeting::STATUS_SCHEDULED,
                    description: $meeting->location,
                    href: $meeting->link,
                ));
            });

        Milestone::query()
            ->with('goal')
            ->where('client_id', $client->getKey())
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $rangeStart->toDateString())
            ->whereDate('due_date', '<=', $rangeEnd->toDateString())
            ->orderBy('due_date')
            ->limit(80)
            ->get()
            ->each(function (Milestone $milestone) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "goal-milestone:{$milestone->id}",
                    title: "Goal milestone: {$milestone->title}",
                    startsAt: $milestone->due_date,
                    kind: 'deadline',
                    status: $this->label((string) $milestone->status),
                    description: $milestone->goal?->title,
                    href: route('portal.dashboard', absolute: false).'#section-goals',
                    allDay: true,
                ));
            });

        MilestoneAction::query()
            ->with('milestone')
            ->where('client_id', $client->getKey())
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $rangeStart->toDateString())
            ->whereDate('due_date', '<=', $rangeEnd->toDateString())
            ->orderBy('due_date')
            ->limit(120)
            ->get()
            ->each(function (MilestoneAction $action) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "milestone-action:{$action->id}",
                    title: "Action: {$action->title}",
                    startsAt: $action->due_date,
                    kind: 'deadline',
                    status: $this->label((string) $action->status),
                    description: $action->milestone?->title,
                    href: route('portal.dashboard', absolute: false).'#section-goals',
                    allDay: true,
                ));
            });

        StrategicPlanMilestone::query()
            ->where('client_id', $client->getKey())
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $rangeStart->toDateString())
            ->whereDate('due_date', '<=', $rangeEnd->toDateString())
            ->orderBy('due_date')
            ->limit(80)
            ->get()
            ->each(function (StrategicPlanMilestone $milestone) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "strategic-plan-milestone:{$milestone->id}",
                    title: "Strategic milestone: {$milestone->title}",
                    startsAt: $milestone->due_date,
                    kind: 'deadline',
                    status: $this->label((string) $milestone->status),
                    description: $milestone->description,
                    href: route('portal.dashboard', absolute: false).'#section-strategic-plan-milestones',
                    allDay: true,
                ));
            });

        Document::query()
            ->visibleToClients()
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(30)
            ->get()
            ->each(function (Document $document) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "document:{$document->id}",
                    title: $document->original_filename,
                    startsAt: $document->created_at,
                    kind: 'document',
                    status: $this->label((string) $document->category),
                    description: 'Document uploaded',
                    href: route('portal.documents.show', $document, absolute: false),
                ));
            });

        Report::query()
            ->where('client_id', $client->getKey())
            ->whereIn('review_status', ['not_required', 'reviewed'])
            ->whereNotNull('generated_at')
            ->latest('generated_at')
            ->limit(30)
            ->get()
            ->each(function (Report $report) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "report:{$report->id}",
                    title: $report->title,
                    startsAt: $report->generated_at,
                    kind: 'report',
                    status: $this->label((string) $report->review_status),
                    description: $this->label((string) $report->type->value),
                    href: route('portal.reports.show', $report, absolute: false),
                ));
            });

        Proposal::query()
            ->where('client_id', $client->getKey())
            ->whereIn('status', [
                ProposalStatus::Released->value,
                ProposalStatus::AwaitingSignature->value,
                ProposalStatus::Signed->value,
            ])
            ->latest()
            ->limit(20)
            ->get()
            ->each(function (Proposal $proposal) use (&$events): void {
                $status = $proposal->status instanceof ProposalStatus ? $proposal->status->value : (string) $proposal->status;
                $href = route('portal.proposals.signoff.show', $proposal, absolute: false);

                $this->addEvent($events, $this->event(
                    id: "proposal-released:{$proposal->id}",
                    title: "Proposal {$proposal->version} released",
                    startsAt: $proposal->released_at,
                    kind: 'proposal',
                    status: $this->label($status),
                    description: 'Proposal available for review',
                    href: $href,
                ));
                $this->addEvent($events, $this->event(
                    id: "proposal-signed:{$proposal->id}",
                    title: "Proposal {$proposal->version} signed",
                    startsAt: $proposal->signed_at,
                    kind: 'proposal',
                    status: 'Signed',
                    description: 'Proposal sign-off completed',
                    href: $href,
                ));
                $this->addEvent($events, $this->event(
                    id: "proposal-expires:{$proposal->id}",
                    title: "Proposal {$proposal->version} expires",
                    startsAt: $proposal->expires_at,
                    kind: 'deadline',
                    status: 'Due',
                    description: 'Proposal expiry date',
                    href: $href,
                    allDay: true,
                ));
            });

        WellbeingCheckin::query()
            ->where('client_id', $client->getKey())
            ->latest('submitted_at')
            ->limit(12)
            ->get()
            ->each(function (WellbeingCheckin $checkin) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "wellbeing:{$checkin->id}",
                    title: 'Wellbeing check-in submitted',
                    startsAt: $checkin->submitted_at ?? $checkin->period_start,
                    kind: 'wellbeing',
                    status: 'Submitted',
                    description: $checkin->period_start?->format('F Y'),
                    href: route('portal.wellbeing.show', absolute: false),
                ));
            });

        $currentWellbeing = WellbeingCheckin::query()
            ->where('client_id', $client->getKey())
            ->whereDate('period_start', now()->startOfMonth()->toDateString())
            ->exists();
        if (! $currentWellbeing) {
            $this->addEvent($events, $this->event(
                id: 'wellbeing-due:'.now()->format('Y-m'),
                title: 'Wellbeing check-in due',
                startsAt: now()->startOfMonth(),
                kind: 'deadline',
                status: 'Due',
                description: 'Monthly wellbeing pulse',
                href: route('portal.wellbeing.show', absolute: false),
                allDay: true,
            ));
        }

        ClientFunderRecord::query()
            ->with('funder')
            ->where('client_id', $client->getKey())
            ->whereNotNull('reporting_deadline')
            ->where('reporting_deadline', '>=', now()->subMonths(1)->toDateString())
            ->where('reporting_deadline', '<=', now()->addMonths(12)->toDateString())
            ->orderBy('reporting_deadline')
            ->limit(40)
            ->get()
            ->each(function (ClientFunderRecord $record) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "funder-report:{$record->id}",
                    title: $record->grant_name ? "Funder report: {$record->grant_name}" : 'Funder report due',
                    startsAt: $record->reporting_deadline,
                    kind: 'deadline',
                    status: 'Due',
                    description: $record->funder?->name,
                    href: route('portal.dashboard', absolute: false),
                    allDay: true,
                ));
            });

        MessageThread::query()
            ->where('client_id', $client->getKey())
            ->whereNotNull('last_activity_at')
            ->latest('last_activity_at')
            ->limit(25)
            ->get()
            ->each(function (MessageThread $thread) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "message-thread:{$thread->id}",
                    title: 'Message thread activity',
                    startsAt: $thread->last_activity_at,
                    kind: 'message',
                    status: 'Updated',
                    href: route('portal.messages.show', $thread, absolute: false),
                ));
            });

        array_push(
            $events,
            ...$this->publicHolidays->eventsBetween(
                $rangeStart,
                $rangeEnd,
                $this->publicHolidays->regionsForClient($client),
            ),
            ...$this->availability->leaveEventsBetween($client, $rangeStart, $rangeEnd),
        );

        $leavePeriods = ClientLeavePeriod::query()
            ->where('client_id', $client->getKey())
            ->whereDate('ends_on', '>=', now()->subMonth()->toDateString())
            ->orderBy('starts_on')
            ->limit(40)
            ->get()
            ->map(fn (ClientLeavePeriod $leave): array => [
                'id' => $leave->id,
                'title' => $leave->title,
                'starts_on' => $leave->starts_on?->toDateString(),
                'ends_on' => $leave->ends_on?->toDateString(),
                'notes' => $leave->notes,
                'destroy_url' => route('portal.calendar.leave-periods.destroy', $leave, absolute: false),
            ])
            ->values()
            ->all();

        return [
            'Client calendar',
            "Dated meetings, documents, reports, proposals, wellbeing, and deadlines for {$client->legal_name}.",
            $events,
            [
                'leavePeriods' => $leavePeriods,
                'leaveStoreUrl' => route('portal.calendar.leave-periods.store', absolute: false),
                'canManageLeavePeriods' => true,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, array<string, mixed>>, 3: array<string, mixed>}
     */
    private function entrepreneurCalendar(User $user, EntrepreneurInviteReconciler $entrepreneurInvites): array
    {
        $entrepreneurInvites->reconcile($user);

        $profile = EntrepreneurProfile::query()
            ->where('user_id', $user->getKey())
            ->first();

        if (! $profile instanceof EntrepreneurProfile) {
            return $this->emptyCalendar('Entrepreneur calendar', 'Plan, assessment, document, message, and readiness activity will appear here.');
        }

        return [
            'Entrepreneur calendar',
            'Business plan, assessments, readiness signals, documents, and messages for your entrepreneur workspace.',
            $this->entrepreneurEvents($profile, false),
            [],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, array<string, mixed>>, 3: array<string, mixed>}
     */
    private function mentorCalendar(User $user): array
    {
        $events = [];

        EntrepreneurProfile::query()
            ->where('assigned_advisor_id', $user->getKey())
            ->latest()
            ->limit(80)
            ->get()
            ->each(function (EntrepreneurProfile $profile) use (&$events): void {
                array_push($events, ...$this->entrepreneurEvents($profile, true));
            });

        return [
            'Mentor calendar',
            'Entrepreneur profile, plan, assessment, readiness, document, and message activity assigned to you.',
            $events,
            [],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, array<string, mixed>>, 3: array<string, mixed>}
     */
    private function panelCalendar(User $user, string $panelType): array
    {
        $member = PanelMember::query()
            ->where('user_id', $user->getKey())
            ->where('panel_type', $panelType)
            ->latest()
            ->first();

        $label = $panelType === PanelMember::TYPE_BROKER ? 'Broker' : 'Coach';
        if (! $member instanceof PanelMember) {
            return $this->emptyCalendar("{$label} calendar", 'Referral, message, and panel agreement activity will appear here after your panel record is active.');
        }

        $events = [];
        $dashboardAnchor = $panelType === PanelMember::TYPE_BROKER ? '#broker-referrals' : '#coach-referrals';

        Referral::query()
            ->with(['client', 'entrepreneurProfile'])
            ->where('panel_member_id', $member->getKey())
            ->where('panel_type', $panelType)
            ->latest('sent_at')
            ->limit(80)
            ->get()
            ->each(function (Referral $referral) use (&$events, $dashboardAnchor): void {
                $subject = $referral->client?->legal_name ?? $referral->entrepreneurProfile?->name ?? 'Referral subject';
                $href = route('dashboard', absolute: false).$dashboardAnchor;

                $this->addEvent($events, $this->event(
                    id: "referral-sent:{$referral->id}",
                    title: "Referral sent: {$subject}",
                    startsAt: $referral->sent_at ?? $referral->created_at,
                    kind: 'referral',
                    status: $this->label((string) $referral->stage),
                    description: $this->label((string) $referral->referral_type),
                    href: $href,
                ));
                $this->addEvent($events, $this->event(
                    id: "referral-closed:{$referral->id}",
                    title: "Referral closed: {$subject}",
                    startsAt: $referral->closed_at,
                    kind: 'referral',
                    status: $this->label((string) $referral->stage),
                    href: $href,
                ));
            });

        ReferralMessage::query()
            ->with(['client', 'referral.entrepreneurProfile'])
            ->whereHas('referral', fn ($query) => $query
                ->where('panel_member_id', $member->getKey())
                ->where('panel_type', $panelType))
            ->latest('sent_at')
            ->limit(50)
            ->get()
            ->each(function (ReferralMessage $message) use (&$events, $dashboardAnchor): void {
                $subject = $message->client?->legal_name ?? $message->referral?->entrepreneurProfile?->name ?? 'Referral subject';
                $this->addEvent($events, $this->event(
                    id: "referral-message:{$message->id}",
                    title: "Message: {$subject}",
                    startsAt: $message->sent_at,
                    kind: 'message',
                    status: 'Sent',
                    description: Str::limit($message->body, 120),
                    href: route('dashboard', absolute: false).$dashboardAnchor,
                ));
            });

        PanelAgreement::query()
            ->where('panel_member_id', $member->getKey())
            ->latest('generated_at')
            ->limit(10)
            ->get()
            ->each(function (PanelAgreement $agreement) use (&$events): void {
                $this->addEvent($events, $this->event(
                    id: "panel-agreement-generated:{$agreement->id}",
                    title: 'Panel agreement generated',
                    startsAt: $agreement->generated_at,
                    kind: 'agreement',
                    status: $this->label((string) $agreement->status),
                    href: route('dashboard', absolute: false).'#panel-agreement',
                ));
                $this->addEvent($events, $this->event(
                    id: "panel-agreement-signed:{$agreement->id}",
                    title: 'Panel agreement signed',
                    startsAt: $agreement->signed_at,
                    kind: 'agreement',
                    status: 'Signed',
                    href: route('dashboard', absolute: false).'#panel-agreement',
                ));
            });

        if ($panelType === PanelMember::TYPE_BROKER) {
            ReverseReferral::query()
                ->where('panel_member_id', $member->getKey())
                ->latest('submitted_at')
                ->limit(25)
                ->get()
                ->each(function (ReverseReferral $referral) use (&$events): void {
                    $this->addEvent($events, $this->event(
                        id: "reverse-referral:{$referral->id}",
                        title: "Reverse referral: {$referral->name}",
                        startsAt: $referral->submitted_at,
                        kind: 'referral',
                        status: $this->label((string) $referral->target_type),
                        description: $referral->company,
                        href: route('dashboard', absolute: false).'#broker-referrals',
                    ));
                });
        }

        return [
            "{$label} calendar",
            $panelType === PanelMember::TYPE_BROKER
                ? 'Broker referrals, reverse referrals, messages, and panel agreement activity.'
                : 'Coach referrals, referral messages, and panel agreement activity.',
            $events,
            [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entrepreneurEvents(EntrepreneurProfile $profile, bool $advisorLinks): array
    {
        $events = [];
        $profileHref = $advisorLinks
            ? route('advisor.entrepreneurs.show', $profile, absolute: false)
            : route('portal.entrepreneur.dashboard', absolute: false);
        $planHref = $advisorLinks
            ? $profileHref
            : route('portal.entrepreneur.plan.show', absolute: false);

        $this->addEvent($events, $this->event(
            id: "entrepreneur-profile:{$profile->id}",
            title: "Profile created: {$profile->name}",
            startsAt: $profile->created_at,
            kind: 'profile',
            status: $this->label($profile->currentStageValue()),
            description: $profile->concept_summary,
            href: $profileHref,
        ));

        BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->limit(20)
            ->get()
            ->each(function (BusinessPlan $plan) use (&$events, $planHref): void {
                $this->addEvent($events, $this->event(
                    id: "business-plan-created:{$plan->id}",
                    title: 'Business plan started',
                    startsAt: $plan->created_at,
                    kind: 'plan',
                    status: $this->label((string) $plan->status),
                    href: $planHref,
                ));
                $this->addEvent($events, $this->event(
                    id: "business-plan-completed:{$plan->id}",
                    title: 'Business plan completed',
                    startsAt: $plan->completed_at,
                    kind: 'plan',
                    status: $this->label((string) $plan->status),
                    href: $planHref,
                ));
                $this->addEvent($events, $this->event(
                    id: "living-plan-next:{$plan->id}",
                    title: 'Living plan update due',
                    startsAt: $plan->living_plan_next_update_at,
                    kind: 'deadline',
                    status: 'Due',
                    href: $planHref,
                    allDay: true,
                ));
            });

        $planIds = BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        PlanAssessment::query()
            ->whereIn('business_plan_id', $planIds)
            ->whereNotNull('finalised_at')
            ->latest('finalised_at')
            ->limit(30)
            ->get()
            ->each(function (PlanAssessment $assessment) use (&$events, $advisorLinks, $profileHref): void {
                $href = $advisorLinks
                    ? $profileHref
                    : route('portal.entrepreneur.assessments.show', $assessment, absolute: false);
                $this->addEvent($events, $this->event(
                    id: "plan-assessment:{$assessment->id}",
                    title: "Assessment round {$assessment->round} finalised",
                    startsAt: $assessment->finalised_at,
                    kind: 'assessment',
                    status: $assessment->overall_grade ?? 'Finalised',
                    href: $href,
                ));
            });

        AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('surfaced_at')
            ->limit(20)
            ->get()
            ->each(function (AdvisoryReadinessSignal $signal) use (&$events, $profileHref): void {
                $this->addEvent($events, $this->event(
                    id: "advisory-readiness:{$signal->id}",
                    title: 'Advisory readiness signal',
                    startsAt: $signal->surfaced_at,
                    kind: 'readiness',
                    status: $signal->score !== null ? "Score {$signal->score}" : 'Surfaced',
                    href: $profileHref,
                ));
            });

        ReadinessAssessment::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('assessed_at')
            ->limit(20)
            ->get()
            ->each(function (ReadinessAssessment $assessment) use (&$events, $profileHref): void {
                $this->addEvent($events, $this->event(
                    id: "readiness-assessment:{$assessment->id}",
                    title: 'Readiness assessment completed',
                    startsAt: $assessment->assessed_at,
                    kind: 'assessment',
                    status: $this->label((string) $assessment->outcome),
                    href: $profileHref,
                ));
            });

        IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('evaluated_at')
            ->limit(20)
            ->get()
            ->each(function (IdeaValidation $validation) use (&$events, $profileHref): void {
                $this->addEvent($events, $this->event(
                    id: "idea-validation:{$validation->id}",
                    title: 'Idea validation evaluated',
                    startsAt: $validation->evaluated_at,
                    kind: 'assessment',
                    status: $validation->advisor_gate_passed_at ? 'Advisor gate passed' : 'Evaluated',
                    href: $profileHref,
                ));
                $this->addEvent($events, $this->event(
                    id: "idea-validation-gate:{$validation->id}",
                    title: 'Advisor gate passed',
                    startsAt: $validation->advisor_gate_passed_at,
                    kind: 'readiness',
                    status: 'Passed',
                    href: $profileHref,
                ));
            });

        Document::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->limit(30)
            ->get()
            ->each(function (Document $document) use (&$events, $advisorLinks, $profileHref): void {
                $href = $advisorLinks
                    ? $profileHref
                    : route('portal.documents.show', $document, absolute: false);
                $this->addEvent($events, $this->event(
                    id: "entrepreneur-document:{$document->id}",
                    title: $document->original_filename,
                    startsAt: $document->created_at,
                    kind: 'document',
                    status: $this->label((string) $document->category),
                    description: 'Document uploaded',
                    href: $href,
                ));
            });

        MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNotNull('last_activity_at')
            ->latest('last_activity_at')
            ->limit(25)
            ->get()
            ->each(function (MessageThread $thread) use (&$events, $advisorLinks, $profileHref): void {
                $href = $advisorLinks
                    ? $profileHref
                    : route('portal.messages.show', $thread, absolute: false);
                $this->addEvent($events, $this->event(
                    id: "entrepreneur-message-thread:{$thread->id}",
                    title: 'Message thread activity',
                    startsAt: $thread->last_activity_at,
                    kind: 'message',
                    status: 'Updated',
                    href: $href,
                ));
            });

        return $events;
    }

    /**
     * @return array{0: string, 1: string, 2: array<int, array<string, mixed>>, 3: array<string, mixed>}
     */
    private function emptyCalendar(string $title, string $subtitle): array
    {
        return [$title, $subtitle, [], []];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function addEvent(array &$events, ?array $event): void
    {
        if ($event !== null) {
            $events[] = $event;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function event(
        string $id,
        string $title,
        mixed $startsAt,
        string $kind,
        ?string $status = null,
        ?string $description = null,
        ?string $href = null,
        bool $allDay = false,
    ): ?array {
        $date = $this->date($startsAt);

        if (! $date instanceof Carbon) {
            return null;
        }

        return [
            'id' => $id,
            'title' => $title,
            'starts_at' => $date->toIso8601String(),
            'kind' => $kind,
            'kind_label' => $this->label($kind),
            'status' => $status,
            'description' => $description,
            'href' => $href,
            'all_day' => $allDay,
        ];
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function sortedEvents(array $events): array
    {
        usort($events, fn (array $left, array $right): int => strcmp((string) $left['starts_at'], (string) $right['starts_at']));

        return array_slice($events, 0, 250);
    }

    private function label(string $value): string
    {
        return Str::of($value)
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();
    }
}
