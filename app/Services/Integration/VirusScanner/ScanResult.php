<?php

declare(strict_types=1);

namespace App\Services\Integration\VirusScanner;

final readonly class ScanResult
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    public const ERROR = 'error';

    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public string $result,
        public ?string $signature = null,
        public ?string $message = null,
        public array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function clean(array $payload = []): self
    {
        return new self(self::CLEAN, payload: $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function infected(string $signature, array $payload = []): self
    {
        return new self(self::INFECTED, signature: $signature, payload: $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function error(string $message, array $payload = []): self
    {
        return new self(self::ERROR, message: $message, payload: $payload);
    }

    public function isClean(): bool
    {
        return $this->result === self::CLEAN;
    }

    public function isInfected(): bool
    {
        return $this->result === self::INFECTED;
    }

    public function isError(): bool
    {
        return $this->result === self::ERROR;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return array_filter([
            'result' => $this->result,
            'signature' => $this->signature,
            'message' => $this->message,
            'payload' => $this->payload,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
