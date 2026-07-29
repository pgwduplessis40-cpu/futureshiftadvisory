<?php

declare(strict_types=1);

namespace App\Services\Operations;

use JsonException;

final class DeploymentIdentity
{
    private const METADATA_PATH = 'app/deployment.json';

    /**
     * @return array{
     *     status:'verified'|'unverified',
     *     version:string|null,
     *     commit:string|null,
     *     deployed_at:string|null,
     *     client_manifest_sha256:string|null,
     *     ssr_manifest_sha256:string|null
     * }
     */
    public function current(): array
    {
        $metadata = $this->readMetadata();

        if ($metadata === null) {
            return [
                'status' => 'unverified',
                'version' => null,
                'commit' => null,
                'deployed_at' => null,
                'client_manifest_sha256' => null,
                'ssr_manifest_sha256' => null,
            ];
        }

        return [
            'status' => 'verified',
            'version' => $metadata['version'],
            'commit' => $metadata['commit'],
            'deployed_at' => $metadata['deployed_at'],
            'client_manifest_sha256' => $metadata['client_manifest_sha256'],
            'ssr_manifest_sha256' => $metadata['ssr_manifest_sha256'],
        ];
    }

    /**
     * @return array{version:string,commit:string,deployed_at:string,client_manifest_sha256:string,ssr_manifest_sha256:string}|null
     */
    private function readMetadata(): ?array
    {
        $path = storage_path(self::METADATA_PATH);

        if (! is_file($path)) {
            return null;
        }

        try {
            $metadata = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($metadata)) {
            return null;
        }

        $keys = ['version', 'commit', 'deployed_at', 'client_manifest_sha256', 'ssr_manifest_sha256'];

        foreach ($keys as $key) {
            if (! is_string($metadata[$key] ?? null) || trim($metadata[$key]) === '') {
                return null;
            }
        }

        return [
            'version' => trim($metadata['version']),
            'commit' => trim($metadata['commit']),
            'deployed_at' => trim($metadata['deployed_at']),
            'client_manifest_sha256' => trim($metadata['client_manifest_sha256']),
            'ssr_manifest_sha256' => trim($metadata['ssr_manifest_sha256']),
        ];
    }
}
