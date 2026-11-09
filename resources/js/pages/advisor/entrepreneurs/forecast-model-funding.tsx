import type { FundingInput } from './forecast-model-types';

const field = 'mt-1 block w-full rounded border p-2';

const FUNDING_TEXT: { key: keyof FundingInput; label: string }[] = [
    { key: 'source', label: 'Where this came from' },
    { key: 'identity', label: 'Who is providing it' },
    { key: 'type', label: 'What kind of funding' },
    { key: 'amount', label: 'Amount (NZD)' },
];

const FUNDING_DATES: { key: keyof FundingInput; label: string }[] = [
    { key: 'available_from', label: 'Available from' },
    { key: 'drawn_on', label: 'Drawn on' },
];

export function emptyFundingSource(): FundingInput {
    return {
        id: '',
        source: '',
        identity: '',
        type: '',
        amount: '',
        commitment: '',
        available_from: '',
        drawn_on: '',
        restriction: '',
        verified: '',
    };
}

export function FundingFields({
    sources,
    complete,
    onChange,
    onComplete,
}: {
    sources: FundingInput[];
    complete: string;
    onChange: (sources: FundingInput[]) => void;
    onComplete: (value: string) => void;
}) {
    const setSource = (index: number, patch: Partial<FundingInput>) =>
        onChange(
            sources.map((source, i) =>
                i === index ? { ...source, ...patch } : source,
            ),
        );

    return (
        <fieldset className="rounded border p-4">
            <legend className="font-semibold">Funding sources</legend>
            <p className="my-2 text-sm">
                Money the business has arranged to receive, kept separate from
                the opening balance it already holds. A source drawn before the
                forecast opens is already in that balance and is refused here,
                because counting it in both places counts it twice. One drawn
                after the forecast ends is refused for the opposite reason: it
                never arrives inside the horizon.
            </p>
            {sources.map((source, index) => (
                <div className="mt-3 rounded border p-3" key={index}>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <label>
                            Reference
                            <input
                                required
                                className={field}
                                value={source.id}
                                onChange={(e) =>
                                    setSource(index, { id: e.target.value })
                                }
                                placeholder="term-loan-2026"
                            />
                        </label>
                        {FUNDING_TEXT.map((entry) => (
                            <label key={entry.key}>
                                {entry.label}
                                <input
                                    required={entry.key === 'source'}
                                    className={field}
                                    value={source[entry.key]}
                                    onChange={(e) =>
                                        setSource(index, {
                                            [entry.key]: e.target.value,
                                        })
                                    }
                                />
                            </label>
                        ))}
                        {FUNDING_DATES.map((entry) => (
                            <label key={entry.key}>
                                {entry.label}
                                <input
                                    type="date"
                                    className={field}
                                    value={source[entry.key]}
                                    onChange={(e) =>
                                        setSource(index, {
                                            [entry.key]: e.target.value,
                                        })
                                    }
                                />
                            </label>
                        ))}
                        <label>
                            Commitment
                            <select
                                className={field}
                                value={source.commitment}
                                onChange={(e) =>
                                    setSource(index, {
                                        commitment: e.target.value,
                                    })
                                }
                            >
                                <option value="">Not stated</option>
                                <option value="committed">
                                    Committed in writing
                                </option>
                                <option value="indicative">
                                    Indicative only
                                </option>
                            </select>
                        </label>
                        <label>
                            Restriction
                            <select
                                className={field}
                                value={source.restriction}
                                onChange={(e) =>
                                    setSource(index, {
                                        restriction: e.target.value,
                                    })
                                }
                            >
                                <option value="">Not stated</option>
                                <option value="unrestricted">
                                    Unrestricted, spendable on anything
                                </option>
                                <option value="restricted">
                                    Restricted to a stated purpose
                                </option>
                            </select>
                        </label>
                        <label>
                            Evidence seen
                            <select
                                className={field}
                                value={source.verified}
                                onChange={(e) =>
                                    setSource(index, {
                                        verified: e.target.value,
                                    })
                                }
                            >
                                <option value="">Not stated</option>
                                <option value="yes">
                                    Offer or agreement sighted
                                </option>
                                <option value="no">Not sighted</option>
                            </select>
                        </label>
                    </div>
                    <button
                        className="mt-3 text-sm underline"
                        type="button"
                        onClick={() =>
                            onChange(sources.filter((_, i) => i !== index))
                        }
                    >
                        Remove this source
                    </button>
                </div>
            ))}
            <button
                className="mt-3 text-sm underline"
                type="button"
                onClick={() => onChange([...sources, emptyFundingSource()])}
            >
                Add a funding source
            </button>
            <label className="mt-4 block">
                Is every funding source recorded?
                <select
                    className={field}
                    value={complete}
                    onChange={(e) => onComplete(e.target.value)}
                >
                    <option value="">Nobody has said</option>
                    <option value="yes">
                        Yes, this is the whole inventory
                    </option>
                    <option value="no">No, sources are still missing</option>
                </select>
            </label>
            <p className="mt-2 text-sm">
                No count can answer this: an empty list is equally a business
                with no funding and a section nobody has filled in. Until
                somebody who looked says so, the funding total and available
                funds stay unstated, because the sum of a partial list is not a
                total.
            </p>
        </fieldset>
    );
}
