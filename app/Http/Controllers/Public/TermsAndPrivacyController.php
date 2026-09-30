<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\TermsVersion;
use App\Services\Terms\TermsAcceptanceGate;
use App\Services\Terms\TermsDocumentRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, independently versioned Terms of Use and Privacy Policy documents.
 * The former combined URL is retained only as a permanent redirect so an old
 * link cannot expose the retired document as the current policy.
 */
final class TermsAndPrivacyController extends Controller
{
    public function __construct(
        private readonly TermsAcceptanceGate $terms,
        private readonly TermsDocumentRenderer $documents,
    ) {}

    public function terms(Request $request): Response
    {
        return $this->showDocument(
            $request,
            TermsVersion::SCOPE_WEBSITE_TERMS,
            'Terms of Use',
            '/terms.json',
        );
    }

    public function privacy(Request $request): Response
    {
        return $this->showDocument(
            $request,
            TermsVersion::SCOPE_PRIVACY_POLICY,
            'Privacy Policy',
            '/privacy.json',
        );
    }

    public function termsJson(): JsonResponse
    {
        return $this->jsonDocument(
            TermsVersion::SCOPE_WEBSITE_TERMS,
            'Future Shift Advisory Terms of Use',
        );
    }

    public function privacyJson(): JsonResponse
    {
        return $this->jsonDocument(
            TermsVersion::SCOPE_PRIVACY_POLICY,
            'Future Shift Advisory Privacy Policy',
        );
    }

    public function legacy(): RedirectResponse
    {
        return to_route('public.terms', status: 301);
    }

    public function legacyJson(): RedirectResponse
    {
        return to_route('public.terms.json', status: 301);
    }

    private function showDocument(
        Request $request,
        string $scope,
        string $documentLabel,
        string $jsonUrl,
    ): Response {
        $version = $this->terms->latestPublishedVersion(withClauses: true, documentScope: $scope);

        return Inertia::render('public/legal-document', [
            'document' => $this->payload($version, 'Future Shift Advisory '.$documentLabel),
            'documentLabel' => $documentLabel,
            'jsonUrl' => $jsonUrl,
            // Do not reflect arbitrary URLs from a public legal page. The
            // checkout can opt into this one, known-safe return destination.
            'returnToIdeaValidation' => $request->query('return_to') === 'idea-validation',
        ]);
    }

    private function jsonDocument(string $scope, string $fallbackTitle): JsonResponse
    {
        $version = $this->terms->latestPublishedVersion(withClauses: true, documentScope: $scope);

        return response()->json([
            'document' => $this->payload($version, $fallbackTitle),
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
    private function payload(?TermsVersion $version, string $fallbackTitle): array
    {
        if (! $version instanceof TermsVersion) {
            return [
                'published' => false,
                'title' => $fallbackTitle,
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
