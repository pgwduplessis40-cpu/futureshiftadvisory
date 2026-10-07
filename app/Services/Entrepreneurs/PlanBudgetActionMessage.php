<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\PlanAssessment;
use Illuminate\Support\Str;

final class PlanBudgetActionMessage
{
    /**
     * @return list<array{key:string,severity:string,title:string,destination:string,steps:list<string>,completion:string}>
     */
    public function actions(PlanAssessment $assessment): array
    {
        $coherence = data_get($assessment->scoring_scope, 'plan_budget_coherence');
        if (! is_array($coherence) || (bool) ($coherence['approval_available'] ?? true)) {
            return [];
        }

        $actions = $coherence['actions'] ?? [];
        if (! is_array($actions)) {
            return [];
        }

        $normalised = [];
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $title = trim((string) ($action['title'] ?? ''));
            $destination = trim((string) ($action['destination'] ?? ''));
            $completion = trim((string) ($action['completion'] ?? ''));
            $steps = collect((array) ($action['steps'] ?? []))
                ->map(fn (mixed $step): string => trim((string) $step))
                ->filter()
                ->values()
                ->all();

            if ($title === '' || $destination === '' || $completion === '' || $steps === []) {
                continue;
            }

            $normalised[] = [
                'key' => trim((string) ($action['key'] ?? $title)),
                'severity' => trim((string) ($action['severity'] ?? 'review')),
                'title' => $title,
                'destination' => $destination,
                'steps' => $steps,
                'completion' => $completion,
            ];
        }

        return collect($normalised)
            ->unique(fn (array $action): string => Str::lower($action['key']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array{key:string,severity:string,title:string,destination:string,steps:list<string>,completion:string}>  $actions
     */
    public function format(array $actions): string
    {
        return collect($actions)
            ->values()
            ->map(function (array $action, int $index): string {
                return implode("\n", [
                    ($index + 1).'. '.$action['title'],
                    'Go to: '.$action['destination'],
                    'Do this:',
                    ...collect($action['steps'])
                        ->map(fn (string $step): string => '- '.$step)
                        ->all(),
                    'When done: '.$action['completion'],
                ]);
            })
            ->implode("\n\n");
    }
}
