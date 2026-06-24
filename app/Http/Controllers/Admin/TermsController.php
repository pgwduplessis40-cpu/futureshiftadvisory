<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TermsAcceptance;
use App\Models\TermsEnforcement;
use App\Models\TermsVersion;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfRenderer;
use App\Services\Terms\TermsAcceptanceGate;
use App\Services\Terms\TermsPdfFallback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class TermsController extends Controller
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly TermsAcceptanceGate $gate,
        private readonly TermsPdfFallback $fallbackPdf,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', TermsVersion::class);

        return Inertia::render('admin/terms/Index', [
            'versions' => TermsVersion::query()
                ->withCount([
                    'clauses',
                    'clauses as material_clauses_count' => fn ($query) => $query->where('material', true),
                ])
                ->latest('created_at')
                ->get()
                ->map(fn (TermsVersion $version): array => $this->versionPayload($version)),
            'enforcement' => $this->enforcementPayload(),
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

    public function download(Request $request, TermsVersion $termsVersion, PdfRenderer $renderer): HttpResponse
    {
        Gate::authorize('view', $termsVersion);

        $termsVersion->load('clauses');
        $html = $this->downloadHtml($termsVersion);
        try {
            $pdf = $renderer->render($html);
        } catch (Throwable $exception) {
            report($exception);
            $pdf = $this->fallbackPdf->reviewDownload($termsVersion);
        }
        $filename = Str::slug('future-shift-advisory-terms-'.$termsVersion->version).'.pdf';

        $this->auditWriter->record('terms.downloaded_for_review', subject: $termsVersion, actor: $request->user(), after: [
            'version' => $termsVersion->version,
            'byte_size' => strlen($pdf),
            'published_at' => $termsVersion->published_at?->toIso8601String(),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
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

    public function activateEnforcement(Request $request): RedirectResponse
    {
        Gate::authorize('publish', TermsVersion::class);

        $latest = $this->gate->latestPublishedVersion();
        abort_unless($latest instanceof TermsVersion, 422, 'Publish a terms version before activating enforcement.');
        abort_if($this->gate->isEnforced(), 422, 'Terms enforcement has already been activated.');

        DB::transaction(function () use ($latest, $request): TermsEnforcement {
            $activation = TermsEnforcement::query()->create([
                'scope' => TermsEnforcement::SCOPE_PLATFORM,
                'activated_at' => now(),
                'activated_by_user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            $this->auditWriter->record('terms.enforcement_activated', subject: $activation, actor: $request->user(), after: [
                'latest_terms_version_id' => $latest->getKey(),
                'latest_terms_version' => $latest->version,
                'activated_at' => $activation->activated_at?->toIso8601String(),
            ]);

            return $activation;
        });

        return to_route('admin.terms.index')->with('status', 'terms-enforcement-activated');
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
            'material_clauses_count' => $version->relationLoaded('clauses')
                ? $version->clauses->where('material', true)->count()
                : $version->material_clauses_count,
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

    /**
     * @return array<string, mixed>
     */
    private function enforcementPayload(): array
    {
        $enforcement = $this->gate->enforcement();
        $latest = $this->gate->latestPublishedVersion();

        return [
            'active' => $enforcement instanceof TermsEnforcement,
            'activated_at' => $enforcement?->activated_at?->toIso8601String(),
            'activated_by' => $enforcement?->activatedBy ? [
                'id' => $enforcement->activatedBy->id,
                'name' => $enforcement->activatedBy->name,
            ] : null,
            'can_activate' => ! ($enforcement instanceof TermsEnforcement) && $latest instanceof TermsVersion,
            'latest_published_version' => $latest instanceof TermsVersion ? [
                'id' => $latest->id,
                'version' => $latest->version,
                'published_at' => $latest->published_at?->toIso8601String(),
            ] : null,
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

    private function downloadHtml(TermsVersion $version): string
    {
        $clauses = $version->clauses
            ->map(fn ($clause): string => sprintf(
                '<section><h2>Clause %s: %s%s</h2><p>%s</p></section>',
                $this->escape((string) $clause->clause_number),
                $this->escape($clause->title),
                $clause->material ? ' <span>(material)</span>' : '',
                nl2br($this->escape($clause->body)),
            ))
            ->implode('');

        return '<!doctype html><html><head><meta charset="utf-8"><title>Future Shift Advisory Terms</title>'
            .'<style>body{font-family:Arial,sans-serif;color:#111827;line-height:1.5}h1{color:#0f172a}h2{font-size:16px;margin-top:22px}p{font-size:12px}.meta{font-size:12px;color:#4b5563}</style>'
            .'</head><body><h1>'.$this->escape($version->title).'</h1>'
            .'<p class="meta">Version '.$this->escape($version->version).' generated for review on '.now()->toDateTimeString().'.</p>'
            .$clauses
            .'</body></html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
