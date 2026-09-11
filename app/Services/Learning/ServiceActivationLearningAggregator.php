<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningUpdate;
use App\Models\ServiceActivation;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ServiceActivationLearningAggregator
{
    public const WINDOW_DAYS = 28;

    public const MINIMUM_REQUESTS = 10;

    public const MATERIAL_RATE_DECLINE = 0.20;

    public function __construct(private readonly AuditWriter $audit) {}

    public function run(?CarbonInterface $at = null): int
    {
        $at ??= now();
        $currentStart = $at->copy()->subDays(self::WINDOW_DAYS);
        $baselineStart = $currentStart->copy()->subDays(self::WINDOW_DAYS);
        $created = 0;

        foreach ($this->serviceTypes($baselineStart, $at) as $serviceType) {
            $current = $this->metrics($serviceType, $currentStart, $at);
            $baseline = $this->metrics($serviceType, $baselineStart, $currentStart);

            foreach ($this->regressions($current, $baseline) as $regression) {
                if ($this->recordCandidate($serviceType, $currentStart, $at, $current, $baseline, $regression)) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * @return Collection<int, string>
     */
    private function serviceTypes(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return ServiceActivation::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereNotNull('service_type')
            ->distinct()
            ->orderBy('service_type')
            ->pluck('service_type');
    }

    /**
     * @return array{requests:int,package_selected:int,payment_completed:int,accepted:int,package_selection_rate:float,payment_completion_rate:float,acceptance_rate:float}
     */
    private function metrics(string $serviceType, CarbonInterface $start, CarbonInterface $end): array
    {
        $activations = ServiceActivation::query()
            ->where('service_type', $serviceType)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->get([
                'service_rate_package_id',
                'payment_completed_at',
                'accepted_at',
            ]);

        $requests = $activations->count();
        $packageSelected = $activations->filter(fn (ServiceActivation $activation): bool => $activation->service_rate_package_id !== null)->count();
        $paymentCompleted = $activations->filter(fn (ServiceActivation $activation): bool => $activation->payment_completed_at !== null)->count();
        $accepted = $activations->filter(fn (ServiceActivation $activation): bool => $activation->accepted_at !== null)->count();

        return [
            'requests' => $requests,
            'package_selected' => $packageSelected,
            'payment_completed' => $paymentCompleted,
            'accepted' => $accepted,
            'package_selection_rate' => $this->rate($packageSelected, $requests),
            'payment_completion_rate' => $this->rate($paymentCompleted, $packageSelected),
            'acceptance_rate' => $this->rate($accepted, $paymentCompleted),
        ];
    }

    /**
     * @param  array{requests:int,package_selected:int,payment_completed:int,accepted:int,package_selection_rate:float,payment_completion_rate:float,acceptance_rate:float}  $current
     * @param  array{requests:int,package_selected:int,payment_completed:int,accepted:int,package_selection_rate:float,payment_completion_rate:float,acceptance_rate:float}  $baseline
     * @return array<int, array{stage:string,denominator:string,current_rate:float,baseline_rate:float,decline:float}>
     */
    private function regressions(array $current, array $baseline): array
    {
        $stages = [
            ['stage' => 'package_selection', 'denominator' => 'requests', 'rate' => 'package_selection_rate'],
            ['stage' => 'payment_completion', 'denominator' => 'package_selected', 'rate' => 'payment_completion_rate'],
            ['stage' => 'acceptance', 'denominator' => 'payment_completed', 'rate' => 'acceptance_rate'],
        ];

        return collect($stages)
            ->filter(function (array $stage) use ($current, $baseline): bool {
                $denominator = $stage['denominator'];
                $rate = $stage['rate'];

                return $current[$denominator] >= self::MINIMUM_REQUESTS
                    && $baseline[$denominator] >= self::MINIMUM_REQUESTS
                    && ($baseline[$rate] - $current[$rate]) >= self::MATERIAL_RATE_DECLINE;
            })
            ->map(fn (array $stage): array => [
                'stage' => $stage['stage'],
                'denominator' => $stage['denominator'],
                'current_rate' => $current[$stage['rate']],
                'baseline_rate' => $baseline[$stage['rate']],
                'decline' => $baseline[$stage['rate']] - $current[$stage['rate']],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{requests:int,package_selected:int,payment_completed:int,accepted:int,package_selection_rate:float,payment_completion_rate:float,acceptance_rate:float}  $current
     * @param  array{requests:int,package_selected:int,payment_completed:int,accepted:int,package_selection_rate:float,payment_completion_rate:float,acceptance_rate:float}  $baseline
     * @param  array{stage:string,denominator:string,current_rate:float,baseline_rate:float,decline:float}  $regression
     */
    private function recordCandidate(
        string $serviceType,
        CarbonInterface $currentStart,
        CarbonInterface $at,
        array $current,
        array $baseline,
        array $regression,
    ): bool {
        $signalKey = hash('sha256', implode('|', [
            'service_activation_funnel',
            $serviceType,
            $regression['stage'],
        ]));

        $existing = LearningUpdate::query()
            ->where('layer_id', LayerCadenceRegistry::LAYER_SERVICE_ACTIVATION)
            ->whereIn('status', [
                LearningUpdate::STATUS_DETECTED,
                LearningUpdate::STATUS_STAGED,
                LearningUpdate::STATUS_DEFERRED,
                LearningUpdate::STATUS_APPROVED,
            ])
            ->where('source->type', 'service_activation_funnel')
            ->where('source->service_type', $serviceType)
            ->where('source->stage', $regression['stage'])
            ->exists();

        if ($existing) {
            return false;
        }

        $label = ServiceActivation::query()
            ->where('service_type', $serviceType)
            ->first()?->clientLabel() ?? Str::headline($serviceType);

        $candidate = LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_SERVICE_ACTIVATION,
            'source' => [
                'type' => 'service_activation_funnel',
                'signal_key' => $signalKey,
                'service_type' => $serviceType,
                'stage' => $regression['stage'],
                'rollup_key' => 'service_activation:funnel:client_portal_workspace_activation',
                'rollup_label' => 'Service activation funnel',
            ],
            'summary' => sprintf(
                'Service activation funnel regression detected for %s at %s.',
                $label,
                Str::headline($regression['stage']),
            ),
            'proposed_change' => [
                'action' => 'review_service_activation_funnel',
                'automatic_application' => false,
                'requires_approval' => true,
                'candidate_surfaces' => [
                    'service_start_cards',
                    'advisor_package_selection',
                    'client_fee_acceptance',
                ],
            ],
            'impact_scope' => [
                'module' => 'service_activation',
                'surface' => 'client_portal_workspace_activation',
                'governance_gate' => 'advisor_or_admin_review_required',
                'direct_write_policy' => 'no_auto_pricing_scope_or_advice_changes',
            ],
            'clients_affected' => $current[$regression['denominator']],
            'magnitude' => $regression['decline'] >= 0.35 ? 'medium' : 'low',
            'confidence' => min(0.95, 0.55 + min(20, $current[$regression['denominator']]) / 100),
            'evidence' => [
                'window_start' => $currentStart->toIso8601String(),
                'window_end' => $at->toIso8601String(),
                'window_days' => self::WINDOW_DAYS,
                'minimum_requests' => self::MINIMUM_REQUESTS,
                'material_rate_decline' => self::MATERIAL_RATE_DECLINE,
                'stage' => $regression['stage'],
                'current' => $current,
                'baseline' => $baseline,
                'current_rate' => $regression['current_rate'],
                'baseline_rate' => $regression['baseline_rate'],
                'rate_decline' => $regression['decline'],
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $candidate, after: [
            'layer_id' => LayerCadenceRegistry::LAYER_SERVICE_ACTIVATION,
            'source_type' => 'service_activation_funnel',
            'signal_key' => $signalKey,
            'automatic_application' => false,
        ]);

        return true;
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : round($numerator / $denominator, 4);
    }
}
