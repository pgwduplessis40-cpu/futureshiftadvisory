<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\PlanAssessment;
use Illuminate\Support\Str;

final class PlanBudgetActionMessage
{
    public function __construct(private readonly PlanBudgetActionMapper $actionMapper) {}

    /**
     * @return list<array{key:string,severity:string,title:string,destination:string,steps:list<string>}>
     */
    public function actions(PlanAssessment $assessment): array
    {
        $coherence = data_get($assessment->scoring_scope, 'plan_budget_coherence');
        if (! is_array($coherence)) {
            return [];
        }

        return $this->actionsForCoherence($coherence);
    }

    /**
     * Rebuild actions from the saved diagnostics whenever they are present.
     *
     * Assessments persist findings as the audit record, while action text is
     * presentation guidance. Mapping the findings at read time keeps historic
     * reviews aligned with current founder guidance, including autosave.
     *
     * @param  array{approval_available?:bool,actions?:array<int, mixed>|string,findings?:array<int, mixed>|string}  $coherence
     * @return list<array{key:string,severity:string,title:string,destination:string,steps:list<string>}>
     */
    public function actionsForCoherence(array $coherence): array
    {
        if ((bool) ($coherence['approval_available'] ?? true)) {
            return [];
        }

        $findings = $this->legacyFindings($coherence);
        if ($findings !== []) {
            return $this->actionMapper->map($findings);
        }

        $normalised = [];
        $actions = $coherence['actions'] ?? [];
        if (is_array($actions)) {
            foreach ($actions as $action) {
                if (! is_array($action)) {
                    continue;
                }

                $title = trim((string) ($action['title'] ?? ''));
                $destination = trim((string) ($action['destination'] ?? ''));
                $steps = collect((array) ($action['steps'] ?? []))
                    ->map(fn (mixed $step): string => trim((string) $step))
                    ->filter()
                    ->values()
                    ->all();

                if ($title === '' || $destination === '' || $steps === []) {
                    continue;
                }

                $normalised[] = [
                    'key' => trim((string) ($action['key'] ?? $title)),
                    'severity' => trim((string) ($action['severity'] ?? 'review')),
                    'title' => $title,
                    'destination' => $destination,
                    'steps' => $steps,
                ];
            }
        }

        $normalised = collect($normalised)
            ->unique(fn (array $action): string => Str::lower($action['key']))
            ->values()
            ->all();

        if ($normalised !== []) {
            return $normalised;
        }

        return [];
    }

    /**
     * @param  list<array{key:string,severity:string,title:string,destination:string,steps:list<string>}>  $actions
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
                ]);
            })
            ->implode("\n\n");
    }

    /**
     * Older assessments predate the canonical action payload. Convert their
     * saved diagnostics through the same mapper, so current and historical
     * reviews produce one consistent set of founder instructions.
     *
     * @param  array{approval_available?:bool,actions?:array<int, mixed>|string,findings?:array<int, mixed>|string}  $coherence
     * @return list<array{category:'budget_support'|'plan_correlation',severity:'missing'|'review',message:string,next_action:string}>
     */
    private function legacyFindings(array $coherence): array
    {
        $findings = $coherence['findings'] ?? [];
        if (! is_array($findings)) {
            return [];
        }

        $normalised = [];
        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            $category = (string) ($finding['category'] ?? '');
            $message = trim((string) ($finding['message'] ?? ''));
            if (! in_array($category, ['budget_support', 'plan_correlation'], true) || $message === '') {
                continue;
            }

            $severity = (string) ($finding['severity'] ?? 'review');

            $normalised[] = [
                'category' => $category,
                'severity' => $severity === 'missing' ? 'missing' : 'review',
                'message' => $message,
                'next_action' => trim((string) ($finding['next_action'] ?? 'Update the matching Budget input.')),
            ];
        }

        return collect($normalised)
            ->unique(fn (array $finding): string => Str::lower(
                $finding['category'].'|'.$finding['message'].'|'.$finding['next_action'],
            ))
            ->values()
            ->all();
    }
}
