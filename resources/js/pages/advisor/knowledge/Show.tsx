import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Pencil, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { KnowledgeEntryDetail } from './types';

type Props = {
    entry: KnowledgeEntryDetail;
    canEdit: boolean;
    indexUrl: string;
};

export default function KnowledgeShow({ entry, canEdit, indexUrl }: Props) {
    const deleteForm = useForm({});

    const destroy = () => {
        if (!window.confirm('Delete this knowledge entry?')) {
            return;
        }

        deleteForm.delete(entry.delete_url);
    };

    return (
        <>
            <Head title={entry.title} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <BookOpen className="size-4" aria-hidden="true" />
                            Knowledge
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            {entry.title}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canEdit && (
                            <Button asChild size="sm" variant="outline">
                                <Link href={entry.edit_url}>
                                    <Pencil
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {canEdit && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={deleteForm.processing}
                                onClick={destroy}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Delete
                            </Button>
                        )}
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

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {entry.category_label}
                        </Badge>
                        {entry.client && (
                            <Badge variant="outline">
                                {entry.client.legal_name}
                            </Badge>
                        )}
                        {entry.author_name && (
                            <Badge variant="outline">{entry.author_name}</Badge>
                        )}
                    </div>

                    <div className="prose prose-sm max-w-none text-sm leading-6 whitespace-pre-wrap">
                        {entry.body}
                    </div>

                    {entry.tags.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {entry.tags.map((tag) => (
                                <Badge key={tag} variant="outline">
                                    {tag}
                                </Badge>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

KnowledgeShow.layout = {
    breadcrumbs: [
        {
            title: 'Knowledge',
            href: '/advisor/knowledge',
        },
    ],
};
