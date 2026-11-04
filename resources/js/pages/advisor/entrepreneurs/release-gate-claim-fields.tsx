type Measurement = 'period' | 'unit' | 'basis';

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

export function ClaimMeasurementFields({
    data,
    setData,
}: {
    data: Record<Measurement, string>;
    setData: (key: Measurement, value: string) => void;
}) {
    const period = data.period.split(':')[0];
    const namedBasis = ['annual_profit', 'current_rate'].includes(data.basis);

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
                >
                    <option value="">Describe the basis</option>
                    <option value="annual_profit">Annual profit</option>
                    <option value="current_rate">Current owner-pay rate</option>
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
