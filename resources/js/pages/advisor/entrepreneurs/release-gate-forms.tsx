import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    ClaimCompanionFields,
    ClaimMeasurementFields,
    booleanKeys,
    claimNames,
    companionKeys,
} from './release-gate-claim-fields';
import type {
    Configuration,
    GateProps,
    RuleResult,
} from './release-gate-types';

// A runway the rule cannot read is refused on save, so the unit it must be
// stated in is set when the statement is chosen rather than left to be
// discovered from an error message.
const unitForKey: Record<string, string> = {
    'runway.months': 'months',
    'runway.start_cash': 'NZD',
    'scope.required_horizon': 'months',
    'plan.margin_explanation': 'text',
};

export function ReleaseClaimForm({
    sections,
    url,
}: {
    sections: GateProps['sections'];
    url: string;
}) {
    const form = useForm({
        section_id: sections[0]?.id ?? '',
        source_hash: sections[0]?.hash ?? '',
        key: 'plan.opening_cash',
        value: '',
        entity: 'business',
        period: 'current',
        unit: 'NZD',
        basis: '',
        as_of: '',
        reason: '',
        companions: {} as Record<string, string>,
    });

    return (
        <details className="rounded border p-4">
            <summary>Confirm a financial statement in the plan</summary>
            <p className="my-3">
                Confirm what a saved answer means, including its date and
                measurement basis. Correct the plan answer first if its wording
                is wrong. Other conflicting statements remain visible.
            </p>
            <form
                className="grid gap-3 sm:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(url, { preserveScroll: true });
                }}
            >
                <label>
                    Plan answer
                    <select
                        className="block w-full rounded border p-2"
                        value={form.data.section_id}
                        onChange={(e) =>
                            form.setData({
                                ...form.data,
                                section_id: e.target.value,
                                source_hash:
                                    sections.find(
                                        (s) => s.id === e.target.value,
                                    )?.hash ?? '',
                            })
                        }
                    >
                        {sections.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.title}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Statement
                    <select
                        className="block w-full rounded border p-2"
                        value={form.data.key}
                        onChange={(e) =>
                            form.setData({
                                ...form.data,
                                key: e.target.value,
                                basis: '',
                                value: '',
                                companions: {},
                                unit:
                                    unitForKey[e.target.value] ??
                                    form.data.unit,
                            })
                        }
                    >
                        {Object.entries(claimNames)
                            .filter(([key]) => !companionKeys.includes(key))
                            .map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                    </select>
                </label>
                {(['value', 'entity', 'as_of'] as const).map((key) => (
                    <label key={key}>
                        {
                            {
                                value: 'Value',
                                entity: 'Business or named offer/cost',
                                as_of: 'As at date',
                            }[key]
                        }
                        {key === 'value' &&
                        booleanKeys.includes(form.data.key) ? (
                            <select
                                className="block w-full rounded border p-2"
                                value={form.data.value}
                                onChange={(e) =>
                                    form.setData('value', e.target.value)
                                }
                                required
                            >
                                <option value="">
                                    Choose whether this is included
                                </option>
                                <option value="true">Included</option>
                                <option value="false">Excluded</option>
                            </select>
                        ) : (
                            <input
                                className="block w-full rounded border p-2"
                                type={key === 'as_of' ? 'date' : 'text'}
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                        )}
                    </label>
                ))}
                <ClaimCompanionFields
                    claimKey={form.data.key}
                    values={form.data.companions}
                    onChange={(next) => form.setData('companions', next)}
                />
                <ClaimMeasurementFields
                    claimKey={form.data.key}
                    data={form.data}
                    setData={(key, value) => form.setData(key, value)}
                />
                <label className="sm:col-span-2">
                    Confirmation reason
                    <textarea
                        required
                        minLength={15}
                        className="block w-full rounded border p-2"
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                    />
                </label>
                {Object.values(form.errors).map((error) => (
                    <p role="alert" key={error}>
                        {error}
                    </p>
                ))}
                <button
                    disabled={form.processing}
                    className="rounded border p-2"
                >
                    Save confirmed statement
                </button>
            </form>
        </details>
    );
}

export function ReleasePolicyForm({
    configuration,
    hash,
    url,
    rules,
}: {
    configuration: Configuration;
    hash: string;
    url: string;
    rules: RuleResult['rule'][];
}) {
    const [rule, setRule] = useState(rules[0]?.id ?? 'PM-01');
    const form = useForm({
        ...configuration,
        configuration_hash: hash,
        reason: '',
    });
    const selected = rules.find((r) => r.id === rule);
    const labels: Record<string, string> = {
        opening_cash_tolerance_percent: 'Opening cash tolerance (%)',
        material_cost: 'Material committed cost (NZD)',
        asset_review: 'Asset review threshold (NZD)',
        cash_tolerance: 'Cash arithmetic tolerance (fixed at NZD 0.01)',
        duplicate_similarity_percent: 'Duplicate wording similarity (%)',
    };

    return (
        <details className="rounded border p-4">
            <summary>Principal Advisor: release policy</summary>
            <p className="my-3">
                Policy changes apply to all plans and require a new review of
                affected decisions. Integrity floors and the eligible exception
                list cannot be changed here.
            </p>
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(url, { preserveScroll: true });
                }}
            >
                {Object.entries(form.data.thresholds).map(([key, value]) => (
                    <label className="block" key={key}>
                        {labels[key] ?? key}
                        <input
                            className="ml-2 rounded border p-2"
                            disabled={key === 'cash_tolerance'}
                            type="number"
                            min="0"
                            step="any"
                            value={value}
                            onChange={(e) =>
                                form.setData('thresholds', {
                                    ...form.data.thresholds,
                                    [key]: e.target.value,
                                })
                            }
                        />
                    </label>
                ))}
                <label className="block">
                    Rule
                    <select
                        className="ml-2 rounded border p-2"
                        value={rule}
                        onChange={(e) => setRule(e.target.value)}
                    >
                        {rules.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.id} · {r.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="block">
                    Severity (minimum: {selected?.minimum_severity})
                    <select
                        className="ml-2 rounded border p-2"
                        value={form.data.severities[rule] ?? selected?.severity}
                        onChange={(e) =>
                            form.setData('severities', {
                                ...form.data.severities,
                                [rule]: e.target.value,
                            })
                        }
                    >
                        {['block', 'review', 'autofix', 'info'].map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="block">
                    Additional advisor guidance
                    <textarea
                        className="block w-full rounded border p-2"
                        maxLength={1000}
                        value={form.data.messages[rule] ?? ''}
                        onChange={(e) => {
                            const messages = { ...form.data.messages };

                            if (e.target.value) {
                                messages[rule] = e.target.value;
                            } else {
                                delete messages[rule];
                            }

                            form.setData('messages', messages);
                        }}
                    />
                </label>
                <label className="block">
                    Reason for this policy version
                    <textarea
                        required
                        minLength={15}
                        maxLength={2000}
                        className="block w-full rounded border p-2"
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                    />
                </label>
                {Object.values(form.errors).map((error) => (
                    <p role="alert" key={error}>
                        {error}
                    </p>
                ))}
                <button
                    disabled={form.processing}
                    className="rounded border p-2"
                >
                    Save policy version
                </button>
            </form>
        </details>
    );
}
