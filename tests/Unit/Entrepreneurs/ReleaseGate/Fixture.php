<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\Audience;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\DocumentBlock;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactSet;
use App\Services\Entrepreneurs\ReleaseGate\SourceReference;
use App\Services\Entrepreneurs\ReleaseGate\SourceVersion;

final class Fixture
{
    public static function f1(string $document = 'plan'): DocumentContext
    {
        $path = dirname(__DIR__, 3).'/Fixtures/Entrepreneurs/ReleaseGate/f1.json';
        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $facts = array_map(self::fact(...), $data['facts']);
        $blocks = [];
        foreach ($data['blocks'] as $row) {
            if ($row['source']['id'] !== $document) {
                continue;
            }
            $blocks[] = new DocumentBlock($row['id'], $row['text'], self::reference($row['source']),
                $row['generated'] ?? false, $row['quotation'] ?? false, $row['heading'] ?? false, $row['internal_instruction'] ?? false);
        }

        return new DocumentContext(new SourceVersion('f1', CanonicalJson::hash($facts), CanonicalJson::hash($blocks), [],
            $data['required_horizon'], $data['as_of'], CanonicalJson::encode($data)), new FactSet($facts), $document, Audience::External, $blocks, true);
    }

    public static function fact(array $row): Fact
    {
        return new Fact($row['key'], $row['value'], $row['entity'] ?? 'business', $row['period'] ?? 'current',
            $row['scenario'] ?? 'base', $row['unit'] ?? null, $row['basis'] ?? null, $row['as_of'] ?? null,
            $row['confirmed'] ?? false, array_map(self::reference(...), $row['sources'] ?? []));
    }

    private static function reference(array $row): SourceReference
    {
        return new SourceReference($row['kind'], $row['id'], $row['location'], $row['version']);
    }
}
