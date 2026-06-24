import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Pencil, Send } from 'lucide-react';
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

                    <div className="flex gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href="/admin/terms">
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                        {version.source_download_url && (
                            <Button asChild size="sm" variant="outline">
                                <a href={version.source_download_url}>
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Source
                                </a>
                            </Button>
                        )}
                        <Button asChild size="sm" variant="outline">
                            <a href={`/admin/terms/${version.id}/download`}>
                                <Download
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Download PDF
                            </a>
                        </Button>
                        {!version.published_at && (
                            <>
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={`/admin/terms/${version.id}/edit`}
                                    >
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
                            </>
                        )}
                    </div>
                </div>

                {version.source_preview_html ? (
                    <article
                        className="rounded-md border bg-background p-6 shadow-xs"
                        dangerouslySetInnerHTML={{
                            __html: version.source_preview_html,
                        }}
                    />
                ) : (
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
                        {version.clauses.length === 0 && (
                            <div className="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                No clauses or DOCX preview content is available
                                for this terms version.
                            </div>
                        )}
                    </article>
                )}
            </div>
        </>
    );
}
