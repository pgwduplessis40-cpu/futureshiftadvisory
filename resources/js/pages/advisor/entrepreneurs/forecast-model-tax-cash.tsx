import type { TaxPaymentInput } from './forecast-model-types';

const field = 'mt-1 block w-full rounded border p-2';

const SCOPES: { value: string; label: string }[] = [
    { value: '', label: 'Nobody has said' },
    { value: 'scheduled', label: 'Scheduled — payments are listed below' },
    { value: 'none_due', label: 'Nothing due in this horizon' },
    { value: 'not_applicable', label: 'Outside this regime' },
];

export function emptyTaxPayment(): TaxPaymentInput {
    return {
        id: '',
        source: '',
        date: '',
        amount: '',
        category: 'tax',
        direction: 'out',
        verified: '',
    };
}

export function TaxCashFields({
    taxState,
    gstState,
    taxSource,
    gstSource,
    payments,
    onState,
    onSource,
    onChange,
}: {
    taxState: string;
    gstState: string;
    taxSource: string;
    gstSource: string;
    payments: TaxPaymentInput[];
    onState: (category: 'tax' | 'gst', value: string) => void;
    onSource: (category: 'tax' | 'gst', value: string) => void;
    onChange: (payments: TaxPaymentInput[]) => void;
}) {
    const setPayment = (index: number, patch: Partial<TaxPaymentInput>) =>
        onChange(
            payments.map((payment, i) =>
                i === index ? { ...payment, ...patch } : payment,
            ),
        );

    return (
        <fieldset className="rounded border p-4">
            <legend className="font-semibold">Tax and GST cash</legend>
            <p className="my-2 text-sm">
                When tax actually leaves the bank, which is a separate question
                from what the tax expense is. A month&rsquo;s tax can be
                recognised long before it is paid, and the gap between the two
                is the tax payable the business carries.
            </p>
            <p className="my-2 text-sm">
                Say what the position is before listing payments. An empty
                schedule is three different answers &mdash; nothing is due, the
                business is outside the regime, or nobody has worked it out yet
                &mdash; and only the first two are finished. A schedule with
                rows contradicts both of them and is refused.
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
                {(['tax', 'gst'] as const).map((category) => (
                    <div key={category} className="space-y-3">
                        <label className="block">
                            {category === 'tax' ? 'Income tax' : 'GST'} position
                            <select
                                className={field}
                                value={category === 'tax' ? taxState : gstState}
                                onChange={(e) =>
                                    onState(category, e.target.value)
                                }
                            >
                                {SCOPES.map((scope) => (
                                    <option
                                        key={scope.value}
                                        value={scope.value}
                                    >
                                        {scope.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block">
                            Where that comes from
                            <input
                                className={field}
                                value={
                                    category === 'tax' ? taxSource : gstSource
                                }
                                onChange={(e) =>
                                    onSource(category, e.target.value)
                                }
                                placeholder={
                                    category === 'tax'
                                        ? 'IRD instalment notice'
                                        : 'GST return or registration status'
                                }
                            />
                        </label>
                    </div>
                ))}
            </div>
            {payments.map((payment, index) => (
                <div className="mt-3 rounded border p-3" key={index}>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <label>
                            Reference
                            <input
                                required
                                className={field}
                                value={payment.id}
                                onChange={(e) =>
                                    setPayment(index, { id: e.target.value })
                                }
                                placeholder="provisional-1"
                            />
                        </label>
                        <label>
                            Where this came from
                            <input
                                required
                                className={field}
                                value={payment.source}
                                onChange={(e) =>
                                    setPayment(index, {
                                        source: e.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            Amount (NZD)
                            <input
                                className={field}
                                inputMode="decimal"
                                value={payment.amount}
                                onChange={(e) =>
                                    setPayment(index, {
                                        amount: e.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            Date
                            <input
                                type="date"
                                className={field}
                                value={payment.date}
                                onChange={(e) =>
                                    setPayment(index, { date: e.target.value })
                                }
                            />
                        </label>
                        <label>
                            Which tax
                            <select
                                className={field}
                                value={payment.category}
                                onChange={(e) =>
                                    setPayment(index, {
                                        category: e.target.value,
                                    })
                                }
                            >
                                <option value="tax">Income tax</option>
                                <option value="gst">GST</option>
                            </select>
                        </label>
                        <label>
                            Direction
                            <select
                                className={field}
                                value={payment.direction}
                                onChange={(e) =>
                                    setPayment(index, {
                                        direction: e.target.value,
                                    })
                                }
                            >
                                <option value="out">
                                    Paid to Inland Revenue
                                </option>
                                <option value="in">Refund received</option>
                            </select>
                        </label>
                        <label>
                            Evidence seen
                            <select
                                className={field}
                                value={payment.verified}
                                onChange={(e) =>
                                    setPayment(index, {
                                        verified: e.target.value,
                                    })
                                }
                            >
                                <option value="">Not stated</option>
                                <option value="yes">Statement sighted</option>
                                <option value="no">Not sighted</option>
                            </select>
                        </label>
                    </div>
                    <button
                        className="mt-3 text-sm underline"
                        type="button"
                        onClick={() =>
                            onChange(payments.filter((_, i) => i !== index))
                        }
                    >
                        Remove this payment
                    </button>
                </div>
            ))}
            <button
                className="mt-3 text-sm underline"
                type="button"
                onClick={() => onChange([...payments, emptyTaxPayment()])}
            >
                Add a tax or GST payment
            </button>
            <p className="mt-3 text-sm">
                Tax already paid before the forecast opens sits in the opening
                balance and is refused here, because counting it in both places
                counts it twice. A refund is recorded as one, but a month that
                carries a refund states no payment figure at all: the cash
                ledger has no category that describes a refund, so a net figure
                could never agree with the ledger it is checked against.
            </p>
        </fieldset>
    );
}
