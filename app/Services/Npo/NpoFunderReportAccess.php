<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\ReportType;
use App\Models\ClientFunderRecord;
use App\Models\NpoFunderReportLink;
use App\Models\NpoFunderReportSession;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class NpoFunderReportAccess
{
    public const ROLE_FUNDER_CONTACT = 'funder_contact';

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function requestInvite(ClientFunderRecord $record, User $actor, string $email): NpoFunderReportLink
    {
        $link = NpoFunderReportLink::query()->create([
            'client_id' => $record->client_id,
            'npo_engagement_id' => $record->npo_engagement_id,
            'client_funder_record_id' => $record->getKey(),
            'guest_email' => $email,
            'status' => NpoFunderReportLink::STATUS_REQUESTED,
            'requested_by_user_id' => $actor->getKey(),
        ]);

        $this->audit->record('npo.funder_report_link.requested', subject: $link, actor: $actor, after: [
            'client_funder_record_id' => $record->getKey(),
            'guest_email' => $email,
        ]);

        return $link->refresh();
    }

    /**
     * @return array{link:NpoFunderReportLink, token:string}
     */
    public function approveInvite(
        NpoFunderReportLink $link,
        Report $report,
        User $actor,
        ?CarbonInterface $expiresAt = null,
    ): array {
        $this->assertReportCanBeShared($report);
        $this->assertLinkMatchesReport($link, $report);

        $token = Str::random(48);
        $link->forceFill([
            'report_id' => $report->getKey(),
            'status' => NpoFunderReportLink::STATUS_APPROVED,
            'token_hash' => NpoFunderReportLink::hashToken($token),
            'approved_by_user_id' => $actor->getKey(),
            'approved_at' => now(),
            'expires_at' => $expiresAt ?? now()->addDays(90),
            'declined_by_user_id' => null,
            'declined_at' => null,
            'decline_reason' => null,
        ])->save();

        $this->audit->record('npo.funder_report_link.approved', subject: $link, actor: $actor, after: [
            'report_id' => $report->getKey(),
            'expires_at' => $link->expires_at?->toIso8601String(),
        ]);

        return ['link' => $link->refresh(), 'token' => $token];
    }

    /**
     * @return array{link:NpoFunderReportLink, token:string}
     */
    public function issueLink(
        Report $report,
        User $actor,
        string $email,
        ?ClientFunderRecord $record = null,
        ?CarbonInterface $expiresAt = null,
    ): array {
        $this->assertReportCanBeShared($report);

        $link = NpoFunderReportLink::query()->create([
            'client_id' => $report->client_id,
            'npo_engagement_id' => $report->npo_engagement_id,
            'report_id' => $report->getKey(),
            'client_funder_record_id' => $record?->getKey(),
            'guest_email' => $email,
            'status' => NpoFunderReportLink::STATUS_REQUESTED,
            'requested_by_user_id' => $actor->getKey(),
        ]);

        return $this->approveInvite($link, $report, $actor, $expiresAt);
    }

    public function declineInvite(NpoFunderReportLink $link, User $actor, string $reason): NpoFunderReportLink
    {
        $link->forceFill([
            'status' => NpoFunderReportLink::STATUS_DECLINED,
            'declined_by_user_id' => $actor->getKey(),
            'declined_at' => now(),
            'decline_reason' => $reason,
        ])->save();

        $this->audit->record('npo.funder_report_link.declined', subject: $link, actor: $actor, after: [
            'decline_reason' => $reason,
        ]);

        return $link->refresh();
    }

    public function revoke(NpoFunderReportLink $link, User $actor): NpoFunderReportLink
    {
        $link->forceFill([
            'status' => NpoFunderReportLink::STATUS_REVOKED,
            'revoked_by_user_id' => $actor->getKey(),
            'revoked_at' => now(),
        ])->save();

        $this->audit->record('npo.funder_report_link.revoked', subject: $link, actor: $actor, after: [
            'report_id' => $link->report_id,
        ]);

        return $link->refresh();
    }

    public function resolveToken(string $token, ?string $requestedReportId = null): Report
    {
        $this->context->apply('system', []);

        $link = NpoFunderReportLink::query()
            ->where('token_hash', NpoFunderReportLink::hashToken($token))
            ->first();

        if (! $link instanceof NpoFunderReportLink || ! $link->isUsable()) {
            throw ValidationException::withMessages(['token' => 'This funder report link is no longer active.']);
        }

        $report = Report::query()->find($link->report_id);
        if (! $report instanceof Report) {
            throw ValidationException::withMessages(['token' => 'This funder report link is no longer active.']);
        }

        $this->assertReportCanBeShared($report);

        DB::transaction(function () use ($link, $requestedReportId): void {
            $link->forceFill(['last_used_at' => now()])->save();

            NpoFunderReportSession::query()->create([
                'client_id' => $link->client_id,
                'npo_funder_report_link_id' => $link->getKey(),
                'report_id' => $link->report_id,
                'accessed_at' => now(),
                'metadata' => [
                    'requested_report_id_ignored' => $requestedReportId,
                ],
            ]);
        });

        $this->audit->record('npo.funder_report_link.accessed', subject: $link, after: [
            'report_id' => $link->report_id,
            'requested_report_id_ignored' => $requestedReportId,
        ]);

        $this->context->apply(self::ROLE_FUNDER_CONTACT, [], reportId: (string) $link->report_id);

        return Report::query()->whereKey($link->report_id)->firstOrFail();
    }

    private function assertReportCanBeShared(Report $report): void
    {
        if ($report->type !== ReportType::FunderAccountability || ! $report->reviewed()) {
            throw new InvalidArgumentException('Funder links require a reviewed Funder Accountability Report.');
        }

        if ($report->npo_engagement_id === null || $report->client_id === null) {
            throw new InvalidArgumentException('Funder links require an engagement-scoped accountability report.');
        }
    }

    private function assertLinkMatchesReport(NpoFunderReportLink $link, Report $report): void
    {
        if ((string) $link->client_id !== (string) $report->client_id || (string) $link->npo_engagement_id !== (string) $report->npo_engagement_id) {
            throw new InvalidArgumentException('Funder link and report must belong to the same NPO engagement.');
        }
    }
}
