import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, LockKeyhole } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    advisory: { available: boolean; label: string; url: string };
    assessmentStatus: string;
    requestUrl: string;
    workspaceUrl: string;
};

export default function AdvisoryServices({
    advisory,
    assessmentStatus,
    requestUrl,
    workspaceUrl,
}: Props) {
    return (
        <>
            <Head title="Advisory services" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 className="text-xl font-semibold">Advisory services</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Tailored support that follows a completed and
                        advisor-approved Business Plan &amp; Budget.
                    </p>
                </div>

                <section className="rounded-md border bg-background p-5">
                    <div className="flex gap-3">
                        {advisory.available ? (
                            <CheckCircle2 className="mt-0.5 size-5 text-emerald-600" />
                        ) : (
                            <LockKeyhole className="mt-0.5 size-5 text-muted-foreground" />
                        )}
                        <div>
                            <h2 className="font-medium">
                                {advisory.available
                                    ? 'Advisory services are ready to request'
                                    : 'Available after BP&B approval'}
                            </h2>
                            {advisory.available ? (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Your Business Plan &amp; Budget has met the
                                    readiness requirements and your advisor has
                                    approved the assessment. Request advisory
                                    support and we will prepare a tailored
                                    proposal for your review and approval before
                                    any advisory work begins.
                                </p>
                            ) : (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    You can request access once your Business
                                    Plan &amp; Budget meets the minimum
                                    requirements and your advisor approves the
                                    assessment. Advisory services will then
                                    provide a tailored proposal for your review
                                    and approval.
                                </p>
                            )}
                            <p className="mt-3 text-xs text-muted-foreground">
                                Assessment status: {assessmentStatus}
                            </p>

                            {advisory.available ? (
                                <Button
                                    className="mt-4"
                                    size="sm"
                                    type="button"
                                    onClick={() => router.post(requestUrl)}
                                >
                                    Request advisory support
                                    <ArrowRight className="size-4" />
                                </Button>
                            ) : (
                                <Button asChild className="mt-4" size="sm">
                                    <Link href={workspaceUrl}>
                                        Continue Business Plan &amp; Budget
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
