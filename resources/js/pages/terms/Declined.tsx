import { Head, Link } from '@inertiajs/react';
import { FileWarning, RotateCcw } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type Props = {
    reviewUrl: string;
    isSuspended: boolean;
};

export default function TermsDeclined({ reviewUrl, isSuspended }: Props) {
    return (
        <>
            <Head title="Terms declined" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                <Alert variant="destructive">
                    <FileWarning className="size-4" aria-hidden="true" />
                    <AlertTitle>Terms declined</AlertTitle>
                    <AlertDescription>
                        {isSuspended
                            ? 'Your account is suspended because the platform terms were declined.'
                            : 'The account is not currently suspended for terms decline.'}
                    </AlertDescription>
                </Alert>

                <div className="space-y-3">
                    <h1 className="text-xl font-semibold">
                        Review and accept to continue
                    </h1>
                    <p className="text-sm leading-6 text-muted-foreground">
                        Your account data remains preserved. You can return to
                        the terms, review the full document, and accept it to
                        restore access.
                    </p>
                    <Button asChild>
                        <Link href={reviewUrl}>
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Review terms
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

TermsDeclined.layout = {
    breadcrumbs: [
        {
            title: 'Terms declined',
            href: '/terms/declined',
        },
    ],
};
