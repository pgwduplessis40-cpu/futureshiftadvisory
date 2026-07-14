<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use InvalidArgumentException;

final class WebsiteUrlPolicy
{
    public function normaliseRootUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('A website URL is required.');
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Use a public http(s) website URL without embedded credentials.');
        }

        if ($port !== null && ! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('Website audit URLs may use only ports 80 or 443.');
        }

        $path = trim((string) ($parts['path'] ?? '/'));
        $path = $path === '' ? '/' : '/'.ltrim($path, '/');

        return $scheme.'://'.$host.($port === null ? '' : ':'.$port).$path;
    }

    /**
     * @return array{url:string,host:string,address:string,port:int}
     */
    public function resolvePublicUrl(string $url): array
    {
        $url = $this->normaliseRootUrl($url);
        $parts = parse_url($url);
        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? (($parts['scheme'] ?? 'https') === 'https' ? 443 : 80));

        if ($this->isBlockedHost($host)) {
            throw new InvalidArgumentException('The nominated website host is not publicly fetchable.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : (gethostbynamel($host) ?: []);
        $address = collect($addresses)->first(fn (mixed $candidate): bool => is_string($candidate) && $this->isPublicIp($candidate));

        if (! is_string($address)) {
            throw new InvalidArgumentException('The nominated website host could not be resolved to a public address.');
        }

        return compact('url', 'host', 'address', 'port');
    }

    private function isBlockedHost(string $host): bool
    {
        $host = strtolower(trim($host, '.'));

        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.invalid');
    }

    private function isPublicIp(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
