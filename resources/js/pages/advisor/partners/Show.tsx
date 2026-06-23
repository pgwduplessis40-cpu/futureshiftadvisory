import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Copy, RefreshCw, XCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Agreement = {
    id: string;
    status: string;
    status_label: string;
    generated_at: string | null;
    signed_at: string | null;
};

type ReferralSummary = {
    id: string;
    stage: string;
    stage_label: string;
    referral_type: string;
    subject: string;
    sent_at: string | null;
    closed_at: string | null;
};

type ReverseReferralSummary = {
    id: string;
    name: string;
    company: string | null;
    target_type: string;
    submitted_at: string | null;
};

type PartnerDetail = {
    id: string;
    panel_type: 'broker' | 'coach';
    panel_label: string;
    business_name: string;
    contact_name: string;
    email: string | null;
    status: string;
    status_label: string;
    invite_accepted_at: string | null;
    invite_expires_at: string | null;
    invite_accept_url: string | null;
    invite_email_subject: string | null;
    invite_email_body: string | null;
    invite_resend_url: string | null;
    invite_cancel_url: string | null;
    industry_label: string;
    regions: string[];
    specialties: string[];
    referrals_count: number;
    active_referrals_count: number;
    reverse_referrals_count: number;
    show_url: string;
    fsp_number: string | null;
    fsp_status: string | null;
    fsp_last_checked_at: string | null;
    applied_at: string | null;
    approved_at: string | null;
    suspended_at: string | null;
    coach_profile: Record<string, unknown>;
    professional_memberships: string[];
    latest_agreement: Agreement | null;
    recent_referrals: ReferralSummary[];
    reverse_referrals: ReverseReferralSummary[];
    back_url: string;
};

export default function PartnerShow({ partner }: { partner: PartnerDetail }) {
    const heading = `${partner.business_name} - ${partner.panel_label}`;
    const [copiedInviteDraft, setCopiedInviteDraft] = useState(false);
    const [copiedInviteLink, setCopiedInviteLink] = useState(false);

    const copyText = (text: string, onCopied: (value: boolean) => void) => {
        void navigator.clipboard.writeText(text).then(() => {
            onCopied(true);
            window.setTimeout(() => onCopied(false), 1800);
        });
    };
    const copyInviteDraft = () => {
        if (!partner.invite_email_body || !partner.email) {
            return;
        }

        copyText(
            [
                `To: ${partner.email}`,
                `Subject: ${partner.invite_email_subject ?? 'Future Shift Advisory invitation'}`,
                '',
                partner.invite_email_body,
            ].join('\n'),
            setCopiedInviteDraft,
        );
    };
    const copyInviteLink = () => {
        if (!partner.invite_accept_url) {
            return;
        }

        copyText(partner.invite_accept_url, setCopiedInviteLink);
    };
    const resendInvite = () => {
        if (!partner.invite_resend_url) {
            return;
        }

        router.post(partner.invite_resend_url, {}, { preserveScroll: true });
    };
    const cancelInvite = () => {
        if (!partner.invite_cancel_url) {
            return;
        }

        if (
            !window.confirm(
                `Cancel the pending invite for ${partner.contact_name}? The current invite link will stop working.`,
            )
        ) {
            return;
        }

        router.delete(partner.invite_cancel_url, { preserveScroll: true });
    };

    return (
        <>
            <Head title={heading} />

            <div className="space-y-6">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Partners / {partner.panel_label}
                        </p>
                        <h1 className="text-xl font-semibold">
                            {partner.business_name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {partner.contact_name}
                            {partner.email ? ` - ${partner.email}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {partner.invite_email_body ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={copyInviteDraft}
                            >
                                <Copy className="size-4" aria-hidden="true" />
                                {copiedInviteDraft
                                    ? 'Draft copied'
                                    : 'Copy Outlook draft'}
                            </Button>
                        ) : null}
                        {partner.invite_accept_url ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={copyInviteLink}
                            >
                                <Copy className="size-4" aria-hidden="true" />
                                {copiedInviteLink
                                    ? 'Link copied'
                                    : 'Copy invite link'}
                            </Button>
                        ) : null}
                        {partner.invite_resend_url ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={resendInvite}
                            >
                                <RefreshCw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Resend invite
                            </Button>
                        ) : null}
                        {partner.invite_cancel_url ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onClick={cancelInvite}
                            >
                                <XCircle
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Cancel invite
                            </Button>
                        ) : null}
                        <Button asChild size="sm" variant="outline">
                            <Link href={partner.back_url}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                    </div>
                </header>

                <section className="grid gap-3 md:grid-cols-4">
                    <Metric label="Status">
                        <Badge variant={statusVariant(partner.status)}>
                            {partner.status_label}
                        </Badge>
                    </Metric>
                    <Metric
                        label={
                            partner.panel_type === 'broker'
                                ? 'Industry'
                                : 'Focus'
                        }
                    >
                        {partner.industry_label}
                    </Metric>
                    <Metric label="Active referrals">
                        {partner.active_referrals_count}
                    </Metric>
                    <Metric label="Total referrals">
                        {partner.referrals_count}
                    </Metric>
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-md border">
                        <SectionHeader title="Partner card" />
                        <dl className="grid gap-3 p-4 text-sm sm:grid-cols-2">
                            <Detail
                                label="Business"
                                value={partner.business_name}
                            />
                            <Detail
                                label="Contact"
                                value={partner.contact_name}
                            />
                            <Detail
                                label="Email"
                                value={partner.email ?? 'Not supplied'}
                            />
                            <Detail
                                label="Invite accepted"
                                value={formatDate(partner.invite_accepted_at)}
                            />
                            <Detail
                                label="Invite expires"
                                value={formatDate(partner.invite_expires_at)}
                            />
                            <Detail
                                label="Invite delivery"
                                value={
                                    partner.invite_email_body
                                        ? 'Manual Outlook send'
                                        : 'No active link'
                                }
                            />
                            <Detail
                                label="Regions"
                                value={formatList(partner.regions)}
                            />
                            <Detail
                                label="Specialties"
                                value={formatList(partner.specialties)}
                            />
                            <Detail
                                label="Approved"
                                value={formatDate(partner.approved_at)}
                            />
                        </dl>
                    </div>

                    <div className="rounded-md border">
                        <SectionHeader title="Governance" />
                        <dl className="grid gap-3 p-4 text-sm sm:grid-cols-2">
                            {partner.panel_type === 'broker' && (
                                <>
                                    <Detail
                                        label="FSP number"
                                        value={
                                            partner.fsp_number ?? 'Not supplied'
                                        }
                                    />
                                    <Detail
                                        label="FSP status"
                                        value={
                                            partner.fsp_status ?? 'Not supplied'
                                        }
                                    />
                                </>
                            )}
                            <Detail
                                label="Applied"
                                value={formatDate(partner.applied_at)}
                            />
                            <Detail
                                label="Suspended"
                                value={formatDate(partner.suspended_at)}
                            />
                            <Detail
                                label="Agreement"
                                value={
                                    partner.latest_agreement?.status_label ??
                                    'Not supplied'
                                }
                            />
                            <Detail
                                label="Agreement signed"
                                value={formatDate(
                                    partner.latest_agreement?.signed_at ?? null,
                                )}
                            />
                        </dl>
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-md border">
                        <SectionHeader
                            title="Recent referrals"
                            count={partner.recent_referrals.length}
                        />
                        {partner.recent_referrals.length > 0 ? (
                            <div className="divide-y">
                                {partner.recent_referrals.map((referral) => (
                                    <div
                                        key={referral.id}
                                        className="grid gap-1 px-4 py-3 text-sm"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {referral.subject}
                                            </span>
                                            <Badge variant="outline">
                                                {referral.stage_label}
                                            </Badge>
                                        </div>
                                        <div className="text-muted-foreground">
                                            {referral.referral_type} - sent{' '}
                                            {formatDate(referral.sent_at)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState>No referrals recorded yet.</EmptyState>
                        )}
                    </div>

                    <div className="rounded-md border">
                        <SectionHeader
                            title="Reverse referrals"
                            count={partner.reverse_referrals_count}
                        />
                        {partner.reverse_referrals.length > 0 ? (
                            <div className="divide-y">
                                {partner.reverse_referrals.map((referral) => (
                                    <div
                                        key={referral.id}
                                        className="grid gap-1 px-4 py-3 text-sm"
                                    >
                                        <div className="font-medium">
                                            {referral.name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {referral.company ??
                                                'Company not supplied'}{' '}
                                            - {referral.target_type} -{' '}
                                            {formatDate(referral.submitted_at)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState>
                                No reverse referrals recorded yet.
                            </EmptyState>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

function Metric({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="rounded-md border px-3 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-medium">{children}</div>
        </div>
    );
}

function SectionHeader({ title, count }: { title: string; count?: number }) {
    return (
        <div className="flex items-center justify-between border-b px-4 py-3">
            <h2 className="font-medium">{title}</h2>
            {typeof count === 'number' && (
                <Badge variant="secondary">{count}</Badge>
            )}
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );
}

function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="px-4 py-8 text-sm text-muted-foreground">
            {children}
        </div>
    );
}

function formatList(values: string[]): string {
    return values.length > 0 ? values.join(', ') : 'Not supplied';
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not supplied';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'active' || status === 'approved') {
        return 'default';
    }

    if (status === 'suspended' || status === 'declined') {
        return 'destructive';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    if (status === 'approved_pending_agreement') {
        return 'secondary';
    }

    return 'outline';
}

PartnerShow.layout = {
    breadcrumbs: [
        {
            title: 'Partners',
            href: '/advisor/partners/brokers',
        },
    ],
};
