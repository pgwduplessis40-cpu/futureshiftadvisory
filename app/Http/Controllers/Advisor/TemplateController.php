<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TemplateController extends Controller
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Template::class);

        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', Template::STATUS_ACTIVE));
        $status = in_array($status, Template::libraryStatuses(), true) ? $status : 'all';

        $templates = Template::query()
            ->library()
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Template $template): array => $this->templateSummary($template))
            ->values()
            ->all();

        return Inertia::render('advisor/templates/Index', [
            'templates' => $templates,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'categories' => Template::categoryOptions(),
            'statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => Template::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => Template::STATUS_ARCHIVED, 'label' => 'Archived'],
            ],
            'canManage' => Gate::allows('create', Template::class),
            'indexUrl' => route('advisor.templates.index', absolute: false),
            'storeUrl' => route('advisor.templates.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Template::class);
        $user = $this->viewer($request);
        $validated = $this->validated($request);

        /** @var Template $template */
        $template = Template::query()->create([
            ...$this->templateAttributes($validated),
            'structure' => [
                'source_kind' => 'manual',
                'sections' => [],
            ],
            'source_reference' => 'manual:user:'.$user->getKey(),
            'version' => 1,
            'created_by_user_id' => $user->getKey(),
            'learning_update_implementation_id' => null,
        ]);

        $this->audit->record('template.created', subject: $template, actor: $user, after: [
            'template_id' => $template->getKey(),
            'category' => $template->category,
            'status' => $template->status,
        ]);

        return to_route('advisor.templates.show', $template)->with('status', 'template-created');
    }

    public function show(Template $template): Response
    {
        Gate::authorize('view', $template);
        abort_if($template->status === Template::STATUS_DRAFT, 404);

        return Inertia::render('advisor/templates/Show', [
            'template' => $this->templateDetail($template->loadMissing('creator')),
            'categories' => Template::categoryOptions(),
            'statuses' => [
                ['value' => Template::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => Template::STATUS_ARCHIVED, 'label' => 'Archived'],
            ],
            'canManage' => Gate::allows('update', $template),
            'indexUrl' => route('advisor.templates.index', absolute: false),
        ]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        Gate::authorize('update', $template);
        abort_if($template->status === Template::STATUS_DRAFT, 404);

        $user = $this->viewer($request);
        $before = $template->only(['category', 'title', 'body', 'status', 'version']);
        $validated = $this->validated($request);

        $template->forceFill([
            ...$this->templateAttributes($validated),
            'version' => $template->version + 1,
        ])->save();

        $this->audit->record('template.updated', subject: $template, actor: $user, before: $before, after: [
            'category' => $template->category,
            'title' => $template->title,
            'status' => $template->status,
            'version' => $template->version,
        ]);

        return to_route('advisor.templates.show', $template)->with('status', 'template-updated');
    }

    /**
     * @param  Builder<Template>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $needle = '%'.Str::lower($search).'%';
        $query->where(function (Builder $query) use ($needle): void {
            $query
                ->whereRaw('lower(title) like ?', [$needle])
                ->orWhereRaw('lower(category) like ?', [$needle])
                ->orWhereRaw('lower(body) like ?', [$needle]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSummary(Template $template): array
    {
        return [
            'id' => $template->id,
            'category' => $template->category,
            'category_label' => Template::categoryLabel($template->category),
            'title' => $template->title,
            'body_excerpt' => Str::limit(preg_replace('/\s+/', ' ', (string) $template->body) ?? (string) $template->body, 220),
            'status' => $template->status,
            'version' => $template->version,
            'source_reference' => $template->source_reference,
            'updated_at' => $template->updated_at?->toIso8601String(),
            'show_url' => route('advisor.templates.show', $template, absolute: false),
            'update_url' => route('advisor.templates.update', $template, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateDetail(Template $template): array
    {
        return [
            ...$this->templateSummary($template),
            'body' => $template->body,
            'structure' => $template->structure,
            'creator_name' => $template->creator?->name,
            'created_at' => $template->created_at?->toIso8601String(),
            'learning_update_implementation_id' => $template->learning_update_implementation_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', Rule::in(Template::categories())],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:40000'],
            'status' => ['required', 'string', Rule::in(Template::libraryStatuses())],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function templateAttributes(array $validated): array
    {
        return [
            'category' => $validated['category'],
            'title' => trim((string) $validated['title']),
            'body' => trim((string) $validated['body']),
            'status' => $validated['status'],
        ];
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
