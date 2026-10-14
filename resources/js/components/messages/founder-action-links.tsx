import { Link } from '@inertiajs/react';
import { LocateFixed, MapPinned } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type FounderActionLink = {
    key: string;
    title: string;
    description: string;
    open_url?: string | null;
    guide_url?: string | null;
};

export function FounderActionLinks({
    links,
    preview = false,
}: {
    links: FounderActionLink[];
    preview?: boolean;
}) {
    if (links.length === 0) {
        return null;
    }

    return (
        <section
            className="mt-3 space-y-3 rounded-md border border-sky-200 bg-sky-50/70 p-3 text-sm text-sky-950"
            aria-label="Plan update links"
        >
            <div className="flex items-start gap-2">
                <MapPinned
                    className="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <h3 className="font-medium">
                        Open the right part of your plan
                    </h3>
                    <p className="mt-1 text-sky-900">
                        {preview
                            ? 'The founder will receive these protected plan links with this reply.'
                            : 'Choose a step to open the relevant Budget area, or use the visual guide to see it highlighted.'}
                    </p>
                </div>
            </div>

            <ul className="space-y-3">
                {links.map((link) => (
                    <li
                        key={link.key}
                        className="rounded-md border border-sky-200 bg-background/80 p-3"
                    >
                        <p className="font-medium text-foreground">
                            {link.title}
                        </p>
                        <p className="mt-1 text-sky-900">{link.description}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {link.open_url ? (
                                <Button asChild size="sm">
                                    <Link href={link.open_url}>
                                        <LocateFixed
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Open this step
                                    </Link>
                                </Button>
                            ) : (
                                <Button size="sm" disabled>
                                    <LocateFixed
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Open this step
                                </Button>
                            )}
                            {link.guide_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={link.guide_url}>
                                        <MapPinned
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Show me where
                                    </Link>
                                </Button>
                            ) : (
                                <Button size="sm" variant="outline" disabled>
                                    <MapPinned
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Show me where
                                </Button>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
