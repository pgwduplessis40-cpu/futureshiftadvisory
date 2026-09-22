<?php

declare(strict_types=1);

namespace App\Support\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reads the public blog from Markdown files in resources/content/blog.
 *
 * Each post is one .md file with a small frontmatter block:
 *
 *   ---
 *   title: How to start a business in New Zealand
 *   description: A practical, honest guide for first-time founders.
 *   date: 2026-09-22
 *   ---
 *   Markdown body...
 *
 * The filename (without .md) is the slug. Posts are authored in the repo and
 * deploy with the site, so the content is trusted - the Markdown is rendered
 * as-is, no user input is involved.
 */
final class BlogRepository
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? resource_path('content/blog');
    }

    /**
     * All posts, newest first, as list metadata (no rendered body).
     *
     * @return array<int, array{slug:string, title:string, description:string, date:string, date_iso:string}>
     */
    public function all(): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $posts = [];
        foreach (glob($this->directory.'/*.md') ?: [] as $file) {
            $parsed = $this->parse($file);
            if ($parsed !== null) {
                $posts[] = $parsed['meta'];
            }
        }

        usort($posts, static fn (array $a, array $b): int => strcmp($b['date_iso'], $a['date_iso']));

        return $posts;
    }

    /**
     * A single post with its rendered HTML body, or null if it does not exist.
     *
     * @return array{slug:string, title:string, description:string, date:string, date_iso:string, html:string}|null
     */
    public function find(string $slug): ?array
    {
        // basename() blocks path traversal (../) through the slug.
        $file = $this->directory.'/'.basename($slug).'.md';
        if (! is_file($file)) {
            return null;
        }

        $parsed = $this->parse($file);
        if ($parsed === null) {
            return null;
        }

        return $parsed['meta'] + ['html' => Str::markdown($parsed['body'])];
    }

    /**
     * @return array{meta: array{slug:string, title:string, description:string, date:string, date_iso:string}, body: string}|null
     */
    private function parse(string $file): ?array
    {
        $raw = (string) file_get_contents($file);
        if (! preg_match('/^---\s*\R(.*?)\R---\s*\R(.*)$/s', $raw, $matches)) {
            return null;
        }

        $front = $this->frontmatter($matches[1]);
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $dateRaw = (string) ($front['date'] ?? '');
        $date = $dateRaw !== ''
            ? Carbon::parse($dateRaw)
            : Carbon::createFromTimestamp((int) filemtime($file));

        return [
            'meta' => [
                'slug' => $slug,
                'title' => (string) ($front['title'] ?? Str::headline($slug)),
                'description' => (string) ($front['description'] ?? ''),
                'date' => $date->format('j F Y'),
                'date_iso' => $date->toDateString(),
            ],
            'body' => trim($matches[2]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function frontmatter(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/', $line, $match)) {
                $out[strtolower($match[1])] = trim($match[2], " \t\"'");
            }
        }

        return $out;
    }
}
