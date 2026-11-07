import { useForm } from '@inertiajs/react';
import type {
    ActualInput,
    ForecastModel,
    SaveForm,
} from './forecast-model-types';

const field = 'mt-1 block w-full rounded border p-2';

function blank(value: string | null): string {
    return value ?? '';
}

function initial(model: ForecastModel | null): SaveForm {
    return {
        expected_revision: model?.revision ?? null,
        start_month: model?.calendar?.start_month ?? '',
        months: model?.calendar?.months ?? 24,
        opening_amount: blank(model?.cash_opening.amount ?? null),
        opening_source: blank(model?.cash_opening.source ?? null),
        opening_verified: model?.cash_opening.verified === true,
        reserve_monthly_fixed_costs: blank(
            model?.reserve.monthly_fixed_costs ?? null,
        ),
        reserve_months_covered: model?.reserve.months_covered ?? 3,
        reserve_includes_owner_pay: model?.reserve.includes_owner_pay === true,
        reserve_one_off_cash_costs: blank(
            model?.reserve.one_off_cash_costs ?? null,
        ),
        reserve_contingency_rate: model?.reserve.contingency_rate ?? '0.10',
        reserve_basis: blank(model?.reserve.basis ?? null),
        actuals: (model?.actuals ?? []).map(
            (row): ActualInput => ({
                period: row.period,
                state: row.state ?? 'trading',
                reconciled: row.reconciled === true,
                closing_bank_cash: blank(row.closing_bank_cash),
                reviewed_by: blank(row.reviewed_by),
                reviewed_on: blank(row.reviewed_on),
                pre_trading_evidence: blank(row.pre_trading_evidence),
            }),
        ),
    };
}

export function SelectionForm({
    model,
    url,
}: {
    model: ForecastModel;
    url: string;
}) {
    const form = useForm({ selected: !model.selected });
    const incomplete = model.sections.filter((s) => !s.captured);

    return (
        <form
            className="space-y-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(url, { preserveScroll: true });
            }}
        >
            {model.selected ? (
                <p>
                    This reconstruction is the plan financial model, selected
                    {model.selected_by ? ` by ${model.selected_by}` : ''}
                    {model.selected_at ? ` on ${model.selected_at}` : ''}.
                    Returning the plan to the legacy model is recorded.
                </p>
            ) : (
                <p>
                    This reconstruction is saved but not selected. The plan
                    still uses the legacy budget, and no release verdict reads
                    anything below.
                </p>
            )}
            {!model.selected && incomplete.length ? (
                <p role="status">
                    {incomplete.length} of {model.sections.length} sections are
                    not captured: {incomplete.map((s) => s.label).join(', ')}.
                    Selecting an incomplete reconstruction is allowed; the
                    missing sections will read as Cannot verify rather than as
                    agreement.
                </p>
            ) : null}
            {Object.values(form.errors).map((error) => (
                <p role="alert" key={error}>
                    {error}
                </p>
            ))}
            <button disabled={form.processing} className="rounded border p-2">
                {model.selected
                    ? 'Return this plan to the legacy model'
                    : 'Select as the plan financial model'}
            </button>
        </form>
    );
}

export function ForecastModelForm({
    model,
    url,
}: {
    model: ForecastModel | null;
    url: string;
}) {
    const form = useForm<SaveForm>(initial(model));
    const rows = form.data.actuals;
    const setRow = (index: number, patch: Partial<ActualInput>) =>
        form.setData(
            'actuals',
            rows.map((row, i) => (i === index ? { ...row, ...patch } : row)),
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
                <legend className="font-semibold">Forecast calendar</legend>
                <div className="grid gap-3 sm:grid-cols-2">
                    <label>
                        First forecast month
                        <input
                            required
                            type="month"
                            className={field}
                            value={form.data.start_month}
                            onChange={(e) =>
                                form.setData('start_month', e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Months in the horizon
                        <input
                            required
                            type="number"
                            min={1}
                            max={60}
                            className={field}
                            value={form.data.months}
                            onChange={(e) =>
                                form.setData('months', Number(e.target.value))
                            }
                        />
                    </label>
                </div>
            </fieldset>
            <fieldset className="rounded border p-4">
                <legend className="font-semibold">Opening cash</legend>
                <p className="my-2 text-sm">
                    Leave the amount empty if it has not been established. An
                    empty box stays unknown; it does not become zero.
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                    <label>
                        Opening balance (NZD)
                        <input
                            className={field}
                            inputMode="decimal"
                            value={form.data.opening_amount}
                            onChange={(e) =>
                                form.setData('opening_amount', e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Source of the balance
                        <input
                            className={field}
                            value={form.data.opening_source}
                            onChange={(e) =>
                                form.setData('opening_source', e.target.value)
                            }
                            placeholder="Bank statement to 31 December 2025"
                        />
                    </label>
                    <div className="sm:col-span-2">
                        <input
                            id="opening-verified"
                            name="opening_verified"
                            type="checkbox"
                            className="mr-2"
                            checked={form.data.opening_verified}
                            onChange={(e) =>
                                form.setData(
                                    'opening_verified',
                                    e.target.checked,
                                )
                            }
                        />
                        <label htmlFor="opening-verified">
                            I have seen the source document for this balance
                        </label>
                    </div>
                </div>
            </fieldset>
            <fieldset className="rounded border p-4">
                <legend className="font-semibold">Reserve policy</legend>
                <p className="my-2 text-sm">
                    The reserve target is calculated from these inputs. It is
                    never typed in directly, so a stated target cannot drift
                    away from the policy behind it.
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                    <label>
                        Monthly fixed costs (NZD)
                        <input
                            className={field}
                            inputMode="decimal"
                            value={form.data.reserve_monthly_fixed_costs}
                            onChange={(e) =>
                                form.setData(
                                    'reserve_monthly_fixed_costs',
                                    e.target.value,
                                )
                            }
                        />
                    </label>
                    <label>
                        Months of cover
                        <input
                            required
                            type="number"
                            min={0}
                            max={60}
                            className={field}
                            value={form.data.reserve_months_covered}
                            onChange={(e) =>
                                form.setData(
                                    'reserve_months_covered',
                                    Number(e.target.value),
                                )
                            }
                        />
                    </label>
                    <label>
                        One-off cash costs (NZD)
                        <input
                            className={field}
                            inputMode="decimal"
                            value={form.data.reserve_one_off_cash_costs}
                            onChange={(e) =>
                                form.setData(
                                    'reserve_one_off_cash_costs',
                                    e.target.value,
                                )
                            }
                        />
                    </label>
                    <label>
                        Contingency rate (0.10 for 10%)
                        <input
                            required
                            className={field}
                            inputMode="decimal"
                            value={form.data.reserve_contingency_rate}
                            onChange={(e) =>
                                form.setData(
                                    'reserve_contingency_rate',
                                    e.target.value,
                                )
                            }
                        />
                    </label>
                    <div className="sm:col-span-2">
                        <input
                            id="reserve-includes-owner-pay"
                            name="reserve_includes_owner_pay"
                            type="checkbox"
                            className="mr-2"
                            checked={form.data.reserve_includes_owner_pay}
                            onChange={(e) =>
                                form.setData(
                                    'reserve_includes_owner_pay',
                                    e.target.checked,
                                )
                            }
                        />
                        <label htmlFor="reserve-includes-owner-pay">
                            The monthly cost figure includes owner pay
                        </label>
                    </div>
                    <label className="sm:col-span-2">
                        What the monthly cost figure covers
                        <input
                            className={field}
                            value={form.data.reserve_basis}
                            onChange={(e) =>
                                form.setData('reserve_basis', e.target.value)
                            }
                            placeholder="Monthly fixed costs including owner pay, excluding delivery materials"
                        />
                    </label>
                </div>
            </fieldset>
            <fieldset className="rounded border p-4">
                <legend className="font-semibold">Reviewed actuals</legend>
                <p className="my-2 text-sm">
                    Record every reporting month that has closed. A month with
                    no trading is recorded as evidenced pre-trading, not left
                    out.
                </p>
                {rows.map((row, index) => (
                    <div
                        className="mt-3 grid gap-3 rounded border p-3 sm:grid-cols-2"
                        key={index}
                    >
                        <label>
                            Month
                            <input
                                required
                                type="month"
                                className={field}
                                value={row.period}
                                onChange={(e) =>
                                    setRow(index, { period: e.target.value })
                                }
                            />
                        </label>
                        <label>
                            Month state
                            <select
                                className={field}
                                value={row.state}
                                onChange={(e) =>
                                    setRow(index, { state: e.target.value })
                                }
                            >
                                <option value="trading">Trading</option>
                                <option value="pre_trading">
                                    Evidenced pre-trading
                                </option>
                            </select>
                        </label>
                        <label>
                            Closing bank cash (NZD)
                            <input
                                className={field}
                                inputMode="decimal"
                                value={row.closing_bank_cash}
                                onChange={(e) =>
                                    setRow(index, {
                                        closing_bank_cash: e.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            Reviewed on
                            <input
                                type="date"
                                className={field}
                                value={row.reviewed_on}
                                onChange={(e) =>
                                    setRow(index, {
                                        reviewed_on: e.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            Reviewed by
                            <input
                                className={field}
                                value={row.reviewed_by}
                                onChange={(e) =>
                                    setRow(index, {
                                        reviewed_by: e.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            Pre-trading evidence
                            <input
                                className={field}
                                value={row.pre_trading_evidence}
                                onChange={(e) =>
                                    setRow(index, {
                                        pre_trading_evidence: e.target.value,
                                    })
                                }
                                placeholder="Required when the month is pre-trading"
                            />
                        </label>
                        <div className="sm:col-span-2">
                            <input
                                id={`actual-reconciled-${index}`}
                                name={`actual_reconciled_${index}`}
                                type="checkbox"
                                className="mr-2"
                                checked={row.reconciled}
                                onChange={(e) =>
                                    setRow(index, {
                                        reconciled: e.target.checked,
                                    })
                                }
                            />
                            <label htmlFor={`actual-reconciled-${index}`}>
                                Reconciled against the named record
                            </label>
                        </div>
                        <button
                            type="button"
                            className="rounded border p-2 sm:col-span-2"
                            onClick={() =>
                                form.setData(
                                    'actuals',
                                    rows.filter((_, i) => i !== index),
                                )
                            }
                        >
                            Remove {row.period || 'this month'}
                        </button>
                    </div>
                ))}
                <button
                    type="button"
                    className="mt-3 rounded border p-2"
                    onClick={() =>
                        form.setData('actuals', [
                            ...rows,
                            {
                                period: '',
                                state: 'trading',
                                reconciled: false,
                                closing_bank_cash: '',
                                reviewed_by: '',
                                reviewed_on: '',
                                pre_trading_evidence: '',
                            },
                        ])
                    }
                >
                    Add a reviewed month
                </button>
            </fieldset>
            {Object.values(form.errors).map((error) => (
                <p role="alert" key={error}>
                    {error}
                </p>
            ))}
            <button disabled={form.processing} className="rounded border p-2">
                Save reconstruction
            </button>
        </form>
    );
}
