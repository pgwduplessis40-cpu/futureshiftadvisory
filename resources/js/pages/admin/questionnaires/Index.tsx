import { Head, Link, router } from '@inertiajs/react';
import { Eye, FilePlus, Pencil, Send } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { QuestionnaireSummary } from './types';

type Props = {
    questionnaires: QuestionnaireSummary[];
    sets: string[];
};

export default function QuestionnairesIndex({ questionnaires, sets }: Props) {
    const defaultSet = sets[0] ?? 'standard_advisory';

    return (
        <>
            <Head title="Questionnaires" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Questionnaires</h1>
                    <Button
                        size="sm"
                        type="button"
                        onClick={() =>
                            router.post('/admin/questionnaires', {
                                set: defaultSet,
                            })
                        }
                    >
                        <FilePlus className="size-4" aria-hidden="true" />
                        Draft
                    </Button>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Set</th>
                                <th className="px-3 py-2 font-medium">
                                    Version
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Sections
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Responses
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {questionnaires.map((questionnaire) => (
                                <tr key={questionnaire.id} className="border-t">
                                    <td className="px-3 py-2" data-label="Set">
                                        <Link
                                            href={`/admin/questionnaires/${questionnaire.id}/preview`}
                                            className="font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                        >
                                            {questionnaire.title}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {questionnaire.set}
                                        </div>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Version"
                                    >
                                        {questionnaire.version}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Status"
                                    >
                                        <Badge
                                            variant={
                                                questionnaire.published_at
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {questionnaire.published_at
                                                ? 'published'
                                                : 'draft'}
                                        </Badge>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Sections"
                                    >
                                        {questionnaire.sections_count}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Responses"
                                    >
                                        {questionnaire.responses_count}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Actions"
                                    >
                                        <div className="flex justify-start gap-2 md:justify-end">
                                            <ActionTooltip
                                                label={`Preview ${questionnaire.title}`}
                                            >
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={`/admin/questionnaires/${questionnaire.id}/preview`}
                                                        aria-label={`Preview ${questionnaire.title}`}
                                                    >
                                                        <Eye
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                            </ActionTooltip>
                                            {!questionnaire.published_at && (
                                                <>
                                                    <ActionTooltip
                                                        label={`Edit ${questionnaire.title}`}
                                                    >
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <Link
                                                                href={`/admin/questionnaires/${questionnaire.id}/edit`}
                                                                aria-label={`Edit ${questionnaire.title}`}
                                                            >
                                                                <Pencil
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </Link>
                                                        </Button>
                                                    </ActionTooltip>
                                                    <ActionTooltip
                                                        label={`Publish ${questionnaire.title}`}
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            type="button"
                                                            aria-label={`Publish ${questionnaire.title}`}
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/questionnaires/${questionnaire.id}/publish`,
                                                                )
                                                            }
                                                        >
                                                            <Send
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                    </ActionTooltip>
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

function ActionTooltip({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent side="top">{label}</TooltipContent>
        </Tooltip>
    );
}
