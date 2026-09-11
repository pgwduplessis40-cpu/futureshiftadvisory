import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, LockKeyhole } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatNzdCurrency } from '@/lib/formatters';

type Offer = {
    available: boolean;
    label: string;
    amount_ex_gst: number | null;
    currency: string | null;
};

export default function PlanBudgetAccess({
    hasPlanBudgetAccess,
    ideaValidationApproved,
    offer,
    workspaceUrl,
}: {
    hasPlanBudgetAccess: boolean;
    ideaValidationApproved: boolean;
    offer: Offer;
    workspaceUrl: string;
}) {
    const price =
        offer.available && offer.amount_ex_gst !== null
            ? `${formatNzdCurrency(offer.amount_ex_gst)} + GST`
            : null;

    return (
        <>
            <Head title="Business Plan & Budget" />
            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 className="text-xl font-semibold">
                        Business Plan &amp; Budget
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        A separate service for turning an approved idea into a
                        practical plan and budget.
                    </p>
                </div>

                {hasPlanBudgetAccess ? (
                    <section className="rounded-md border bg-background p-5">
                        <div className="flex gap-3">
                            <CheckCircle2 className="mt-0.5 size-5 text-emerald-600" />
                            <div>
                                <h2 className="font-medium">
                                    Your workspace is ready
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    You have access to Business Plan &amp;
                                    Budget.
                                </p>
                                <Button asChild className="mt-4" size="sm">
                                    <Link href={workspaceUrl}>
                                        Open Business Plan &amp; Budget
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </section>
                ) : !ideaValidationApproved ? (
                    <section className="rounded-md border bg-background p-5">
                        <div className="flex gap-3">
                            <LockKeyhole className="mt-0.5 size-5 text-muted-foreground" />
                            <div>
                                <h2 className="font-medium">
                                    Available after approval
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Once your advisor validates your idea, you
                                    will be able to purchase Business Plan &amp;
                                    Budget here.
                                </p>
                                <Button
                                    asChild
                                    className="mt-4"
                                    size="sm"
                                    variant="outline"
                                >
                                    <Link href={workspaceUrl}>
                                        Return to Idea Validation
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </section>
                ) : (
                    <section className="rounded-md border bg-background p-5">
                        <div className="flex gap-3">
                            <CheckCircle2 className="mt-0.5 size-5 text-emerald-600" />
                            <div>
                                <h2 className="font-medium">
                                    Your idea has been validated
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Business Plan &amp; Budget is ready for your
                                    next step{price ? ` — ${price}.` : '.'}
                                </p>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Secure self-service checkout is the next
                                    release step; an advisor remains assigned to
                                    you once it is purchased.
                                </p>
                            </div>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
