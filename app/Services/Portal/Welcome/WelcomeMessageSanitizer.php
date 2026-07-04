<?php

declare(strict_types=1);

namespace App\Services\Portal\Welcome;

final class WelcomeMessageSanitizer
{
    /**
     * Keep the source as plain Markdown with placeholders. Raw HTML is removed
     * before storage so portal rendering is not the only XSS boundary.
     */
    public function sanitize(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = $this->removeRawHtml($body);
        $body = $this->removeUnsafeMarkdownLinks($body);
        $body = preg_replace("/[ \t]+\n/", "\n", $body) ?? $body;
        $body = preg_replace("/\n{4,}/", "\n\n\n", $body) ?? $body;

        return trim($body);
    }

    private function removeRawHtml(string $body): string
    {
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?<\/\1>/is', '', $body) ?? $body;
        $body = preg_replace('/<[^>]+>/', '', $body) ?? $body;

        return $body;
    }

    private function removeUnsafeMarkdownLinks(string $body): string
    {
        return preg_replace_callback(
            '/\[([^\]\n]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/',
            function (array $matches): string {
                $label = (string) $matches[1];
                $target = trim((string) $matches[2]);

                return $this->isSafeLinkTarget($target)
                    ? $matches[0]
                    : $label;
            },
            $body,
        ) ?? $body;
    }

    private function isSafeLinkTarget(string $target): bool
    {
        if ($target === '') {
            return false;
        }

        $lower = strtolower($target);

        return str_starts_with($lower, 'https://')
            || str_starts_with($lower, 'http://')
            || str_starts_with($lower, 'mailto:')
            || str_starts_with($target, '/')
            || str_starts_with($target, '#');
    }
}
