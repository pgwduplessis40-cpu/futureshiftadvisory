<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use App\Models\User;
use Illuminate\Support\Str;

final class FounderChangeRequestMessage
{
    /**
     * @param  array<int, string>  $paragraphs
     */
    public function build(EntrepreneurProfile $profile, array $paragraphs): string
    {
        return collect([$this->salutation($profile), ...$paragraphs])
            ->map(fn (string $paragraph): string => $this->normalise($paragraph))
            ->filter()
            ->implode("\n\n");
    }

    public function fromAdvisorFeedback(EntrepreneurProfile $profile, string $feedback): string
    {
        $feedback = $this->normalise($feedback);

        if ($this->alreadyAddressed($feedback)) {
            return $feedback;
        }

        return $this->build($profile, [
            'Thank you for submitting your idea validation. I am not ready to approve the business plan builder gate yet.',
            $feedback,
            'Please update the idea validation and resubmit it for review.',
        ]);
    }

    private function salutation(EntrepreneurProfile $profile): string
    {
        $firstName = $this->firstName($profile);

        return $firstName === null ? 'Hello,' : "Dear {$firstName},";
    }

    private function firstName(EntrepreneurProfile $profile): ?string
    {
        foreach ([$profile->name, $profile->user instanceof User ? $profile->user->name : null] as $name) {
            $candidate = $this->nameCandidate($name);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function nameCandidate(mixed $name): ?string
    {
        $candidate = trim((string) $name);

        if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
            return null;
        }

        $candidate = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
        $firstName = trim(Str::before($candidate, ' '), " \t\n\r\0\x0B,.");

        return $firstName === '' || filter_var($firstName, FILTER_VALIDATE_EMAIL) !== false
            ? null
            : $firstName;
    }

    private function alreadyAddressed(string $feedback): bool
    {
        return preg_match('/\A(?:Dear|Hello|Hi)\b[^,\n]*,/i', $feedback) === 1;
    }

    private function normalise(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = preg_replace("/[ \t]+\n/", "\n", $body) ?? $body;

        return trim(preg_replace("/\n{3,}/", "\n\n", $body) ?? $body);
    }
}
