import type {
    AssetInputRow,
    TaxPeriodInput,
    TermsForm,
} from './forecast-model-types';

const field = 'mt-1 block w-full rounded border p-2';

/** The one depreciation method the engine implements. */
const METHOD = 'straight_line_full_start_month';

const ASSET_TEXT: { key: keyof AssetInputRow; label: string }[] = [
    { key: 'source', label: 'Where this came from' },
    { key: 'cost', label: 'Cost (NZD)' },
    { key: 'policy_source', label: 'Policy it was classified under' },
    { key: 'life_months', label: 'Useful life (whole months)' },
    { key: 'residual', label: 'Residual value (NZD)' },
    { key: 'opening_depreciation', label: 'Depreciation already taken (NZD)' },
];

const ASSET_DATES: { key: keyof AssetInputRow; label: string }[] = [
    { key: 'paid_on', label: 'Paid on' },
    { key: 'recognition_start_on', label: 'Depreciation starts' },
];

export function emptyAsset(): AssetInputRow {
    return {
        id: '',
        source: '',
        cost: '',
        classification: '',
        policy_source: '',
        reviewed: '',
        paid_on: '',
        recognition_start_on: '',
        life_months: '',
        residual: '',
        method: '',
        opening_depreciation: '',
    };
}

export function emptyTaxPeriod(): TaxPeriodInput {
    return {
        start_on: '',
        end_on: '',
        accounting_profit: '',
        tax_adjustments: '',
    };
}

export function AssetFields({
    assets,
    onChange,
}: {
    assets: AssetInputRow[];
    onChange: (assets: AssetInputRow[]) => void;
}) {
    const setAsset = (index: number, patch: Partial<AssetInputRow>) =>
        onChange(
            assets.map((asset, i) =>
                i === index ? { ...asset, ...patch } : asset,
            ),
        );

    return (
        <fieldset className="rounded border p-4">
            <legend className="font-semibold">Assets and depreciation</legend>
            <p className="my-2 text-sm">
                Anything costing 1,000 NZD or more needs its classification
                reviewed before the engine will depreciate it. Leave the
                capital-or-expense question unanswered while it genuinely is: an
                unclassified item is reported as unclassified, not quietly
                expensed.
            </p>
            {assets.map((asset, index) => (
                <div className="mt-3 rounded border p-3" key={index}>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <label>
                            Reference
                            <input
                                required
                                className={field}
                                value={asset.id}
                                onChange={(e) =>
                                    setAsset(index, { id: e.target.value })
                                }
                                placeholder="laptop-2026"
                            />
                        </label>
                        {ASSET_TEXT.map((entry) => (
                            <label key={entry.key}>
                                {entry.label}
                                <input
                                    required={entry.key === 'source'}
                                    className={field}
                                    value={asset[entry.key]}
                                    onChange={(e) =>
                                        setAsset(index, {
                                            [entry.key]: e.target.value,
                                        })
                                    }
                                />
                            </label>
                        ))}
                        {ASSET_DATES.map((entry) => (
                            <label key={entry.key}>
                                {entry.label}
                                <input
                                    type="date"
                                    className={field}
                                    value={asset[entry.key]}
                                    onChange={(e) =>
                                        setAsset(index, {
                                            [entry.key]: e.target.value,
                                        })
                                    }
                                />
                            </label>
                        ))}
                        <label>
                            Capital or expense
                            <select
                                className={field}
                                value={asset.classification}
                                onChange={(e) =>
                                    setAsset(index, {
                                        classification: e.target.value,
                                    })
                                }
                            >
                                <option value="">Not yet classified</option>
                                <option value="capital">
                                    Capital, depreciated over its life
                                </option>
                                <option value="expense">
                                    Expensed when paid
                                </option>
                            </select>
                        </label>
                        <label>
                            Classification reviewed
                            <select
                                className={field}
                                value={asset.reviewed}
                                onChange={(e) =>
                                    setAsset(index, {
                                        reviewed: e.target.value,
                                    })
                                }
                            >
                                <option value="">Not stated</option>
                                <option value="yes">
                                    Reviewed against the policy
                                </option>
                                <option value="no">Not reviewed</option>
                            </select>
                        </label>
                        <label>
                            Depreciation method
                            <select
                                className={field}
                                value={asset.method}
                                onChange={(e) =>
                                    setAsset(index, { method: e.target.value })
                                }
                            >
                                <option value="">Not stated</option>
                                <option value={METHOD}>
                                    Straight line, full month from the start
                                </option>
                            </select>
                        </label>
                    </div>
                    <button
                        type="button"
                        className="mt-3 rounded border p-2"
                        onClick={() =>
                            onChange(assets.filter((_, i) => i !== index))
                        }
                    >
                        Remove {asset.id || 'this asset'}
                    </button>
                </div>
            ))}
            <button
                type="button"
                className="mt-3 rounded border p-2"
                onClick={() => onChange([...assets, emptyAsset()])}
            >
                Add an asset
            </button>
        </fieldset>
    );
}

type PolicyKey =
    | 'tax_entity_basis'
    | 'tax_rate_percent'
    | 'tax_policy_source'
    | 'tax_eligibility_source'
    | 'tax_version';

const POLICY_FIELDS: { key: PolicyKey; label: string }[] = [
    { key: 'tax_entity_basis', label: 'Entity basis' },
    { key: 'tax_rate_percent', label: 'Planning tax rate (percent)' },
    { key: 'tax_policy_source', label: 'Where the rate came from' },
    {
        key: 'tax_eligibility_source',
        label: 'Where loss eligibility came from',
    },
    { key: 'tax_version', label: 'Policy version' },
];

const PERIOD_FIELDS: { key: keyof TaxPeriodInput; label: string }[] = [
    { key: 'accounting_profit', label: 'Accounting profit (NZD)' },
    { key: 'tax_adjustments', label: 'Tax adjustments (NZD)' },
];

export function TaxFields({
    data,
    onChange,
}: {
    data: TermsForm;
    onChange: (patch: Partial<TermsForm>) => void;
}) {
    const periods = data.tax_periods;
    const setPeriod = (index: number, patch: Partial<TaxPeriodInput>) =>
        onChange({
            tax_periods: periods.map((period, i) =>
                i === index ? { ...period, ...patch } : period,
            ),
        });

    return (
        <fieldset className="rounded border p-4">
            <legend className="font-semibold">Tax policy and timing</legend>
            <p className="my-2 text-sm">
                Losses carry from one period into the next, so the periods have
                to meet end to end with no gap and no overlap. A period that
                made a loss is written with a leading minus, for example -4500.
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
                {POLICY_FIELDS.map((entry) => (
                    <label key={entry.key}>
                        {entry.label}
                        <input
                            className={field}
                            value={data[entry.key]}
                            onChange={(e) =>
                                onChange({ [entry.key]: e.target.value })
                            }
                        />
                    </label>
                ))}
                <label>
                    Losses available to carry forward
                    <select
                        className={field}
                        value={data.tax_losses_eligible}
                        onChange={(e) =>
                            onChange({ tax_losses_eligible: e.target.value })
                        }
                    >
                        <option value="">Not stated</option>
                        <option value="yes">Yes, losses carry forward</option>
                        <option value="no">No, they do not</option>
                    </select>
                </label>
            </div>
            {periods.map((period, index) => (
                <div
                    className="mt-3 grid gap-3 rounded border p-3 sm:grid-cols-4"
                    key={index}
                >
                    <label>
                        Period starts
                        <input
                            required
                            type="date"
                            className={field}
                            value={period.start_on}
                            onChange={(e) =>
                                setPeriod(index, { start_on: e.target.value })
                            }
                        />
                    </label>
                    <label>
                        Period ends
                        <input
                            required
                            type="date"
                            className={field}
                            value={period.end_on}
                            onChange={(e) =>
                                setPeriod(index, { end_on: e.target.value })
                            }
                        />
                    </label>
                    {PERIOD_FIELDS.map((entry) => (
                        <label key={entry.key}>
                            {entry.label}
                            <input
                                className={field}
                                value={period[entry.key]}
                                onChange={(e) =>
                                    setPeriod(index, {
                                        [entry.key]: e.target.value,
                                    })
                                }
                            />
                        </label>
                    ))}
                    <button
                        type="button"
                        className="rounded border p-2 sm:col-span-4"
                        onClick={() =>
                            onChange({
                                tax_periods: periods.filter(
                                    (_, i) => i !== index,
                                ),
                            })
                        }
                    >
                        Remove {period.start_on || 'this period'}
                    </button>
                </div>
            ))}
            <button
                type="button"
                className="mt-3 rounded border p-2"
                onClick={() =>
                    onChange({ tax_periods: [...periods, emptyTaxPeriod()] })
                }
            >
                Add a tax period
            </button>
        </fieldset>
    );
}
