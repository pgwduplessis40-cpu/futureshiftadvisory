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
    entry: KnowledgeEntryDetail & {
        client_id: string | null;
        tags_string: string;
    };
    categories: CategoryOption[];
    clients: ClientOption[];
    updateUrl: string;
    showUrl: string;
    indexUrl: string;
};

export default function KnowledgeEdit({
    entry,
    categories,
    clients,
    updateUrl,
    showUrl,
}: Props) {
    return (
        <>
            <Head title={`Edit ${entry.title}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <BookOpen className="size-4" aria-hidden="true" />
                            Knowledge
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Edit entry
                        </h1>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href={showUrl}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Entry
                        </Link>
                    </Button>
                </div>

                <KnowledgeForm
                    entry={entry}
                    categories={categories}
                    clients={clients}
                    submitUrl={updateUrl}
                    method="patch"
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

KnowledgeEdit.layout = {
    breadcrumbs: [
        {
            title: 'Knowledge',
            href: '/advisor/knowledge',
        },
    ],
};
