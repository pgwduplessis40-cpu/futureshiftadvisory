<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\IndustryIntelligenceSignal;
use App\Models\LearningUpdate;
use App\Models\SharedIntelligencePattern;
use App\Services\Audit\AuditWriter;
use App\Services\Privacy\CohortGuard;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SharedLayer
{
    public const DOMAIN_ADVISORY = 'advisory';

    public const DOMAIN_ENTREPRENEUR = 'entrepreneur';

    public function __construct(
        private readonly CohortGuard $cohortGuard,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return Collection<int, SharedIntelligencePattern>
     */
    public function run(?CarbonInterface $generatedAt = null): Collection
    {
        $generatedAt ??= now()->addMinute();
        $this->context->apply('system', []);

        return DB::transaction(function () use ($generatedAt): Collection {
            return $this->advisoryToEntrepreneur($generatedAt)
                ->merge($this->entrepreneurToAdvisory($generatedAt))
                ->values();
        });
    }

    /**
     * @return Collection<int, SharedIntelligencePattern>
     */
    private function advisoryToEntrepreneur(CarbonInterface $generatedAt): Collection
    {
        return IndustryIntelligenceSignal::query()
            ->where('suppressed', false)
            ->oldest('generated_at')
            ->get()
            ->map(fn (IndustryIntelligenceSignal $signal): ?SharedIntelligencePattern => $this->createPattern(
                sourceDomain: self::DOMAIN_ADVISORY,
                targetDomain: self::DOMAIN_ENTREPRENEUR,
                sourceKey: 'industry_intelligence:'.$signal->signal_key,
                cohortSize: $signal->cohort_size,
                generatedAt: $generatedAt,
                pattern: [
                    'source' => 'cross_client_industry_signal',
                    'industry_code' => $signal->industry_code,
                    'signal_type' => $signal->signal_type,
                    'pattern' => data_get($signal->aggregate, 'pattern'),
                    'distribution' => data_get($signal->aggregate, 'severity_distribution', []),
                    'privacy' => $signal->aggregate['privacy'] ?? $this->cohortGuard->privacyMetadata(),
                ],
            ))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, SharedIntelligencePattern>
     */
    private function entrepreneurToAdvisory(CarbonInterface $generatedAt): Collection
    {
        return LearningUpdate::query()
            ->where('source->type', 'plan_quality_benchmarks')
            ->oldest('created_at')
            ->get()
            ->map(function (LearningUpdate $update) use ($generatedAt): ?SharedIntelligencePattern {
                $benchmark = data_get($update->evidence, 'benchmark', []);
                $cohortSize = (int) data_get($benchmark, 'cohort_size', 0);

                return $this->createPattern(
                    sourceDomain: self::DOMAIN_ENTREPRENEUR,
                    targetDomain: self::DOMAIN_ADVISORY,
                    sourceKey: 'plan_quality_benchmark:'.(string) ($update->source['signal_key'] ?? $update->id),
                    cohortSize: $cohortSize,
                    generatedAt: $generatedAt,
                    pattern: [
                        'source' => 'entrepreneur_plan_quality_benchmark',
                        'industry' => data_get($benchmark, 'industry'),
                        'average_score' => data_get($benchmark, 'average_score'),
                        'distribution' => data_get($benchmark, 'distribution', []),
                        'grade_distribution' => data_get($benchmark, 'grade_distribution', []),
                        'privacy' => data_get($benchmark, 'privacy', $this->cohortGuard->privacyMetadata()),
                    ],
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $pattern
     */
    private function createPattern(
        string $sourceDomain,
        string $targetDomain,
        string $sourceKey,
        int $cohortSize,
        CarbonInterface $generatedAt,
        array $pattern,
    ): ?SharedIntelligencePattern {
        if (! $this->cohortGuard->allows($cohortSize)) {
            return null;
        }

        $signalKey = hash('sha256', implode('|', [$sourceDomain, $targetDomain, $sourceKey]));

        if (SharedIntelligencePattern::query()->where('signal_key', $signalKey)->exists()) {
            return null;
        }

        /** @var SharedIntelligencePattern $shared */
        $shared = SharedIntelligencePattern::query()->create([
            'source_domain' => $sourceDomain,
            'target_domain' => $targetDomain,
            'signal_key' => $signalKey,
            'pattern' => [
                ...$this->cohortGuard->sanitise($pattern),
                'aggregate_only' => true,
                'privacy' => $this->cohortGuard->privacyMetadata(),
            ],
            'cohort_size' => $cohortSize,
            'generated_at' => $generatedAt,
        ]);

        $this->audit->record('intelligence.shared_pattern_generated', subject: $shared, after: [
            'source_domain' => $sourceDomain,
            'target_domain' => $targetDomain,
            'cohort_size' => $cohortSize,
            'aggregate_only' => true,
        ]);

        return $shared;
    }
}
