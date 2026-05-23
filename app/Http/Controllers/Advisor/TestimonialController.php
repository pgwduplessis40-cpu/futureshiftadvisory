<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Testimonial;
use App\Services\Testimonials\TestimonialCapture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TestimonialController extends Controller
{
    public function __construct(private readonly TestimonialCapture $testimonials) {}

    public function index(): Response
    {
        return Inertia::render('advisor/testimonials/Index', [
            'testimonials' => $this->testimonials->library()
                ->map(fn (Testimonial $testimonial): array => $this->payload($testimonial))
                ->values(),
        ]);
    }

    public function requestFromNps(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $this->testimonials->requestFromNps($client, (int) $validated['score'], $request->user());

        return back()->with('status', 'testimonial-request-processed');
    }

    public function capture(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $validated = $request->validate([
            'marketing_consent' => ['required', 'boolean'],
            'display_mode' => ['required', 'string', Rule::in([Testimonial::DISPLAY_NAMED, Testimonial::DISPLAY_ANONYMOUS])],
            'display_name' => ['nullable', 'string', 'max:255'],
            'quote' => ['nullable', 'string', 'max:4000'],
        ]);

        $this->testimonials->captureConsent(
            testimonial: $testimonial,
            marketingConsent: (bool) $validated['marketing_consent'],
            displayMode: $validated['display_mode'],
            quote: $validated['quote'] ?? null,
            submitter: $request->user(),
            displayName: $validated['display_name'] ?? null,
        );

        return back()->with('status', 'testimonial-consent-captured');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Testimonial $testimonial): array
    {
        return [
            'id' => $testimonial->id,
            'client_id' => $testimonial->client_id,
            'client_name' => $testimonial->client?->legal_name,
            'quote' => $testimonial->quote,
            'display_mode' => $testimonial->display_mode,
            'display_name' => $testimonial->display_name,
            'source_type' => $testimonial->source_type,
            'source_score' => $testimonial->source_score,
            'consented_at' => $testimonial->consented_at?->toIso8601String(),
        ];
    }
}
