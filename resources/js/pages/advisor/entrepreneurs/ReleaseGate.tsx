import { Head, Link, useForm } from '@inertiajs/react';
import { ReleaseClaimForm, ReleasePolicyForm } from './release-gate-forms';
import type { GateProps } from './release-gate-types';

function RiskForm({
    url,
    run,
    rule,
    check,
}: {
    url: string;
    run: string;
    rule: string;
    check: string;
}) {
    const form = useForm({
        run_id: run,
        rule_id: rule,
        subcheck_id: check,
        reason: '',
    });

    return (
        <form
            className="mt-3 space-y-2"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(url, { preserveScroll: true });
            }}
        >
            <label className="block">
                Principal Advisor risk decision
                <textarea
                    required
                    minLength={15}
                    maxLength={2000}
                    className="mt-1 block w-full rounded border p-2"
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    placeholder="Explain why this business risk is acceptable and the action or monitoring agreed."
                />
            </label>
            <p className="text-sm">
                The finding stays on record. This decision does not approve
                external issue or waive integrity checks.
            </p>
            {Object.values(form.errors).map((error) => (
                <p key={error} role="alert">
                    {error}
                </p>
            ))}
            <button
                disabled={form.processing}
                className="rounded border px-3 py-2"
            >
                Record risk acceptance
            </button>
        </form>
    );
}

export default function ReleaseGateReview(props: GateProps) {
    const { report, configuration, urls } = props;

    return (
        <main className="mx-auto max-w-5xl space-y-6 p-6">
            <Head title="Plan release review" />
            <Link className="underline" href={urls.workspace}>
                Back to plan and budget
            </Link>
            <h1 className="text-2xl font-semibold">Plan release review</h1>
            <p className="rounded border border-amber-500 bg-amber-50 p-4 text-amber-950">
                <strong>Draft - not approved for external issue.</strong>{' '}
                Working downloads remain available. Resolve the findings below,
                then complete the remaining evidence and final-file checks.
            </p>
            <nav className="flex gap-4" aria-label="Document checks">
                {['plan', 'budget', 'brief'].map((kind) => (
                    <Link
                        key={kind}
                        className="underline"
                        href={`${urls.review}?document=${kind}`}
                        aria-current={
                            report.document === kind ? 'page' : undefined
                        }
                    >
                        {kind === 'plan'
                            ? 'Business plan'
                            : kind === 'budget'
                              ? 'Budget Pack'
                              : 'Founder brief'}
                    </Link>
                ))}
            </nav>
            {['block', 'review', 'autofix', 'info'].map((severity) => {
                const findings = report.findings.filter(
                    (f) => f.severity === severity,
                );

                return findings.length ? (
                    <section key={severity} className="space-y-3">
                        <h2 className="text-xl font-semibold">
                            {severity === 'block'
                                ? 'Must resolve'
                                : severity === 'review'
                                  ? 'Advisor review'
                                  : severity === 'autofix'
                                    ? 'Document corrections'
                                    : 'Information'}
                        </h2>
                        {findings.map((finding) => {
                            const rule = report.results.find(
                                (r) => r.rule.id === finding.rule_id,
                            )?.rule;
                            const check = finding.subcheck;
                            const eligible =
                                check.severity === 'review' &&
                                (severity !== 'block' ||
                                    rule?.override_policy ===
                                        'principal_advisor');

                            return (
                                <article
                                    className="rounded border p-4"
                                    key={`${finding.rule_id}:${check.id}`}
                                >
                                    <h3 className="font-semibold">
                                        {finding.rule_id} · {rule?.name} ·{' '}
                                        {check.result === 'cannot_verify'
                                            ? 'Cannot verify'
                                            : 'Fail'}
                                    </h3>
                                    <p>{check.message}</p>
                                    {configuration.messages[finding.rule_id] ? (
                                        <p className="mt-2">
                                            Advisor guidance:{' '}
                                            {
                                                configuration.messages[
                                                    finding.rule_id
                                                ]
                                            }
                                        </p>
                                    ) : null}
                                    {check.missing_inputs.length ? (
                                        <p className="mt-2">
                                            Needed:{' '}
                                            {check.missing_inputs.join('; ')}
                                        </p>
                                    ) : null}
                                    {check.compared.length ? (
                                        <div className="mt-3 overflow-x-auto">
                                            <table className="w-full text-left text-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Source value</th>
                                                        <th>
                                                            Period and basis
                                                        </th>
                                                        <th>Source</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {check.compared.map(
                                                        (fact, index) => (
                                                            <tr
                                                                className="border-t"
                                                                key={index}
                                                            >
                                                                <td className="py-2">
                                                                    {
                                                                        fact.entity
                                                                    }
                                                                    :{' '}
                                                                    {fact.value ===
                                                                    null
                                                                        ? 'Not supplied'
                                                                        : String(
                                                                              fact.value,
                                                                          )}{' '}
                                                                    {fact.unit}
                                                                </td>
                                                                <td>
                                                                    {
                                                                        fact.period
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        fact.scenario
                                                                    }
                                                                    <br />
                                                                    {fact.basis ??
                                                                        'Basis not confirmed'}{' '}
                                                                    {fact.as_of}
                                                                </td>
                                                                <td>
                                                                    {fact.sources
                                                                        .map(
                                                                            (
                                                                                source,
                                                                            ) =>
                                                                                source.location,
                                                                        )
                                                                        .join(
                                                                            '; ',
                                                                        ) ||
                                                                        'Source not supplied'}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : null}
                                    {finding.accepted ? (
                                        <p className="mt-2 font-medium">
                                            Risk accepted for these inputs and
                                            policy. Original result retained.
                                        </p>
                                    ) : props.can_accept && eligible ? (
                                        <RiskForm
                                            url={urls.decision}
                                            run={props.run_id}
                                            rule={finding.rule_id}
                                            check={check.id}
                                        />
                                    ) : null}
                                </article>
                            );
                        })}
                    </section>
                ) : null;
            })}
            <details className="rounded border p-4">
                <summary>All checks, including passing subchecks</summary>
                {report.results
                    .filter((r) => r.enabled)
                    .map((r) => (
                        <section className="my-3" key={r.rule.id}>
                            <h3 className="font-semibold">
                                {r.rule.id} · {r.rule.name}:{' '}
                                {r.result?.replaceAll('_', ' ')}
                            </h3>
                            <ul className="list-disc pl-5">
                                {r.subchecks.map((check) => (
                                    <li key={check.id}>
                                        {check.result.replaceAll('_', ' ')}:{' '}
                                        {check.message} {check.scope_reason}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
            </details>
            <details className="rounded border p-4">
                <summary>
                    Checks still to be implemented ({report.deferred.length})
                </summary>
                <p>
                    These are deferred requirements. They have not passed and
                    are not a reason to skip verification.
                </p>
                <p>{report.deferred.join(', ')}</p>
            </details>
            {props.can_confirm ? (
                <ReleaseClaimForm sections={props.sections} url={urls.claim} />
            ) : null}
            {props.can_configure ? (
                <ReleasePolicyForm
                    configuration={configuration}
                    hash={report.configuration_hash}
                    url={urls.policy}
                    rules={report.results.map((r) => r.rule)}
                />
            ) : null}
        </main>
    );
}
