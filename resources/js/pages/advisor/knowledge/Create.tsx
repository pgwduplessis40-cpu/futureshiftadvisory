import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { KnowledgeForm } from './Form';
import type {
    CategoryOption,
    ClientOption,
    KnowledgeEntryDetail,
} from './types';

type Props = {
    entry: Pick<
        KnowledgeEntryDetail,
        'category' | 'title' | 'body' | 'tags_string'
    > & {
        client_id: string | null;
    };
    categories: CategoryOption[];
    clients: ClientOption[];
    storeUrl: string;
    indexUrl: string;
};

export default function KnowledgeCreate({
    entry,
    categories,
    clients,
    storeUrl,
    indexUrl,
}: Props) {
    return (
        <>
            <Head title="New knowledge entry" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <BookOpen className="size-4" aria-hidden="true" />
                            Knowledge
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            New entry
                        </h1>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href={indexUrl}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </div>

                <KnowledgeForm
                    entry={entry}
                    categories={categories}
                    clients={clients}
                    submitUrl={storeUrl}
                    method="post"
                    submitLabel="Save entry"
                />
            </div>
        </>
    );
}

KnowledgeCreate.layout = {
    breadcrumbs: [
        {
            title: 'Knowledge',
            href: '/advisor/knowledge',
        },
        {
            title: 'New',
            href: '/advisor/knowledge/create',
        },
    ],
};
