import { Head, Link } from '@inertiajs/react';
import { Pencil, Send } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { TermsVersion } from './types';

type Props = {
    version: TermsVersion;
};

export default function TermsPreview({ version }: Props) {
    return (
        <>
            <Head title={`Preview terms ${version.version}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold">
                            {version.title}
                        </h1>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                Version {version.version}
                            </Badge>
                            <Badge
                                variant={
                                    version.published_at
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {version.published_at ? 'published' : 'draft'}
                            </Badge>
                            {version.material && <Badge>material</Badge>}
                            <Badge variant="outline">
                                {version.material_clauses_count ?? 0} material
                                clauses
                            </Badge>
                        </div>
                    </div>

                    {!version.published_at && (
                        <div className="flex gap-2">
                            <Button asChild size="sm" variant="outline">
                                <Link href={`/admin/terms/${version.id}/edit`}>
                                    <Pencil
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Edit
                                </Link>
                            </Button>
                            <Button asChild size="sm">
                                <Link
                                    href={`/admin/terms/${version.id}/publish`}
                                >
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Publish
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                <article className="space-y-6">
                    {version.clauses.map((clause) => (
                        <section key={clause.id} className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-base font-semibold">
                                    Clause {clause.clause_number}:{' '}
                                    {clause.title}
                                </h2>
                                {clause.material && (
                                    <Badge variant="outline">material</Badge>
                                )}
                            </div>
                            <div className="text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                {clause.body}
                            </div>
                        </section>
                    ))}
                </article>
            </div>
        </>
    );
}
