import { router } from '@inertiajs/react';
import { Download, FileText, LoaderCircle, RefreshCw } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { BriefWorkflow } from './founder-brief-workflow';
import type { EntrepreneurDetail } from './types';

type Brief = NonNullable<EntrepreneurDetail['latest_plan']>['lender_brief'];

export function FounderReadyBriefAction({
    lenderBrief,
}: {
    lenderBrief: Brief | undefined;
}) {
    return lenderBrief ? <BriefReleaseActions brief={lenderBrief} /> : null;
}

function BriefReleaseActions({ brief }: { brief: Brief }) {
    const [note, setNote] = useState('');
    const [acknowledgedToken, setAcknowledgedToken] = useState<string | null>(
        null,
    );
    const acknowledged = acknowledgedToken === brief.approval_token;
    const [busy, setBusy] = useState<'retry' | 'approve' | null>(null);
    const [error, setError] = useState<string | null>(null);
    const submitting = useRef(false);
    const noteId = useId();
    const inFlight = ['queued', 'running', 'retrying'].includes(
        brief.summary_recovery.state,
    );

    useEffect(() => {
        if (!inFlight) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['entrepreneur'] }),
            4000,
        );

        return () => window.clearInterval(timer);
    }, [inFlight]);

    const submit = (action: 'retry' | 'approve') => {
        if (submitting.current) {
            return;
        }

        submitting.current = true;
        setBusy(action);
        setError(null);
        router.post(
            action === 'retry' ? brief.retry_url : brief.approve_url,
            action === 'retry'
                ? {}
                : {
                      note: note.trim(),
                      acknowledged,
                      approval_token: brief.approval_token,
                  },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        Object.values(errors).join(' ') ||
                            'The action could not be completed. Please try again.',
                    ),
                onFinish: () => {
                    submitting.current = false;
                    setBusy(null);
                },
            },
        );
    };

    return (
        <section
            className="space-y-4 rounded-md border bg-background p-5"
            aria-label="Founder-ready brief release"
        >
            <h2 className="text-lg font-semibold">
                Founder brief for investors and financiers
            </h2>
            <BriefWorkflow brief={brief} />
            {brief.release_gate_url ? (
                <a className="text-sm underline" href={brief.release_gate_url}>
                    Review release checks and source values
                </a>
            ) : null}
            {brief.forecast_model_url ? (
                <a
                    className="text-sm underline"
                    href={brief.forecast_model_url}
                >
                    Build the version 2 financial model
                </a>
            ) : null}
            {brief.can_generate ? (
                <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm" variant="outline">
                        <a
                            href={
                                brief.active
                                    ? brief.document_url
                                    : brief.preview_url
                            }
                            target="_blank"
                            rel="noreferrer"
                        >
                            <FileText className="size-4" aria-hidden="true" />
                            View founder brief PDF
                        </a>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <a
                            href={
                                brief.active
                                    ? brief.download_url
                                    : brief.preview_download_url
                            }
                        >
                            <Download className="size-4" aria-hidden="true" />
                            Download founder brief PDF
                        </a>
                    </Button>
                </div>
            ) : null}
            {brief.active ? (
                <>
                    <p role="status" className="text-sm">
                        The brief is ready for external issue.
                    </p>
                    {brief.review.approved ? (
                        <div className="rounded-md border p-3 text-sm">
                            <p className="font-medium">
                                Advisor release approved
                            </p>
                            <p className="mt-1 whitespace-pre-wrap">
                                {brief.review.note}
                            </p>
                            <details className="mt-2">
                                <summary className="cursor-pointer">
                                    Reviewed findings
                                </summary>
                                <ul className="mt-2 list-disc space-y-1 pl-5">
                                    {brief.review.findings.map((finding) => (
                                        <li key={finding}>{finding}</li>
                                    ))}
                                </ul>
                            </details>
                        </div>
                    ) : null}
                </>
            ) : (
                <>
                    {brief.summary_recovery.can_retry || inFlight ? (
                        <div className="space-y-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={
                                    !brief.summary_recovery.can_retry ||
                                    busy !== null
                                }
                                onClick={() => submit('retry')}
                            >
                                {inFlight || busy === 'retry' ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <RefreshCw
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {inFlight
                                    ? 'Generating summary…'
                                    : busy === 'retry'
                                      ? 'Requesting summary…'
                                      : 'Retry summary'}
                            </Button>
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {brief.summary_recovery.message} Once the
                                completed plan and budget have been assessed and
                                the summary is ready, the PDF will be available
                                here for review.
                            </p>
                        </div>
                    ) : null}
                    {brief.can_generate ? (
                        <details className="rounded-md border p-3 text-sm">
                            <summary className="cursor-pointer font-medium">
                                All review findings ({brief.reasons.length})
                            </summary>
                            <ul className="mt-3 max-w-3xl list-disc space-y-2 pl-5 text-muted-foreground">
                                {brief.reasons.map((reason) => (
                                    <li key={reason}>{reason}</li>
                                ))}
                            </ul>
                        </details>
                    ) : null}
                    {brief.review.can_approve ? (
                        <form
                            className="space-y-3 rounded-md border p-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                submit('approve');
                            }}
                        >
                            <p className="text-sm">
                                Review the founder brief PDF and the listed
                                findings. Record why this version can be issued.
                                Approval does not change the forecast figures or
                                the client's answers.
                            </p>
                            <label
                                htmlFor={noteId}
                                className="block text-sm font-medium"
                            >
                                Reason for approving the brief
                            </label>
                            <textarea
                                id={noteId}
                                className="min-h-24 w-full rounded-md border bg-background p-2 text-sm"
                                value={note}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                                minLength={15}
                                maxLength={2000}
                                required
                            />
                            <label
                                htmlFor={`${noteId}-acknowledged`}
                                className="flex items-start gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    id={`${noteId}-acknowledged`}
                                    name="acknowledged"
                                    className="mt-1"
                                    checked={acknowledged}
                                    onChange={(event) =>
                                        setAcknowledgedToken(
                                            event.target.checked
                                                ? brief.approval_token
                                                : null,
                                        )
                                    }
                                    required
                                />
                                I have reviewed all listed findings and approve
                                this version for external issue.
                            </label>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={
                                    busy !== null ||
                                    note.trim().length < 15 ||
                                    !acknowledged
                                }
                            >
                                {busy === 'approve'
                                    ? 'Recording approval…'
                                    : 'Approve brief for external issue'}
                            </Button>
                        </form>
                    ) : null}
                </>
            )}
            {error ? (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            ) : null}
        </section>
    );
}
