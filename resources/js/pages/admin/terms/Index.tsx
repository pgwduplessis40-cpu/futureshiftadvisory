import { Head, Link, router } from '@inertiajs/react';
import { Eye, FilePlus, Pencil, Send } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { TermsVersion } from './types';

type Props = {
    versions: TermsVersion[];
};

export default function TermsIndex({ versions }: Props) {
    return (
        <>
            <Head title="Terms versions" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Terms versions</h1>
                    <Button
                        size="sm"
                        type="button"
                        onClick={() => router.post('/admin/terms')}
                    >
                        <FilePlus className="size-4" aria-hidden="true" />
                        Draft
                    </Button>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Version
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Material
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Clauses
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {versions.map((version) => (
                                <tr key={version.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">
                                        {version.version}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                version.published_at
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {version.published_at
                                                ? 'published'
                                                : 'draft'}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        {version.material ? 'yes' : 'no'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {version.clauses_count ?? 0}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={`/admin/terms/${version.id}/preview`}
                                                    aria-label={`Preview version ${version.version}`}
                                                >
                                                    <Eye
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            </Button>
                                            {!version.published_at && (
                                                <>
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={`/admin/terms/${version.id}/edit`}
                                                            aria-label={`Edit version ${version.version}`}
                                                        >
                                                            <Pencil
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={`/admin/terms/${version.id}/publish`}
                                                            aria-label={`Publish version ${version.version}`}
                                                        >
                                                            <Send
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Link>
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
