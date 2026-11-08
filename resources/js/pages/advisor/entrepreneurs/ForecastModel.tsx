import { Head, Link } from '@inertiajs/react';
import { DriversFormSection } from './forecast-model-drivers';
import { ForecastModelForm, SelectionForm } from './forecast-model-forms';
import { TermsFormSection } from './forecast-model-terms';
import type { ForecastModelProps } from './forecast-model-types';

export default function ForecastModelReview({
    model,
    engine,
    can_edit,
    urls,
}: ForecastModelProps) {
    const reserve = model?.reserve;

    return (
        <main className="mx-auto max-w-5xl space-y-6 p-6">
            <Head title="Version 2 financial model" />
            <Link className="underline" href={urls.workspace}>
                Back to plan and budget
            </Link>
            <h1 className="text-2xl font-semibold">
                Version 2 financial model
            </h1>
            <p className="rounded border border-amber-500 bg-amber-50 p-4 text-amber-950">
                <strong>
                    Saving a reconstruction changes no release verdict.
                </strong>{' '}
                Every plan is still checked against the twenty enabled rules,
                whether or not a reconstruction is selected here.
            </p>
            {model === null ? (
                <p>
                    No reconstruction has been saved for this plan. The plan
                    uses the legacy budget.
                </p>
            ) : (
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Current reconstruction
                    </h2>
                    <p>
                        Revision {model.revision} on engine{' '}
                        {model.engine_version}.{' '}
                        {model.selected
                            ? 'Selected as the plan financial model.'
                            : 'Saved but not selected.'}
                    </p>
                    {model.load_error ? (
                        <p role="alert">
                            This reconstruction cannot be read back by the
                            engine: {model.load_error} It cannot be selected
                            until it is corrected.
                        </p>
                    ) : null}
                    <table className="w-full text-left text-sm">
                        <caption className="text-left">
                            What this reconstruction holds
                        </caption>
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>State</th>
                                <th>Edited here</th>
                            </tr>
                        </thead>
                        <tbody>
                            {model.sections.map((section) => (
                                <tr className="border-t" key={section.key}>
                                    <td className="py-2">{section.label}</td>
                                    <td>{section.detail}</td>
                                    <td>
                                        {section.editable
                                            ? 'Yes'
                                            : 'On a later screen'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {reserve ? (
                        <p>
                            {reserve.target === null
                                ? `Reserve target not calculated. Still needed: ${reserve.missing.join(', ')}.`
                                : `Reserve target ${reserve.target} NZD, from a base reserve of ${reserve.base_reserve} and contingency of ${reserve.contingency}.`}
                        </p>
                    ) : null}
                    {can_edit ? (
                        <SelectionForm model={model} url={urls.selection} />
                    ) : null}
                </section>
            )}
            {can_edit ? (
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Record the reconstruction
                    </h2>
                    <p>
                        This form captures the calendar, opening cash, reserve
                        policy and reviewed actuals. Saving here leaves every
                        other section unchanged.
                    </p>
                    {/* Keyed on the revision so a successful save remounts the
                        form against the revision it produced. Without this the
                        form would keep posting the revision it loaded with and
                        a second save in a row would be refused as a conflict. */}
                    <ForecastModelForm
                        key={model?.revision ?? 'new'}
                        model={model}
                        url={urls.save}
                    />
                </section>
            ) : null}
            {can_edit ? (
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Revenue streams and founder capacity
                    </h2>
                    {model === null ? (
                        <p>
                            A monthly schedule only means something inside a
                            horizon. Record the forecast calendar and opening
                            position above, then come back to the streams that
                            sit inside it.
                        </p>
                    ) : (
                        <DriversFormSection
                            key={model.revision}
                            model={model}
                            url={urls.drivers}
                        />
                    )}
                </section>
            ) : null}
            {can_edit ? (
                <section className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Trade terms, owner pay, assets and tax
                    </h2>
                    {model === null ? (
                        <p>
                            Depreciation and tax are both projected across a
                            horizon. Record the forecast calendar and opening
                            position above, then come back to the terms that sit
                            inside it.
                        </p>
                    ) : (
                        <TermsFormSection
                            key={model.revision}
                            model={model}
                            url={urls.terms}
                        />
                    )}
                </section>
            ) : null}
            <details className="rounded border p-4">
                <summary>
                    Engine version {engine.engine_version} and its components
                </summary>
                <ul className="list-disc pl-5">
                    {Object.entries(engine.components).map(([part, id]) => (
                        <li key={part}>
                            {part.replaceAll('_', ' ')}: {id}
                        </li>
                    ))}
                </ul>
            </details>
            <Link className="underline" href={urls.gate}>
                Go to the plan release review
            </Link>
        </main>
    );
}
