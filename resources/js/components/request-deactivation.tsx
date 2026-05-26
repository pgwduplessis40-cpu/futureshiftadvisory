import { Form, usePage } from '@inertiajs/react';
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
import { Label } from '@/components/ui/label';
import type { Auth } from '@/types';

type Props = {
    requestedAt?: string | null;
};

export default function RequestDeactivation({ requestedAt }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const alreadyRequested = Boolean(requestedAt);
    const accountLabel = accountTypeLabel(auth.user.user_type);

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Account deactivation"
                description={`Request assisted deactivation of your ${accountLabel} account`}
            />
            <div className="space-y-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-200/10 dark:bg-amber-700/10">
                <div className="relative space-y-2 text-amber-900 dark:text-amber-100">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium">
                            {alreadyRequested
                                ? 'Deactivation request submitted'
                                : 'Request deactivation'}
                        </p>
                        {alreadyRequested ? (
                            <Badge variant="secondary">
                                Pending advisor review
                            </Badge>
                        ) : null}
                    </div>
                    <p className="max-w-2xl text-sm">
                        {alreadyRequested
                            ? 'Your account remains active while Future Shift Advisory reviews the request.'
                            : 'This does not delete your account. It sends a request for your advisor to review and preserves your records until the request is actioned.'}
                    </p>
                    {requestedAt ? (
                        <p className="text-xs">
                            Requested {formatDate(requestedAt)}
                        </p>
                    ) : null}
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="outline"
                            disabled={alreadyRequested}
                            data-test="request-deactivation-button"
                        >
                            Request deactivation
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Confirm deactivation request</DialogTitle>
                        <DialogDescription>
                            Your account will not be deleted immediately. Future
                            Shift Advisory will review the request, preserve the
                            audit trail, and handle any deactivation steps.
                        </DialogDescription>

                        <Form
                            {...ProfileController.requestDeactivation.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="reason">Reason</Label>
                                        <textarea
                                            id="reason"
                                            name="reason"
                                            placeholder="Optional note for your advisor"
                                            rows={4}
                                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        <InputError message={errors.reason} />
                                    </div>

                                    <div className="grid gap-2">
                                        <label className="flex gap-3 rounded-md border bg-background p-3 text-sm">
                                            <input
                                                type="checkbox"
                                                name="confirm_deactivation"
                                                value="yes"
                                                className="mt-0.5 size-4"
                                            />
                                            <span>
                                                I confirm I want Future Shift
                                                Advisory to review deactivation
                                                of my {accountLabel} account.
                                            </span>
                                        </label>
                                        <InputError
                                            message={
                                                errors.confirm_deactivation
                                            }
                                        />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button disabled={processing} asChild>
                                            <button
                                                type="submit"
                                                data-test="confirm-request-deactivation-button"
                                            >
                                                Submit request
                                            </button>
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

function accountTypeLabel(value?: string | null): string {
    if (!value) {
        return 'user';
    }

    return value
        .split('_')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ')
        .toLowerCase();
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
