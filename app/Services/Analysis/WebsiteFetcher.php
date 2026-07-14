<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\WebsiteUrlConfirmation;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

final class WebsiteFetcher
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly WebsiteUrlPolicy $urls,
    ) {}

    /**
     * @return array{fetch_status:string,root_url:string,pages:array<int, array<string, mixed>>,technical:array<string, mixed>,errors:array<int, string>}
     */
    public function fetch(WebsiteUrlConfirmation $confirmation): array
    {
        $rootUrl = $this->urls->normaliseRootUrl($confirmation->root_url);
        $root = $this->urls->resolvePublicUrl($rootUrl);
        $origin = ($parts = parse_url($rootUrl))
            ? strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '')
            : $rootUrl;
        $technical = [
            'https' => str_starts_with($rootUrl, 'https://'),
            'certificate_validity' => 'not_measured',
            'robots' => ['measured' => false],
            'sitemap' => ['measured' => false],
            'redirects' => [],
        ];
        $errors = [];

        $robots = $this->observe($origin.'/robots.txt');
        if ($robots !== null) {
            $technical['robots'] = [
                'measured' => true,
                'status' => $robots['status'],
                'url' => $robots['url'],
            ];

            if ($robots['status'] === 200 && $this->robotsDisallowAudit((string) $robots['body'])) {
                return [
                    'fetch_status' => 'blocked',
                    'root_url' => $rootUrl,
                    'pages' => [],
                    'technical' => $technical,
                    'errors' => ['The nominated website disallows this audit in robots.txt.'],
                ];
            }
        }

        $sitemap = $this->observe($origin.'/sitemap.xml');
        if ($sitemap !== null) {
            $technical['sitemap'] = [
                'measured' => true,
                'status' => $sitemap['status'],
                'url' => $sitemap['url'],
            ];
        }

        $queue = [$root['url']];
        $seen = [];
        $pages = [];
        $totalBytes = 0;
        $maxPages = (int) config('website_audit.max_pages', 15);
        $maxTotalBytes = (int) config('website_audit.max_total_bytes', 2_000_000);

        while ($queue !== [] && count($pages) < $maxPages && $totalBytes < $maxTotalBytes) {
            $candidate = array_shift($queue);
            if (! is_string($candidate) || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;

            try {
                $page = $this->observe($candidate);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            if ($page === null) {
                $errors[] = 'Could not fetch '.$candidate.'.';

                continue;
            }

            $body = (string) $page['body'];
            $remaining = $maxTotalBytes - $totalBytes;
            if (strlen($body) > $remaining) {
                $body = substr($body, 0, max(0, $remaining));
                $page['truncated'] = true;
            }
            $page['body'] = $body;
            $totalBytes += strlen($body);
            $technical['redirects'] = [...$technical['redirects'], ...(array) $page['redirect_chain']];
            $pages[] = $page;

            if ($page['status'] >= 200 && $page['status'] < 300 && $this->isHtml($page)) {
                foreach ($this->discoverInternalLinks($page['url'], $body) as $link) {
                    if (! isset($seen[$link]) && count($queue) + count($pages) < $maxPages) {
                        $queue[] = $link;
                    }
                }
            }
        }

        $hasSuccessfulHtml = collect($pages)->contains(fn (array $page): bool => $page['status'] >= 200
            && $page['status'] < 300
            && $this->isHtml($page));

        return [
            'fetch_status' => $hasSuccessfulHtml && $errors === [] ? 'ok' : ($pages === [] ? 'unreachable' : 'partial'),
            'root_url' => $rootUrl,
            'pages' => $pages,
            'technical' => $technical,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function observe(string $url): ?array
    {
        $current = $this->urls->resolvePublicUrl($url);
        $redirectChain = [];

        for ($redirects = 0; $redirects < 5; $redirects++) {
            $result = $this->http->probe(
                service: 'website_audit:'.$current['host'],
                endpoint: $current['url'],
                acceptableStatusCodes: range(200, 599),
                headers: [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'User-Agent' => (string) config('website_audit.user_agent'),
                ],
                timeoutSeconds: (int) config('website_audit.timeout_seconds', 15),
                options: $this->requestOptions($current),
            );

            if ($result->fromFallback) {
                return null;
            }

            $location = $this->header($result, 'location');
            if ($result->statusCode >= 300 && $result->statusCode < 400 && $location !== null) {
                $next = $this->absoluteUrl($current['url'], $location);
                $redirectChain[] = [
                    'from' => $current['url'],
                    'to' => $next,
                    'status' => $result->statusCode,
                ];
                $current = $this->urls->resolvePublicUrl($next);

                continue;
            }

            return [
                'url' => $current['url'],
                'status' => $result->statusCode,
                'body' => $result->body ?? '',
                'content_type' => $this->header($result, 'content-type'),
                'redirect_chain' => $redirectChain,
                'truncated' => false,
            ];
        }

        return null;
    }

    /**
     * @param  array{host:string,address:string,port:int}  $resolved
     * @return array<string, mixed>
     */
    private function requestOptions(array $resolved): array
    {
        if (! defined('CURLOPT_RESOLVE')) {
            return ['allow_redirects' => false];
        }

        return [
            'allow_redirects' => false,
            'curl' => [constant('CURLOPT_RESOLVE') => ["{$resolved['host']}:{$resolved['port']}:{$resolved['address']}"]],
        ];
    }

    private function header(IntegrationResult $result, string $name): ?string
    {
        foreach ($result->headers as $header => $values) {
            if (strtolower($header) === strtolower($name)) {
                $value = is_array($values) ? $values[0] ?? null : $values;

                return is_string($value) && trim($value) !== '' ? trim($value) : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function isHtml(array $page): bool
    {
        $contentType = strtolower((string) ($page['content_type'] ?? ''));

        return str_contains($contentType, 'text/html') || str_contains(strtolower((string) ($page['body'] ?? '')), '<html');
    }

    /**
     * @return array<int, string>
     */
    private function discoverInternalLinks(string $baseUrl, string $html): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $links = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto:|tel:|javascript:)#i', $href) === 1) {
                continue;
            }

            try {
                $url = $this->absoluteUrl($baseUrl, $href);
                if (strtolower((string) parse_url($url, PHP_URL_HOST)) !== $baseHost) {
                    continue;
                }
                $links[] = $this->urls->normaliseRootUrl($url);
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return array_values(array_unique($links));
    }

    private function absoluteUrl(string $baseUrl, string $reference): string
    {
        if (preg_match('#^https?://#i', $reference) === 1) {
            return $reference;
        }

        $base = parse_url($baseUrl);
        if ($base === false) {
            throw new InvalidArgumentException('Could not resolve website link.');
        }

        $origin = strtolower((string) $base['scheme']).'://'.strtolower((string) $base['host']).(isset($base['port']) ? ':'.$base['port'] : '');
        if (str_starts_with($reference, '//')) {
            return strtolower((string) $base['scheme']).':'.$reference;
        }
        if (str_starts_with($reference, '/')) {
            return $origin.$reference;
        }

        $basePath = (string) ($base['path'] ?? '/');
        $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath).'/';

        return $origin.$directory.$reference;
    }

    private function robotsDisallowAudit(string $body): bool
    {
        $applies = false;
        $disallowsRoot = false;

        foreach (preg_split('/\R/', strtolower($body)) ?: [] as $line) {
            $line = trim(preg_replace('/\s*#.*$/', '', $line) ?? '');
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, 'user-agent:')) {
                $agent = trim(substr($line, strlen('user-agent:')));
                $applies = $agent === '*' || str_contains(strtolower((string) config('website_audit.user_agent')), $agent);

                continue;
            }
            if ($applies && str_starts_with($line, 'disallow:')) {
                $path = trim(substr($line, strlen('disallow:')));
                $disallowsRoot = $path === '/';
            }
        }

        return $disallowsRoot;
    }
}
