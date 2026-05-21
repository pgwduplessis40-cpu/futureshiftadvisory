import { Head } from '@inertiajs/react';
import { ClipboardCheck, Hourglass } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type EntrepreneurProfile = {
    id: string;
    name: string;
    email: string;
    stage: string;
    stage_label: string;
    concept_summary: string | null;
} | null;

type Props = {
    profile: EntrepreneurProfile;
};

export default function EntrepreneurDashboard({ profile }: Props) {
    return (
        <>
            <Head title="Entrepreneur portal" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Entrepreneur portal
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {profile?.name ?? 'Profile pending'}
                        </div>
                    </div>
                    <Badge variant="secondary">
                        {profile?.stage_label ?? 'Onboarding'}
                    </Badge>
                </div>

                <section className="space-y-4 rounded-md border p-4">
                    <div className="flex items-center gap-2">
                        <Hourglass className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Phase 1 access</h2>
                    </div>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Your invite is active and the entrepreneur module is in
                        onboarding mode. Readiness assessment, idea validation,
                        plan building, and scoring arrive in Phase 3.
                    </p>
                </section>

                <section className="space-y-4 rounded-md border p-4">
                    <div className="flex items-center gap-2">
                        <ClipboardCheck className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Profile</h2>
                    </div>
                    <dl className="grid gap-3 text-sm">
                        <Detail label="Email" value={profile?.email} />
                        <Detail
                            label="Concept"
                            value={profile?.concept_summary}
                        />
                    </dl>
                </section>
            </div>
        </>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[110px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

EntrepreneurDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneur portal',
            href: '/portal/entrepreneur',
        },
    ],
};
