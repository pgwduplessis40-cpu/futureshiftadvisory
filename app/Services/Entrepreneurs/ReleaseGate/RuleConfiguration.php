<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

final readonly class RuleConfiguration implements \JsonSerializable
{
    public const VERSION = '2026-11-04.1';

    public const DEFAULTS = [
        'opening_cash_tolerance_percent' => '1',
        'material_cost' => '1000',
        'asset_review' => '1000',
        'cash_tolerance' => '0.01',
        'duplicate_similarity_percent' => '90',
    ];

    /**
     * @param  array<string,string>  $thresholds
     * @param  array<string,string>  $severities
     * @param  array<string,string>  $messages
     */
    public function __construct(public array $thresholds = [], public array $severities = [], public array $messages = [])
    {
        foreach ($thresholds as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS) || preg_match('/^\d{1,12}(?:\.\d{1,8})?$/D', $value) !== 1 || (float) $value < 0
                || (str_ends_with($key, '_percent') && (float) $value > 100)
                || ($key === 'cash_tolerance' && $value !== '0.01')) {
                throw new \InvalidArgumentException('Invalid release-rule threshold: '.$key);
            }
        }
        foreach ($severities as $id => $value) {
            $rule = RuleCatalogue::get($id);
            $severity = Severity::tryFrom($value);
            if ($severity === null || $severity->rank() < $rule->severity->rank()) {
                throw new \InvalidArgumentException('Rule severity cannot fall below its code-enforced floor: '.$id);
            }
        }
        foreach ($messages as $id => $message) {
            RuleCatalogue::get($id);
            if (mb_strlen(trim($message)) < 5 || mb_strlen($message) > 1000) {
                throw new \InvalidArgumentException('Rule guidance must be between 5 and 1000 characters.');
            }
        }
    }

    public function threshold(string $key): string
    {
        return $this->thresholds[$key] ?? self::DEFAULTS[$key] ?? throw new \InvalidArgumentException('Unknown rule threshold.');
    }

    public function severity(RuleDefinition $rule, Subcheck $check): Severity
    {
        $configured = isset($this->severities[$rule->id]) ? Severity::from($this->severities[$rule->id]) : $check->severity;

        return $configured->rank() >= $check->severity->rank() ? $configured : $check->severity;
    }

    public function hash(): string
    {
        return CanonicalJson::hash($this);
    }

    /** @return array{version:string,thresholds:array<string,string>,severities:array<string,string>,messages:array<string,string>} */
    public function jsonSerialize(): array
    {
        return ['version' => self::VERSION, 'thresholds' => array_replace(self::DEFAULTS, $this->thresholds), 'severities' => $this->severities, 'messages' => $this->messages];
    }
}
