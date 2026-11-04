<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use Brick\Math\BigDecimal;

final readonly class Fact implements \JsonSerializable
{
    /** @param list<SourceReference> $sources */
    public function __construct(
        public string $key,
        public string|int|bool|null $value,
        public string $entity = 'business',
        public string $period = 'current',
        public string $scenario = 'base',
        public ?string $unit = null,
        public ?string $basis = null,
        public ?string $asOf = null,
        public bool $confirmed = false,
        public array $sources = [],
    ) {}

    public function number(): ?BigDecimal
    {
        return (is_string($this->value) || is_int($this->value)) && preg_match('/^-?\d{1,18}(?:\.\d{1,12})?$/D', (string) $this->value) === 1
            ? BigDecimal::of($this->value) : null;
    }

    /** @return array{key:string,value:string|int|bool|null,entity:string,period:string,scenario:string,unit:string|null,basis:string|null,as_of:string|null,confirmed:bool,sources:list<SourceReference>} */
    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'value' => $this->value, 'entity' => $this->entity, 'period' => $this->period,
            'scenario' => $this->scenario, 'unit' => $this->unit, 'basis' => $this->basis, 'as_of' => $this->asOf,
            'confirmed' => $this->confirmed, 'sources' => $this->sources];
    }
}
