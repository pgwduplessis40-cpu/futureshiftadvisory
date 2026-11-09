<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastDates;
use App\Services\Entrepreneurs\Forecast\FundingSource;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TaxPolicy;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use Illuminate\Validation\ValidationException;

/**
 * Turns the trade, owner pay, asset and tax form into engine value objects.
 *
 * These four sections share a form because they share a failure mode: each one
 * is a set of dated records whose identities and ordering the cash ledger
 * relies on. The ledger refuses to count one movement twice and refuses two
 * movements that claim the same position on the same day, so those invariants
 * are enforced here, at the point of entry, with messages that name the record
 * at fault rather than left for the engine to discover later.
 *
 * @phpstan-type SettlementRow array{id:string,source:string,date:string|null,amount:string|null,category:string|null,verified:string|null}
 * @phpstan-type TradeRow array{id:string,source:string,kind:string,raised_on:string|null,amount:string|null,settlements:list<SettlementRow>}
 * @phpstan-type OwnerPayRow array{effective_on:string,weekly_amount:string|null,trigger:string,threshold:string|null}
 * @phpstan-type AssetRow array{id:string,source:string,cost:string|null,classification:string|null,policy_source:string|null,reviewed:string|null,paid_on:string|null,recognition_start_on:string|null,life_months:int|string|null,residual:string|null,method:string|null,opening_depreciation:string|null}
 * @phpstan-type TaxPeriodRow array{start_on:string,end_on:string,accounting_profit:string|null,tax_adjustments:string|null}
 * @phpstan-type FundingRow array{id:string,source:string,identity:string|null,type:string|null,amount:string|null,commitment:string|null,available_from:string|null,drawn_on:string|null,restriction:string|null,verified:string|null}
 * @phpstan-type TermsData array{expected_revision:int|null,trade_items:list<TradeRow>,owner_pay_steps:list<OwnerPayRow>,owner_pay_source:string|null,owner_pay_first_payment_on:string|null,assets:list<AssetRow>,tax_entity_basis:string|null,tax_rate_percent:string|null,tax_losses_eligible:string|null,tax_policy_source:string|null,tax_eligibility_source:string|null,tax_version:string|null,tax_periods:list<TaxPeriodRow>,opening_eligible_losses:string|null,funding_sources:list<FundingRow>,funding_inventory_complete:string|null}
 */
final readonly class AdvisorForecastTerms
{
    /**
     * Settlement categories each kind of trade item can carry.
     *
     * A receivable settles with cash coming in and a payable with cash going
     * out, so the direction is never asked for: it follows from the kind. That
     * leaves the category, and only the categories the engine accepts in that
     * direction are offered. An internal restriction transfer is excluded
     * outright, because it moves cash between the business's own accounts and
     * settles nothing owed to or by anybody.
     *
     * @var array<string,list<string>>
     */
    public const CATEGORIES = [
        'receivable' => ['operating_receipt', 'funding', 'tax', 'gst', 'capex'],
        'payable' => ['operating_payment', 'owner_pay', 'debt_principal', 'distribution', 'interest', 'fees', 'tax', 'gst', 'capex'],
    ];

    /**
     * The one depreciation method the engine implements. Offering a free-text
     * method would invite an advisor to name one the engine then refuses.
     */
    public const METHOD = 'straight_line_full_start_month';

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        $number = ['present', 'nullable', 'string', 'max:30'];
        $date = ['present', 'nullable', 'date_format:Y-m-d'];
        // Three states, written out rather than posted as a boolean. An empty
        // select and a "no" are different answers, and a boolean cannot hold
        // the difference once an empty field has crossed the wire.
        $stated = ['present', 'nullable', 'in:yes,no'];

        return [
            'expected_revision' => ['present', 'nullable', 'integer', 'min:0'],
            'trade_items' => ['present', 'array', 'max:50'],
            'trade_items.*.id' => ['required', 'string', 'max:60'],
            'trade_items.*.source' => ['required', 'string', 'max:255'],
            'trade_items.*.kind' => ['required', 'in:receivable,payable'],
            'trade_items.*.raised_on' => $date,
            'trade_items.*.amount' => $number,
            'trade_items.*.settlements' => ['present', 'array', 'max:24'],
            'trade_items.*.settlements.*.id' => ['required', 'string', 'max:60'],
            'trade_items.*.settlements.*.source' => ['required', 'string', 'max:255'],
            'trade_items.*.settlements.*.date' => $date,
            'trade_items.*.settlements.*.amount' => $number,
            // Left unanswered rather than required, so that an unchosen
            // category is reported in words naming the item it belongs to.
            'trade_items.*.settlements.*.category' => ['present', 'nullable', 'string', 'max:40'],
            'trade_items.*.settlements.*.verified' => $stated,
            'owner_pay_steps' => ['present', 'array', 'max:24'],
            'owner_pay_steps.*.effective_on' => ['required', 'date_format:Y-m-d'],
            'owner_pay_steps.*.weekly_amount' => $number,
            'owner_pay_steps.*.trigger' => ['required', 'in:base,trailing_three_month_average_revenue'],
            'owner_pay_steps.*.threshold' => $number,
            // The steps say what is paid and from when; the schedule also
            // needs the day the first payment lands and the record the whole
            // arrangement comes from, which every payment carries into the
            // ledger as its provenance.
            'owner_pay_source' => ['present', 'nullable', 'string', 'max:255'],
            'owner_pay_first_payment_on' => $date,
            'assets' => ['present', 'array', 'max:50'],
            'assets.*.id' => ['required', 'string', 'max:60'],
            'assets.*.source' => ['required', 'string', 'max:255'],
            'assets.*.cost' => $number,
            'assets.*.classification' => ['present', 'nullable', 'in:capital,expense'],
            'assets.*.policy_source' => ['present', 'nullable', 'string', 'max:255'],
            'assets.*.reviewed' => $stated,
            'assets.*.paid_on' => $date,
            'assets.*.recognition_start_on' => $date,
            'assets.*.life_months' => ['present', 'nullable', 'integer', 'min:1', 'max:1200'],
            'assets.*.residual' => $number,
            'assets.*.method' => ['present', 'nullable', 'in:'.self::METHOD],
            'assets.*.opening_depreciation' => $number,
            'tax_entity_basis' => ['present', 'nullable', 'string', 'max:120'],
            'tax_rate_percent' => $number,
            'tax_losses_eligible' => $stated,
            'tax_policy_source' => ['present', 'nullable', 'string', 'max:255'],
            'tax_eligibility_source' => ['present', 'nullable', 'string', 'max:255'],
            'tax_version' => ['present', 'nullable', 'string', 'max:60'],
            // Losses brought into the first planning period, recorded beside
            // the policy rather than inside it: a policy with losses eligible
            // and nothing carried in is complete, not incomplete.
            'opening_eligible_losses' => $number,
            'tax_periods' => ['present', 'array', 'max:24'],
            'tax_periods.*.start_on' => ['required', 'date_format:Y-m-d'],
            'tax_periods.*.end_on' => ['required', 'date_format:Y-m-d'],
            // Signed, unlike every other figure on this form: a period that
            // made a loss is written with a leading minus.
            'tax_periods.*.accounting_profit' => ['present', 'nullable', 'string', 'max:31'],
            'tax_periods.*.tax_adjustments' => ['present', 'nullable', 'string', 'max:31'],
            'funding_sources' => ['present', 'array', 'max:50'],
            'funding_sources.*.id' => ['required', 'string', 'max:60'],
            'funding_sources.*.source' => ['required', 'string', 'max:255'],
            'funding_sources.*.identity' => ['present', 'nullable', 'string', 'max:255'],
            'funding_sources.*.type' => ['present', 'nullable', 'string', 'max:120'],
            'funding_sources.*.amount' => $number,
            'funding_sources.*.commitment' => ['present', 'nullable', 'in:'.implode(',', FundingSource::COMMITMENTS)],
            'funding_sources.*.available_from' => $date,
            'funding_sources.*.drawn_on' => $date,
            'funding_sources.*.restriction' => ['present', 'nullable', 'in:'.implode(',', FundingSource::RESTRICTIONS)],
            'funding_sources.*.verified' => $stated,
            // Whether every source is recorded, which no count can answer: an
            // empty list is equally a business with no funding and a section
            // nobody has filled in.
            'funding_inventory_complete' => $stated,
        ];
    }

    /**
     * Rebuilds the reconstruction with these four sections replaced and every
     * other section carried through exactly as it was stored.
     *
     * @param  TermsData  $data
     */
    public function apply(ForecastModelInputs $existing, array $data): ForecastModelInputs
    {
        $this->guard($data, $existing);

        return new ForecastModelInputs(
            calendar: $existing->calendar,
            streams: $existing->streams,
            founderMonths: $existing->founderMonths,
            cashOpening: $existing->cashOpening,
            bankOpening: $existing->bankOpening,
            tradeItems: $this->tradeItems($data['trade_items']),
            ownerPaySteps: array_map($this->ownerPayStep(...), $data['owner_pay_steps']),
            assets: array_map($this->asset(...), $data['assets']),
            taxPolicy: $this->taxPolicy($data),
            taxPeriods: array_map($this->taxPeriod(...), $data['tax_periods']),
            actualMonths: $existing->actualMonths,
            reservePolicy: $existing->reservePolicy,
            ownerPaySource: $this->blank($data['owner_pay_source']),
            ownerPayFirstPaymentOn: $this->blank($data['owner_pay_first_payment_on']),
            openingEligibleLosses: $this->blank($data['opening_eligible_losses']),
            fundingSources: array_map($this->fundingSource(...), $data['funding_sources']),
            fundingInventoryComplete: $this->stated($data['funding_inventory_complete']),
        );
    }

    /** @param  FundingRow  $row */
    private function fundingSource(array $row): FundingSource
    {
        return new FundingSource(
            trim($row['id']), trim($row['source']), $this->blank($row['identity']), $this->blank($row['type']),
            $this->blank($row['amount']), $this->blank($row['commitment']), $this->blank($row['available_from']),
            $this->blank($row['drawn_on']), $this->blank($row['restriction']), $this->stated($row['verified']),
        );
    }

    /**
     * @param  list<TradeRow>  $rows
     * @return list<TradeItem>
     */
    private function tradeItems(array $rows): array
    {
        // One counter across every settlement on the form, not one per item.
        // The ledger positions a movement by its date and sequence together
        // and rejects two movements that land on the same position, so two
        // settlements of different items paid on one day must not both be
        // numbered from zero. Numbering follows the order on screen, which is
        // therefore the order the movements take within a day.
        $sequence = 0;
        $items = [];
        foreach ($rows as $row) {
            $direction = $row['kind'] === 'receivable' ? 'in' : 'out';
            $settlements = [];
            foreach ($row['settlements'] as $entry) {
                $settlements[] = new CashMovement(
                    trim($entry['id']), trim($entry['source']), $this->blank($entry['date']), $this->blank($entry['amount']),
                    // The guard above has already refused an unchosen or
                    // unsuitable category, so one survives to here.
                    $direction, (string) $entry['category'], $sequence, $this->stated($entry['verified']),
                );
                $sequence++;
            }
            $items[] = new TradeItem(
                trim($row['id']), trim($row['source']), $row['kind'], $this->blank($row['raised_on']), $this->blank($row['amount']),
                // No settlement rows means nobody has said how this item
                // settles, which is not the same as saying it settles with
                // nothing. An empty list would be that second claim, so an
                // item with no rows keeps its terms absent and is reported as
                // missing rather than as a fully unsettled balance.
                $settlements === [] ? null : $settlements,
            );
        }

        return $items;
    }

    /** @param  OwnerPayRow  $row */
    private function ownerPayStep(array $row): OwnerPayStep
    {
        return new OwnerPayStep(
            $row['effective_on'],
            $this->blank($row['weekly_amount']),
            $row['trigger'],
            // A threshold belongs to the revenue trigger and means nothing
            // beside a base step; the engine refuses the pair outright. The
            // form only asks for one against the revenue trigger, so a value
            // left behind by a changed trigger is dropped rather than turned
            // into an error about something the advisor never stated.
            $row['trigger'] === 'base' ? null : $this->blank($row['threshold']),
        );
    }

    /** @param  AssetRow  $row */
    private function asset(array $row): AssetInput
    {
        return new AssetInput(
            trim($row['id']), trim($row['source']), $this->blank($row['cost']), $this->blank($row['classification']),
            $this->blank($row['policy_source']), $this->stated($row['reviewed']), $this->blank($row['paid_on']),
            // A number typed into a text box arrives as a numeric string, and
            // the asset declares an integer. Validation has already confined
            // it to a whole number in range, so the cast is the last step.
            $this->blank($row['recognition_start_on']), $row['life_months'] === null ? null : (int) $row['life_months'],
            $this->blank($row['residual']),
            $this->blank($row['method']), $this->blank($row['opening_depreciation']),
        );
    }

    /**
     * A policy nobody has stated anything about stays absent rather than
     * becoming an empty policy. Saving the trade terms beside it would
     * otherwise report tax as captured when nothing about it has been said.
     *
     * @param  TermsData  $data
     */
    private function taxPolicy(array $data): ?TaxPolicy
    {
        $policy = new TaxPolicy(
            $this->blank($data['tax_entity_basis']), $this->blank($data['tax_rate_percent']),
            $this->stated($data['tax_losses_eligible']), $this->blank($data['tax_policy_source']),
            $this->blank($data['tax_eligibility_source']), $this->blank($data['tax_version']),
        );

        return array_filter($policy->jsonSerialize(), fn (string|bool|null $value): bool => $value !== null) === []
            ? null
            : $policy;
    }

    /** @param  TaxPeriodRow  $row */
    private function taxPeriod(array $row): TaxPeriod
    {
        return new TaxPeriod($row['start_on'], $row['end_on'], $this->blank($row['accounting_profit']), $this->blank($row['tax_adjustments']));
    }

    /** @param  TermsData  $data */
    private function guard(array $data, ForecastModelInputs $existing): void
    {
        $this->rejectRepeats(array_column($data['funding_sources'], 'id'), 'funding_sources',
            'Each funding source needs its own reference. Two share one, and the ledger refuses to count a draw twice.');
        // Two sources under one name are one source recorded twice as far as
        // a reconciliation can tell, and the total would count it twice.
        $this->rejectRepeats(array_values(array_filter(array_map(
            fn (array $row): string => (string) $this->blank($row['identity']), $data['funding_sources']))),
            'funding_sources', 'Two funding sources are named the same. A reconciliation cannot tell one from the other.');
        $this->rejectDrawsOutsideCalendar($data['funding_sources'], $existing);
        $this->rejectRepeats(array_column($data['trade_items'], 'id'), 'trade_items',
            'Each trade item needs its own reference. Two share one, and the engine refuses to count a trade twice.');
        $this->rejectRepeats(array_column($data['assets'], 'id'), 'assets',
            'Each asset needs its own reference. Two share one, and findings are reported against it.');
        $settlements = [];
        foreach ($data['trade_items'] as $item) {
            foreach ($item['settlements'] as $entry) {
                $settlements[] = $entry['id'];
                $this->rejectUnsuitableCategory($item['kind'], trim($item['id']), $entry['category']);
            }
        }
        // Across every item, not within one. A settlement allocated to two
        // trade items would count the same cash against both of them.
        $this->rejectRepeats($settlements, 'trade_items',
            'A settlement reference appears twice. One receipt or payment can settle only one trade item.');
        $this->rejectTaxGaps($data['tax_periods']);
    }

    /**
     * A settlement whose category the engine will not accept in the direction
     * its trade item implies is refused here, where the item and the category
     * can both be named, rather than by a constructor that can only say the
     * pair did not match.
     */
    private function rejectUnsuitableCategory(string $kind, string $item, ?string $category): void
    {
        $named = $item === '' ? 'this trade item' : $item;
        if ($category === null || trim($category) === '') {
            throw ValidationException::withMessages(['trade_items' => 'Say how the settlement of '.$named.' moves cash before recording it.']);
        }
        if (! in_array($category, self::CATEGORIES[$kind] ?? [], true)) {
            throw ValidationException::withMessages(['trade_items' => 'A '.$kind.' cannot settle as '.str_replace('_', ' ', $category)
                .'. Cash moves '.($kind === 'receivable' ? 'in' : 'out').' on a '.$kind.', and that category moves it the other way.']);
        }
    }

    /**
     * A draw outside the forecast calendar overstates available funds, in one
     * of two different ways.
     *
     * Money drawn before the forecast opens is already inside the opening
     * balance, so adding it to opening cash counts it twice. Money drawn
     * after the forecast ends never arrives inside the horizon, so it is not
     * funding this forecast can spend. Both are refused here, where the
     * source and the date can be named, rather than left to surface as a
     * reconciliation failure nobody can act on.
     *
     * @param  list<FundingRow>  $rows
     */
    private function rejectDrawsOutsideCalendar(array $rows, ForecastModelInputs $existing): void
    {
        $opens = $existing->calendar->startMonth.'-01';
        $closes = ForecastDates::end($existing->calendar);
        foreach ($rows as $row) {
            $drawn = $this->blank($row['drawn_on']);
            if ($drawn === null || ($drawn >= $opens && $drawn <= $closes)) {
                continue;
            }
            $named = trim($row['id']) === '' ? 'this funding source' : trim($row['id']);
            throw ValidationException::withMessages(['funding_sources' => $drawn < $opens
                ? $named.' is drawn on '.$drawn.', before the forecast opens on '.$opens
                    .'. Money already received belongs in the opening balance, or it is counted twice.'
                : $named.' is drawn on '.$drawn.', after the forecast ends on '.$closes
                    .'. Funding that arrives later is not available to this forecast.']);
        }
    }

    /** @param  list<string>  $references */
    private function rejectRepeats(array $references, string $field, string $message): void
    {
        $trimmed = array_map(trim(...), $references);
        if (count($trimmed) !== count(array_unique($trimmed))) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * Planning tax periods carry losses from one into the next, so the engine
     * requires them to meet end to end. It throws when they do not, which
     * would leave a stored reconstruction that fails the moment it is
     * projected; the gap is named here instead.
     *
     * @param  list<TaxPeriodRow>  $rows
     */
    private function rejectTaxGaps(array $rows): void
    {
        usort($rows, fn (array $a, array $b): int => strcmp($a['start_on'], $b['start_on']));
        $previousEnd = null;
        foreach ($rows as $row) {
            if ($row['start_on'] > $row['end_on']) {
                // The period reports this against itself, more precisely than
                // a comparison with its neighbour could.
                return;
            }
            if ($previousEnd !== null && $row['start_on'] !== ForecastDates::shift($previousEnd, 1)) {
                throw ValidationException::withMessages(['tax_periods' => 'Planning tax periods must meet end to end, with no gap and no overlap. '
                    .$row['start_on'].' does not start the day after '.$previousEnd.'.']);
            }
            $previousEnd = $row['end_on'];
        }
    }

    private function blank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    /** Reads the three states a yes/no question can be left in. */
    private function stated(?string $value): ?bool
    {
        return match ($value) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }
}
