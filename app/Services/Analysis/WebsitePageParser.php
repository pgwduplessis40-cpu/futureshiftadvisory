<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class WebsitePageParser
{
    /**
     * @param  array<string, mixed>  $page
     * @return array{page:array<string, mixed>,evidence:array<string, mixed>}
     */
    public function parse(array $page): array
    {
        $html = (string) ($page['body'] ?? '');
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $text = $this->normaliseText((string) $document->textContent);
        $excerptLimit = (int) config('website_audit.max_page_text_bytes', 12_000);
        $excerpt = substr($text, 0, $excerptLimit);
        $images = $xpath->query('//img') ?: [];
        $imagesWithAlt = 0;
        foreach ($images as $image) {
            if (trim((string) $image->attributes?->getNamedItem('alt')?->nodeValue) !== '') {
                $imagesWithAlt++;
            }
        }

        $headings = [];
        foreach (['h1', 'h2', 'h3'] as $tag) {
            $headings[$tag] = collect($xpath->query('//'.$tag) ?: [])
                ->map(fn (DOMNode $node): string => $this->normaliseText((string) $node->textContent))
                ->filter()
                ->take(12)
                ->values()
                ->all();
        }

        $ctaText = collect($xpath->query('//a|//button|//input[@type="submit"]') ?: [])
            ->map(function (DOMNode $node): string {
                $value = $node instanceof DOMElement ? ($node->getAttribute('value') ?: $node->textContent) : $node->textContent;

                return strtolower($this->normaliseText((string) $value));
            })
            ->filter()
            ->filter(fn (string $value): bool => preg_match('/\b(contact|enquir|inquir|book|quote|call|start|talk|consult)\b/i', $value) === 1)
            ->take(8)
            ->values()
            ->all();

        $url = (string) ($page['url'] ?? '');
        $source = 'website:'.$url.' as at '.now()->toIso8601String();

        return [
            'page' => [
                'url' => $url,
                'http_status' => (int) ($page['status'] ?? 0),
                'redirect_chain' => (array) ($page['redirect_chain'] ?? []),
                'title' => $this->firstText($xpath, '//title'),
                'meta_description' => $this->metaContent($xpath, 'description'),
                'canonical' => $this->linkHref($xpath, 'canonical'),
                'open_graph' => $this->metaProperty($xpath, 'og:title') !== null || $this->metaProperty($xpath, 'og:description') !== null,
                'twitter_card' => $this->metaName($xpath, 'twitter:card') !== null,
                'headings' => $headings,
                'schema_types' => $this->schemaTypes($xpath),
                'has_phone_link' => ($xpath->query('//a[starts-with(@href, "tel:")]')?->length ?? 0) > 0,
                'has_email_link' => ($xpath->query('//a[starts-with(@href, "mailto:")]')?->length ?? 0) > 0,
                'has_form' => ($xpath->query('//form')?->length ?? 0) > 0,
                'cta_text' => $ctaText,
                'image_alt' => ['total' => $images->length, 'with_alt' => $imagesWithAlt],
                'word_count' => str_word_count($text),
                'viewport' => $this->metaName($xpath, 'viewport'),
                'language' => $document->documentElement?->getAttribute('lang') ?: null,
                'content_type' => $page['content_type'] ?? null,
                'truncated' => (bool) ($page['truncated'] ?? false),
                'source_reference' => $source,
            ],
            'evidence' => [
                'url' => $url,
                'source_reference' => $source,
                'content_hash' => hash('sha256', $html),
                'byte_count' => strlen($html),
                'text_excerpt' => $excerpt,
                'truncated' => (bool) ($page['truncated'] ?? false) || strlen($text) > strlen($excerpt),
            ],
        ];
    }

    private function firstText(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node instanceof DOMNode ? $this->normaliseText((string) $node->textContent) : null;
    }

    private function metaContent(DOMXPath $xpath, string $name): ?string
    {
        return $this->attribute($xpath, sprintf('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]', strtolower($name)), 'content');
    }

    private function metaName(DOMXPath $xpath, string $name): ?string
    {
        return $this->metaContent($xpath, $name);
    }

    private function metaProperty(DOMXPath $xpath, string $property): ?string
    {
        return $this->attribute($xpath, sprintf('//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]', strtolower($property)), 'content');
    }

    private function linkHref(DOMXPath $xpath, string $rel): ?string
    {
        return $this->attribute($xpath, sprintf('//link[translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="%s"]', strtolower($rel)), 'href');
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $node = $xpath->query($query)?->item(0);
        if (! $node instanceof DOMElement) {
            return null;
        }

        $value = trim($node->getAttribute($attribute));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function schemaTypes(DOMXPath $xpath): array
    {
        $types = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $node) {
            $decoded = json_decode((string) $node->textContent, true);
            $this->collectSchemaTypes($decoded, $types);
        }

        return array_values(array_unique($types));
    }

    /**
     * @param  array<int, string>  $types
     */
    private function collectSchemaTypes(mixed $value, array &$types): void
    {
        if (! is_array($value)) {
            return;
        }
        if (array_key_exists('@type', $value)) {
            foreach ((array) $value['@type'] as $type) {
                if (is_scalar($type) && trim((string) $type) !== '') {
                    $types[] = trim((string) $type);
                }
            }
        }
        foreach ($value as $item) {
            $this->collectSchemaTypes($item, $types);
        }
    }

    private function normaliseText(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)));
    }
}
