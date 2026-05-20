<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Services\Audit\AuditWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TermsController extends Controller
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', TermsVersion::class);

        return Inertia::render('admin/terms/Index', [
            'versions' => TermsVersion::query()
                ->withCount('clauses')
                ->latest('created_at')
                ->get()
                ->map(fn (TermsVersion $version): array => $this->versionPayload($version)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TermsVersion::class);

        $draft = DB::transaction(function () use ($request): TermsVersion {
            $source = TermsVersion::query()
                ->with('clauses')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->first();

            $draft = TermsVersion::query()->create([
                'version' => $this->nextVersion(),
                'title' => $source?->title ?? 'Future Shift Advisory Terms and Conditions',
                'material' => false,
                'notice_period_days' => $source?->notice_period_days ?? 30,
                'reviewer_reference' => $source?->reviewer_reference,
                'created_by_user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            $source?->clauses->each(function ($clause) use ($draft): void {
                $draft->clauses()->create([
                    'clause_number' => $clause->clause_number,
                    'title' => $clause->title,
                    'body' => $clause->body,
                    'material' => $clause->material,
                ]);
            });

            return $draft;
        });

        return to_route('admin.terms.edit', $draft);
    }

    public function edit(TermsVersion $termsVersion): Response
    {
        Gate::authorize('update', $termsVersion);

        return Inertia::render('admin/terms/Edit', [
            'version' => $this->versionPayload($termsVersion->load('clauses')),
        ]);
    }

    public function update(Request $request, TermsVersion $termsVersion): RedirectResponse
    {
        Gate::authorize('update', $termsVersion);
        abort_if($termsVersion->isPublished(), 422, 'Published terms versions are immutable.');

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:40', Rule::unique('terms_versions', 'version')->ignore($termsVersion->id)],
            'title' => ['required', 'string', 'max:255'],
            'material' => ['required', 'boolean'],
            'notice_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'reviewer_reference' => ['nullable', 'string', 'max:255'],
            'clauses' => ['required', 'array', 'size:14'],
            'clauses.*.id' => ['nullable', 'uuid'],
            'clauses.*.clause_number' => ['required', 'integer', 'min:1', 'max:99', 'distinct'],
            'clauses.*.title' => ['required', 'string', 'max:255'],
            'clauses.*.body' => ['required', 'string'],
            'clauses.*.material' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($termsVersion, $validated): void {
            $termsVersion->update([
                'version' => $validated['version'],
                'title' => $validated['title'],
                'material' => $validated['material'],
                'notice_period_days' => $validated['notice_period_days'],
                'reviewer_reference' => $validated['reviewer_reference'] ?? null,
            ]);

            $seen = [];
            foreach ($validated['clauses'] as $clause) {
                $saved = $termsVersion->clauses()->updateOrCreate(
                    ['clause_number' => $clause['clause_number']],
                    [
                        'title' => $clause['title'],
                        'body' => $clause['body'],
                        'material' => $clause['material'],
                    ],
                );
                $seen[] = $saved->getKey();
            }

            $termsVersion->clauses()
                ->whereNotIn('id', $seen)
                ->delete();
        });

        return to_route('admin.terms.edit', $termsVersion)->with('status', 'terms-updated');
    }

    public function preview(TermsVersion $termsVersion): Response
    {
        Gate::authorize('view', $termsVersion);

        return Inertia::render('admin/terms/Preview', [
            'version' => $this->versionPayload($termsVersion->load('clauses')),
        ]);
    }

    public function confirmPublish(TermsVersion $termsVersion): Response
    {
        Gate::authorize('publish', $termsVersion);

        return Inertia::render('admin/terms/Publish', [
            'version' => $this->versionPayload($termsVersion->load('clauses')),
        ]);
    }

    public function publish(Request $request, TermsVersion $termsVersion): RedirectResponse
    {
        Gate::authorize('publish', $termsVersion);
        abort_if($termsVersion->isPublished(), 422, 'This terms version has already been published.');

        $validated = $request->validate([
            'material' => ['required', 'boolean'],
            'notice_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'reviewer_reference' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $termsVersion, $validated): void {
            $publishedAt = now();
            $prior = TermsVersion::query()
                ->published()
                ->whereKeyNot($termsVersion->getKey())
                ->orderByDesc('published_at')
                ->first();

            $termsVersion->forceFill([
                'material' => $validated['material'],
                'notice_period_days' => $validated['notice_period_days'],
                'reviewer_reference' => $validated['reviewer_reference'] ?? null,
                'published_at' => $publishedAt,
                'published_by_user_id' => $request->user()?->getAuthIdentifier(),
            ])->save();

            $queued = 0;
            if ($termsVersion->material && $prior instanceof TermsVersion) {
                $expiresAt = $publishedAt->copy()->addDays($termsVersion->notice_period_days);
                $queued = TermsAcceptance::query()
                    ->where('terms_version_id', $prior->getKey())
                    ->active()
                    ->update([
                        'expires_at' => $expiresAt,
                        'reacceptance_notice_queued_at' => $publishedAt,
                    ]);

                $this->auditWriter->record('terms.reacceptance_queued', subject: $termsVersion, after: [
                    'prior_terms_version_id' => $prior->getKey(),
                    'new_terms_version_id' => $termsVersion->getKey(),
                    'expires_at' => $expiresAt->toIso8601String(),
                    'users_queued' => $queued,
                ]);
            }

            $this->auditWriter->record('terms.published', subject: $termsVersion, after: [
                'version' => $termsVersion->version,
                'material' => $termsVersion->material,
                'notice_period_days' => $termsVersion->notice_period_days,
                'published_at' => $publishedAt->toIso8601String(),
                'reacceptance_users_queued' => $queued,
            ]);
        });

        return to_route('admin.terms.preview', $termsVersion)->with('status', 'terms-published');
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(TermsVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'title' => $version->title,
            'material' => $version->material,
            'notice_period_days' => $version->notice_period_days,
            'reviewer_reference' => $version->reviewer_reference,
            'published_at' => $version->published_at?->toIso8601String(),
            'published_by_user_id' => $version->published_by_user_id,
            'clauses_count' => $version->clauses_count,
            'clauses' => $version->relationLoaded('clauses')
                ? $version->clauses->map(fn ($clause): array => [
                    'id' => $clause->id,
                    'clause_number' => $clause->clause_number,
                    'title' => $clause->title,
                    'body' => $clause->body,
                    'material' => $clause->material,
                ])->values()
                : [],
        ];
    }

    private function nextVersion(): string
    {
        $versions = TermsVersion::query()->pluck('version');
        $next = $versions
            ->map(fn (string $version): int => (int) preg_replace('/\D+/', '', $version))
            ->max() + 1;

        return (string) max(1, $next);
    }
}
