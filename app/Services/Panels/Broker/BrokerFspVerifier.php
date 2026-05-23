<?php

declare(strict_types=1);

namespace App\Services\Panels\Broker;

use App\Models\PanelMember;
use App\Models\User;
use App\Notifications\BrokerFspLapsedNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Fsp\Contracts\FspClient;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

final class BrokerFspVerifier
{
    public function __construct(
        private readonly FspClient $fsp,
        private readonly AuditWriter $audit,
    ) {}

    public function validateForApproval(PanelMember $member, User $actor): PanelMember
    {
        if ($member->panel_type !== PanelMember::TYPE_BROKER) {
            throw new InvalidArgumentException('FSP validation only applies to broker panel members.');
        }

        $member = $this->verify($member, $actor);

        if ($member->fsp_status !== PanelMember::FSP_STATUS_CURRENT) {
            throw new InvalidArgumentException('Broker FSP registration must be current before approval.');
        }

        return $member;
    }

    public function reverify(PanelMember $member, ?User $actor = null): PanelMember
    {
        if ($member->panel_type !== PanelMember::TYPE_BROKER) {
            throw new InvalidArgumentException('FSP re-verification only applies to broker panel members.');
        }

        $member = $this->verify($member, $actor);

        if ($member->fsp_status === PanelMember::FSP_STATUS_CURRENT) {
            return $member;
        }

        $before = [
            'status' => $member->getOriginal('status'),
            'fsp_status' => $member->getOriginal('fsp_status'),
        ];

        $member->forceFill([
            'status' => PanelMember::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ])->save();

        $this->audit->record('panel.broker_fsp_lapsed', subject: $member, actor: $actor, before: $before, after: [
            'status' => PanelMember::STATUS_SUSPENDED,
            'fsp_number' => $member->fsp_number,
            'fsp_status' => $member->fsp_status,
        ]);

        $this->notifyAdvisors($member->refresh());

        return $member->refresh();
    }

    /**
     * @return array{checked:int, current:int, suspended:int}
     */
    public function reverifyDue(int $days = 30): array
    {
        $result = [
            'checked' => 0,
            'current' => 0,
            'suspended' => 0,
        ];

        PanelMember::query()
            ->where('panel_type', PanelMember::TYPE_BROKER)
            ->where('status', PanelMember::STATUS_ACTIVE)
            ->where(function ($query) use ($days): void {
                $query
                    ->whereNull('fsp_last_checked_at')
                    ->orWhere('fsp_last_checked_at', '<=', now()->subDays(max(1, $days)));
            })
            ->orderBy('fsp_last_checked_at')
            ->get()
            ->each(function (PanelMember $member) use (&$result): void {
                $result['checked']++;
                $verified = $this->reverify($member);

                if ($verified->status === PanelMember::STATUS_SUSPENDED) {
                    $result['suspended']++;
                } else {
                    $result['current']++;
                }
            });

        return $result;
    }

    private function verify(PanelMember $member, ?User $actor = null): PanelMember
    {
        $member = $member->refresh();
        $fspNumber = $this->fspNumber($member);
        $record = $this->fsp->lookup($fspNumber);
        $status = $this->normaliseStatus($record);
        $before = [
            'fsp_number' => $member->fsp_number,
            'fsp_status' => $member->fsp_status,
        ];
        $application = $member->application ?? [];

        $member->forceFill([
            'fsp_number' => $fspNumber,
            'fsp_status' => $status,
            'fsp_last_checked_at' => now(),
            'application' => [
                ...$application,
                'fsp_number' => $fspNumber,
                'fsp_snapshot' => $record,
            ],
        ])->save();

        $this->audit->record('panel.broker_fsp_checked', subject: $member, actor: $actor, before: $before, after: [
            'fsp_number' => $fspNumber,
            'fsp_status' => $status,
            'source_badge' => $record['source_badge'] ?? null,
        ]);

        return $member->refresh();
    }

    private function fspNumber(PanelMember $member): string
    {
        $application = $member->application ?? [];
        $fspNumber = strtoupper(trim((string) ($application['fsp_number'] ?? $member->fsp_number ?? '')));

        if ($fspNumber === '') {
            throw new InvalidArgumentException('Broker application requires an FSP number.');
        }

        return $fspNumber;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function normaliseStatus(array $record): string
    {
        if (($record['found'] ?? true) === false) {
            return PanelMember::FSP_STATUS_UNKNOWN;
        }

        return match (strtolower((string) ($record['status'] ?? 'unknown'))) {
            'current', 'active', 'registered' => PanelMember::FSP_STATUS_CURRENT,
            'lapsed', 'inactive', 'deregistered', 'cancelled', 'suspended' => PanelMember::FSP_STATUS_LAPSED,
            default => PanelMember::FSP_STATUS_UNKNOWN,
        };
    }

    private function notifyAdvisors(PanelMember $member): void
    {
        $recipients = User::query()
            ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new BrokerFspLapsedNotification($member));
    }
}
