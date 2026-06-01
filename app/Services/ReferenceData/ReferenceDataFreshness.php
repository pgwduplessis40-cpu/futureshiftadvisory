<?php

declare(strict_types=1);

namespace App\Services\ReferenceData;

use App\Models\EconomicIndicator;
use App\Models\LearningUpdate;
use App\Models\ReferenceDataEntry;
use App\Models\User;
use App\Notifications\ReferenceDataStaleNotification;
use Carbon\CarbonInterface;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

final class ReferenceDataFreshness
{
    public const STATUS_FRESH = 'fresh';

    public const STATUS_DUE_SOON = 'due_soon';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_MISSING = 'missing';

    private const DUE_SOON_DAYS = 14;

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?CarbonInterface $at = null): array
    {
        $tasks = $this->tasks($at);

        return [
            'summary' => [
                'total' => $tasks->count(),
                'fresh' => $tasks->where('status', self::STATUS_FRESH)->count(),
                'due_soon' => $tasks->where('status', self::STATUS_DUE_SOON)->count(),
                'overdue' => $tasks->where('status', self::STATUS_OVERDUE)->count(),
                'missing' => $tasks->where('status', self::STATUS_MISSING)->count(),
            ],
            'index_url' => route('admin.reference-data.index', absolute: false),
            'items' => $tasks
                ->reject(fn (array $task): bool => $task['status'] === self::STATUS_FRESH)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function tasks(?CarbonInterface $at = null): Collection
    {
        $at ??= now();

        return collect($this->definitions())
            ->map(fn (array $definition): array => $this->task($definition, $at))
            ->sortBy(fn (array $task): array => [
                $this->statusRank((string) $task['status']),
                $task['due_at'] ?? '9999-12-31',
                $task['label'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    public function syncNotifications(Collection $recipients, ?CarbonInterface $at = null): int
    {
        $dashboard = $this->dashboard($at);
        $activeTasks = collect($dashboard['items']);
        $activeKeys = $activeTasks
            ->pluck('key')
            ->filter()
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();
        $sent = 0;

        $recipients->each(function (User $user) use ($activeTasks, $activeKeys, &$sent): void {
            $this->clearResolvedNotifications($user, $activeKeys);

            foreach ($activeTasks as $task) {
                if (! is_array($task) || $this->hasUnreadNotification($user, (string) $task['key'])) {
                    continue;
                }

                Notification::send($user, new ReferenceDataStaleNotification($task));
                $sent++;
            }
        });

        return $sent;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'key' => 'economic_indicator:ocr',
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'indicator' => EconomicIndicator::OCR,
                'label' => 'OCR reference rate',
                'cadence_days' => 45,
            ],
            [
                'key' => 'economic_indicator:cpi_annual',
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'indicator' => EconomicIndicator::CPI_ANNUAL,
                'label' => 'CPI annual',
                'cadence_days' => 100,
            ],
            [
                'key' => 'economic_indicator:gdp_quarterly',
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'indicator' => EconomicIndicator::GDP_QUARTERLY,
                'label' => 'GDP quarterly',
                'cadence_days' => 100,
            ],
            [
                'key' => 'economic_indicator:unemployment_rate',
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'indicator' => EconomicIndicator::UNEMPLOYMENT_RATE,
                'label' => 'Unemployment rate',
                'cadence_days' => 100,
            ],
            [
                'key' => ReferenceDataEntry::DATASET_VALUATION_MULTIPLE,
                'dataset' => ReferenceDataEntry::DATASET_VALUATION_MULTIPLE,
                'label' => 'Valuation multiples',
                'cadence_days' => 100,
            ],
            [
                'key' => ReferenceDataEntry::DATASET_INDUSTRY_WACC,
                'dataset' => ReferenceDataEntry::DATASET_INDUSTRY_WACC,
                'label' => 'Industry WACC',
                'cadence_days' => 100,
            ],
            [
                'key' => ReferenceDataEntry::DATASET_CPB_BENCHMARK,
                'dataset' => ReferenceDataEntry::DATASET_CPB_BENCHMARK,
                'label' => 'Cost-per-beneficiary benchmarks',
                'cadence_days' => 365,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function task(array $definition, CarbonInterface $at): array
    {
        $entry = $this->latestImplementedEntry($definition);
        $cadenceDays = (int) $definition['cadence_days'];
        $lastAsAt = $entry?->as_at;
        $dueAt = $lastAsAt?->copy()->addDays($cadenceDays);
        $status = $this->status($lastAsAt, $dueAt, $at);

        return [
            'key' => (string) $definition['key'],
            'dataset' => (string) $definition['dataset'],
            'indicator' => isset($definition['indicator']) ? (string) $definition['indicator'] : null,
            'label' => (string) $definition['label'],
            'status' => $status,
            'cadence_days' => $cadenceDays,
            'last_as_at' => $lastAsAt?->toDateString(),
            'due_at' => $dueAt?->toDateString(),
            'source' => $entry?->source,
            'entry_id' => $entry?->id,
            'action_url' => route('admin.reference-data.index', absolute: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function latestImplementedEntry(array $definition): ?ReferenceDataEntry
    {
        if (! Schema::hasTable('reference_data_entries') || ! Schema::hasTable('learning_updates')) {
            return null;
        }

        $entries = ReferenceDataEntry::query()
            ->where('dataset', (string) $definition['dataset'])
            ->whereHas('learningUpdate', fn ($query) => $query->where('status', LearningUpdate::STATUS_IMPLEMENTED))
            ->latest('as_at')
            ->latest()
            ->limit(250)
            ->get();

        if (! isset($definition['indicator'])) {
            return $entries->first();
        }

        return $entries
            ->first(fn (ReferenceDataEntry $entry): bool => (string) data_get($entry->payload, 'indicator') === (string) $definition['indicator']);
    }

    private function status(?CarbonInterface $lastAsAt, ?CarbonInterface $dueAt, CarbonInterface $at): string
    {
        if (! $lastAsAt instanceof CarbonInterface || ! $dueAt instanceof CarbonInterface) {
            return self::STATUS_MISSING;
        }

        if ($dueAt->lessThanOrEqualTo($at)) {
            return self::STATUS_OVERDUE;
        }

        if ($dueAt->diffInDays($at, absolute: true) <= self::DUE_SOON_DAYS) {
            return self::STATUS_DUE_SOON;
        }

        return self::STATUS_FRESH;
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            self::STATUS_MISSING => 0,
            self::STATUS_OVERDUE => 1,
            self::STATUS_DUE_SOON => 2,
            default => 3,
        };
    }

    private function hasUnreadNotification(User $user, string $key): bool
    {
        return $user->notifications()
            ->where('type', 'reference_data.stale')
            ->whereNull('read_at')
            ->get()
            ->contains(fn (DatabaseNotification $notification): bool => (string) data_get($notification->data, 'dataset_key') === $key);
    }

    /**
     * @param  array<int, string>  $activeKeys
     */
    private function clearResolvedNotifications(User $user, array $activeKeys): void
    {
        $user->notifications()
            ->where('type', 'reference_data.stale')
            ->whereNull('read_at')
            ->get()
            ->each(function (DatabaseNotification $notification) use ($activeKeys): void {
                $key = (string) data_get($notification->data, 'dataset_key');
                if (! in_array($key, $activeKeys, true)) {
                    $notification->markAsRead();
                }
            });
    }
}
