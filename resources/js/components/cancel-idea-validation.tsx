import { Form } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Details = {
    eligible: boolean;
    reason: string | null;
    refund_amount: string | null;
    currency: string | null;
    submitted_at: string | null;
};

export default function CancelIdeaValidation({
    details,
}: {
    details?: Details | null;
}) {
    if (!details) {
        return null;
    }

    if (!details.eligible) {
        return details.reason === 'submitted' ? (
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Idea Validation cancellation"
                    description="Cancellation and refund are available only before Idea Validation is submitted for advisor review."
                />
                <div className="rounded-lg border border-muted bg-muted/30 p-4 text-sm text-muted-foreground">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium text-foreground">
                            Cancellation is unavailable
                        </p>
                        <Badge variant="secondary">Submitted</Badge>
                    </div>
                    <p className="mt-2 max-w-2xl">
                        Your Idea Validation has been submitted for advisor
                        review. The cancellation and refund option is no longer
                        available. If you need help with the service, please
                        contact Future Shift Advisory.
                    </p>
                </div>
            </div>
        ) : null;
    }

    const amount = `${details.currency ?? 'NZD'} ${Number(
        details.refund_amount ?? 0,
    ).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Idea Validation cancellation"
                description="Cancel before submitting your Idea Validation for advisor review."
            />
            <div className="space-y-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-200/10 dark:bg-amber-700/10">
                <div className="space-y-2 text-amber-900 dark:text-amber-100">
                    <p className="font-medium">
                        Cancel Idea Validation, receive a refund &amp;
                        deactivate account
                    </p>
                    <p className="max-w-2xl text-sm">
                        You are eligible for a full refund of {amount},
                        including GST, to your original payment method.
                        Cancelling will deactivate your Future Shift Advisory
                        account immediately. You will no longer be able to sign
                        in, access saved information, submit your idea, or
                        receive advisor feedback.
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="outline"
                            data-test="cancel-idea-validation-button"
                        >
                            Cancel, refund &amp; deactivate
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Cancel Idea Validation and deactivate your account?
                        </DialogTitle>
                        <DialogDescription>
                            A full refund of {amount}, including GST, will be
                            initiated to the original payment method. Your
                            Future Shift Advisory account will be deactivated
                            and this action cannot be undone.
                        </DialogDescription>

                        <Form
                            action={ProfileController.cancelIdeaValidation.url()}
                            method="post"
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <label className="flex gap-3 rounded-md border bg-background p-3 text-sm">
                                        <input
                                            type="checkbox"
                                            name="confirm_cancellation"
                                            value="yes"
                                            className="mt-0.5 size-4"
                                        />
                                        <span>
                                            I understand that cancelling refunds
                                            my Idea Validation and deactivates
                                            my account immediately.
                                        </span>
                                    </label>
                                    <InputError
                                        message={errors.confirm_cancellation}
                                    />

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Keep account active
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            disabled={processing}
                                            variant="destructive"
                                            type="submit"
                                            data-test="confirm-cancel-idea-validation-button"
                                        >
                                            Cancel, refund &amp; deactivate
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
