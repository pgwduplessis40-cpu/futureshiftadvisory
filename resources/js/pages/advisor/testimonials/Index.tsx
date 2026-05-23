import { Head } from '@inertiajs/react';
import { Quote } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type Testimonial = {
    id: string;
    client_id: string;
    client_name: string | null;
    quote: string;
    display_mode: string;
    display_name: string | null;
    source_type: string;
    source_score: number | null;
    consented_at: string | null;
};

type Props = {
    testimonials: Testimonial[];
};

export default function TestimonialsIndex({ testimonials }: Props) {
    return (
        <>
            <Head title="Testimonials" />

            <div className="space-y-6">
                <header className="flex items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Quote className="size-4" aria-hidden="true" />
                            Consent library
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Testimonials
                        </h1>
                    </div>
                    <Badge variant="secondary">
                        {testimonials.length} approved
                    </Badge>
                </header>

                {testimonials.length === 0 ? (
                    <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                        No consented testimonials are available.
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {testimonials.map((testimonial) => (
                            <article
                                key={testimonial.id}
                                className="space-y-3 rounded-md border p-4"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {testimonial.display_mode}
                                    </Badge>
                                    {testimonial.source_score !== null && (
                                        <Badge variant="secondary">
                                            NPS {testimonial.source_score}
                                        </Badge>
                                    )}
                                </div>
                                <blockquote className="text-sm leading-6 text-muted-foreground">
                                    "{testimonial.quote}"
                                </blockquote>
                                <div className="text-sm font-medium">
                                    {testimonial.display_name ??
                                        testimonial.client_name ??
                                        'Anonymous client'}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {formatDate(testimonial.consented_at)}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Consent date unavailable';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

TestimonialsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Testimonials',
            href: '/advisor/testimonials',
        },
    ],
};
