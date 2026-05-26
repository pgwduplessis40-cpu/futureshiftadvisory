import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Sparkles, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { KnowledgeForm } from './Form';
import type {
    CategoryOption,
    ClientOption,
    KnowledgeDraftDetail,
} from './types';

type Props = {
    draft: KnowledgeDraftDetail;
    categories: CategoryOption[];
    clients: ClientOption[];
    acceptUrl: string;
    discardUrl: string;
    indexUrl: string;
};

export default function KnowledgeDraftReview({
    draft,
    categories,
    clients,
    acceptUrl,
    discardUrl,
    indexUrl,
}: Props) {
    const discardForm = useForm({});

    const discard = () => {
        if (!window.confirm('Discard this knowledge draft?')) {
            return;
        }

        discardForm.patch(discardUrl, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`Review ${draft.title}`} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Sparkles className="size-4" aria-hidden="true" />
                            AI draft
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Review draft
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={discardForm.processing}
                            onClick={discard}
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                            Discard
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={indexUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <BookOpen className="size-4" aria-hidden="true" />
                        <Badge variant="secondary">
                            {draft.category_label}
                        </Badge>
                        {draft.client && (
                            <Badge variant="outline">
                                {draft.client.legal_name}
                            </Badge>
                        )}
                        {draft.source_reference && (
                            <Badge variant="outline">
                                {draft.source_reference}
                            </Badge>
                        )}
                    </div>
                </section>

                <KnowledgeForm
                    entry={draft}
                    categories={categories}
                    clients={clients}
                    submitUrl={acceptUrl}
                    method="patch"
                    submitLabel="Accept draft"
                />
            </div>
        </>
    );
}

KnowledgeDraftReview.layout = {
    breadcrumbs: [
        {
            title: 'Knowledge',
            href: '/advisor/knowledge',
        },
        {
            title: 'Draft',
            href: '/advisor/knowledge',
        },
    ],
};
