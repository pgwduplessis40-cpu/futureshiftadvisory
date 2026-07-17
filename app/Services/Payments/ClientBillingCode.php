<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Client;
use Illuminate\Database\QueryException;
use RuntimeException;

final class ClientBillingCode
{
    private const PREFIX = 'FSA';

    public function shortCode(Client|string $client): string
    {
        if ($client instanceof Client) {
            $stored = $this->normaliseStored($client->billing_code ?? null);
            if ($stored !== '') {
                return $stored;
            }

            if (! $client->exists) {
                return $this->fromClientId((string) $client->getKey());
            }

            return $this->assignShortCode($client);
        }

        return $this->fromClientId($client);
    }

    public function xeroContactNumber(Client|string $client): string
    {
        $id = $client instanceof Client ? (string) $client->getKey() : $client;

        return mb_substr(self::PREFIX.'-'.$this->cleanId($id), 0, 50);
    }

    public function stripeStatementSuffix(Client|string $client): string
    {
        return mb_substr($this->shortCode($client), 0, 10);
    }

    private function assignShortCode(Client $client): string
    {
        $clientId = (string) $client->getKey();

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = $this->fromClientId($clientId, $attempt);

            if ($this->codeBelongsToAnotherClient($code, $client)) {
                continue;
            }

            try {
                $client->forceFill(['billing_code' => $code])->save();

                return $code;
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                $client->refresh();

                $stored = $this->normaliseStored($client->billing_code ?? null);
                if ($stored !== '') {
                    return $stored;
                }
            }
        }

        throw new RuntimeException('Unable to assign a unique client billing code.');
    }

    private function fromClientId(string $clientId, int $attempt = 0): string
    {
        $suffix = $attempt === 0
            ? mb_substr($this->cleanId($clientId), 0, 6)
            : strtoupper(substr(hash('sha256', $clientId.'|'.$attempt), 0, 6));

        return self::PREFIX.'-'.$suffix;
    }

    private function codeBelongsToAnotherClient(string $code, Client $client): bool
    {
        return Client::query()
            ->where('billing_code', $code)
            ->whereKeyNot($client->getKey())
            ->exists();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }

    private function cleanId(string $clientId): string
    {
        $cleaned = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $clientId) ?? '');

        return $cleaned !== '' ? $cleaned : strtoupper(substr(hash('sha256', $clientId), 0, 6));
    }

    private function normaliseStored(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z0-9 -]{5,10}$/', $value) === 1 ? $value : '';
    }
}
