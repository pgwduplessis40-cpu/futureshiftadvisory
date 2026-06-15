import { Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Inbox,
    Mail,
    MessageSquare,
    Paperclip,
    Plus,
    Send,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type MessagingClient = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type_label?: string;
};

export type ThreadSummary = {
    id: string;
    subject: string;
    last_activity_at: string | null;
    messages_count: number;
    unread_count: number;
    url: string;
};

export type SelectedThread = {
    id: string;
    subject: string;
    last_activity_at: string | null;
    reply_url: string;
    participants: Array<{
        id: number;
        name: string;
        user_type: string;
    }>;
    messages: ThreadMessage[];
};

export type ThreadMessage = {
    id: string;
    body: string;
    sender_name: string;
    sender_user_id: number | null;
    sender_user_type: string | null;
    channel: string;
    delivery_state: string;
    email_subject: string | null;
    email_recipients: Array<{
        user_id: number;
        email: string;
        name: string;
        delivery_state: string;
    }>;
    channel_decision: Record<string, unknown> | null;
    mine: boolean;
    attachments: Array<{
        document_id: string;
    }>;
    sent_at: string | null;
};

type MessageForm = {
    subject: string;
    body: string;
    attachments: File[];
};

type Props = {
    client: MessagingClient;
    threads: ThreadSummary[];
    selectedThread: SelectedThread | null;
    createUrl: string;
    indexUrl: string;
    backHref?: string;
    backLabel?: string;
};

export function ThreadedMessaging({
    client,
    threads,
    selectedThread,
    createUrl,
    indexUrl,
    backHref,
    backLabel = 'Back',
}: Props) {
    const [createUploadKey, setCreateUploadKey] = useState(0);
    const [replyUploadKey, setReplyUploadKey] = useState(0);
    const createForm = useForm<MessageForm>({
        subject: '',
        body: '',
        attachments: [],
    });
    const replyForm = useForm<MessageForm>({
        subject: '',
        body: '',
        attachments: [],
    });

    const submitCreate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        createForm.post(createUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setCreateUploadKey((key) => key + 1);
            },
        });
    };

    const submitReply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selectedThread) {
            return;
        }

        replyForm.post(selectedThread.reply_url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset();
                setReplyUploadKey((key) => key + 1);
            },
        });
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <MessageSquare className="size-4" aria-hidden="true" />
                        {client.engagement_type_label ?? 'Client portal'}
                    </div>
                    <h1 className="mt-1 text-xl font-semibold">
                        {client.trading_name || client.legal_name}
                    </h1>
                </div>
                <div className="flex flex-wrap gap-2">
                    {backHref && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={backHref}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {backLabel}
                            </Link>
                        </Button>
                    )}
                    <Button asChild variant="outline" size="sm">
                        <Link href={indexUrl}>
                            <Inbox className="size-4" aria-hidden="true" />
                            Threads
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
                <aside
                    className={cn(
                        'space-y-4 lg:order-none',
                        selectedThread ? 'order-2' : 'order-1',
                    )}
                >
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-sm font-medium">Threads</h2>
                            <Badge variant="outline">{threads.length}</Badge>
                        </div>

                        <div className="mt-4 grid gap-2">
                            {threads.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No messages yet.
                                </p>
                            ) : (
                                threads.map((thread) => (
                                    <ThreadLink
                                        key={thread.id}
                                        thread={thread}
                                        active={
                                            thread.id === selectedThread?.id
                                        }
                                    />
                                ))
                            )}
                        </div>
                    </section>

                    <section className="rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <Plus className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">New thread</h2>
                        </div>

                        <form
                            onSubmit={submitCreate}
                            className="mt-4 grid gap-3"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="message_subject">Subject</Label>
                                <Input
                                    id="message_subject"
                                    value={createForm.data.subject}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'subject',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={createForm.errors.subject}
                                />
                            </div>

                            <MessageBodyField
                                id="message_body"
                                value={createForm.data.body}
                                error={createForm.errors.body}
                                onChange={(value) =>
                                    createForm.setData('body', value)
                                }
                            />

                            <AttachmentField
                                id="new_thread_attachments"
                                inputKey={createUploadKey}
                                files={createForm.data.attachments}
                                error={createForm.errors.attachments}
                                onChange={(files) =>
                                    createForm.setData('attachments', files)
                                }
                            />

                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                <Send className="size-4" aria-hidden="true" />
                                Send
                            </Button>
                        </form>
                    </section>
                </aside>

                <section
                    className={cn(
                        'min-h-[360px] rounded-md border bg-background md:min-h-[520px] lg:order-none',
                        selectedThread ? 'order-1' : 'order-2',
                    )}
                >
                    {selectedThread ? (
                        <div className="flex min-h-[360px] flex-col md:min-h-[520px]">
                            <div className="border-b p-4">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 className="text-base font-semibold">
                                            {selectedThread.subject}
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {selectedThread.participants
                                                .map(
                                                    (participant) =>
                                                        participant.name,
                                                )
                                                .join(', ')}
                                        </p>
                                    </div>
                                    <Badge variant="secondary">
                                        {selectedThread.messages.length}
                                    </Badge>
                                </div>
                            </div>

                            <div className="flex-1 space-y-4 p-4">
                                {selectedThread.messages.map((message) => (
                                    <MessageBubble
                                        key={message.id}
                                        message={message}
                                    />
                                ))}
                            </div>

                            <form
                                onSubmit={submitReply}
                                className="grid gap-3 border-t p-4"
                            >
                                <MessageBodyField
                                    id="reply_body"
                                    label="Reply"
                                    value={replyForm.data.body}
                                    error={replyForm.errors.body}
                                    onChange={(value) =>
                                        replyForm.setData('body', value)
                                    }
                                />

                                <AttachmentField
                                    id="reply_attachments"
                                    inputKey={replyUploadKey}
                                    files={replyForm.data.attachments}
                                    error={replyForm.errors.attachments}
                                    onChange={(files) =>
                                        replyForm.setData('attachments', files)
                                    }
                                />

                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        disabled={replyForm.processing}
                                    >
                                        <Send
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Reply
                                    </Button>
                                </div>
                            </form>
                        </div>
                    ) : (
                        <div className="flex min-h-[520px] items-center justify-center p-6 text-center">
                            <div>
                                <Inbox
                                    className="mx-auto size-8 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <h2 className="mt-3 text-base font-medium">
                                    No thread selected
                                </h2>
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </div>
    );
}

function ThreadLink({
    thread,
    active,
}: {
    thread: ThreadSummary;
    active: boolean;
}) {
    return (
        <Link
            href={thread.url}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'grid gap-2 rounded-md border p-3 text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                active ? 'border-primary/30 bg-muted/60' : 'hover:bg-muted/60',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <span className="font-medium">{thread.subject}</span>
                {thread.unread_count > 0 && (
                    <Badge variant="secondary">{thread.unread_count}</Badge>
                )}
            </div>
            <div className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span>{thread.messages_count} messages</span>
                <span>{formatDate(thread.last_activity_at)}</span>
            </div>
        </Link>
    );
}

function MessageBubble({ message }: { message: ThreadMessage }) {
    return (
        <article
            className={cn(
                'max-w-full rounded-md border p-3 sm:max-w-[min(42rem,88%)]',
                message.mine ? 'ml-auto bg-muted/60' : 'bg-background',
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="text-sm font-medium">
                        {message.sender_name}
                    </div>
                    {message.channel === 'email' && (
                        <Badge variant="outline" className="gap-1">
                            <Mail className="size-3" aria-hidden="true" />
                            Email
                        </Badge>
                    )}
                    {message.channel === 'email' && (
                        <Badge variant="secondary">
                            {deliveryLabel(message.delivery_state)}
                        </Badge>
                    )}
                </div>
                <div className="text-xs text-muted-foreground">
                    {formatDate(message.sent_at)}
                </div>
            </div>
            {message.email_subject && (
                <div className="mt-2 text-sm font-medium">
                    {message.email_subject}
                </div>
            )}
            {message.email_recipients.length > 0 && (
                <div className="mt-1 text-xs text-muted-foreground">
                    To:{' '}
                    {message.email_recipients
                        .map((recipient) => recipient.name || recipient.email)
                        .join(', ')}
                </div>
            )}
            <p className="mt-2 text-sm leading-6 whitespace-pre-wrap">
                {message.body}
            </p>

            {message.attachments.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {message.attachments.map((attachment) => (
                        <Badge
                            key={attachment.document_id}
                            variant="outline"
                            className="gap-1"
                        >
                            <Paperclip className="size-3" aria-hidden="true" />
                            {shortDocumentId(attachment.document_id)}
                        </Badge>
                    ))}
                </div>
            )}
        </article>
    );
}

function MessageBodyField({
    id,
    value,
    error,
    onChange,
    label = 'Message',
}: {
    id: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    label?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <textarea
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                rows={4}
                className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError message={error} />
        </div>
    );
}

function AttachmentField({
    id,
    inputKey,
    files,
    error,
    onChange,
}: {
    id: string;
    inputKey: number;
    files: File[];
    error?: string;
    onChange: (files: File[]) => void;
}) {
    return (
        <div className="grid gap-2">
            <FileDropzone
                key={inputKey}
                id={id}
                files={files}
                label="Attachments"
                multiple
                onFilesChange={onChange}
            />
            <InputError message={error} />
        </div>
    );
}

function formatDate(value: string | null) {
    if (!value) {
        return 'Just now';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function shortDocumentId(id: string) {
    return `Document ${id.slice(0, 8)}`;
}

function deliveryLabel(value: string) {
    const labels: Record<string, string> = {
        failed: 'Failed',
        partial: 'Partial',
        sent: 'Sent',
        skipped_parallel_in_app: 'Skipped: in-app',
        skipped_preference: 'Skipped: preference',
    };

    return labels[value] ?? value.replaceAll('_', ' ');
}
