<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

/**
 * @phpstan-type ReferenceData array{kind:string,id:string,location:string,version:string}
 * @phpstan-type FactData array{key:string,value:string|int|bool|null,entity?:string,period?:string,scenario?:string,unit?:string|null,basis?:string|null,as_of?:string|null,confirmed?:bool,sources?:list<ReferenceData>}
 */
final class FactCodec
{
    /** @param FactData $data */
    public static function decode(array $data): Fact
    {
        return new Fact($data['key'], $data['value'], $data['entity'] ?? 'business', $data['period'] ?? 'current',
            $data['scenario'] ?? 'base', $data['unit'] ?? null, $data['basis'] ?? null, $data['as_of'] ?? null,
            $data['confirmed'] ?? false, array_map(fn (array $s): SourceReference => new SourceReference($s['kind'], $s['id'], $s['location'], $s['version']), $data['sources'] ?? []));
    }
}
