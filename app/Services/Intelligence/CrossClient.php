<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Enums\AnalysisModule;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\IndustryIntelligenceSignal;
use App\Models\User;
use App\Notifications\CrossClientIntelligenceNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Privacy\CohortGuard;
use App\Support\RequestContext;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class CrossClient
{
    public const SIGNAL_REPEATED_FINDING_PATTERN = 'repeated_finding_pattern';

    public function __construct(
        private readonly CohortGuard $cohortGuard,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return Collection<int, IndustryIntelligenceSignal>
     */
    public function run(int $windowDays = 90, ?CarbonInterface $generatedAt = null): Collection
    {
        $windowDays = max(1, $windowDays);
        $generatedAt ??= now()->addMinute();
        $windowStart = $generatedAt->copy()->subDays($windowDays);
        $period = $generatedAt->format('Y-m');

        $this->context->apply('system', []);

        return DB::transaction(function () use ($windowStart, $generatedAt, $period): Collection {
            $created = collect();

            foreach ($this->patternGroups($windowStart, $generatedAt) as $group) {
                $clientIds = $group['client_ids'];
                $cohortSize = count($clientIds);
                $signalKey = $this->signalKey((string) $group['industry_code'], (string) $group['pattern'], $period);

                if (IndustryIntelligenceSignal::query()->where('signal_key', $signalKey)->exists()) {
                    continue;
                }

                $aggregate = $this->cohortGuard->releaseAggregate(
                    cohortSize: $cohortSize,
                    aggregate: [
                        'pattern' => $group['pattern'],
                        'finding_count' => $group['finding_count'],
                        'severity_distribution' => $group['severity_distribution'],
                        'module_distribution' => $group['module_distribution'],
                    ],
                    suppressedMessage: 'Industry pattern suppressed below the minimum cohort.',
                    metadata: [
                        'industry_code' => $group['industry_code'],
                        'signal_type' => self::SIGNAL_REPEATED_FINDING_PATTERN,
                    ],
                );

                /** @var IndustryIntelligenceSignal $signal */
                $signal = IndustryIntelligenceSignal::query()->create([
                    'industry_code' => $group['industry_code'],
                    'signal_type' => self::SIGNAL_REPEATED_FINDING_PATTERN,
                    'signal_key' => $signalKey,
                    'aggregate' => $aggregate,
                    'cohort_size' => $cohortSize,
                    'generated_at' => $generatedAt,
                    'suppressed' => (bool) ($aggregate['suppressed'] ?? true),
                ]);

                if (! $signal->suppressed) {
                    $this->alert($signal, $clientIds);
                    $signal->forceFill(['alerted_at' => now()])->save();
                }

                $this->audit->record('intelligence.cross_client_signal_generated', subject: $signal, after: [
                    'industry_code' => $signal->industry_code,
                    'signal_type' => $signal->signal_type,
                    'cohort_size' => $signal->cohort_size,
                    'suppressed' => $signal->suppressed,
                    'aggregate_only' => true,
                ]);

                $created->push($signal->refresh());
            }

            return $created->values();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function patternGroups(CarbonInterface $windowStart, CarbonInterface $windowEnd): Collection
    {
        return AnalysisFinding::query()
            ->with(['client', 'run'])
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereHas('run', fn ($query) => $query->where('module', '!=', AnalysisModule::DdWorkstream->value))
            ->oldest('created_at')
            ->get()
            ->filter(fn (AnalysisFinding $finding): bool => $finding->client instanceof Client)
            ->groupBy(fn (AnalysisFinding $finding): string => $this->industry($finding->client).'|'.$this->pattern($finding->title))
            ->map(function (Collection $findings, string $key): array {
                [$industry, $pattern] = explode('|', $key, 2);

                return [
                    'industry_code' => $industry,
                    'pattern' => $pattern,
                    'client_ids' => $findings->pluck('client_id')->filter()->unique()->values()->all(),
                    'finding_count' => $findings->count(),
                    'severity_distribution' => $findings
                        ->map(fn (AnalysisFinding $finding): string => $this->enumValue($finding->severity))
                        ->countBy()
                        ->all(),
                    'module_distribution' => $findings
                        ->map(fn (AnalysisFinding $finding): string => $this->enumValue($finding->run?->module))
                        ->filter()
                        ->countBy()
                        ->all(),
                ];
            })
            ->filter(fn (array $group): bool => trim((string) $group['pattern']) !== '')
            ->values();
    }

    /**
     * @param  array<int, string>  $clientIds
     */
    private function alert(IndustryIntelligenceSignal $signal, array $clientIds): void
    {
        $recipients = $clientIds === []
            ? new EloquentCollection
            : ClientTeamMember::query()
                ->with('user')
                ->whereIn('client_id', $clientIds)
                ->get()
                ->pluck('user')
                ->filter(fn (mixed $user): bool => $user instanceof User && $user->user_type === User::TYPE_ADVISOR)
                ->unique(fn (User $user): int => (int) $user->getKey())
                ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CrossClientIntelligenceNotification($signal));
    }

    private function signalKey(string $industryCode, string $pattern, string $period): string
    {
        return hash('sha256', implode('|', [self::SIGNAL_REPEATED_FINDING_PATTERN, $industryCode, $pattern, $period]));
    }

    private function industry(Client $client): string
    {
        foreach (['industry_code', 'industry', 'sector'] as $key) {
            $value = data_get($client->registry_sources, $key);

            if (is_string($value) && trim($value) !== '') {
                return $this->normalise($value);
            }
        }

        return $this->normalise($this->enumValue($client->engagement_type));
    }

    private function pattern(string $title): string
    {
        return $this->normalise($title);
    }

    private function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($value)));
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
