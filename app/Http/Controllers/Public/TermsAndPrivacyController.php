<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\TermsVersion;
use App\Services\Terms\TermsAcceptanceGate;
use App\Services\Terms\TermsDocumentRenderer;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public source of truth for the current FSA Terms and Privacy Policy.
 *
 * The website can link to the human-readable page or consume the deliberately
 * small JSON representation. Both are derived from the same published terms
 * version the app presents for acceptance, so legal wording cannot drift
 * between the website and the customer portal.
 */
final class TermsAndPrivacyController extends Controller
{
    public function __construct(
        private readonly TermsAcceptanceGate $terms,
        private readonly TermsDocumentRenderer $documents,
    ) {}

    public function show(): Response
    {
        $version = $this->terms->latestPublishedVersion(withClauses: true);

        return Inertia::render('public/terms-and-privacy', [
            'document' => $this->payload($version),
        ]);
    }

    public function json(): JsonResponse
    {
        $version = $this->terms->latestPublishedVersion(withClauses: true);

        return response()->json([
            'document' => $this->payload($version),
        ], 200, [
            'Cache-Control' => 'public, max-age=300',
            // This is public legal content. A separately hosted public
            // website may safely read it without sending credentials.
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @return array{
     *     published: bool,
     *     title: string,
     *     version: string|null,
     *     published_at: string|null,
     *     source_preview_html: string|null,
     *     clauses: list<array{id: int|string, clause_number: int, title: string, body: string}>
     * }
     */
    private function payload(?TermsVersion $version): array
    {
        if (! $version instanceof TermsVersion) {
            return [
                'published' => false,
                'title' => 'Future Shift Advisory Terms and Privacy Policy',
                'version' => null,
                'published_at' => null,
                'source_preview_html' => null,
                'clauses' => [],
            ];
        }

        return [
            'published' => true,
            'title' => $version->title,
            'version' => $version->version,
            'published_at' => $version->published_at?->toIso8601String(),
            'source_preview_html' => $this->documents->sourcePreviewHtml($version),
            'clauses' => $version->clauses->map(fn ($clause): array => [
                'id' => $clause->id,
                'clause_number' => $clause->clause_number,
                'title' => $clause->title,
                'body' => $clause->body,
            ])->values()->all(),
        ];
    }
}
