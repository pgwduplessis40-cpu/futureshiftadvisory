import type { ConfirmedClaim } from './release-gate-types';

type Measurement = 'period' | 'unit' | 'basis';

export const claimNames: Record<string, string> = {
    'plan.profitability': 'Profitability year',
    'plan.owner_pay': 'Owner pay',
    'plan.opening_cash': 'Opening cash',
    'plan.margin_min': 'Profit margin target minimum',
    'plan.margin_max': 'Profit margin target maximum',
    'plan.margin_explanation': 'Margin explanation',
    'plan.commitment': 'Committed cost',
    'plan.commitment_in_scope': 'Cost included in current plan',
    'scope.required_horizon': 'Required forecast horizon in months',
    'runway.months': 'Runway in months',
    'runway.start_cash': 'Cash the runway starts from',
    'runway.includes_receipts': 'Runway counts future customer receipts',
    'runway.includes_owner_pay': 'Runway counts owner pay',
};

// Mirrors ClaimVersionStore::BOOLEAN_KEYS. A yes/no answer typed as prose is
// refused by the server, so it is never offered as a free text box.
export const booleanKeys = [
    'plan.commitment_in_scope',
    'runway.includes_receipts',
    'runway.includes_owner_pay',
];

// Mirrors ClaimVersionStore::GROUPS. PM-10 reads a runway's inclusions at the
// same subject and period as the figure, so they are confirmed as one act.
// Offered separately they would be four chances to disagree about a
// coordinate nothing made them share, and the rule would find the figure,
// none of its companions, and report evidence the advisor had entered as
// absent.
export const groups: Record<string, string[]> = {
    'runway.months': [
        'runway.start_cash',
        'runway.includes_receipts',
        'runway.includes_owner_pay',
    ],
};

// A companion is never offered as a statement of its own, because the server
// refuses one recorded on its own.
export const companionKeys = Object.values(groups).flat();

function readValue(claim: ConfirmedClaim) {
    if (typeof claim.value === 'boolean') {
        return claim.value ? 'Counted' : 'Not counted';
    }

    if (claim.value === null) {
        return 'Not stated';
    }

    return claim.unit === null
        ? String(claim.value)
        : `${claim.value} ${claim.unit}`;
}

/**
 * What the gate is holding, and what it has stopped reading.
 *
 * A confirmed claim binds to the wording it was confirmed against, so one
 * whose plan answer has since been edited is no longer read. Nothing said so:
 * the advisor saw the claim saved and then saw the rule report its evidence
 * missing, with no way to connect the two. A claim that no longer applies is
 * listed as such rather than disappearing, because it is still on the record
 * and re-confirming it against the current wording is the way back.
 */
export function ConfirmedClaims({ claims }: { claims: ConfirmedClaim[] }) {
    if (claims.length === 0) {
        return <p>No statements have been confirmed on this plan yet.</p>;
    }

    const stale = claims.filter((claim) => !claim.applies).length;

    return (
        <>
            {stale > 0 && (
                <p role="status" className="my-3">
                    {stale === 1
                        ? '1 confirmed statement is no longer read, because the plan answer behind it changed.'
                        : `${stale} confirmed statements are no longer read, because the plan answers behind them changed.`}{' '}
                    Confirm them again against the current wording.
                </p>
            )}
            <ul className="space-y-3">
                {claims.map((claim) => (
                    <li
                        className="rounded border p-3"
                        key={`${claim.key}:${claim.entity}:${claim.period}`}
                    >
                        <p className="font-semibold">
                            {claimNames[claim.key] ?? claim.key}
                            {': '}
                            {readValue(claim)}
                        </p>
                        <p>
                            {claim.entity}
                            {' · '}
                            {claim.period}
                            {claim.basis === null ? '' : ` · ${claim.basis}`}
                            {claim.as_of === null
                                ? ''
                                : ` · as at ${claim.as_of}`}
                        </p>
                        <p>
                            {claim.applies
                                ? 'Read by the gate.'
                                : 'No longer read: the plan answer behind this changed after it was confirmed.'}
                            {claim.section_title === null
                                ? ''
                                : ` From "${claim.section_title}".`}
                        </p>
                        <p>
                            Confirmed
                            {claim.confirmed_by === null
                                ? ''
                                : ` by ${claim.confirmed_by}`}
                            {claim.revision > 1
                                ? ` · revision ${claim.revision}`
                                : ''}
                        </p>
                        <p>{claim.reason}</p>
                    </li>
                ))}
            </ul>
        </>
    );
}

/**
 * The statements that travel with a figure, asked for beside it.
 *
 * Every one is required: an unanswered inclusion left blank would reach the
 * server as an empty string, which reads as "not counted" rather than as
 * unanswered, so the form does not offer a way to leave one out.
 */
export function ClaimCompanionFields({
    claimKey,
    values,
    onChange,
}: {
    claimKey: string;
    values: Record<string, string>;
    onChange: (next: Record<string, string>) => void;
}) {
    return (
        <>
            {(groups[claimKey] ?? []).map((companion) => (
                <label key={companion}>
                    {claimNames[companion]}
                    {booleanKeys.includes(companion) ? (
                        <select
                            className="block w-full rounded border p-2"
                            required
                            value={values[companion] ?? ''}
                            onChange={(e) =>
                                onChange({
                                    ...values,
                                    [companion]: e.target.value,
                                })
                            }
                        >
                            <option value="">
                                Choose whether this is counted
                            </option>
                            <option value="true">Counted</option>
                            <option value="false">Not counted</option>
                        </select>
                    ) : (
                        <input
                            className="block w-full rounded border p-2"
                            required
                            value={values[companion] ?? ''}
                            onChange={(e) =>
                                onChange({
                                    ...values,
                                    [companion]: e.target.value,
                                })
                            }
                        />
                    )}
                </label>
            ))}
        </>
    );
}

const units = {
    NZD: 'New Zealand dollars',
    'NZD/week': 'New Zealand dollars per week',
    'NZD/month': 'New Zealand dollars per month',
    'NZD/year': 'New Zealand dollars per year',
    percent: 'Percent',
    year: 'Year number',
    months: 'Number of months',
    boolean: 'Included or excluded',
    text: 'Explanation',
};

// PM-10 measures a runway on these two bases and no others, and the server
// refuses a claim stated on anything else. Offering a description box here
// would take an answer the rule could never read.
const runwayBases = {
    essential_cost_no_sales: 'Essential costs with no sales',
    forecast_cash: 'Forecast cash while trading',
};

export function ClaimMeasurementFields({
    claimKey,
    data,
    setData,
}: {
    claimKey: string;
    data: Record<Measurement, string>;
    setData: (key: Measurement, value: string) => void;
}) {
    const period = data.period.split(':')[0];
    const runway = claimKey === 'runway.months';
    const namedBasis =
        ['annual_profit', 'current_rate'].includes(data.basis) || runway;

    return (
        <>
            <label>
                Period
                <select
                    className="block w-full rounded border p-2"
                    value={period}
                    onChange={(e) =>
                        setData(
                            'period',
                            ['year', 'month'].includes(e.target.value)
                                ? `${e.target.value}:1`
                                : e.target.value,
                        )
                    }
                >
                    <option value="current">Current position</option>
                    <option value="year">Forecast year</option>
                    <option value="month">Forecast month</option>
                    <option value="unspecified">Not yet specified</option>
                </select>
            </label>
            {['year', 'month'].includes(period) && (
                <label>
                    {period === 'year'
                        ? 'Forecast year number'
                        : 'Forecast month number'}
                    <input
                        className="block w-full rounded border p-2"
                        type="number"
                        min="1"
                        step="1"
                        required
                        value={data.period.split(':')[1] ?? ''}
                        onChange={(e) =>
                            setData('period', `${period}:${e.target.value}`)
                        }
                    />
                </label>
            )}
            <label>
                Unit
                <select
                    className="block w-full rounded border p-2"
                    value={data.unit}
                    onChange={(e) => setData('unit', e.target.value)}
                >
                    {Object.entries(units).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </label>
            <label>
                Measurement basis
                <select
                    className="block w-full rounded border p-2"
                    value={namedBasis ? data.basis : ''}
                    onChange={(e) => setData('basis', e.target.value)}
                    required={runway}
                >
                    {runway ? (
                        <>
                            <option value="">
                                Choose what the runway measures
                            </option>
                            {Object.entries(runwayBases).map(
                                ([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </>
                    ) : (
                        <>
                            <option value="">Describe the basis</option>
                            <option value="annual_profit">Annual profit</option>
                            <option value="current_rate">
                                Current owner-pay rate
                            </option>
                        </>
                    )}
                </select>
            </label>
            {!namedBasis && (
                <label className="sm:col-span-2">
                    Basis description
                    <input
                        className="block w-full rounded border p-2"
                        required
                        value={data.basis}
                        onChange={(e) => setData('basis', e.target.value)}
                        placeholder="For cash, identify the accounts and any restrictions on use."
                    />
                </label>
            )}
        </>
    );
}
