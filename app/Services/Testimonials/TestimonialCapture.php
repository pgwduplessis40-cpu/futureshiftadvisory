<?php

declare(strict_types=1);

namespace App\Services\Testimonials;

use App\Models\Client;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TestimonialCapture
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function requestFromNps(Client $client, int $score, ?User $actor = null): ?Testimonial
    {
        if ($score < 8) {
            return null;
        }

        return DB::transaction(function () use ($client, $score, $actor): Testimonial {
            /** @var Testimonial $testimonial */
            $testimonial = Testimonial::query()->firstOrCreate(
                [
                    'client_id' => $client->getKey(),
                    'source_type' => Testimonial::SOURCE_NPS,
                    'status' => Testimonial::STATUS_REQUESTED,
                ],
                [
                    'source_score' => $score,
                    'display_mode' => Testimonial::DISPLAY_ANONYMOUS,
                    'requested_by_user_id' => $actor?->getAuthIdentifier(),
                    'requested_at' => now(),
                ],
            );

            $testimonial->forceFill([
                'source_score' => $score,
                'requested_by_user_id' => $actor?->getAuthIdentifier(),
                'requested_at' => $testimonial->requested_at ?? now(),
            ])->save();

            $this->audit->record('testimonial.requested', subject: $testimonial, actor: $actor, after: [
                'client_id' => $client->getKey(),
                'source_type' => Testimonial::SOURCE_NPS,
                'source_score' => $score,
            ]);

            return $testimonial->refresh();
        });
    }

    public function captureConsent(
        Testimonial $testimonial,
        bool $marketingConsent,
        string $displayMode,
        ?string $quote,
        ?User $submitter = null,
        ?string $displayName = null,
    ): Testimonial {
        if (! in_array($displayMode, [Testimonial::DISPLAY_NAMED, Testimonial::DISPLAY_ANONYMOUS], true)) {
            throw new InvalidArgumentException("Unsupported testimonial display mode [{$displayMode}].");
        }

        if ($marketingConsent && trim((string) $quote) === '') {
            throw new InvalidArgumentException('Marketing consent testimonials require testimonial text.');
        }

        return DB::transaction(function () use ($testimonial, $marketingConsent, $displayMode, $quote, $submitter, $displayName): Testimonial {
            /** @var Testimonial $locked */
            $locked = Testimonial::query()->with('client')->whereKey($testimonial->getKey())->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'quote' => $marketingConsent ? trim((string) $quote) : null,
                'marketing_consent' => $marketingConsent,
                'display_mode' => $displayMode,
                'display_name' => $marketingConsent && $displayMode === Testimonial::DISPLAY_NAMED
                    ? ($displayName ?: $locked->client?->legal_name)
                    : null,
                'status' => $marketingConsent ? Testimonial::STATUS_CONSENTED : Testimonial::STATUS_DECLINED,
                'submitted_by_user_id' => $submitter?->getAuthIdentifier(),
                'consented_at' => $marketingConsent ? now() : null,
                'declined_at' => $marketingConsent ? null : now(),
            ])->save();

            $this->audit->record(
                $marketingConsent ? 'testimonial.consent_captured' : 'testimonial.declined',
                subject: $locked,
                actor: $submitter,
                after: [
                    'marketing_consent' => $marketingConsent,
                    'display_mode' => $displayMode,
                    'display_name' => $locked->display_name,
                    'status' => $locked->status,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * @return Collection<int, Testimonial>
     */
    public function library(bool $includeAnonymous = true): Collection
    {
        return Testimonial::query()
            ->with('client')
            ->where('marketing_consent', true)
            ->where('status', Testimonial::STATUS_CONSENTED)
            ->whereNotNull('quote')
            ->when(! $includeAnonymous, fn ($query) => $query->where('display_mode', Testimonial::DISPLAY_NAMED))
            ->latest('consented_at')
            ->get();
    }
}
