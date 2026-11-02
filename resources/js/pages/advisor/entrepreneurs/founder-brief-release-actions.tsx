import { router } from '@inertiajs/react';
import { Download, FileText, LoaderCircle, RefreshCw } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
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
            className="basis-full space-y-3"
            aria-label="Founder-ready brief release"
        >
            <p className="text-sm font-medium">Founder brief with financials</p>
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
            <p className="text-sm text-muted-foreground">
                8–12 pages including financials.{' '}
                {brief.active
                    ? 'Approved for external issue.'
                    : 'Advisor draft available now, including while the summary is pending. Review the PDF before approval; downloading it does not approve external issue.'}
            </p>
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
                    <p className="text-sm font-medium">
                        Founder-ready brief — action needed
                    </p>
                    <p className="text-sm text-muted-foreground">
                        This brief is for external issue. Its release checks are
                        separate from client progression.
                    </p>
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
                                {brief.summary_recovery.message} You can review
                                the draft PDF now. Once the summary is ready,
                                review the updated PDF and approve release here.
                            </p>
                        </div>
                    ) : null}
                    <ul className="max-w-3xl list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        {brief.reasons.map((reason) => (
                            <li key={reason}>{reason}</li>
                        ))}
                    </ul>
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
