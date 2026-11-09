import { useForm } from '@inertiajs/react';
import { AssetFields, TaxFields } from './forecast-model-assets';
import { FundingFields } from './forecast-model-funding';
import type {
    AssetInputRow,
    ForecastModel,
    FundingInput,
    OwnerPayInput,
    SettlementInput,
    TaxPeriodInput,
    TermsForm,
    TradeItemInput,
} from './forecast-model-types';

const field = 'mt-1 block w-full rounded border p-2';

function text(value: string | null): string {
    return value ?? '';
}

function number(value: number | null): string {
    return value === null ? '' : String(value);
}

/** A question nobody has answered posts an empty string, which the server
 * stores as an absent answer rather than as an answer of no. */
function stated(value: boolean | null): string {
    if (value === null) {
        return '';
    }

    return value ? 'yes' : 'no';
}

/**
 * Settlement categories each kind of trade item can carry, mirroring the
 * server's own list. A receivable brings cash in and a payable sends it out,
 * so the direction is never asked for and only the categories that move cash
 * the right way are offered.
 */
const CATEGORIES: Record<string, { value: string; label: string }[]> = {
    receivable: [
        { value: 'operating_receipt', label: 'Customer receipt' },
        { value: 'funding', label: 'Funding drawn down' },
        { value: 'tax', label: 'Tax refund' },
        { value: 'gst', label: 'GST refund' },
        { value: 'capex', label: 'Proceeds from selling an asset' },
    ],
    payable: [
        { value: 'operating_payment', label: 'Supplier or operating payment' },
        { value: 'owner_pay', label: 'Owner pay' },
        { value: 'debt_principal', label: 'Debt principal repaid' },
        { value: 'distribution', label: 'Distribution to owners' },
        { value: 'interest', label: 'Interest' },
        { value: 'fees', label: 'Fees' },
        { value: 'tax', label: 'Tax paid' },
        { value: 'gst', label: 'GST paid' },
        { value: 'capex', label: 'Capital purchase' },
    ],
};

const SETTLEMENT_FIELDS: { key: keyof SettlementInput; label: string }[] = [
    { key: 'source', label: 'Where this came from' },
    { key: 'amount', label: 'Amount (NZD)' },
];

function emptySettlement(): SettlementInput {
    return {
        id: '',
        source: '',
        date: '',
        amount: '',
        category: '',
        verified: '',
    };
}

function initial(model: ForecastModel | null): TermsForm {
    return {
        expected_revision: model?.revision ?? null,
        funding_sources: (model?.funding_sources ?? []).map(
            (source): FundingInput => ({
                id: source.id,
                source: source.source,
                identity: text(source.identity),
                type: text(source.type),
                amount: text(source.amount),
                commitment: text(source.commitment),
                available_from: text(source.available_from),
                drawn_on: text(source.drawn_on),
                restriction: text(source.restriction),
                verified: stated(source.verified),
            }),
        ),
        funding_inventory_complete: stated(
            model?.funding_inventory_complete ?? null,
        ),
        trade_items: (model?.trade_items ?? []).map(
            (item): TradeItemInput => ({
                id: item.id,
                source: item.source,
                kind: item.kind,
                raised_on: text(item.raised_on),
                amount: text(item.amount),
                settlements: (item.settlements ?? []).map(
                    (settlement): SettlementInput => ({
                        id: settlement.id,
                        source: settlement.source,
                        date: text(settlement.date),
                        amount: text(settlement.amount),
                        category: settlement.category,
                        verified: stated(settlement.verified),
                    }),
                ),
            }),
        ),
        owner_pay_steps: (model?.owner_pay_steps ?? []).map(
            (step): OwnerPayInput => ({
                effective_on: step.effective_on,
                weekly_amount: text(step.weekly_amount),
                trigger: step.trigger,
                threshold: text(step.threshold),
            }),
        ),
        assets: (model?.assets ?? []).map(
            (asset): AssetInputRow => ({
                id: asset.id,
                source: asset.source,
                cost: text(asset.cost),
                classification: text(asset.classification),
                policy_source: text(asset.policy_source),
                reviewed: stated(asset.reviewed),
                paid_on: text(asset.paid_on),
                recognition_start_on: text(asset.recognition_start_on),
                life_months: number(asset.life_months),
                residual: text(asset.residual),
                method: text(asset.method),
                opening_depreciation: text(asset.opening_depreciation),
            }),
        ),
        owner_pay_source: text(model?.owner_pay_source ?? null),
        owner_pay_first_payment_on: text(
            model?.owner_pay_first_payment_on ?? null,
        ),
        opening_eligible_losses: text(model?.opening_eligible_losses ?? null),
        tax_entity_basis: text(model?.tax.entity_basis ?? null),
        tax_rate_percent: text(model?.tax.rate_percent ?? null),
        tax_losses_eligible: stated(model?.tax.losses_eligible ?? null),
        tax_policy_source: text(model?.tax.policy_source ?? null),
        tax_eligibility_source: text(model?.tax.eligibility_source ?? null),
        tax_version: text(model?.tax.version ?? null),
        tax_periods: (model?.tax.periods ?? []).map(
            (period): TaxPeriodInput => ({
                start_on: period.start_on,
                end_on: period.end_on,
                accounting_profit: text(period.accounting_profit),
                tax_adjustments: text(period.tax_adjustments),
            }),
        ),
    };
}

export function TermsFormSection({
    model,
    url,
}: {
    model: ForecastModel | null;
    url: string;
}) {
    const form = useForm<TermsForm>(initial(model));
    const items = form.data.trade_items;
    const steps = form.data.owner_pay_steps;

    const setItem = (index: number, patch: Partial<TradeItemInput>) =>
        form.setData(
            'trade_items',
            items.map((item, i) =>
                i === index ? { ...item, ...patch } : item,
            ),
        );
    const setSettlement = (
        index: number,
        row: number,
        patch: Partial<SettlementInput>,
    ) =>
        setItem(index, {
            settlements: items[index].settlements.map((settlement, i) =>
                i === row ? { ...settlement, ...patch } : settlement,
            ),
        });
    // Changing the kind reverses the direction the cash moves, which leaves
    // any category already chosen pointing the wrong way. Those are cleared
    // rather than carried over, so the advisor chooses again instead of the
    // engine refusing a pairing nobody meant to make.
    const setKind = (index: number, kind: string) =>
        setItem(index, {
            kind,
            settlements: items[index].settlements.map((settlement) => ({
                ...settlement,
                category: CATEGORIES[kind]?.some(
                    (option) => option.value === settlement.category,
                )
                    ? settlement.category
                    : '',
            })),
        });
    const setStep = (row: number, patch: Partial<OwnerPayInput>) =>
        form.setData(
            'owner_pay_steps',
            steps.map((step, i) => (i === row ? { ...step, ...patch } : step)),
        );

    return (
        <form
            className="space-y-6"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(url, { preserveScroll: true });
            }}
        >
            <FundingFields
                sources={form.data.funding_sources}
                complete={form.data.funding_inventory_complete}
                onChange={(sources) => form.setData('funding_sources', sources)}
                onComplete={(value) =>
                    form.setData('funding_inventory_complete', value)
                }
            />
            <fieldset className="rounded border p-4">
                <legend className="font-semibold">Trade terms</legend>
                <p className="my-2 text-sm">
                    A settlement moves cash in on a receivable and out on a
                    payable, so the direction follows the item and is never
                    asked for. Settlements are paid in the order they are listed
                    here. An item with no settlements recorded is reported as
                    having no terms yet, not as one that settles with nothing.
                </p>
                {items.map((item, index) => (
                    <div className="mt-3 rounded border p-3" key={index}>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <label>
                                Reference
                                <input
                                    required
                                    className={field}
                                    value={item.id}
                                    onChange={(e) =>
                                        setItem(index, { id: e.target.value })
                                    }
                                    placeholder="invoice-104"
                                />
                            </label>
                            <label>
                                Where this came from
                                <input
                                    required
                                    className={field}
                                    value={item.source}
                                    onChange={(e) =>
                                        setItem(index, {
                                            source: e.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label>
                                Owed to or by the business
                                <select
                                    className={field}
                                    value={item.kind}
                                    onChange={(e) =>
                                        setKind(index, e.target.value)
                                    }
                                >
                                    <option value="receivable">
                                        Owed to the business
                                    </option>
                                    <option value="payable">
                                        Owed by the business
                                    </option>
                                </select>
                            </label>
                            <label>
                                Raised on
                                <input
                                    type="date"
                                    className={field}
                                    value={item.raised_on}
                                    onChange={(e) =>
                                        setItem(index, {
                                            raised_on: e.target.value,
                                        })
                                    }
                                />
                            </label>
                            <label>
                                Amount (NZD)
                                <input
                                    className={field}
                                    value={item.amount}
                                    onChange={(e) =>
                                        setItem(index, {
                                            amount: e.target.value,
                                        })
                                    }
                                />
                            </label>
                        </div>
                        {item.settlements.map((settlement, row) => (
                            <div
                                className="mt-3 grid gap-3 rounded border p-3 sm:grid-cols-3"
                                key={row}
                            >
                                <label>
                                    Settlement reference
                                    <input
                                        required
                                        className={field}
                                        value={settlement.id}
                                        onChange={(e) =>
                                            setSettlement(index, row, {
                                                id: e.target.value,
                                            })
                                        }
                                    />
                                </label>
                                {SETTLEMENT_FIELDS.map((entry) => (
                                    <label key={entry.key}>
                                        {entry.label}
                                        <input
                                            required={entry.key === 'source'}
                                            className={field}
                                            value={settlement[entry.key]}
                                            onChange={(e) =>
                                                setSettlement(index, row, {
                                                    [entry.key]: e.target.value,
                                                })
                                            }
                                        />
                                    </label>
                                ))}
                                <label>
                                    Settles on
                                    <input
                                        type="date"
                                        className={field}
                                        value={settlement.date}
                                        onChange={(e) =>
                                            setSettlement(index, row, {
                                                date: e.target.value,
                                            })
                                        }
                                    />
                                </label>
                                <label>
                                    How the cash moves
                                    <select
                                        className={field}
                                        value={settlement.category}
                                        onChange={(e) =>
                                            setSettlement(index, row, {
                                                category: e.target.value,
                                            })
                                        }
                                    >
                                        <option value="">Not chosen</option>
                                        {(CATEGORIES[item.kind] ?? []).map(
                                            (option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </label>
                                <label>
                                    Verified against a document
                                    <select
                                        className={field}
                                        value={settlement.verified}
                                        onChange={(e) =>
                                            setSettlement(index, row, {
                                                verified: e.target.value,
                                            })
                                        }
                                    >
                                        <option value="">Not stated</option>
                                        <option value="yes">Verified</option>
                                        <option value="no">Not verified</option>
                                    </select>
                                </label>
                                <button
                                    type="button"
                                    className="rounded border p-2 sm:col-span-3"
                                    onClick={() =>
                                        setItem(index, {
                                            settlements:
                                                item.settlements.filter(
                                                    (_, i) => i !== row,
                                                ),
                                        })
                                    }
                                >
                                    Remove {settlement.id || 'this settlement'}
                                </button>
                            </div>
                        ))}
                        <div className="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                className="rounded border p-2"
                                onClick={() =>
                                    setItem(index, {
                                        settlements: [
                                            ...item.settlements,
                                            emptySettlement(),
                                        ],
                                    })
                                }
                            >
                                Add a settlement to {item.id || 'this item'}
                            </button>
                            <button
                                type="button"
                                className="rounded border p-2"
                                onClick={() =>
                                    form.setData(
                                        'trade_items',
                                        items.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                Remove {item.id || 'this item'}
                            </button>
                        </div>
                    </div>
                ))}
                <button
                    type="button"
                    className="mt-3 rounded border p-2"
                    onClick={() =>
                        form.setData('trade_items', [
                            ...items,
                            {
                                id: '',
                                source: '',
                                kind: 'receivable',
                                raised_on: '',
                                amount: '',
                                settlements: [],
                            },
                        ])
                    }
                >
                    Add a trade item
                </button>
            </fieldset>
            <fieldset className="rounded border p-4">
                <legend className="font-semibold">Owner pay schedule</legend>
                <p className="my-2 text-sm">
                    A step either starts on its date or waits for trailing
                    three-month average revenue to reach a threshold. A
                    threshold belongs only to the revenue trigger; a step that
                    starts on its date carries none.
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                    <label>
                        First payment lands on
                        <input
                            type="date"
                            className={field}
                            value={form.data.owner_pay_first_payment_on}
                            onChange={(e) =>
                                form.setData(
                                    'owner_pay_first_payment_on',
                                    e.target.value,
                                )
                            }
                        />
                    </label>
                    <label>
                        Where the arrangement is recorded
                        <input
                            className={field}
                            value={form.data.owner_pay_source}
                            onChange={(e) =>
                                form.setData('owner_pay_source', e.target.value)
                            }
                        />
                    </label>
                </div>
                <p className="my-2 text-sm">
                    Both are needed before any pay reaches the cash ledger.
                    Every payment the schedule generates carries that record as
                    its provenance, and without a first payment date there is
                    nothing to run the weekly series from.
                </p>
                {steps.map((step, row) => (
                    <div
                        className="mt-3 grid gap-3 rounded border p-3 sm:grid-cols-4"
                        key={row}
                    >
                        <label>
                            Effective on
                            <input
                                required
                                type="date"
                                className={field}
                                value={step.effective_on}
                                onChange={(e) =>
                                    setStep(row, {
                                        effective_on: e.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            Weekly amount (NZD)
                            <input
                                className={field}
                                value={step.weekly_amount}
                                onChange={(e) =>
                                    setStep(row, {
                                        weekly_amount: e.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            What starts this step
                            <select
                                className={field}
                                value={step.trigger}
                                onChange={(e) =>
                                    setStep(row, {
                                        trigger: e.target.value,
                                        threshold:
                                            e.target.value === 'base'
                                                ? ''
                                                : step.threshold,
                                    })
                                }
                            >
                                <option value="base">Its date alone</option>
                                <option value="trailing_three_month_average_revenue">
                                    Trailing three-month average revenue
                                </option>
                            </select>
                        </label>
                        {step.trigger === 'base' ? null : (
                            <label>
                                Revenue threshold (NZD)
                                <input
                                    className={field}
                                    value={step.threshold}
                                    onChange={(e) =>
                                        setStep(row, {
                                            threshold: e.target.value,
                                        })
                                    }
                                />
                            </label>
                        )}
                        <button
                            type="button"
                            className="rounded border p-2 sm:col-span-4"
                            onClick={() =>
                                form.setData(
                                    'owner_pay_steps',
                                    steps.filter((_, i) => i !== row),
                                )
                            }
                        >
                            Remove {step.effective_on || 'this step'}
                        </button>
                    </div>
                ))}
                <button
                    type="button"
                    className="mt-3 rounded border p-2"
                    onClick={() =>
                        form.setData('owner_pay_steps', [
                            ...steps,
                            {
                                effective_on: '',
                                weekly_amount: '',
                                trigger: 'base',
                                threshold: '',
                            },
                        ])
                    }
                >
                    Add an owner pay step
                </button>
            </fieldset>
            <AssetFields
                assets={form.data.assets}
                onChange={(assets) => form.setData('assets', assets)}
            />
            <TaxFields
                data={form.data}
                onChange={(patch) =>
                    form.setData((previous) => ({ ...previous, ...patch }))
                }
            />
            {Object.values(form.errors).map((error) => (
                <p role="alert" key={error}>
                    {error}
                </p>
            ))}
            <button disabled={form.processing} className="rounded border p-2">
                Save trade, pay, assets and tax
            </button>
        </form>
    );
}
