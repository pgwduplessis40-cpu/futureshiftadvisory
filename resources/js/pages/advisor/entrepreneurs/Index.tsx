import { Head, Link } from '@inertiajs/react';
import { Plus, Search, UsersRound } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { CapacitySummary, EntrepreneurSummary } from './types';

type Props = {
    entrepreneurs: EntrepreneurSummary[];
    capacity: CapacitySummary;
};

export default function EntrepreneursIndex({ entrepreneurs, capacity }: Props) {
    return (
        <>
            <Head title="Entrepreneurs" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Entrepreneurs</h1>
                        <div className="text-sm text-muted-foreground">
                            {capacity.active_count} active of {capacity.limit}
                        </div>
                    </div>
                    {capacity.blocked ? (
                        <Button size="sm" disabled>
                            <Plus className="size-4" aria-hidden="true" />
                            New
                        </Button>
                    ) : (
                        <Button asChild size="sm">
                            <Link href="/advisor/entrepreneurs/create">
                                <Plus className="size-4" aria-hidden="true" />
                                New
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric
                        label="Active"
                        value={`${capacity.active_count}/${capacity.limit}`}
                        explanation="Entrepreneurs currently counted against the active portfolio capacity."
                    />
                    <Metric
                        label="Warning"
                        value={`${capacity.warning_threshold}`}
                        explanation="The capacity threshold where the advisor should start planning intake carefully."
                    />
                    <Metric
                        label="Remaining"
                        value={`${capacity.remaining}`}
                        explanation="Available entrepreneur slots before the active capacity limit is reached."
                    />
                </div>

                <div className="overflow-hidden rounded-md border">
                    {entrepreneurs.length > 0 ? (
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Entrepreneur
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Stage
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Advisor
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entrepreneurs.map((entrepreneur) => (
                                    <tr
                                        key={entrepreneur.id}
                                        className="border-t"
                                    >
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/advisor/entrepreneurs/${entrepreneur.id}`}
                                                className="font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                            >
                                                {entrepreneur.name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {entrepreneur.email}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge variant="secondary">
                                                {entrepreneur.stage_label}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2">
                                            {entrepreneur.assigned_advisor_name ??
                                                'Unassigned'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={`/advisor/entrepreneurs/${entrepreneur.id}`}
                                                        aria-label={`Open ${entrepreneur.name}`}
                                                    >
                                                        <Search
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="flex items-center gap-3 px-4 py-10 text-sm text-muted-foreground">
                            <UsersRound className="size-4" aria-hidden="true" />
                            No entrepreneurs yet.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    explanation,
}: {
    label: string;
    value: string;
    explanation: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div className="rounded-md border p-4">
                    <div className="text-xs text-muted-foreground">{label}</div>
                    <div className="mt-2 text-sm font-medium">{value}</div>
                </div>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

EntrepreneursIndex.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneurs',
            href: '/advisor/entrepreneurs',
        },
    ],
};
