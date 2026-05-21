<?php

declare(strict_types=1);

namespace App\Services\Integration\Fixtures;

use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;

final class FixtureRepository
{
    /**
     * @return array<string, mixed>
     */
    public function find(string $fixture, string $key): array
    {
        $data = $this->load($fixture);
        $record = Arr::get($data, $key);

        return is_array($record) ? $record : $this->missing($fixture, $key);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $fixture): array
    {
        $path = base_path("database/fixtures/integration/{$fixture}.json");
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Integration fixture [{$fixture}] is not readable.");
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Integration fixture [{$fixture}] contains invalid JSON.", previous: $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function missing(string $fixture, string $key): array
    {
        return [
            'source' => $fixture,
            'lookup_key' => $key,
            'found' => false,
            'source_badge' => 'stub_miss',
            'degraded' => true,
        ];
    }
}
