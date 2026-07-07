<?php

declare(strict_types=1);

namespace App\Services\Offboarding;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\OffboardingRecord;
use App\Models\User;
use App\Notifications\OffboardingCompletedNotification;
use App\Notifications\ReengagementReminderNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\AdvisorClientCapacity;
use App\Services\Clients\LifecycleManager;
use App\Services\Knowledge\KnowledgeCaptureService;
use App\Services\Pdf\PdfRenderer;
use App\Services\Reports\BrandedReportLayout;
use App\Services\Storage\SecureFileWriter;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

final class OffboardingService
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly AdvisorClientCapacity $capacity,
        private readonly PdfRenderer $renderer,
        private readonly SecureFileWriter $writer,
        private readonly LifecycleManager $lifecycle,
        private readonly KnowledgeCaptureService $knowledgeCapture,
        private readonly BrandedReportLayout $layout,
    ) {}

    /**
     * @param  array{exit_interview_notes?: string|null, handover_notes?: string|null, privacy_acknowledged?: bool}  $details
     */
    public function trigger(Client $client, User $triggeredBy, array $details = []): OffboardingRecord
    {
        $client->loadMissing(['primaryContact', 'teamMembers.user']);
        $triggeredAt = now();
        $leadAdvisor = $this->leadAdvisorFor($client, $triggeredBy);
        $capacityBefore = $leadAdvisor instanceof User
            ? $this->capacity->summary($leadAdvisor)['active_count']
            : null;

        $record = DB::transaction(function () use ($client, $triggeredBy, $details, $triggeredAt, $leadAdvisor, $capacityBefore): OffboardingRecord {
            $paths = $this->writeArtifacts($client, $triggeredBy, $details, $triggeredAt);

            $record = OffboardingRecord::query()->create([
                'client_id' => $client->getKey(),
                'triggered_by_user_id' => $triggeredBy->getKey(),
                'status' => OffboardingRecord::STATUS_COMPLETED,
                'triggered_at' => $triggeredAt,
                'final_report_path' => $paths['final_report_path'],
                'engagement_summary_path' => $paths['engagement_summary_path'],
                'handover_path' => $paths['handover_path'],
                'exit_interview_path' => $paths['exit_interview_path'],
                'privacy_notice_path' => $paths['privacy_notice_path'],
                'reengagement_due' => $triggeredAt->copy()->addDays($this->reengagementDays()),
                'metadata' => [
                    'phase' => 1,
                    'phase_note' => 'Phase 2 will enrich these placeholder offboarding PDFs with detailed analysis.',
                    'exit_interview_notes' => $details['exit_interview_notes'] ?? null,
                    'handover_notes' => $details['handover_notes'] ?? null,
                    'privacy_acknowledged' => (bool) ($details['privacy_acknowledged'] ?? false),
                ],
            ]);

            $capacityAfter = $leadAdvisor instanceof User
                ? $this->capacity->summary($leadAdvisor)['active_count']
                : null;
            $capacityDelta = $capacityBefore !== null && $capacityAfter !== null
                ? $capacityAfter - $capacityBefore
                : 0;

            $record->forceFill([
                'advisor_capacity_before' => $capacityBefore,
                'advisor_capacity_after' => $capacityAfter,
                'advisor_capacity_delta' => $capacityDelta,
                'advisor_capacity_released' => $capacityDelta < 0,
            ])->save();

            $this->auditWriter->record('offboarding.triggered', subject: $record, actor: $triggeredBy, after: [
                'client_id' => $client->getKey(),
                'triggered_by_user_id' => $triggeredBy->getKey(),
                'artifact_paths' => $paths,
                'reengagement_due' => $record->reengagement_due?->toIso8601String(),
                'advisor_capacity_before' => $capacityBefore,
                'advisor_capacity_after' => $capacityAfter,
                'advisor_capacity_delta' => $capacityDelta,
            ]);

            return $record;
        });

        $this->lifecycle->offboard(
            client: $client,
            actor: $triggeredBy,
            reason: 'Structured offboarding completed.',
            sendNotifications: false,
        );
        $this->notifyClient($record);
        $this->knowledgeCapture->captureFromOffboarding($record, $leadAdvisor ?? $triggeredBy);

        return $record->refresh();
    }

    public function sendDueReengagementReminders(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $processed = 0;

        OffboardingRecord::query()
            ->with(['client.teamMembers.user'])
            ->whereNull('reengagement_reminder_sent_at')
            ->whereNotNull('reengagement_due')
            ->where('reengagement_due', '<=', $now)
            ->orderBy('reengagement_due')
            ->chunkById(100, function ($records) use ($now, &$processed): void {
                foreach ($records as $record) {
                    $recipients = $this->advisorRecipients($record->client);

                    if ($recipients->isNotEmpty()) {
                        Notification::send($recipients->all(), new ReengagementReminderNotification($record));
                    }

                    $record->forceFill([
                        'reengagement_reminder_sent_at' => $now,
                    ])->save();

                    $this->auditWriter->record('offboarding.reengagement_reminder.sent', subject: $record, actor: null, after: [
                        'client_id' => $record->client_id,
                        'recipient_count' => $recipients->count(),
                        'reengagement_due' => $record->reengagement_due?->toIso8601String(),
                    ]);

                    $processed++;
                }
            });

        return $processed;
    }

    public function reengagementDays(): int
    {
        return max(1, (int) config('fsa.offboarding.reengagement_days', 90));
    }

    /**
     * @param  array{exit_interview_notes?: string|null, handover_notes?: string|null, privacy_acknowledged?: bool}  $details
     * @return array<string, string>
     */
    private function writeArtifacts(Client $client, User $triggeredBy, array $details, CarbonInterface $triggeredAt): array
    {
        $definitions = [
            'final_report_path' => 'Final progress report',
            'engagement_summary_path' => 'Engagement summary',
            'handover_path' => 'Handover document',
            'exit_interview_path' => 'Exit interview record',
            'privacy_notice_path' => 'Privacy notice',
        ];

        $paths = [];

        foreach ($definitions as $column => $title) {
            $pdf = $this->renderer->render($this->artifactHtml($title, $client, $triggeredBy, $details, $triggeredAt));
            $path = $this->writePdfDocument($pdf, $title, $client, $triggeredBy);

            $paths[$column] = $path;
        }

        return $paths;
    }

    /**
     * @param  array{exit_interview_notes?: string|null, handover_notes?: string|null, privacy_acknowledged?: bool}  $details
     */
    private function artifactHtml(
        string $title,
        Client $client,
        User $triggeredBy,
        array $details,
        CarbonInterface $triggeredAt,
    ): string {
        $notes = trim((string) ($details['exit_interview_notes'] ?? ''));
        $handover = trim((string) ($details['handover_notes'] ?? ''));

        $clientName = (string) ($client->legal_name ?: $client->trading_name ?: 'Client');
        $notice = '<article class="report-section missing-panel"><h2>Phase 1 placeholder</h2><p>This offboarding artifact is a branded placeholder. Phase 2 will enrich it with detailed progress analysis, generated summaries, and richer handover material.</p></article>';
        $advisorNotes = sprintf(
            '<article class="report-section"><h2>Advisor notes</h2><p class="section-body">%s</p></article>',
            $this->escape($notes !== '' ? $notes : 'No exit interview notes captured in Phase 1.'),
        );
        $handoverNotes = sprintf(
            '<article class="report-section"><h2>Handover notes</h2><p class="section-body">%s</p></article>',
            $this->escape($handover !== '' ? $handover : 'No handover notes captured in Phase 1.'),
        );

        return $this->layout->document(
            title: $title,
            templateKey: 'offboarding-artifact',
            documentTag: 'Offboarding artifact',
            eyebrow: 'Client offboarding',
            heading: $title,
            subheading: $clientName,
            meta: [
                'Client' => $clientName,
                'NZBN' => $client->nzbn ?? '-',
                'Engagement' => $this->engagementLabel($client),
                'Triggered by' => $triggeredBy->name.' <'.$triggeredBy->email.'>',
                'Triggered at' => $triggeredAt->format(DATE_ATOM),
            ],
            contentHtml: $notice.$advisorNotes.$handoverNotes,
            footer: 'Generated using Future Shift Advisory offboarding',
            snapshotTitle: 'Offboarding snapshot',
            metaColumns: 3,
        );
    }

    private function writePdfDocument(string $pdf, string $title, Client $client, User $triggeredBy): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fsa-offboarding-');

        if ($tmp === false || file_put_contents($tmp, $pdf) === false) {
            throw new RuntimeException("Offboarding artifact [{$title}] could not be prepared.");
        }

        try {
            $uploadedFile = new UploadedFile(
                path: $tmp,
                originalName: (Str::slug($title) ?: 'offboarding-artifact').'.pdf',
                mimeType: 'application/pdf',
                test: true,
            );

            $document = $this->writer->write(
                uploadedFile: $uploadedFile,
                owner: $triggeredBy,
                category: Document::CATEGORY_OTHER,
                clientId: (string) $client->getKey(),
            );

            return $document->stored_path;
        } finally {
            @unlink($tmp);
        }
    }

    private function engagementLabel(Client $client): string
    {
        return $client->engagement_type instanceof EngagementType
            ? $client->engagement_type->label()
            : (string) $client->engagement_type;
    }

    private function leadAdvisorFor(Client $client, User $fallback): ?User
    {
        $lead = $client->teamMembers
            ->first(fn (ClientTeamMember $member): bool => $member->role === 'lead_advisor'
                && $member->user instanceof User
                && in_array($member->user->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true))
            ?->user;

        if ($lead instanceof User) {
            return $lead;
        }

        return in_array($fallback->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true)
            ? $fallback
            : null;
    }

    /**
     * @return Collection<int, User>
     */
    private function clientRecipients(Client $client): Collection
    {
        return $client->teamMembers
            ->map(fn (ClientTeamMember $member): ?User => $member->user)
            ->push($client->primaryContact)
            ->filter(fn (?User $user): bool => $user instanceof User
                && in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true))
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function advisorRecipients(Client $client): Collection
    {
        return $client->teamMembers
            ->map(fn (ClientTeamMember $member): ?User => $member->user)
            ->filter(fn (?User $user): bool => $user instanceof User
                && in_array($user->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true))
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values();
    }

    private function notifyClient(OffboardingRecord $record): void
    {
        $record->loadMissing(['client.primaryContact', 'client.teamMembers.user']);
        $client = $record->client;

        if (! $client instanceof Client) {
            return;
        }

        $recipients = $this->clientRecipients($client);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients->all(), new OffboardingCompletedNotification($record));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
