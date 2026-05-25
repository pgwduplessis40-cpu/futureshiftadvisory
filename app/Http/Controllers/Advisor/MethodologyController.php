<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Support\Methodology\MethodologyEntry;
use App\Support\Methodology\MethodologyRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class MethodologyController extends Controller
{
    public function __construct(private readonly MethodologyRegistry $registry) {}

    public function index(Request $request): Response
    {
        $query = Str::lower(trim((string) $request->query('q', '')));
        $entries = collect($this->registry->all());

        if ($query !== '') {
            $entries = $entries->filter(fn (MethodologyEntry $entry): bool => Str::contains(
                Str::lower(implode(' ', [
                    $entry->id,
                    $entry->area,
                    $entry->name,
                    $entry->summary,
                    $entry->formula,
                    implode(' ', $entry->whereUsed),
                ])),
                $query,
            ));
        }

        return Inertia::render('advisor/knowledge/Methodologies', [
            'entries' => $entries
                ->sortBy([['area', 'asc'], ['name', 'asc']])
                ->map(fn (MethodologyEntry $entry): array => $this->summary($entry))
                ->values()
                ->all(),
            'areas' => collect($this->registry->all())
                ->map(fn (MethodologyEntry $entry): string => $entry->area)
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'filters' => ['q' => $query],
            'indexUrl' => route('advisor.knowledge.methodologies.index', absolute: false),
            'knowledgeIndexUrl' => route('advisor.knowledge.index', absolute: false),
        ]);
    }

    public function show(string $methodology): Response
    {
        try {
            $entry = $this->registry->get($methodology);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return Inertia::render('advisor/knowledge/MethodologyShow', [
            'entry' => [
                ...$this->summary($entry),
                'formula' => $entry->formula,
                'inputs' => $entry->inputs,
                'config_refs' => $entry->configRefs,
                'parameters' => $this->resolvedParameters($entry),
                'sources' => $entry->sources,
                'owning_service' => $entry->owningService,
                'version' => $entry->version,
            ],
            'indexUrl' => route('advisor.knowledge.methodologies.index', absolute: false),
            'knowledgeIndexUrl' => route('advisor.knowledge.index', absolute: false),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(MethodologyEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'area' => $entry->area,
            'name' => $entry->name,
            'summary' => $entry->summary,
            'where_used' => $this->whereUsed($entry),
            'show_url' => route('advisor.knowledge.methodologies.show', $entry->id, absolute: false),
        ];
    }

    /**
     * @return array<int, array{key:string,label:string}>
     */
    private function whereUsed(MethodologyEntry $entry): array
    {
        return array_map(
            fn (string $featureKey): array => [
                'key' => $featureKey,
                'label' => $this->registry->featureLabel($featureKey),
            ],
            $entry->whereUsed,
        );
    }

    /**
     * @return array<int, array{key:string,value:mixed}>
     */
    private function resolvedParameters(MethodologyEntry $entry): array
    {
        return collect($this->registry->resolvedParameters($entry))
            ->map(fn (mixed $value, string $key): array => [
                'key' => $key,
                'value' => $value,
            ])
            ->values()
            ->all();
    }
}
