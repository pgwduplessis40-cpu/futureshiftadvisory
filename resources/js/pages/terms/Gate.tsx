import { Head, router, useForm } from '@inertiajs/react';
import { Check, FileText, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { TermsVersion } from './types';

type Props = {
    version: TermsVersion;
    acceptUrl: string;
    declineUrl: string;
    hasDeclined: boolean;
};

export default function TermsGate({
    version,
    acceptUrl,
    declineUrl,
    hasDeclined,
}: Props) {
    const [hasReachedEnd, setHasReachedEnd] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);
    const form = useForm<{ scroll_end_confirmed: boolean }>({
        scroll_end_confirmed: false,
    });

    useEffect(() => {
        const confirmScrollEnd = () => {
            setHasReachedEnd(true);
            form.setData('scroll_end_confirmed', true);
        };

        window.addEventListener('scroll-end', confirmScrollEnd);

        return () => window.removeEventListener('scroll-end', confirmScrollEnd);
    }, [form]);

    const handleScroll = () => {
        const element = scrollRef.current;

        if (!element || hasReachedEnd) {
            return;
        }

        const reachedEnd =
            element.scrollTop + element.clientHeight >=
            element.scrollHeight - 8;

        if (reachedEnd) {
            window.dispatchEvent(
                new CustomEvent('scroll-end', {
                    detail: { termsVersionId: version.id },
                }),
            );
        }
    };

    const accept = () => {
        if (!hasReachedEnd) {
            return;
        }

        form.post(acceptUrl, { preserveScroll: true });
    };

    const decline = () => {
        router.post(declineUrl);
    };

    return (
        <>
            <Head title="Terms and conditions" />

            <div className="mx-auto flex h-[calc(100vh-5rem)] w-full max-w-5xl flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <FileText className="size-5" aria-hidden="true" />
                            <h1 className="text-xl font-semibold">
                                {version.title}
                            </h1>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                Version {version.version}
                            </Badge>
                            {hasDeclined && (
                                <Badge variant="outline">
                                    account suspended
                                </Badge>
                            )}
                        </div>
                    </div>
                </header>

                <div
                    ref={scrollRef}
                    onScroll={handleScroll}
                    className="min-h-0 flex-1 overflow-y-auto rounded-md border bg-background p-5 shadow-xs"
                    data-scroll-end-reached={hasReachedEnd}
                >
                    <article className="space-y-6">
                        {version.clauses.map((clause) => (
                            <section key={clause.id} className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-base font-semibold">
                                        Clause {clause.clause_number}:{' '}
                                        {clause.title}
                                    </h2>
                                    {clause.material && (
                                        <Badge variant="outline">
                                            material
                                        </Badge>
                                    )}
                                </div>
                                <div className="text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                    {clause.body}
                                </div>
                            </section>
                        ))}
                    </article>
                </div>

                <footer className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    <p className="text-sm text-muted-foreground">
                        {hasReachedEnd
                            ? 'End of terms reached.'
                            : 'End of terms not reached.'}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={decline}
                            disabled={form.processing}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Decline
                        </Button>
                        <Button
                            type="button"
                            onClick={accept}
                            disabled={!hasReachedEnd || form.processing}
                            data-testid="terms-accept-button"
                        >
                            <Check className="size-4" aria-hidden="true" />
                            Accept
                        </Button>
                    </div>
                </footer>
            </div>
        </>
    );
}

TermsGate.layout = {
    breadcrumbs: [
        {
            title: 'Terms',
            href: '/terms/pending',
        },
    ],
};
