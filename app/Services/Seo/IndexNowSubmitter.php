<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

/**
 * Notifies IndexNow (Bing, Yandex, and other participating engines) the moment
 * public content changes, so new blog posts are crawled in hours rather than
 * waiting to be discovered.
 *
 * The call goes through the resilience layer, never a raw HTTP client, per the
 * integration rules. With no key configured this no-ops, so local and test
 * environments never reach out to the network.
 */
final class IndexNowSubmitter
{
    public function __construct(private readonly ResilientHttp $http) {}

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    /**
     * @param  list<string>  $urls
     */
    public function submit(array $urls): bool
    {
        $urls = array_values(array_unique(array_filter(
            $urls,
            static fn (string $url): bool => trim($url) !== '',
        )));

        $host = $this->host();

        if (! $this->isConfigured() || $host === '' || $urls === []) {
            return false;
        }

        $result = $this->http->post('indexnow', $this->endpoint(), [
            'host' => $host,
            'key' => $this->key(),
            'keyLocation' => 'https://'.$host.'/'.$this->key().'.txt',
            'urlList' => $urls,
        ]);

        return $result->successful();
    }

    private function key(): string
    {
        return trim((string) Config::get('services.indexnow.key'));
    }

    private function endpoint(): string
    {
        $endpoint = trim((string) Config::get('services.indexnow.endpoint'));

        return $endpoint !== '' ? $endpoint : 'https://api.indexnow.org/indexnow';
    }

    private function host(): string
    {
        $host = parse_url((string) Config::get('app.url'), PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
