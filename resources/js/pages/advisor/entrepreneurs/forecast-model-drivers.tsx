import { useForm } from '@inertiajs/react';
import type {
    DriversForm,
    ForecastModel,
    FounderMonthInput,
    StreamInput,
    StreamMonthInput,
} from './forecast-model-types';

const field = 'mt-1 block w-full rounded border p-2';

function text(value: string | null): string {
    return value ?? '';
}

/** A stream nobody has classified posts an empty string, which the server
 * stores as an absent flag rather than as exclusion from the base case. */
function membership(value: boolean | null): string {
    if (value === null) {
        return '';
    }

    return value ? 'base' : 'upside';
}

function emptyMonth(): StreamMonthInput {
    return {
        period: '',
        booked_units: '',
        delivery_units: '',
        invoiced_units: '',
        founder_hours: '',
        additional_capacity_units: '',
        provider: '',
        provider_unit_cost: '',
        no_cost_basis: '',
    };
}

function initial(model: ForecastModel | null): DriversForm {
    return {
        expected_revision: model?.revision ?? null,
        streams: (model?.streams ?? []).map(
            (stream): StreamInput => ({
                id: stream.id,
                name: stream.name,
                unit: text(stream.unit),
                price: text(stream.price),
                preparation_hours_per_unit: text(
                    stream.preparation_hours_per_unit,
                ),
                delivery_hours_per_unit: text(stream.delivery_hours_per_unit),
                in_base_case: membership(stream.in_base_case),
                evidence_status: text(stream.evidence_status),
                schedule: stream.schedule.map(
                    (month): StreamMonthInput => ({
                        period: month.period,
                        booked_units: text(month.booked_units),
                        delivery_units: text(month.delivery_units),
                        invoiced_units: text(month.invoiced_units),
                        founder_hours: text(month.founder_hours),
                        additional_capacity_units: text(
                            month.additional_capacity_units,
                        ),
                        provider: text(month.provider),
                        provider_unit_cost: text(month.provider_unit_cost),
                        no_cost_basis: text(month.no_cost_basis),
                    }),
                ),
            }),
        ),
        founder_months: (model?.founder_months ?? []).map(
            (month): FounderMonthInput => ({
                period: month.period,
                available_hours: text(month.available_hours),
                administration_hours: text(month.administration_hours),
                business_development_hours: text(
                    month.business_development_hours,
                ),
            }),
        ),
    };
}

const MONTH_FIELDS: { key: keyof StreamMonthInput; label: string }[] = [
    { key: 'booked_units', label: 'Units booked' },
    { key: 'delivery_units', label: 'Units delivered' },
    { key: 'invoiced_units', label: 'Units invoiced' },
    { key: 'founder_hours', label: 'Founder hours used' },
    { key: 'additional_capacity_units', label: 'Units from extra capacity' },
    { key: 'provider', label: 'Capacity provider' },
    { key: 'provider_unit_cost', label: 'Provider cost per unit' },
    { key: 'no_cost_basis', label: 'Why the provider costs nothing' },
];

const FOUNDER_FIELDS: { key: keyof FounderMonthInput; label: string }[] = [
    { key: 'available_hours', label: 'Hours available' },
    { key: 'administration_hours', label: 'Hours on administration' },
    { key: 'business_development_hours', label: 'Hours on winning work' },
];

const STREAM_FIELDS: { key: keyof StreamInput; label: string }[] = [
    { key: 'unit', label: 'What one unit is' },
    { key: 'price', label: 'Price per unit (NZD)' },
    { key: 'preparation_hours_per_unit', label: 'Preparation hours per unit' },
    { key: 'delivery_hours_per_unit', label: 'Delivery hours per unit' },
];

export function DriversFormSection({
    model,
    url,
}: {
    model: ForecastModel | null;
    url: string;
}) {
    const form = useForm<DriversForm>(initial(model));
    const streams = form.data.streams;
    const founderMonths = form.data.founder_months;

    const setStream = (index: number, patch: Partial<StreamInput>) =>
        form.setData(
            'streams',
            streams.map((stream, i) =>
                i === index ? { ...stream, ...patch } : stream,
            ),
        );
    const setMonth = (
        index: number,
        row: number,
        patch: Partial<StreamMonthInput>,
    ) =>
        setStream(index, {
            schedule: streams[index].schedule.map((month, i) =>
                i === row ? { ...month, ...patch } : month,
            ),
        });
    const setFounder = (row: number, patch: Partial<FounderMonthInput>) =>
        form.setData(
            'founder_months',
            founderMonths.map((month, i) =>
                i === row ? { ...month, ...patch } : month,
            ),
        );

    return (
        <form
            className="space-y-6"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(url, { preserveScroll: true });
            }}
        >
            <fieldset className="rounded border p-4">
                <legend className="font-semibold">Founder capacity</legend>
                <p className="my-2 text-sm">
                    Record the hours the founder actually has in a month, and
                    what already claims them. A month left out is a month with
                    no stated capacity, not a month with none.
                </p>
                {founderMonths.map((month, row) => (
                    <div
                        className="mt-3 grid gap-3 rounded border p-3 sm:grid-cols-4"
                        key={row}
                    >
                        <label>
                            Month
                            <input
                                required
                                type="month"
                                className={field}
                                value={month.period}
                                onChange={(e) =>
                                    setFounder(row, { period: e.target.value })
                                }
                            />
                        </label>
                        {FOUNDER_FIELDS.map((entry) => (
                            <label key={entry.key}>
                                {entry.label}
                                <input
                                    className={field}
                                    inputMode="decimal"
                                    value={month[entry.key]}
                                    onChange={(e) =>
                                        setFounder(row, {
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
                                form.setData(
                                    'founder_months',
                                    founderMonths.filter((_, i) => i !== row),
                                )
                            }
                        >
                            Remove {month.period || 'this month'}
                        </button>
                    </div>
                ))}
                <button
                    type="button"
                    className="mt-3 rounded border p-2"
                    onClick={() =>
                        form.setData('founder_months', [
                            ...founderMonths,
                            {
                                period: '',
                                available_hours: '',
                                administration_hours: '',
                                business_development_hours: '',
                            },
                        ])
                    }
                >
                    Add a capacity month
                </button>
            </fieldset>
            <fieldset className="rounded border p-4">
                <legend className="font-semibold">Revenue streams</legend>
                <p className="my-2 text-sm">
                    Each stream needs its own reference, because release
                    findings are reported against it. Leave the base-case
                    question unanswered only while it genuinely is: an
                    unanswered stream is reported as unverified, never treated
                    as upside.
                </p>
                {streams.map((stream, index) => (
                    <div className="mt-3 rounded border p-3" key={index}>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label>
                                Reference
                                <input
                                    required
                                    className={field}
                                    value={stream.id}
                                    onChange={(e) =>
                                        setStream(index, {
                                            id: e.target.value,
                                        })
                                    }
                                    placeholder="workshops"
                                />
                            </label>
                            <label>
                                Stream name
                                <input
                                    required
                                    className={field}
                                    value={stream.name}
                                    onChange={(e) =>
                                        setStream(index, {
                                            name: e.target.value,
                                        })
                                    }
                                />
                            </label>
                            {STREAM_FIELDS.map((entry) => (
                                <label key={entry.key}>
                                    {entry.label}
                                    <input
                                        className={field}
                                        value={stream[entry.key] as string}
                                        onChange={(e) =>
                                            setStream(index, {
                                                [entry.key]: e.target.value,
                                            })
                                        }
                                    />
                                </label>
                            ))}
                            <label>
                                Counted in the base case
                                <select
                                    className={field}
                                    value={stream.in_base_case}
                                    onChange={(e) =>
                                        setStream(index, {
                                            in_base_case: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">Not yet decided</option>
                                    <option value="base">
                                        Yes, part of the base case
                                    </option>
                                    <option value="upside">
                                        No, upside only
                                    </option>
                                </select>
                            </label>
                            <label>
                                Evidence behind the numbers
                                <select
                                    className={field}
                                    value={stream.evidence_status}
                                    onChange={(e) =>
                                        setStream(index, {
                                            evidence_status: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">Not stated</option>
                                    <option value="verified">
                                        Verified against a document
                                    </option>
                                    <option value="own_record">
                                        The business&rsquo;s own record
                                    </option>
                                    <option value="founder_stated">
                                        Stated by the founder
                                    </option>
                                    <option value="founder_assumption">
                                        A founder assumption
                                    </option>
                                </select>
                            </label>
                        </div>
                        {stream.schedule.map((month, row) => (
                            <div
                                className="mt-3 grid gap-3 rounded border p-3 sm:grid-cols-3"
                                key={row}
                            >
                                <label>
                                    Month
                                    <input
                                        required
                                        type="month"
                                        className={field}
                                        value={month.period}
                                        onChange={(e) =>
                                            setMonth(index, row, {
                                                period: e.target.value,
                                            })
                                        }
                                    />
                                </label>
                                {MONTH_FIELDS.map((entry) => (
                                    <label key={entry.key}>
                                        {entry.label}
                                        <input
                                            className={field}
                                            value={month[entry.key]}
                                            onChange={(e) =>
                                                setMonth(index, row, {
                                                    [entry.key]: e.target.value,
                                                })
                                            }
                                        />
                                    </label>
                                ))}
                                <button
                                    type="button"
                                    className="rounded border p-2 sm:col-span-3"
                                    onClick={() =>
                                        setStream(index, {
                                            schedule: stream.schedule.filter(
                                                (_, i) => i !== row,
                                            ),
                                        })
                                    }
                                >
                                    Remove {month.period || 'this month'} from{' '}
                                    {stream.name || 'this stream'}
                                </button>
                            </div>
                        ))}
                        <div className="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                className="rounded border p-2"
                                onClick={() =>
                                    setStream(index, {
                                        schedule: [
                                            ...stream.schedule,
                                            emptyMonth(),
                                        ],
                                    })
                                }
                            >
                                Add a month to {stream.name || 'this stream'}
                            </button>
                            <button
                                type="button"
                                className="rounded border p-2"
                                onClick={() =>
                                    form.setData(
                                        'streams',
                                        streams.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                Remove {stream.name || 'this stream'}
                            </button>
                        </div>
                    </div>
                ))}
                <button
                    type="button"
                    className="mt-3 rounded border p-2"
                    onClick={() =>
                        form.setData('streams', [
                            ...streams,
                            {
                                id: '',
                                name: '',
                                unit: '',
                                price: '',
                                preparation_hours_per_unit: '',
                                delivery_hours_per_unit: '',
                                in_base_case: '',
                                evidence_status: '',
                                schedule: [],
                            },
                        ])
                    }
                >
                    Add a revenue stream
                </button>
            </fieldset>
            {Object.values(form.errors).map((error) => (
                <p role="alert" key={error}>
                    {error}
                </p>
            ))}
            <button disabled={form.processing} className="rounded border p-2">
                Save streams and capacity
            </button>
        </form>
    );
}
