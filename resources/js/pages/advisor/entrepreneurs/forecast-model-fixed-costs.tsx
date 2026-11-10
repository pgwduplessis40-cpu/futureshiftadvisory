import type { FixedCostInput } from './forecast-model-types';

const field = 'mt-1 block w-full rounded border p-2';

const COST_TEXT: { key: keyof FixedCostInput; label: string }[] = [
    { key: 'source', label: 'Where this came from' },
    { key: 'rate', label: 'Rate per charge (NZD)' },
    { key: 'quantity', label: 'How many' },
];

const COST_DATES: { key: keyof FixedCostInput; label: string }[] = [
    { key: 'first_invoice_on', label: 'First invoiced on' },
    { key: 'last_invoice_on', label: 'Last invoiced on' },
];

export function emptyFixedCost(): FixedCostInput {
    return {
        id: '',
        source: '',
        rate: '',
        quantity: '',
        frequency: '',
        first_invoice_on: '',
        last_invoice_on: '',
        creditor_days: '',
        reviewed: '',
    };
}

export function FixedCostFields({
    costs,
    complete,
    onChange,
    onComplete,
}: {
    costs: FixedCostInput[];
    complete: string;
    onChange: (costs: FixedCostInput[]) => void;
    onComplete: (value: string) => void;
}) {
    const setCost = (index: number, patch: Partial<FixedCostInput>) =>
        onChange(
            costs.map((cost, i) =>
                i === index ? { ...cost, ...patch } : cost,
            ),
        );

    return (
        <fieldset className="rounded border p-4">
            <legend className="font-semibold">Fixed costs</legend>
            <p className="my-2 text-sm">
                Overheads the business carries whatever it sells, recorded as a
                rate charged on a cadence rather than as individual invoices.
                The expense and the cash are then both derived from what you
                state here, instead of being typed twice and left to disagree.
            </p>
            <p className="my-2 text-sm">
                A cost is recognised at its annualised rate, spread evenly: an
                annual premium invoiced once in March is an expense of a twelfth
                every month, not a spike in March. The difference between that
                and the cash paid in a month is the accrual or prepayment the
                cost carries, which is a real position rather than an error.
            </p>
            {costs.map((cost, index) => (
                <div className="mt-3 rounded border p-3" key={index}>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <label>
                            Reference
                            <input
                                required
                                className={field}
                                value={cost.id}
                                onChange={(e) =>
                                    setCost(index, { id: e.target.value })
                                }
                                placeholder="lease"
                            />
                        </label>
                        {COST_TEXT.map((entry) => (
                            <label key={entry.key}>
                                {entry.label}
                                <input
                                    required={entry.key === 'source'}
                                    className={field}
                                    value={cost[entry.key]}
                                    onChange={(e) =>
                                        setCost(index, {
                                            [entry.key]: e.target.value,
                                        })
                                    }
                                />
                            </label>
                        ))}
                        <label>
                            How often it is charged
                            <select
                                className={field}
                                value={cost.frequency}
                                onChange={(e) =>
                                    setCost(index, {
                                        frequency: e.target.value,
                                    })
                                }
                            >
                                <option value="">Not stated</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annual">Annually</option>
                            </select>
                        </label>
                        {COST_DATES.map((entry) => (
                            <label key={entry.key}>
                                {entry.label}
                                <input
                                    type="date"
                                    className={field}
                                    value={cost[entry.key]}
                                    onChange={(e) =>
                                        setCost(index, {
                                            [entry.key]: e.target.value,
                                        })
                                    }
                                />
                            </label>
                        ))}
                        <label>
                            Days to pay after invoice
                            <input
                                className={field}
                                inputMode="numeric"
                                value={cost.creditor_days}
                                onChange={(e) =>
                                    setCost(index, {
                                        creditor_days: e.target.value,
                                    })
                                }
                                placeholder="20"
                            />
                        </label>
                        <label>
                            Evidence seen
                            <select
                                className={field}
                                value={cost.reviewed}
                                onChange={(e) =>
                                    setCost(index, { reviewed: e.target.value })
                                }
                            >
                                <option value="">Not stated</option>
                                <option value="yes">
                                    Contract or invoice sighted
                                </option>
                                <option value="no">Not sighted</option>
                            </select>
                        </label>
                    </div>
                    <button
                        className="mt-3 text-sm underline"
                        type="button"
                        onClick={() =>
                            onChange(costs.filter((_, i) => i !== index))
                        }
                    >
                        Remove this cost
                    </button>
                </div>
            ))}
            <button
                className="mt-3 text-sm underline"
                type="button"
                onClick={() => onChange([...costs, emptyFixedCost()])}
            >
                Add a fixed cost
            </button>
            <p className="mt-3 text-sm">
                The invoice dates have to sit inside the forecast calendar.
                Charges raised before it opens are already in the opening
                position, so recognising them again would double the expense;
                charges raised after it ends are not expenses this forecast
                carries. Record the part of the arrangement that falls inside
                the horizon.
            </p>
            <label className="mt-4 block">
                Is every fixed cost recorded?
                <select
                    className={field}
                    value={complete}
                    onChange={(e) => onComplete(e.target.value)}
                >
                    <option value="">Nobody has said</option>
                    <option value="yes">Yes, this is the whole schedule</option>
                    <option value="no">No, costs are still missing</option>
                </select>
            </label>
            <p className="mt-2 text-sm">
                No count can answer this: an empty list is equally a business
                with no overheads and a section nobody has filled in. One cost
                left unpriced withholds the monthly total rather than
                understating it as the sum of the costs that happen to be known.
            </p>
        </fieldset>
    );
}
