<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningLayerRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class LayerCadenceRegistry
{
    public const CADENCE_HOURLY = 'hourly';

    public const CADENCE_DAILY = 'daily';

    public const CADENCE_WEEKLY = 'weekly';

    public const CADENCE_MONTHLY = 'monthly';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function definitions(): Collection
    {
        return collect([
            $this->layer(1, 'AI output quality review', self::CADENCE_DAILY, 30),
            $this->layer(2, 'Data quality pattern review', self::CADENCE_DAILY, 30),
            $this->layer(3, 'Bias monitoring', self::CADENCE_DAILY, 30, 'analysis:bias-calibration'),
            $this->layer(4, 'Analysis calibration review', self::CADENCE_WEEKLY, 60),
            $this->layer(5, 'Document verification calibration', self::CADENCE_WEEKLY, 60),
            $this->layer(6, 'Client lifecycle signal review', self::CADENCE_DAILY, 30),
            $this->layer(7, 'Red flag accuracy review', self::CADENCE_DAILY, 30),
            $this->layer(8, 'Report wording review', self::CADENCE_WEEKLY, 90),
            $this->layer(9, 'Proposal economics review', self::CADENCE_WEEKLY, 90),
            $this->layer(10, 'Notification delivery review', self::CADENCE_DAILY, 30),
            $this->layer(11, 'Advisor feedback learning', self::CADENCE_DAILY, 30, 'analysis:feedback-learning'),
            $this->layer(12, 'Economic indicator refresh', self::CADENCE_DAILY, 7, 'economic-indicators:refresh'),
            $this->layer(13, 'Valuation multiple refresh', self::CADENCE_WEEKLY, 30, 'valuation-multiples:refresh'),
            $this->layer(14, 'Legislative currency monitor', self::CADENCE_DAILY, 30, 'compliance:legislative-currency'),
            $this->layer(15, 'Funnel analytics suggestions', self::CADENCE_MONTHLY, 90, 'analytics:funnel-learning'),
            $this->layer(16, 'Questionnaire optimisation', self::CADENCE_WEEKLY, 90, 'questionnaires:optimisation-learning'),
            $this->layer(17, 'Coach referral calibration', self::CADENCE_WEEKLY, 90, 'coach:signal-calibration-learning'),
            $this->layer(18, 'Entrepreneur rating framework review', self::CADENCE_MONTHLY, 120, 'learning:rating-validity-tests'),
            $this->layer(19, 'Entrepreneur assessment feedback', self::CADENCE_WEEKLY, 90, 'learning:conversion-outcomes'),
            $this->layer(20, 'Entrepreneur guidance quality', self::CADENCE_WEEKLY, 90, 'learning:plan-quality-benchmarks'),
            $this->layer(21, 'DD risk pattern review', self::CADENCE_WEEKLY, 90, 'learning:dd-learning'),
            $this->layer(22, 'Post-acquisition migration review', self::CADENCE_WEEKLY, 90),
            $this->layer(23, 'Payment failure pattern review', self::CADENCE_DAILY, 30),
            $this->layer(24, 'Referral conversion review', self::CADENCE_WEEKLY, 90),
            $this->layer(25, 'Wellbeing trend calibration', self::CADENCE_WEEKLY, 90),
            $this->layer(26, 'Knowledge assessment gap review', self::CADENCE_WEEKLY, 90),
            $this->layer(27, 'Scenario assumption review', self::CADENCE_WEEKLY, 90),
            $this->layer(28, 'Succession readiness review', self::CADENCE_MONTHLY, 180),
            $this->layer(29, 'Integration degradation review', self::CADENCE_HOURLY, 7),
            $this->layer(30, 'Document expiry pattern review', self::CADENCE_DAILY, 60),
            $this->layer(31, 'Communication preference review', self::CADENCE_WEEKLY, 90),
            $this->layer(32, 'Terms comprehension feedback', self::CADENCE_MONTHLY, 180),
            $this->layer(33, 'Template suggestions', self::CADENCE_WEEKLY, 90, 'templates:suggest'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(int $layerId): array
    {
        $definition = $this->definitions()->firstWhere('id', $layerId);

        if (! is_array($definition)) {
            throw new \InvalidArgumentException("Unknown learning layer [{$layerId}].");
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function isDue(array $definition, CarbonInterface $at, ?LearningLayerRun $latestRun = null): bool
    {
        if (! $latestRun instanceof LearningLayerRun || ! $latestRun->ran_at instanceof CarbonInterface) {
            return true;
        }

        return match ($definition['cadence']) {
            self::CADENCE_HOURLY => $latestRun->ran_at->lte($at->copy()->subHour()),
            self::CADENCE_DAILY => $latestRun->ran_at->lte($at->copy()->subDay()),
            self::CADENCE_WEEKLY => $latestRun->ran_at->lte($at->copy()->subWeek()),
            self::CADENCE_MONTHLY => $latestRun->ran_at->lte($at->copy()->subMonth()),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function layer(int $id, string $name, string $cadence, int $windowDays, ?string $command = null): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'cadence' => $cadence,
            'window_days' => $windowDays,
            'command' => $command,
            'governed_candidates_only' => true,
        ];
    }
}
