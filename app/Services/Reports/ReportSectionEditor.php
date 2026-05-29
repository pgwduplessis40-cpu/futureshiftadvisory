<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ReportSection;
use App\Models\ReportSectionComment;
use App\Models\ReportSectionRevision;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReportSectionEditor
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ReportComposer $composer,
    ) {}

    /**
     * @param  array{title?: string, body?: string, metadata?: array<string, mixed>}  $changes
     */
    public function edit(Report $report, ReportSection $section, User $actor, array $changes, ?string $reason = null): ReportSection
    {
        $this->assertBelongsToReport($report, $section);

        $updated = DB::transaction(function () use ($report, $section, $actor, $changes, $reason): ReportSection {
            /** @var ReportSection $locked */
            $locked = ReportSection::query()
                ->whereKey($section->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = [
                'title' => $locked->title,
                'body' => $locked->body,
                'metadata' => $locked->metadata ?? [],
            ];
            $after = [
                'title' => array_key_exists('title', $changes) ? $changes['title'] : $locked->title,
                'body' => array_key_exists('body', $changes) ? $changes['body'] : $locked->body,
                'metadata' => array_key_exists('metadata', $changes) ? $changes['metadata'] : ($locked->metadata ?? []),
            ];

            if ($before === $after) {
                return $locked->refresh();
            }

            $revisionNumber = ((int) ReportSectionRevision::query()
                ->where('report_section_id', $locked->getKey())
                ->max('revision_number')) + 1;

            ReportSectionRevision::query()->create([
                'report_id' => $report->getKey(),
                'report_section_id' => $locked->getKey(),
                'client_id' => $locked->client_id,
                'entrepreneur_profile_id' => $locked->entrepreneur_profile_id,
                'revision_number' => $revisionNumber,
                'title_before' => $before['title'],
                'title_after' => $after['title'],
                'body_before' => $before['body'],
                'body_after' => $after['body'],
                'metadata_before' => $before['metadata'],
                'metadata_after' => $after['metadata'],
                'edited_by_user_id' => $actor->getKey(),
                'reason' => $reason,
                'edited_at' => now(),
            ]);

            $locked->forceFill([
                'title' => $after['title'],
                'body' => $after['body'],
                'metadata' => [
                    ...($after['metadata'] ?? []),
                    'advisor_edited' => true,
                    'latest_revision_number' => $revisionNumber,
                    'last_edited_at' => now()->toIso8601String(),
                    'last_edited_by_user_id' => $actor->getKey(),
                ],
            ])->save();

            $metadata = $report->metadata ?? [];
            $metadata['section_edits_count'] = (int) ($metadata['section_edits_count'] ?? 0) + 1;
            $metadata['last_section_edit_at'] = now()->toIso8601String();

            $reportUpdates = ['metadata' => $metadata];
            if ($report->review_status === 'reviewed') {
                $reportUpdates['review_status'] = 'pending_review';
                $reportUpdates['reviewed_by_user_id'] = null;
                $reportUpdates['reviewed_at'] = null;
            }

            $report->forceFill($reportUpdates)->save();

            $this->audit->record('report.section_edited', subject: $locked, actor: $actor, before: $before, after: [
                ...$after,
                'revision_number' => $revisionNumber,
                'report_id' => $report->getKey(),
                'report_review_status' => $report->review_status,
            ]);

            return $locked->refresh();
        });

        $this->composer->rerenderArtifacts($report->refresh());

        return $updated->refresh();
    }

    public function comment(Report $report, ReportSection $section, User $actor, string $body, string $visibility): ReportSectionComment
    {
        $this->assertBelongsToReport($report, $section);

        if (! in_array($visibility, [ReportSectionComment::VISIBILITY_ADVISOR_ONLY, ReportSectionComment::VISIBILITY_CLIENT_VISIBLE], true)) {
            throw new InvalidArgumentException('Unsupported report section comment visibility.');
        }

        /** @var ReportSectionComment $comment */
        $comment = ReportSectionComment::query()->create([
            'report_id' => $report->getKey(),
            'report_section_id' => $section->getKey(),
            'client_id' => $section->client_id,
            'entrepreneur_profile_id' => $section->entrepreneur_profile_id,
            'visibility' => $visibility,
            'body' => $body,
            'created_by_user_id' => $actor->getKey(),
        ]);

        $this->audit->record('report.section_commented', subject: $comment, actor: $actor, after: [
            'report_id' => $report->getKey(),
            'report_section_id' => $section->getKey(),
            'visibility' => $visibility,
        ]);

        return $comment->refresh();
    }

    private function assertBelongsToReport(Report $report, ReportSection $section): void
    {
        if ((string) $section->report_id !== (string) $report->getKey()) {
            throw new InvalidArgumentException('Report section does not belong to the selected report.');
        }
    }
}
