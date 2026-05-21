<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class KnowledgeController extends Controller
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeEntry::class);
        $user = $this->viewer($request);
        $search = trim((string) $request->query('q', ''));

        $entries = KnowledgeEntry::query()
            ->forAuthor($user)
            ->with('client');

        $this->applySearch($entries, $search);

        if ($search === '') {
            $entries->latest('updated_at');
        }

        return Inertia::render('advisor/knowledge/Index', [
            'entries' => $entries
                ->limit(100)
                ->get()
                ->map(fn (KnowledgeEntry $entry): array => $this->entrySummary($entry))
                ->values()
                ->all(),
            'filters' => ['q' => $search],
            'canCreate' => Gate::allows('create', KnowledgeEntry::class),
            'indexUrl' => route('advisor.knowledge.index', absolute: false),
            'createUrl' => route('advisor.knowledge.create', absolute: false),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', KnowledgeEntry::class);

        return Inertia::render('advisor/knowledge/Create', [
            'entry' => $this->formDefaults(),
            'categories' => KnowledgeEntry::categoryOptions(),
            'clients' => $this->clientOptions(),
            'storeUrl' => route('advisor.knowledge.store', absolute: false),
            'indexUrl' => route('advisor.knowledge.index', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeEntry::class);
        $user = $this->viewer($request);
        $validated = $this->validated($request);

        $entry = KnowledgeEntry::query()->create([
            ...$this->entryAttributes($validated),
            'author_user_id' => $user->getKey(),
        ]);

        $this->auditWriter->record('knowledge_entry.created', subject: $entry, actor: $user, after: [
            'knowledge_entry_id' => $entry->id,
            'category' => $entry->category,
            'client_id' => $entry->client_id,
        ]);

        return to_route('advisor.knowledge.show', $entry)->with('status', 'knowledge-entry-created');
    }

    public function show(KnowledgeEntry $knowledgeEntry): Response
    {
        Gate::authorize('view', $knowledgeEntry);

        return Inertia::render('advisor/knowledge/Show', [
            'entry' => $this->entryDetail($knowledgeEntry->loadMissing('client', 'author')),
            'canEdit' => Gate::allows('update', $knowledgeEntry),
            'indexUrl' => route('advisor.knowledge.index', absolute: false),
        ]);
    }

    public function edit(KnowledgeEntry $knowledgeEntry): Response
    {
        Gate::authorize('update', $knowledgeEntry);

        return Inertia::render('advisor/knowledge/Edit', [
            'entry' => [
                ...$this->entryDetail($knowledgeEntry->loadMissing('client')),
                'tags_string' => implode(', ', $knowledgeEntry->tags ?? []),
            ],
            'categories' => KnowledgeEntry::categoryOptions(),
            'clients' => $this->clientOptions(),
            'updateUrl' => route('advisor.knowledge.update', $knowledgeEntry, absolute: false),
            'showUrl' => route('advisor.knowledge.show', $knowledgeEntry, absolute: false),
            'indexUrl' => route('advisor.knowledge.index', absolute: false),
        ]);
    }

    public function update(Request $request, KnowledgeEntry $knowledgeEntry): RedirectResponse
    {
        Gate::authorize('update', $knowledgeEntry);
        $user = $this->viewer($request);
        $before = $knowledgeEntry->only(['client_id', 'category', 'title', 'tags']);

        $validated = $this->validated($request);
        $knowledgeEntry->update($this->entryAttributes($validated));

        $this->auditWriter->record('knowledge_entry.updated', subject: $knowledgeEntry, actor: $user, before: $before, after: [
            'client_id' => $knowledgeEntry->client_id,
            'category' => $knowledgeEntry->category,
            'title' => $knowledgeEntry->title,
            'tags' => $knowledgeEntry->tags,
        ]);

        return to_route('advisor.knowledge.show', $knowledgeEntry)->with('status', 'knowledge-entry-updated');
    }

    public function destroy(Request $request, KnowledgeEntry $knowledgeEntry): RedirectResponse
    {
        Gate::authorize('delete', $knowledgeEntry);
        $user = $this->viewer($request);
        $snapshot = $knowledgeEntry->only(['id', 'client_id', 'category', 'title', 'tags']);

        $this->auditWriter->record('knowledge_entry.deleted', subject: $knowledgeEntry, actor: $user, before: $snapshot);
        $knowledgeEntry->delete();

        return to_route('advisor.knowledge.index')->with('status', 'knowledge-entry-deleted');
    }

    /**
     * @param  Builder<KnowledgeEntry>  $entries
     */
    private function applySearch(Builder $entries, string $search): void
    {
        if ($search === '') {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $entries
                ->select('knowledge_entries.*')
                ->selectRaw("ts_rank_cd(search_vector, plainto_tsquery('english', ?)) as search_rank", [$search])
                ->whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$search])
                ->orderByDesc('search_rank')
                ->orderByDesc('updated_at');

            return;
        }

        $needle = '%'.Str::lower($search).'%';
        $entries
            ->where(function (Builder $query) use ($needle): void {
                $query
                    ->whereRaw('lower(title) like ?', [$needle])
                    ->orWhereRaw('lower(category) like ?', [$needle])
                    ->orWhereRaw('lower(body) like ?', [$needle]);
            })
            ->latest('updated_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function entrySummary(KnowledgeEntry $entry): array
    {
        $rank = $entry->getAttribute('search_rank');

        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'category' => $entry->category,
            'category_label' => KnowledgeEntry::categoryLabel($entry->category),
            'body_excerpt' => Str::limit(preg_replace('/\s+/', ' ', $entry->body) ?? $entry->body, 220),
            'tags' => $entry->tags ?? [],
            'client' => $entry->client instanceof Client
                ? ['id' => $entry->client->id, 'legal_name' => $entry->client->legal_name]
                : null,
            'search_rank' => is_numeric($rank) ? (float) $rank : null,
            'updated_at' => $entry->updated_at?->toIso8601String(),
            'show_url' => route('advisor.knowledge.show', $entry, absolute: false),
            'edit_url' => route('advisor.knowledge.edit', $entry, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entryDetail(KnowledgeEntry $entry): array
    {
        return [
            ...$this->entrySummary($entry),
            'client_id' => $entry->client_id,
            'body' => $entry->body,
            'author_name' => $entry->author?->name,
            'created_at' => $entry->created_at?->toIso8601String(),
            'update_url' => route('advisor.knowledge.update', $entry, absolute: false),
            'delete_url' => route('advisor.knowledge.destroy', $entry, absolute: false),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function formDefaults(): array
    {
        return [
            'client_id' => null,
            'category' => KnowledgeEntry::CATEGORY_METHODOLOGY,
            'title' => '',
            'body' => '',
            'tags_string' => '',
        ];
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    private function clientOptions(): array
    {
        return Client::query()
            ->orderBy('legal_name')
            ->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'label' => $client->legal_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
            'category' => ['required', 'string', Rule::in(KnowledgeEntry::categories())],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'tags' => ['nullable', 'string', 'max:800'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function entryAttributes(array $validated): array
    {
        return [
            'client_id' => $validated['client_id'] ?? null,
            'category' => $validated['category'],
            'title' => trim((string) $validated['title']),
            'body' => trim((string) $validated['body']),
            'tags' => $this->tagsFrom($validated['tags'] ?? null),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tagsFrom(mixed $value): array
    {
        if (! is_scalar($value)) {
            return [];
        }

        return collect(explode(',', (string) $value))
            ->map(fn (string $tag): string => Str::of($tag)->squish()->limit(48, '')->value())
            ->filter(fn (string $tag): bool => $tag !== '')
            ->unique(fn (string $tag): string => Str::lower($tag))
            ->values()
            ->all();
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
