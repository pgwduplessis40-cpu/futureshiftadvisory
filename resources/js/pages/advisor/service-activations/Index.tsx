import { Head, Link } from '@inertiajs/react';
import { BriefcaseBusiness } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ActivationSummary = {
    id: string;
    client_name: string | null;
    service_type: string;
    client_label: string;
    status: string;
    status_label: string;
    advisor_name: string | null;
    package_label: string | null;
    requested_at: string | null;
    url: string;
};

type Props = {
    activations: ActivationSummary[];
};

export default function ServiceActivationsIndex({ activations }: Props) {
    return (
        <>
            <Head title="Service activations" />

            <main className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold">
                        Service activations
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Client-requested cross-service workspaces awaiting
                        package selection, acceptance, or advisor follow-up.
                    </p>
                </div>

                <section className="overflow-hidden rounded-md border bg-background">
                    <table className="fsa-responsive-table table-fixed md:table-fixed">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="w-[24%] px-3 py-2 font-medium">
                                    Client
                                </th>
                                <th className="w-[24%] px-3 py-2 font-medium">
                                    Workspace
                                </th>
                                <th className="w-[16%] px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="w-[20%] px-3 py-2 font-medium">
                                    Package
                                </th>
                                <th className="w-[16%] px-3 py-2 font-medium">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {activations.length === 0 ? (
                                <tr>
                                    <td
                                        className="px-3 py-3 text-muted-foreground"
                                        colSpan={5}
                                    >
                                        No service activation requests yet.
                                    </td>
                                </tr>
                            ) : (
                                activations.map((activation) => (
                                    <tr
                                        key={activation.id}
                                        className="border-t"
                                    >
                                        <td
                                            className="px-3 py-3"
                                            data-label="Client"
                                        >
                                            {activation.client_name ?? '-'}
                                        </td>
                                        <td
                                            className="px-3 py-3"
                                            data-label="Workspace"
                                        >
                                            <div className="flex items-center gap-2">
                                                <BriefcaseBusiness
                                                    className="size-4 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                {activation.client_label}
                                            </div>
                                        </td>
                                        <td
                                            className="px-3 py-3"
                                            data-label="Status"
                                        >
                                            <Badge variant="secondary">
                                                {activation.status_label}
                                            </Badge>
                                        </td>
                                        <td
                                            className="px-3 py-3"
                                            data-label="Package"
                                        >
                                            {activation.package_label ?? '-'}
                                        </td>
                                        <td
                                            className="px-3 py-3"
                                            data-label="Action"
                                        >
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link href={activation.url}>
                                                    Review
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </section>
            </main>
        </>
    );
}

ServiceActivationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Service activations',
            href: '/advisor/service-activations',
        },
    ],
};
