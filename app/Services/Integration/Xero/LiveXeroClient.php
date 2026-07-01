<?php

declare(strict_types=1);

namespace App\Services\Integration\Xero;

use App\Models\AccountingConnection;
use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Xero\Contracts\XeroClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

final class LiveXeroClient implements XeroClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
    ) {}

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $this->ensureLive();

        $result = $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->tokenEndpoint(),
            options: [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic '.base64_encode($this->credential('client_id').':'.$this->credential('client_secret')),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ],
            ],
            cacheKey: null,
            fallback: null,
        );

        $token = $this->payload($result, 'Xero token exchange failed.');
        $accessToken = $this->stringValue($token, 'access_token');
        if ($accessToken === '') {
            throw new InvalidArgumentException('Xero token response did not include an access token.');
        }

        $tenant = $this->tenant($accessToken);
        $expiresIn = (int) ($token['expires_in'] ?? 0);
        $scope = $this->scopes((string) ($token['scope'] ?? ''));

        return [
            ...$token,
            'expires_at' => $expiresIn > 0 ? Carbon::now()->addSeconds(max(60, $expiresIn - 60))->toIso8601String() : null,
            'external_tenant_id' => $tenant['tenantId'],
            'external_tenant_name' => $tenant['tenantName'] ?? null,
            'external_tenant_type' => $tenant['tenantType'] ?? null,
            'scopes' => $scope,
            'source' => $this->provider(),
            'source_badge' => 'live',
            'degraded' => false,
            'correlation_id' => $result->correlationId,
        ];
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function refreshAccessToken(array $token): array
    {
        $this->ensureLive();

        $refreshToken = $this->stringValue($token, 'refresh_token');
        if ($refreshToken === '') {
            throw new InvalidArgumentException('Xero refresh token is missing. Reconnect Xero before syncing invoices.');
        }

        $result = $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->tokenEndpoint(),
            options: [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic '.base64_encode($this->credential('client_id').':'.$this->credential('client_secret')),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ],
            cacheKey: null,
            fallback: null,
        );

        $refreshed = $this->payload($result, 'Xero token refresh failed.');
        $accessToken = $this->stringValue($refreshed, 'access_token');
        if ($accessToken === '') {
            throw new InvalidArgumentException('Xero token refresh did not return an access token.');
        }

        $expiresIn = (int) ($refreshed['expires_in'] ?? 0);
        $scope = $this->scopes((string) ($refreshed['scope'] ?? ($token['scope'] ?? '')));

        return [
            ...$token,
            ...$refreshed,
            'expires_at' => $expiresIn > 0 ? Carbon::now()->addSeconds(max(60, $expiresIn - 60))->toIso8601String() : null,
            'scopes' => $scope,
            'source' => $this->provider(),
            'source_badge' => 'live',
            'degraded' => false,
            'correlation_id' => $result->correlationId,
        ];
    }

    public function financialSnapshot(AccountingConnection $connection, array $token): array
    {
        $this->ensureLive();

        $accessToken = $this->stringValue($token, 'access_token');
        $tenantId = (string) $connection->external_tenant_id;
        if ($accessToken === '' || $tenantId === '') {
            throw new InvalidArgumentException('Xero connection is missing an access token or tenant id.');
        }

        $headers = $this->xeroHeaders($accessToken, $tenantId);

        [$periodStart, $periodEnd, $profitAndLossReport, $hasReportActivity] = $this->latestProfitAndLossPeriod($headers);
        $balanceSheetReport = $this->report('BalanceSheet', [
            'date' => $periodEnd->toDateString(),
        ], $headers);
        $bankSummaryReport = $this->report('BankSummary', [
            'fromDate' => $periodStart->toDateString(),
            'toDate' => $periodEnd->toDateString(),
        ], $headers);
        $hasReportActivity = $hasReportActivity
            || $this->reportHasActivity($balanceSheetReport)
            || $this->reportHasActivity($bankSummaryReport);

        $revenue = $this->reportAmount($profitAndLossReport, ['Total Income', 'Income', 'Revenue', 'Sales']);
        $grossProfit = $this->reportAmount($profitAndLossReport, ['Gross Profit', 'Total Gross Profit']);
        $operatingExpenses = $this->reportAmount($profitAndLossReport, ['Total Operating Expenses', 'Operating Expenses', 'Total Expenses']);
        $netProfit = $this->reportAmount($profitAndLossReport, ['Net Profit', 'Net Profit/(Loss)', 'Net Earnings']);
        $assets = $this->reportAmount($balanceSheetReport, ['Total Assets', 'Assets']);
        $liabilities = $this->reportAmount($balanceSheetReport, ['Total Liabilities', 'Liabilities']);
        $equity = $this->reportAmount($balanceSheetReport, ['Net Assets', 'Total Equity', 'Equity']);
        $currentAssets = $this->reportAmount($balanceSheetReport, ['Total Current Assets', 'Current Assets']);
        $currentLiabilities = $this->reportAmount($balanceSheetReport, ['Total Current Liabilities', 'Current Liabilities']);
        $closingCash = $this->reportAmount($balanceSheetReport, ['Bank', 'Cash', 'Cash and Cash Equivalents']);
        $operatingCashFlow = $this->reportAmount($bankSummaryReport, ['Net Movement', 'Total Net Movement', 'Cash Movement']);

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'source' => $this->provider(),
            'source_badge' => $hasReportActivity ? 'live' : 'live_no_data',
            'degraded' => ! $hasReportActivity,
            'profit_and_loss' => [
                'revenue' => $revenue,
                'gross_profit' => $grossProfit,
                'operating_expenses' => $operatingExpenses,
                'net_profit' => $netProfit,
            ],
            'balance_sheet' => [
                'assets' => $assets,
                'liabilities' => $liabilities,
                'equity' => $equity,
                'cash' => $closingCash,
            ],
            'cash_flow' => [
                'operating_cash_flow' => $operatingCashFlow,
                'closing_cash' => $closingCash,
            ],
            'metrics' => [
                'gross_margin' => $this->ratio($grossProfit, $revenue),
                'net_margin' => $this->ratio($netProfit, $revenue),
                'current_ratio' => $this->ratio($currentAssets, $currentLiabilities),
                'operating_cash_flow' => $operatingCashFlow,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $token
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function createContact(array $token, string $tenantId, array $contact): array
    {
        $this->ensureLive();

        $headers = $this->xeroHeaders($this->accessToken($token), $tenantId);
        $result = $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->accountingEndpoint('Contacts'),
            options: [
                'headers' => $headers,
                'json' => [
                    'Contacts' => [$contact],
                ],
            ],
            cacheKey: null,
            fallback: null,
        );

        return $this->payload($result, 'Xero contact creation failed.');
    }

    /**
     * @param  array<string, mixed>  $token
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    public function createInvoice(array $token, string $tenantId, array $invoice): array
    {
        $this->ensureLive();

        $headers = $this->xeroHeaders($this->accessToken($token), $tenantId);
        $result = $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->accountingEndpoint('Invoices'),
            options: [
                'headers' => $headers,
                'json' => [
                    'Invoices' => [$invoice],
                ],
            ],
            cacheKey: null,
            fallback: null,
        );

        return $this->payload($result, 'Xero invoice creation failed.');
    }

    public function revoke(AccountingConnection $connection, array $token): void
    {
        $this->ensureLive();

        $accessToken = $this->stringValue($token, 'access_token');
        if ($accessToken === '') {
            return;
        }

        $this->http->request(
            method: 'POST',
            service: $this->provider(),
            endpoint: $this->revokeEndpoint(),
            options: [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic '.base64_encode($this->credential('client_id').':'.$this->credential('client_secret')),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'token' => $accessToken,
                ],
            ],
            cacheKey: null,
            fallback: null,
        );
    }

    private function provider(): string
    {
        return AccountingConnection::PROVIDER_XERO;
    }

    private function ensureLive(): void
    {
        if (! $this->live->isLive($this->provider())) {
            throw IntegrationDisabledException::forService($this->provider());
        }
    }

    private function tokenEndpoint(): string
    {
        return (string) Config::get('integrations.accounting.xero.token_url', 'https://identity.xero.com/connect/token');
    }

    private function revokeEndpoint(): string
    {
        return (string) Config::get('integrations.accounting.xero.revoke_url', 'https://identity.xero.com/connect/revocation');
    }

    private function baseEndpoint(string $path): string
    {
        return rtrim((string) Config::get('integrations.accounting.xero.base_url', 'https://api.xero.com'), '/').'/'.ltrim($path, '/');
    }

    private function accountingEndpoint(string $path): string
    {
        return rtrim((string) Config::get('integrations.accounting.xero.api_base_url', 'https://api.xero.com/api.xro/2.0'), '/').'/'.ltrim($path, '/');
    }

    private function credential(string $field): string
    {
        return (string) ($this->credentials->get($this->provider(), $field) ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(IntegrationResult $result, string $message): array
    {
        if ($result->fromFallback || ! is_array($result->data)) {
            throw new InvalidArgumentException($message);
        }

        return $result->data;
    }

    /**
     * @return array<string, mixed>
     */
    private function tenant(string $accessToken): array
    {
        $result = $this->http->request(
            method: 'GET',
            service: $this->provider(),
            endpoint: $this->baseEndpoint('connections'),
            options: [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ],
            cacheKey: null,
            fallback: null,
        );

        $connections = $this->payload($result, 'Xero tenant discovery failed.');
        $tenant = collect($connections)
            ->filter(fn (mixed $connection): bool => is_array($connection))
            ->first(fn (array $connection): bool => (string) ($connection['tenantType'] ?? '') === 'ORGANISATION')
            ?? collect($connections)->first(fn (mixed $connection): bool => is_array($connection));

        if (! is_array($tenant) || ! is_string($tenant['tenantId'] ?? null) || $tenant['tenantId'] === '') {
            throw new InvalidArgumentException('Xero did not return an authorised organisation tenant.');
        }

        return $tenant;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function report(string $name, array $query, array $headers): array
    {
        $result = $this->http->request(
            method: 'GET',
            service: $this->provider(),
            endpoint: $this->accountingEndpoint("Reports/{$name}"),
            options: [
                'headers' => $headers,
                'query' => $query,
            ],
            cacheKey: null,
            fallback: null,
        );

        return $this->payload($result, "Xero {$name} report pull failed.");
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0:Carbon,1:Carbon,2:array<string, mixed>,3:bool}
     */
    private function latestProfitAndLossPeriod(array $headers): array
    {
        $fallback = null;

        for ($monthsBack = 1; $monthsBack <= 36; $monthsBack++) {
            $periodEnd = Carbon::now()->subMonthsNoOverflow($monthsBack)->endOfMonth();
            $periodStart = $periodEnd->copy()->startOfMonth();
            $report = $this->report('ProfitAndLoss', [
                'fromDate' => $periodStart->toDateString(),
                'toDate' => $periodEnd->toDateString(),
            ], $headers);

            $hasActivity = $this->reportHasActivity($report);
            $fallback ??= [$periodStart, $periodEnd, $report, $hasActivity];

            if ($hasActivity) {
                return [$periodStart, $periodEnd, $report, true];
            }
        }

        return $fallback;
    }

    /**
     * @return array<string, string>
     */
    private function xeroHeaders(string $accessToken, string $tenantId): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$accessToken,
            'xero-tenant-id' => $tenantId,
        ];
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function accessToken(array $token): string
    {
        $accessToken = $this->stringValue($token, 'access_token');
        if ($accessToken === '') {
            throw new InvalidArgumentException('Xero access token is missing.');
        }

        return $accessToken;
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<int, string>  $labels
     */
    private function reportAmount(array $report, array $labels): float
    {
        $normalisedLabels = collect($labels)
            ->map(fn (string $label): string => $this->normaliseLabel($label))
            ->all();

        foreach ($this->reportRows($report) as $row) {
            $label = $this->normaliseLabel((string) ($row['label'] ?? ''));
            if (in_array($label, $normalisedLabels, true)) {
                return $row['amount'];
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array{label:string, amount:float}>
     */
    private function reportRows(array $report): array
    {
        return $this->flattenRows((array) data_get($report, 'Reports.0.Rows', []));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function reportHasActivity(array $report): bool
    {
        foreach ($this->reportRows($report) as $row) {
            if (abs($row['amount']) > 0.0001) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{label:string, amount:float}>
     */
    private function flattenRows(array $rows): array
    {
        $flattened = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = is_array($row['Cells'] ?? null) ? $row['Cells'] : [];
            $label = $this->cellValue($cells, 0);
            $amount = $this->lastNumericCell($cells);

            if ($label !== '' && $amount !== null) {
                $flattened[] = ['label' => $label, 'amount' => $amount];
            }

            if (is_array($row['Rows'] ?? null)) {
                array_push($flattened, ...$this->flattenRows($row['Rows']));
            }
        }

        return $flattened;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function cellValue(array $cells, int $index): string
    {
        $cell = $cells[$index] ?? null;

        return is_array($cell) ? (string) ($cell['Value'] ?? '') : '';
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function lastNumericCell(array $cells): ?float
    {
        foreach (array_reverse($cells) as $cell) {
            if (! is_array($cell)) {
                continue;
            }

            $value = str_replace([',', '$', 'NZD', ' '], '', (string) ($cell['Value'] ?? ''));
            if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
                $value = '-'.trim($value, '()');
            }

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function normaliseLabel(string $label): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $label) ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key): string
    {
        return is_scalar($payload[$key] ?? null) ? (string) $payload[$key] : '';
    }

    private function ratio(float $numerator, float $denominator): float
    {
        return $denominator == 0.0 ? 0.0 : round($numerator / $denominator, 4);
    }

    /**
     * @return array<int, string>
     */
    private function scopes(string $scope): array
    {
        $scopes = array_values(array_filter(preg_split('/\s+/', trim($scope)) ?: []));

        return $scopes !== []
            ? $scopes
            : [
                'accounting.reports.balancesheet.read',
                'accounting.reports.profitandloss.read',
                'accounting.reports.banksummary.read',
                'offline_access',
            ];
    }
}
