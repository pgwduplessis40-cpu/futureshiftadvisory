<?php

declare(strict_types=1);

namespace App\Services\Pv;

final class PvWaterfallReportChart
{
    /**
     * @param  array<int, array{key:string, label:string, kind:string, value:float|int, start:float|int, end:float|int}>  $steps
     */
    public function render(array $steps): string
    {
        return view('reports.partials.pv-waterfall-chart', [
            'steps' => $this->prepare($steps),
        ])->render();
    }

    /**
     * @param  array<int, array{key:string, label:string, kind:string, value:float|int, start:float|int, end:float|int}>  $steps
     * @return array<int, array{key:string, label:string, value:string, left:float, width:float, color:string}>
     */
    private function prepare(array $steps): array
    {
        $max = max(1.0, ...array_map(
            fn (array $step): float => max((float) $step['start'], (float) $step['end'], (float) $step['value']),
            $steps,
        ));

        return array_map(function (array $step) use ($max): array {
            $start = (float) $step['start'];
            $end = (float) $step['end'];
            $value = (float) $step['value'];
            $width = abs($end - $start);

            if ($width === 0.0) {
                $width = abs($value);
            }

            return [
                'key' => (string) $step['key'],
                'label' => (string) $step['label'],
                'value' => $this->formatCurrency($value),
                'left' => max(0.0, $start) / $max * 100,
                'width' => max(1.0, $width / $max * 100),
                'color' => $this->color((string) $step['kind']),
            ];
        }, $steps);
    }

    private function color(string $kind): string
    {
        return match ($kind) {
            'total' => '#111827',
            'decrease' => '#e11d48',
            'increase' => '#10b981',
            default => '#0ea5e9',
        };
    }

    private function formatCurrency(float $value): string
    {
        return 'NZD '.number_format($value, 0);
    }
}
