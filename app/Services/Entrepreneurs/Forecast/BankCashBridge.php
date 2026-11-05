<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * @phpstan-import-type LedgerResult from CashLedger
 *
 * @phpstan-type BankPoint array{date:string,movement_id:?string,bank:?string,restricted:?string,unrestricted:?string}
 * @phpstan-type BankResult array{status:string,scope:string,bridge_reconciled:bool,input_hash:string,analytical:LedgerResult,bank:LedgerResult,restricted:LedgerResult,unrestricted:LedgerResult,checkpoints:list<BankPoint>,missing:list<string>,errors:list<string>}
 */
final class BankCashBridge implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.assets_tax_actuals'];
    }

    /** @param list<CashMovement> $analyticalMovements
     * @param  list<CashMovement>  $restrictions  Out reserves cash; in releases it. Neither changes bank cash.
     * @return BankResult
     */
    public function calculate(ForecastCalendar $calendar, BankOpening $opening, array $analyticalMovements, TaxCashPlan $tax, array $restrictions): array
    {
        foreach ($analyticalMovements as $movement) {
            if (in_array($movement->category, ['tax', 'gst', 'restricted_transfer'], true)) {
                throw new \InvalidArgumentException('Tax/GST and restrictions have separate inputs and must not be counted twice.');
            }
        }
        $reserveChanges = [];
        foreach ($restrictions as $movement) {
            if ($movement->category !== 'restricted_transfer') {
                throw new \InvalidArgumentException('Internal restriction entries must use restricted_transfer.');
            }
            $reserveChanges[] = new CashMovement($movement->id, $movement->source, $movement->date, $movement->amount,
                $movement->direction === 'in' ? 'out' : 'in', 'restricted_transfer', $movement->sequence, $movement->verified);
        }
        $issues = $tax->issues();
        $missing = $issues['missing'];
        $errors = $issues['errors'];
        $analyticalOpening = AccountingNumbers::cents($opening->analytical);
        $gstOpening = AccountingNumbers::cents($opening->gstBridge);
        $bankOpening = AccountingNumbers::cents($opening->bank);
        $restrictedOpening = AccountingNumbers::cents($opening->restricted);
        foreach (['analytical' => $analyticalOpening, 'gst_bridge' => $gstOpening, 'bank' => $bankOpening, 'restricted' => $restrictedOpening] as $field => $value) {
            if ($value === null) {
                $missing[] = 'opening.'.$field;
            }
        }
        if ($analyticalOpening !== null && $gstOpening !== null && $bankOpening !== null
            && AccountingNumbers::same(DriverNumbers::money($analyticalOpening->plus($gstOpening)), $opening->bank) === false) {
            $errors[] = 'opening.analytical_plus_gst_does_not_equal_bank';
        }
        $taxMovements = array_values(array_filter($tax->movements, fn (CashMovement $m): bool => $m->category === 'tax'));
        $engine = new CashLedger;
        $analytical = $engine->calculate($calendar, new CashOpening($opening->analytical, $opening->source, $opening->reviewed), [...$analyticalMovements, ...$taxMovements]);
        $bank = $engine->calculate($calendar, new CashOpening($opening->bank, $opening->source, $opening->reviewed), [...$analyticalMovements, ...$tax->movements]);
        $restricted = $engine->calculate($calendar, new CashOpening($opening->restricted, $opening->source, $opening->reviewed), $reserveChanges);
        $available = $bankOpening !== null && $restrictedOpening !== null ? $bankOpening->minus($restrictedOpening) : null;
        $unrestricted = $engine->calculate($calendar, new CashOpening(AccountingNumbers::money($available), $opening->source, $opening->reviewed),
            [...$analyticalMovements, ...$tax->movements, ...$restrictions]);
        foreach (['analytical' => $analytical, 'bank' => $bank, 'restricted' => $restricted, 'unrestricted' => $unrestricted] as $name => $ledger) {
            foreach ($ledger['missing'] as $field) {
                $missing[] = $name.'.'.$field;
            }
        }
        $bankPoints = $restrictedPoints = [];
        foreach ($bank['checkpoints'] as $point) {
            if ($point['movement_id'] !== null) {
                $bankPoints[$point['movement_id']] = $point['balance'];
            }
        }
        foreach ($restricted['checkpoints'] as $point) {
            if ($point['movement_id'] !== null) {
                $restrictedPoints[$point['movement_id']] = $point['balance'];
            }
        }
        $bankBalance = $bank['checkpoints'][0]['balance'];
        $restrictedBalance = $restricted['checkpoints'][0]['balance'];
        $points = [];
        foreach ($unrestricted['checkpoints'] as $point) {
            $id = $point['movement_id'] ?? '';
            if (array_key_exists($id, $bankPoints)) {
                $bankBalance = $bankPoints[$id];
            }
            if (array_key_exists($id, $restrictedPoints)) {
                $restrictedBalance = $restrictedPoints[$id];
            }
            if ($restrictedBalance !== null && BigDecimal::of($restrictedBalance)->isNegative()) {
                $errors[] = $point['date'].'.restricted_cash_negative';
            }
            if ($bankBalance !== null && $restrictedBalance !== null && BigDecimal::of($restrictedBalance)->isGreaterThan(BigDecimal::max(0, $bankBalance))) {
                $errors[] = $point['date'].'.restricted_cash_exceeds_bank';
            }
            $points[] = ['date' => $point['date'], 'movement_id' => $point['movement_id'], 'bank' => $bankBalance,
                'restricted' => $restrictedBalance, 'unrestricted' => $point['balance']];
        }

        return ['status' => AccountingNumbers::status($missing, $errors), 'scope' => 'supplied_cash_projection_bridge',
            'bridge_reconciled' => $missing === [] && $errors === [], 'input_hash' => CanonicalJson::hash([$calendar, $opening, $analyticalMovements, $tax, $restrictions]),
            'analytical' => $analytical, 'bank' => $bank, 'restricted' => $restricted, 'unrestricted' => $unrestricted,
            'checkpoints' => $points, 'missing' => $missing, 'errors' => array_values(array_unique($errors))];
    }
}
