<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RatingFramework;
use App\Services\Entrepreneurs\RatingFrameworkManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RatingFrameworkController extends Controller
{
    public function __construct(
        private readonly RatingFrameworkManager $frameworks,
    ) {}

    public function index(): Response
    {
        $frameworks = RatingFramework::query()
            ->with('criteria')
            ->whereNull('industry_variant')
            ->orderByDesc('version')
            ->get();

        return Inertia::render('admin/rating-frameworks/Index', [
            'frameworks' => $frameworks
                ->map(fn (RatingFramework $framework): array => $this->frameworkPayload($framework))
                ->values()
                ->all(),
            'draft_url' => route('admin.rating-frameworks.drafts.store', absolute: false),
        ]);
    }

    public function storeDraft(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $criteria = $this->validatedCriteria($request);
        $base = RatingFramework::query()
            ->whereNull('industry_variant')
            ->where('status', RatingFramework::STATUS_PUBLISHED)
            ->latest('version')
            ->firstOrFail();

        DB::transaction(function () use ($base, $criteria, $actor): void {
            $draft = $this->frameworks->revise($base, $criteria, $actor);
            $draft->forceFill(['production_ready' => true])->save();
        });

        return to_route('admin.rating-frameworks.index')->with('status', 'rating-framework-draft-created');
    }

    public function publish(Request $request, RatingFramework $ratingFramework): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($ratingFramework->status === RatingFramework::STATUS_DRAFT, 404);

        $ratingFramework->loadMissing('criteria');
        $this->assertValidCriteria($ratingFramework->criteria->map(fn ($criterion): array => [
            'number' => (int) $criterion->number,
            'name' => (string) $criterion->name,
            'weight' => (float) $criterion->weight,
            'descriptors' => $criterion->descriptors ?? [],
        ])->values()->all());
        $ratingFramework->forceFill(['production_ready' => true])->save();
        $this->frameworks->publish($ratingFramework, $actor);

        return to_route('admin.rating-frameworks.index')->with('status', 'rating-framework-published');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validatedCriteria(Request $request): array
    {
        $validated = $request->validate([
            'criteria' => ['required', 'array', 'min:1', 'max:50'],
            'criteria.*.number' => ['required', 'integer', 'min:1', 'max:50'],
            'criteria.*.name' => ['required', 'string', 'max:180'],
            'criteria.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'criteria.*.descriptors' => ['required', 'array'],
            'criteria.*.descriptors.exceptional' => ['required', 'string', 'max:1000'],
            'criteria.*.descriptors.strong' => ['required', 'string', 'max:1000'],
            'criteria.*.descriptors.developing' => ['required', 'string', 'max:1000'],
            'criteria.*.descriptors.needs_work' => ['required', 'string', 'max:1000'],
        ]);

        $criteria = collect((array) $validated['criteria'])
            ->map(fn (array $criterion): array => [
                'number' => (int) $criterion['number'],
                'name' => trim((string) $criterion['name']),
                'weight' => (float) $criterion['weight'],
                'descriptors' => [
                    'exceptional' => trim((string) data_get($criterion, 'descriptors.exceptional')),
                    'strong' => trim((string) data_get($criterion, 'descriptors.strong')),
                    'developing' => trim((string) data_get($criterion, 'descriptors.developing')),
                    'needs_work' => trim((string) data_get($criterion, 'descriptors.needs_work')),
                ],
                'industry_variants' => [],
                'is_placeholder' => false,
            ])
            ->sortBy('number')
            ->values()
            ->all();

        $this->assertValidCriteria($criteria);

        return $criteria;
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     */
    private function assertValidCriteria(array $criteria): void
    {
        $numbers = collect($criteria)->pluck('number');

        if ($numbers->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'criteria' => 'Criterion numbers must be unique.',
            ]);
        }

        $total = round((float) collect($criteria)->sum('weight'), 3);
        if (abs($total - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'criteria' => 'Rubric weights must total 100%. Current total: '.$total.'%.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function frameworkPayload(RatingFramework $framework): array
    {
        return [
            'id' => $framework->id,
            'version' => $framework->version,
            'status' => $framework->status,
            'production_ready' => $framework->production_ready,
            'published_at' => $framework->published_at?->toIso8601String(),
            'publish_url' => $framework->status === RatingFramework::STATUS_DRAFT
                ? route('admin.rating-frameworks.publish', $framework, absolute: false)
                : null,
            'criteria' => $framework->criteria
                ->map(fn ($criterion): array => [
                    'id' => $criterion->id,
                    'number' => (int) $criterion->number,
                    'name' => (string) $criterion->name,
                    'weight' => (float) $criterion->weight,
                    'descriptors' => $criterion->descriptors ?? [],
                    'is_placeholder' => (bool) $criterion->is_placeholder,
                ])
                ->values()
                ->all(),
        ];
    }
}
