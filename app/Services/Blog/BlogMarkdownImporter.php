<?php

declare(strict_types=1);

namespace App\Services\Blog;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BlogMarkdownImporter
{
    public const MAX_FILE_BYTES = 524288;

    public const MAX_TITLE_LENGTH = 200;

    public const MAX_SLUG_LENGTH = 200;

    public const MAX_DESCRIPTION_LENGTH = 300;

    public const MAX_BODY_LENGTH = 100000;

    /**
     * @return array{title:string,slug:string,description:string,body:string}
     */
    public function parse(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'md') {
            $this->reject('file', 'Choose a Markdown (.md) file.');
        }

        $content = (string) $file->get();
        if (strlen($content) > self::MAX_FILE_BYTES) {
            $this->reject('file', 'The Markdown file may not be larger than 512 KB.');
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $this->reject('file', 'The Markdown file must be valid UTF-8 text.');
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (! preg_match('/\A---[ \t]*\n(?<frontmatter>.*?)\n---[ \t]*\n(?<body>.*)\z/s', $content, $matches)) {
            $this->reject('file', 'The Markdown file must start with a valid frontmatter block.');
        }

        $frontmatter = $this->parseFrontmatter((string) $matches['frontmatter']);
        foreach (['title', 'description', 'date'] as $field) {
            if (! array_key_exists($field, $frontmatter) || $frontmatter[$field] === '') {
                $this->reject('file', "Frontmatter requires a {$field} value.");
            }
        }

        $title = trim($frontmatter['title']);
        $description = trim($frontmatter['description']);
        $body = trim((string) $matches['body']);
        $slug = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($slug === '') {
            $this->reject('file', 'The Markdown filename must contain letters or numbers for its initial slug.');
        }

        if (! $this->isIsoDate($frontmatter['date'])) {
            $this->reject('file', 'Frontmatter date must use the YYYY-MM-DD format.');
        }

        $this->assertLength('title', $title, self::MAX_TITLE_LENGTH);
        $this->assertLength('slug', $slug, self::MAX_SLUG_LENGTH);
        $this->assertLength('description', $description, self::MAX_DESCRIPTION_LENGTH);
        $this->assertLength('body', $body, self::MAX_BODY_LENGTH);

        return compact('title', 'slug', 'description', 'body');
    }

    /**
     * @return array<string, string>
     */
    private function parseFrontmatter(string $frontmatter): array
    {
        $values = [];

        foreach (explode("\n", $frontmatter) as $line) {
            if (! preg_match('/\A([A-Za-z_][A-Za-z0-9_]*):[ \t]*(.*)\z/', $line, $match)) {
                $this->reject('file', 'Frontmatter contains an invalid line.');
            }

            $key = strtolower($match[1]);
            if (! in_array($key, ['title', 'description', 'date'], true) || array_key_exists($key, $values)) {
                $this->reject('file', 'Frontmatter may contain title, description, and date once each.');
            }

            $values[$key] = trim($match[2], " \t\"'");
        }

        return $values;
    }

    private function isIsoDate(string $date): bool
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Pacific/Auckland');
        } catch (\Throwable) {
            return false;
        }

        return $parsed->format('Y-m-d') === $date;
    }

    private function assertLength(string $field, string $value, int $maximum): void
    {
        if (mb_strlen($value) > $maximum) {
            $this->reject($field, "The {$field} may not be longer than {$maximum} characters.");
        }
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
