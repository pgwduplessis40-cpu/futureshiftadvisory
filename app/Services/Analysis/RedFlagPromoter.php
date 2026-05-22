<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisModule;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\RedFlag;
use App\Models\User;
use App\Notifications\RedFlagUrgentNotification;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

final class RedFlagPromoter
{
    public function promoteFinding(AnalysisFinding $finding): ?RedFlag
    {
        $finding->loadMissing(['run', 'client']);

        if ($this->enumValue($finding->severity) !== FindingSeverity::Critical->value) {
            return null;
        }

        $redFlag = RedFlag::query()->firstOrCreate(
            ['analysis_finding_id' => $finding->getKey()],
            [
                'client_id' => $finding->client_id,
                'source_type' => 'analysis_finding',
                'source_key' => (string) $finding->getKey(),
                'category' => $this->categoryFor($this->enumValue($finding->run?->module)),
                'severity' => $this->enumValue($finding->severity),
                'headline' => $finding->title,
                'detail' => $finding->body,
                'surfaced_at' => now(),
            ],
        );

        if ($redFlag->wasRecentlyCreated) {
            $this->notify($redFlag);
        }

        return $redFlag;
    }

    public function promoteMonitorSignal(
        Client $client,
        string $sourceType,
        string $sourceKey,
        string $category,
        string $headline,
        string $detail,
    ): RedFlag {
        $redFlag = RedFlag::query()->firstOrCreate(
            [
                'client_id' => $client->getKey(),
                'source_type' => $sourceType,
                'source_key' => $sourceKey,
            ],
            [
                'category' => $category,
                'severity' => FindingSeverity::Critical->value,
                'headline' => $headline,
                'detail' => $detail,
                'surfaced_at' => now(),
            ],
        );

        if ($redFlag->wasRecentlyCreated) {
            $this->notify($redFlag);
        }

        return $redFlag;
    }

    private function notify(RedFlag $redFlag): void
    {
        $recipients = $this->alertRecipients((string) $redFlag->client_id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new RedFlagUrgentNotification($redFlag));
    }

    /**
     * @return Collection<int, User>
     */
    private function alertRecipients(string $clientId): Collection
    {
        $superAdmins = User::query()
            ->where('user_type', User::TYPE_SUPER_ADMIN)
            ->get();

        $advisors = ClientTeamMember::query()
            ->with('user')
            ->where('client_id', $clientId)
            ->get()
            ->pluck('user')
            ->filter(fn (mixed $user): bool => $user instanceof User && $user->user_type === User::TYPE_ADVISOR)
            ->values();

        return $superAdmins
            ->merge($advisors)
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->values();
    }

    private function categoryFor(string $module): string
    {
        return match ($module) {
            AnalysisModule::Financial->value => RedFlag::CATEGORY_FINANCIAL,
            AnalysisModule::Compliance->value => RedFlag::CATEGORY_COMPLIANCE,
            AnalysisModule::RegulatoryImpact->value => RedFlag::CATEGORY_REGULATORY,
            AnalysisModule::InsuranceRisk->value => RedFlag::CATEGORY_INSURANCE,
            AnalysisModule::Hr->value, AnalysisModule::Succession->value => RedFlag::CATEGORY_KEY_PERSON,
            default => RedFlag::CATEGORY_VIABILITY,
        };
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
