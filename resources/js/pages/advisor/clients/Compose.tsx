import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Inbox, Mail, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
};

type Recipient = {
    id: number;
    name: string;
    email: string;
    role: string;
    default_selected: boolean;
    preference_channel: string;
    preference_frequency: string;
};

type ComposeForm = {
    recipient_user_ids: number[];
    subject: string;
    body: string;
    logical_message_key: string;
};

type Props = {
    client: ClientPayload;
    recipients: Recipient[];
    storeUrl: string;
    backUrl: string;
    messagesUrl: string;
};

const preferenceLabels: Record<string, string> = {
    both: 'Email and in-platform',
    daily: 'Daily',
    email_only: 'Email only',
    immediate: 'Immediate',
    in_platform_only: 'In-platform only',
    weekly: 'Weekly',
};

export default function ClientCompose({
    client,
    recipients,
    storeUrl,
    backUrl,
    messagesUrl,
}: Props) {
    const defaultRecipientIds = recipients
        .filter((recipient) => recipient.default_selected)
        .map((recipient) => recipient.id);
    const form = useForm<ComposeForm>({
        recipient_user_ids:
            defaultRecipientIds.length > 0
                ? defaultRecipientIds
                : recipients.map((recipient) => recipient.id),
        subject: '',
        body: '',
        logical_message_key: '',
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeUrl, { preserveScroll: true });
    };

    const toggleRecipient = (id: number, checked: boolean) => {
        form.setData(
            'recipient_user_ids',
            checked
                ? Array.from(new Set([...form.data.recipient_user_ids, id]))
                : form.data.recipient_user_ids.filter(
                      (recipientId) => recipientId !== id,
                  ),
        );
    };

    return (
        <>
            <Head title={`${client.legal_name} email`} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Mail className="size-4" aria-hidden="true" />
                            Email
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            {client.trading_name || client.legal_name}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={messagesUrl}>
                                <Inbox className="size-4" aria-hidden="true" />
                                Messages
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={backUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Client
                            </Link>
                        </Button>
                    </div>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]"
                >
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="grid gap-2">
                            <Label htmlFor="email_subject">Subject</Label>
                            <Input
                                id="email_subject"
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                            />
                            <InputError message={form.errors.subject} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email_body">Message</Label>
                            <textarea
                                id="email_body"
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                rows={12}
                                className="min-h-72 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError message={form.errors.body} />
                        </div>

                        <Button type="submit" disabled={form.processing}>
                            <Send className="size-4" aria-hidden="true" />
                            Send email
                        </Button>
                    </section>

                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-sm font-medium">Recipients</h2>
                            <Badge variant="outline">
                                {form.data.recipient_user_ids.length}
                            </Badge>
                        </div>

                        <div className="grid gap-3">
                            {recipients.map((recipient) => (
                                <label
                                    key={recipient.id}
                                    htmlFor={`recipient_${recipient.id}`}
                                    className="grid cursor-pointer grid-cols-[auto_minmax(0,1fr)] gap-3 rounded-md border p-3"
                                >
                                    <Checkbox
                                        id={`recipient_${recipient.id}`}
                                        checked={form.data.recipient_user_ids.includes(
                                            recipient.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleRecipient(
                                                recipient.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm font-medium">
                                            {recipient.name}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {recipient.email}
                                        </span>
                                        <span className="mt-2 flex flex-wrap gap-2">
                                            <Badge variant="secondary">
                                                {recipient.role.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                            <Badge variant="outline">
                                                {preferenceLabels[
                                                    recipient.preference_channel
                                                ] ??
                                                    recipient.preference_channel}
                                            </Badge>
                                            <Badge variant="outline">
                                                {preferenceLabels[
                                                    recipient
                                                        .preference_frequency
                                                ] ??
                                                    recipient.preference_frequency}
                                            </Badge>
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>

                        <InputError message={errors.recipient_user_ids} />
                    </section>
                </form>
            </div>
        </>
    );
}

ClientCompose.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
    ],
};
