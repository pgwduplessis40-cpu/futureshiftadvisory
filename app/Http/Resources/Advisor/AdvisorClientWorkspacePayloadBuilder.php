<?php

declare(strict_types=1);

namespace App\Http\Resources\Advisor;

use App\Enums\NpoEngagementSubType;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\GovernanceReviewFinding;
use App\Models\IndustryBriefing;
use App\Models\KnowledgeAssessment;
use App\Models\Meeting;
use App\Models\NpoEngagement;
use App\Models\PreMeetingBrief;
use App\Models\Report;
use App\Models\ReportSectionComment;
use App\Models\ReportSectionRevision;

/**
 * Typed resource contracts for the advisor client workspace activity panels.
 *
 * These builders keep report, meeting and governance data shaped at the
 * Inertia boundary, so the controller only coordinates the workspace.
 *
 * @phpstan-type GovernanceFindingPayload array{
 *     id:string,
 *     finding_key:string,
 *     category:string,
 *     severity:string,
 *     title:string,
 *     body:string,
 *     status:string,
 *     advisor_notes:?string,
 *     review_url:string,
 *     reviewed_at:?string
 * }
 * @phpstan-type GovernanceReviewPayload array{
 *     id:string,
 *     run_url:string,
 *     findings_count:int,
 *     pending_review_count:int,
 *     reviewed_count:int,
 *     high_priority_count:int,
 *     can_generate_report:bool,
 *     findings:list<GovernanceFindingPayload>
 * }
 * @phpstan-type KnowledgeCalibrationPayload array{
 *     source:string,
 *     language_depth:string,
 *     financial_detail:string,
 *     strategic_framing:string,
 *     leadership_context:string,
 *     advisor_review_note:string,
 *     scores:array{financial_literacy:int,strategic_awareness:int,leadership:int}
 * }
 * @phpstan-type KnowledgeAssessmentPayload array{
 *     id:string,
 *     financial_literacy:int,
 *     strategic_awareness:int,
 *     leadership:int,
 *     calibration:KnowledgeCalibrationPayload,
 *     assessed_at:?string
 * }
 * @phpstan-type ReportPayload array{
 *     id:string,
 *     type:string,
 *     type_label:string,
 *     title:string,
 *     generated_at:?string,
 *     pdf_byte_size:?int,
 *     pptx_byte_size:?int,
 *     view_url:string,
 *     download_url:string,
 *     pptx_url:?string,
 *     review_status:string,
 *     reviewed_at:?string,
 *     review_url:string,
 *     release_url:?string,
 *     can_review:bool,
 *     section_count:int,
 *     revision_count:int,
 *     comment_count:int
 * }
 * @phpstan-type MeetingPayload array{
 *     id:string,
 *     title:string,
 *     scheduled_at:?string,
 *     location:?string,
 *     link:?string,
 *     attendees:list<string>,
 *     calendar_synced:bool,
 *     brief_status:string
 * }
 * @phpstan-type IndustryBriefingPayload array{
 *     id:string,
 *     period:string,
 *     body:string,
 *     status:string,
 *     reviewed_at:?string,
 *     sent_at:?string,
 *     review_url:string,
 *     can_review:bool
 * }
 * @phpstan-type PreMeetingBriefPayload array{
 *     id:string,
 *     meeting_title:?string,
 *     meeting_at:?string,
 *     body:string,
 *     red_flag_count:int,
 *     generated_at:?string,
 *     reviewed_at:?string,
 *     sent_at:?string,
 *     review_url:string,
 *     can_review:bool
 * }
 */
final class AdvisorClientWorkspacePayloadBuilder
{
    /** @return GovernanceReviewPayload|null */
    public function npoGovernanceReview(Client $client): ?array
    {
        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
            ->latest()
            ->first();

        if (! $engagement instanceof NpoEngagement) {
            return null;
        }

        $findings = GovernanceReviewFinding::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 when 'low' then 3 else 4 end")
            ->latest('updated_at')
            ->orderBy('id')
            ->get();
        $pending = $findings->where('status', GovernanceReviewFinding::STATUS_PENDING_ADVISOR_REVIEW);
        $reviewed = $findings->where('status', GovernanceReviewFinding::STATUS_REVIEWED);

        return [
            'id' => (string) $engagement->getKey(),
            'run_url' => route('advisor.npo-engagements.governance-review.analysis', $engagement, absolute: false),
            'findings_count' => $findings->count(),
            'pending_review_count' => $pending->count(),
            'reviewed_count' => $reviewed->count(),
            'high_priority_count' => $findings
                ->filter(fn (GovernanceReviewFinding $finding): bool => in_array($finding->severity->value, ['critical', 'high'], true))
                ->count(),
            'can_generate_report' => $reviewed->isNotEmpty(),
            'findings' => $findings
                ->take(8)
                ->map(fn (GovernanceReviewFinding $finding): array => [
                    'id' => (string) $finding->getKey(),
                    'finding_key' => (string) $finding->finding_key,
                    'category' => (string) $finding->category,
                    'severity' => $finding->severity->value,
                    'title' => (string) $finding->title,
                    'body' => (string) $finding->body,
                    'status' => (string) $finding->status,
                    'advisor_notes' => is_string($finding->advisor_notes) ? $finding->advisor_notes : null,
                    'review_url' => route('advisor.governance-review-findings.review', $finding, absolute: false),
                    'reviewed_at' => $finding->reviewed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return KnowledgeAssessmentPayload|null */
    public function latestKnowledgeAssessment(Client $client): ?array
    {
        $assessment = KnowledgeAssessment::query()
            ->where('client_id', $client->getKey())
            ->latest('assessed_at')
            ->latest('created_at')
            ->first();

        if (! $assessment instanceof KnowledgeAssessment) {
            return null;
        }

        $calibration = $assessment->calibration;

        return [
            'id' => (string) $assessment->getKey(),
            'financial_literacy' => (int) $assessment->financial_literacy,
            'strategic_awareness' => (int) $assessment->strategic_awareness,
            'leadership' => (int) $assessment->leadership,
            'calibration' => [
                'source' => (string) ($calibration['source'] ?? 'knowledge_assessment'),
                'language_depth' => (string) ($calibration['language_depth'] ?? 'standard'),
                'financial_detail' => (string) ($calibration['financial_detail'] ?? 'balanced'),
                'strategic_framing' => (string) ($calibration['strategic_framing'] ?? 'balanced'),
                'leadership_context' => (string) ($calibration['leadership_context'] ?? 'standard'),
                'advisor_review_note' => (string) ($calibration['advisor_review_note'] ?? ''),
                'scores' => [
                    'financial_literacy' => (int) data_get($calibration, 'scores.financial_literacy', $assessment->financial_literacy),
                    'strategic_awareness' => (int) data_get($calibration, 'scores.strategic_awareness', $assessment->strategic_awareness),
                    'leadership' => (int) data_get($calibration, 'scores.leadership', $assessment->leadership),
                ],
            ],
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
        ];
    }

    /** @return list<ReportPayload> */
    public function reports(Client $client): array
    {
        return Report::query()
            ->where('client_id', $client->getKey())
            ->latest('generated_at')
            ->limit(8)
            ->get()
            ->map(fn (Report $report): array => [
                'id' => (string) $report->getKey(),
                'type' => $report->type->value,
                'type_label' => $report->type->label(),
                'title' => (string) $report->title,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'pdf_byte_size' => $report->pdf_byte_size === null ? null : (int) $report->pdf_byte_size,
                'pptx_byte_size' => $report->pptx_byte_size === null ? null : (int) $report->pptx_byte_size,
                'view_url' => route('advisor.reports.download', $report, absolute: false),
                'download_url' => route('advisor.reports.download', $report, absolute: false),
                'pptx_url' => $report->pptx_path !== null
                    ? route('advisor.reports.pptx', $report, absolute: false)
                    : null,
                'review_status' => (string) $report->review_status,
                'reviewed_at' => $report->reviewed_at?->toIso8601String(),
                'review_url' => route('advisor.reports.review', $report, absolute: false),
                'release_url' => $report->type === ReportType::Client
                    ? route('advisor.reports.release', $report, absolute: false)
                    : null,
                'can_review' => in_array($report->type, [
                    ReportType::Client,
                    ReportType::DueDiligence,
                    ReportType::Valuation,
                    ReportType::AcquisitionGoNoGo,
                    ReportType::Trajectory,
                    ReportType::SuccessionValueGap,
                    ReportType::FunderAccountability,
                    ReportType::ImpactSummary,
                ], true) && $report->review_status === 'pending_review',
                'section_count' => $report->sections()->count(),
                'revision_count' => ReportSectionRevision::query()
                    ->where('report_id', $report->getKey())
                    ->count(),
                'comment_count' => ReportSectionComment::query()
                    ->where('report_id', $report->getKey())
                    ->whereNull('resolved_at')
                    ->count(),
            ])
            ->values()
            ->all();
    }

    /** @return list<MeetingPayload> */
    public function meetings(Client $client): array
    {
        return Meeting::query()
            ->with('preMeetingBrief')
            ->withCount('calendarEventMappings')
            ->where('client_id', $client->getKey())
            ->where('scheduled_at', '>=', now()->subDay())
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get()
            ->map(fn (Meeting $meeting): array => [
                'id' => (string) $meeting->getKey(),
                'title' => (string) $meeting->title,
                'scheduled_at' => $meeting->scheduled_at->toIso8601String(),
                'location' => is_string($meeting->location) ? $meeting->location : null,
                'link' => is_string($meeting->link) ? $meeting->link : null,
                'attendees' => $this->attendeeNames($meeting->attendees),
                'calendar_synced' => (int) $meeting->calendar_event_mappings_count > 0,
                'brief_status' => $meeting->preMeetingBrief?->sent_at !== null
                    ? 'sent'
                    : ($meeting->preMeetingBrief instanceof PreMeetingBrief ? 'draft' : 'pending'),
            ])
            ->values()
            ->all();
    }

    /** @return list<IndustryBriefingPayload> */
    public function industryBriefings(Client $client): array
    {
        return IndustryBriefing::query()
            ->where('client_id', $client->getKey())
            ->latest('period')
            ->limit(6)
            ->get()
            ->map(fn (IndustryBriefing $briefing): array => [
                'id' => (string) $briefing->getKey(),
                'period' => $briefing->period->toDateString(),
                'body' => (string) $briefing->body,
                'status' => (string) $briefing->status,
                'reviewed_at' => $briefing->reviewed_at?->toIso8601String(),
                'sent_at' => $briefing->sent_at?->toIso8601String(),
                'review_url' => route('advisor.industry-briefings.review', $briefing, absolute: false),
                'can_review' => $briefing->status === IndustryBriefing::STATUS_DRAFT,
            ])
            ->values()
            ->all();
    }

    /** @return list<PreMeetingBriefPayload> */
    public function preMeetingBriefs(Client $client): array
    {
        return PreMeetingBrief::query()
            ->with('meeting')
            ->where('client_id', $client->getKey())
            ->latest('meeting_at')
            ->limit(6)
            ->get()
            ->map(fn (PreMeetingBrief $brief): array => [
                'id' => (string) $brief->getKey(),
                'meeting_title' => $brief->meeting?->title,
                'meeting_at' => $brief->meeting_at?->toIso8601String(),
                'body' => (string) $brief->body,
                'red_flag_count' => count($brief->red_flag_ids),
                'generated_at' => $brief->generated_at?->toIso8601String(),
                'reviewed_at' => $brief->reviewed_at?->toIso8601String(),
                'sent_at' => $brief->sent_at?->toIso8601String(),
                'review_url' => route('advisor.pre-meeting-briefs.review', $brief, absolute: false),
                'can_review' => $brief->sent_at === null,
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function attendeeNames(mixed $attendees): array
    {
        if (! is_array($attendees)) {
            return [];
        }

        return collect($attendees)
            ->filter(static fn (mixed $attendee): bool => is_string($attendee) && trim($attendee) !== '')
            ->map(static fn (string $attendee): string => trim($attendee))
            ->values()
            ->all();
    }
}
