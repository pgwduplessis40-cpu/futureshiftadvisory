import { Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';

export default function TermsPending() {
    return (
        <>
            <Head title="Terms pending" />

            <div className="flex items-center gap-3">
                <FileText className="size-6" aria-hidden="true" />
                <div>
                    <h1 className="text-lg font-semibold">
                        Terms acceptance pending
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        The terms acceptance gate is scheduled for WO-11.
                    </p>
                </div>
            </div>
        </>
    );
}

TermsPending.layout = {
    title: 'Terms pending',
    description: 'Account security is complete',
};
