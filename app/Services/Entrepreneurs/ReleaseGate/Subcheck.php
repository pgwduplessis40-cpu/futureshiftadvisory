<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class Subcheck implements \JsonSerializable
{
    /**
     * @param  list<Fact>  $compared
     * @param  list<string>  $missingInputs
     */
    public function __construct(
        public string $id,
        public CheckStatus $status,
        public Severity $severity,
        public string $message,
        public array $compared = [],
        public array $missingInputs = [],
        public ?string $scopeReason = null,
        public string $precision = '0.01',
    ) {
        if ($status === CheckStatus::CannotVerify && $missingInputs === []) {
            throw new \InvalidArgumentException('Cannot verify requires named missing inputs.');
        }
        if ($status === CheckStatus::NotApplicable && trim($scopeReason ?? '') === '') {
            throw new \InvalidArgumentException('Not applicable requires a proven scope reason.');
        }
        if ($status === CheckStatus::Pass && $missingInputs !== []) {
            throw new \InvalidArgumentException('A check with missing inputs cannot pass.');
        }
    }

    /** @param list<Fact> $compared */
    public static function missing(string $id, string $input, Severity $severity = Severity::Block, array $compared = []): self
    {
        return new self($id, CheckStatus::CannotVerify, $severity, 'Cannot verify: provide '.$input.'.', compared: $compared, missingInputs: [$input]);
    }

    public function hash(): string
    {
        return CanonicalJson::hash($this);
    }

    /** @return array{id:string,result:string,severity:string,message:string,compared:list<Fact>,missing_inputs:list<string>,scope_reason:string|null,precision:string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'result' => $this->status->value, 'severity' => $this->severity->value,
            'message' => $this->message, 'compared' => $this->compared, 'missing_inputs' => $this->missingInputs,
            'scope_reason' => $this->scopeReason, 'precision' => $this->precision];
    }
}
