import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarClock,
    HeartHandshake,
    ListChecks,
    Pencil,
    Pin,
    PinOff,
    Send,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type PostType = 'message' | 'quote' | 'image';

type BoardPost = {
    id: string;
    type: PostType;
    title: string | null;
    body: string | null;
    attribution: string | null;
    status: 'draft' | 'published' | 'archived';
    pinned: boolean;
    image_url: string | null;
    image_filename: string | null;
    image_scanner_result: string | null;
    image_is_quarantined: boolean;
    published_at: string | null;
    scheduled_at: string | null;
    featured_at: string | null;
    created_by: string | null;
    created_at: string | null;
};

type RotationSchedule = {
    id: string;
    name: string;
    status: 'scheduled' | 'cancelled';
    phase: 'upcoming' | 'active' | 'completed' | 'cancelled';
    starts_at: string | null;
    ends_at: string | null;
    cadence_days: number;
    post_count: number;
    posts: Array<{
        id: string;
        title: string | null;
        body: string | null;
        position: number;
        scheduled_at: string | null;
    }>;
};

type Props = {
    posts: BoardPost[];
    rotationSchedules: RotationSchedule[];
    storeUrl: string;
};

const typeLabels: Record<PostType, string> = {
    message: 'Message',
    quote: 'Quote',
    image: 'Image',
};

const statusVariants: Record<
    BoardPost['status'],
    'default' | 'secondary' | 'outline'
> = {
    published: 'default',
    draft: 'secondary',
    archived: 'outline',
};

function action(id: string, verb: string): void {
    router.post(
        `/admin/inspiration-board/${id}/${verb}`,
        {},
        {
            preserveScroll: true,
        },
    );
}

function dateTimeLocalValue(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
}

function defaultRotationStart(): string {
    const date = new Date();
    const daysUntilMonday = (8 - date.getDay()) % 7;

    date.setDate(date.getDate() + daysUntilMonday);
    date.setHours(6, 0, 0, 0);

    if (date.getTime() <= Date.now()) {
        date.setDate(date.getDate() + 7);
    }

    return dateTimeLocalValue(date.toISOString());
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Not scheduled';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-NZ', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function isFutureScheduled(post: BoardPost): boolean {
    return Boolean(
        post.status === 'published' &&
        post.scheduled_at &&
        new Date(post.scheduled_at).getTime() > Date.now(),
    );
}

function schedulePostSummary(schedule: RotationSchedule): string {
    const visiblePosts = schedule.posts.slice(0, 3).map((post) => {
        const label = post.title ?? post.body ?? 'Untitled quote';
        const compactLabel =
            label.length > 72 ? `${label.slice(0, 69)}...` : label;

        return `${post.position}. ${compactLabel}`;
    });

    if (schedule.posts.length > visiblePosts.length) {
        visiblePosts.push(
            `+ ${schedule.posts.length - visiblePosts.length} more`,
        );
    }

    return visiblePosts.join(' · ');
}

export default function InspirationBoardIndex({
    posts,
    rotationSchedules,
    storeUrl,
}: Props) {
    const [selectedQuoteIds, setSelectedQuoteIds] = useState<string[]>([]);
    const publishedQuoteCount = posts.filter(
        (post) =>
            post.status === 'published' &&
            post.type === 'quote' &&
            !post.pinned &&
            !isFutureScheduled(post),
    ).length;
    const form = useForm<{
        type: PostType;
        title: string;
        body: string;
        attribution: string;
        scheduled_at: string;
        image: File | null;
    }>({
        type: 'quote',
        title: '',
        body: '',
        attribution: '',
        scheduled_at: '',
        image: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(storeUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function toggleRotationQuote(postId: string, checked: boolean): void {
        setSelectedQuoteIds((current) => {
            if (checked) {
                return current.includes(postId)
                    ? current
                    : [...current, postId];
            }

            return current.filter((id) => id !== postId);
        });
    }

    return (
        <>
            <Head title="Inspiration board" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Engagement"
                    icon={HeartHandshake}
                    title="Inspiration board"
                    description="Post motivational messages, quotes, or images for clients and entrepreneurs. Everything is kept in the library to reuse later."
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="lg:col-span-1">
                        <form
                            onSubmit={submit}
                            className="space-y-4 rounded-md border bg-background p-4"
                        >
                            <div className="space-y-2">
                                <Label>Type</Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={(value) =>
                                        form.setData('type', value as PostType)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="quote">
                                            Quote
                                        </SelectItem>
                                        <SelectItem value="message">
                                            Message
                                        </SelectItem>
                                        <SelectItem value="image">
                                            Image
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="title">Title (optional)</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.title} />
                            </div>

                            {form.data.type === 'image' ? (
                                <div className="space-y-2">
                                    <Label htmlFor="image">Image</Label>
                                    <input
                                        id="image"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                        onChange={(event) =>
                                            form.setData(
                                                'image',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                        className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:bg-muted file:px-3 file:py-1.5 file:text-sm"
                                    />
                                    <InputError message={form.errors.image} />
                                    <Label
                                        htmlFor="body"
                                        className="pt-2 text-xs text-muted-foreground"
                                    >
                                        Caption (optional)
                                    </Label>
                                    <textarea
                                        id="body"
                                        value={form.data.body}
                                        rows={2}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        className="min-h-16 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Label htmlFor="body">
                                        {form.data.type === 'quote'
                                            ? 'Quote'
                                            : 'Message'}
                                    </Label>
                                    <textarea
                                        id="body"
                                        value={form.data.body}
                                        rows={5}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        required
                                    />
                                    <InputError message={form.errors.body} />
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="attribution">
                                    Attribution (optional)
                                </Label>
                                <Input
                                    id="attribution"
                                    placeholder="e.g. Maya Angelou"
                                    value={form.data.attribution}
                                    onChange={(event) =>
                                        form.setData(
                                            'attribution',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.attribution} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="scheduled_at">
                                    Release date (optional)
                                </Label>
                                <Input
                                    id="scheduled_at"
                                    type="datetime-local"
                                    value={form.data.scheduled_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'scheduled_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.scheduled_at}
                                />
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Saved to the library as a draft. Publish it to
                                make it available for a rotation.
                            </p>
                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Add to library
                                </Button>
                            </div>
                        </form>
                    </section>

                    <section className="space-y-3 lg:col-span-2">
                        <RotationScheduler
                            publishedQuoteCount={publishedQuoteCount}
                            selectedQuoteIds={selectedQuoteIds}
                            onScheduled={() => setSelectedQuoteIds([])}
                        />
                        <RotationScheduleList schedules={rotationSchedules} />

                        {posts.length === 0 ? (
                            <EmptyState
                                icon={HeartHandshake}
                                title="No posts yet"
                                description="Add your first message, quote, or image. It stays in the library to reuse later."
                            />
                        ) : (
                            posts.map((post) => {
                                const selectedPosition =
                                    selectedQuoteIds.indexOf(post.id) + 1;

                                return (
                                    <PostCard
                                        key={post.id}
                                        post={post}
                                        rotationEligible={
                                            post.status === 'published' &&
                                            post.type === 'quote' &&
                                            !post.pinned &&
                                            !isFutureScheduled(post)
                                        }
                                        selectedRotationPosition={
                                            selectedPosition > 0
                                                ? selectedPosition
                                                : null
                                        }
                                        onRotationSelectionChange={
                                            toggleRotationQuote
                                        }
                                    />
                                );
                            })
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

function RotationScheduler({
    publishedQuoteCount,
    selectedQuoteIds,
    onScheduled,
}: {
    publishedQuoteCount: number;
    selectedQuoteIds: string[];
    onScheduled: () => void;
}) {
    const form = useForm<{
        name: string;
        start_at: string;
        cadence_days: string;
        post_ids: string[];
    }>({
        name: '',
        start_at: defaultRotationStart(),
        cadence_days: '7',
        post_ids: [],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            post_ids: selectedQuoteIds,
        }));
        form.post('/admin/inspiration-board/schedule-rotation', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('name');
                onScheduled();
            },
        });
    }

    return (
        <SectionCard className="space-y-4">
            <div className="flex flex-wrap items-start gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <CalendarClock
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h2 className="text-sm font-semibold">
                            Rotation schedule
                        </h2>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Selected published quotes are featured in selection
                        order.
                    </p>
                </div>
                <Badge variant="outline">
                    {selectedQuoteIds.length} selected
                </Badge>
            </div>

            <form
                onSubmit={submit}
                className="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_10rem_auto]"
            >
                <div className="space-y-2">
                    <Label htmlFor="rotation_name">Schedule name</Label>
                    <Input
                        id="rotation_name"
                        placeholder="e.g. Q3 founder series"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="rotation_start_at">Start date</Label>
                    <Input
                        id="rotation_start_at"
                        type="datetime-local"
                        value={form.data.start_at}
                        onChange={(event) =>
                            form.setData('start_at', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.start_at} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="rotation_cadence_days">Days between</Label>
                    <Input
                        id="rotation_cadence_days"
                        type="number"
                        min="1"
                        max="365"
                        value={form.data.cadence_days}
                        onChange={(event) =>
                            form.setData('cadence_days', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.cadence_days} />
                </div>
                <div className="flex items-end">
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            publishedQuoteCount === 0 ||
                            selectedQuoteIds.length === 0
                        }
                        className="w-full md:w-auto"
                    >
                        <CalendarClock className="size-4" aria-hidden="true" />
                        Schedule rotation
                    </Button>
                </div>
            </form>
            <InputError message={form.errors.post_ids} />
        </SectionCard>
    );
}

function RotationScheduleList({
    schedules,
}: {
    schedules: RotationSchedule[];
}) {
    if (schedules.length === 0) {
        return null;
    }

    return (
        <SectionCard className="space-y-3">
            <div className="flex items-center gap-2">
                <ListChecks
                    className="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                <h2 className="text-sm font-semibold">Rotation schedules</h2>
            </div>

            <div className="divide-y rounded-md border">
                {schedules.map((schedule) => (
                    <div
                        key={schedule.id}
                        className="flex flex-wrap items-start gap-3 p-3"
                    >
                        <div className="min-w-0 flex-1 space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="text-sm font-semibold">
                                    {schedule.name}
                                </p>
                                <Badge
                                    variant={
                                        schedule.phase === 'active'
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {schedule.phase}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {formatDateTime(schedule.starts_at)} to{' '}
                                {formatDateTime(schedule.ends_at)} ·{' '}
                                {schedule.cadence_days} days between ·{' '}
                                {schedule.post_count} quotes
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {schedulePostSummary(schedule)}
                            </p>
                        </div>
                        {schedule.status === 'scheduled' && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Cancel this rotation? Future quotes will return to drafts.',
                                        )
                                    ) {
                                        router.delete(
                                            `/admin/inspiration-board/schedule-rotation/${schedule.id}`,
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                <X className="size-4" aria-hidden="true" />
                                Cancel schedule
                            </Button>
                        )}
                    </div>
                ))}
            </div>
        </SectionCard>
    );
}

function PostCard({
    post,
    rotationEligible,
    selectedRotationPosition,
    onRotationSelectionChange,
}: {
    post: BoardPost;
    rotationEligible: boolean;
    selectedRotationPosition: number | null;
    onRotationSelectionChange: (postId: string, checked: boolean) => void;
}) {
    const [editing, setEditing] = useState(false);
    const editForm = useForm({
        title: post.title ?? '',
        body: post.body ?? '',
        attribution: post.attribution ?? '',
        scheduled_at: dateTimeLocalValue(post.scheduled_at),
    });

    function beginEdit() {
        editForm.setData({
            title: post.title ?? '',
            body: post.body ?? '',
            attribution: post.attribution ?? '',
            scheduled_at: dateTimeLocalValue(post.scheduled_at),
        });
        setEditing(true);
    }

    function submitEdit(event: FormEvent) {
        event.preventDefault();
        editForm.patch(`/admin/inspiration-board/${post.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    }

    return (
        <SectionCard className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{typeLabels[post.type]}</Badge>
                <Badge variant={statusVariants[post.status]}>
                    {isFutureScheduled(post) ? 'scheduled' : post.status}
                </Badge>
                {post.pinned && (
                    <Badge variant="secondary">
                        <Pin className="mr-1 size-3" aria-hidden="true" />
                        Pinned
                    </Badge>
                )}
                {post.image_is_quarantined && (
                    <Badge variant="secondary">Quarantined image</Badge>
                )}
                {post.scheduled_at && (
                    <Badge variant="outline">
                        {formatDateTime(post.scheduled_at)}
                    </Badge>
                )}
                <div className="ml-auto flex items-center gap-3">
                    {rotationEligible && (
                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium">
                            <Checkbox
                                checked={selectedRotationPosition !== null}
                                onCheckedChange={(checked) =>
                                    onRotationSelectionChange(
                                        post.id,
                                        checked === true,
                                    )
                                }
                                aria-label={`Include ${post.title ?? 'quote'} in rotation`}
                            />
                            <span>
                                {selectedRotationPosition === null
                                    ? 'Select'
                                    : `Selected ${selectedRotationPosition}`}
                            </span>
                        </label>
                    )}
                    <span className="text-xs text-muted-foreground">
                        {post.created_by ?? 'admin'}
                    </span>
                </div>
            </div>

            {editing ? (
                <form
                    onSubmit={submitEdit}
                    className="space-y-3 rounded-md border bg-muted/20 p-3"
                >
                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor={`title-${post.id}`}>
                                Title (optional)
                            </Label>
                            <Input
                                id={`title-${post.id}`}
                                value={editForm.data.title}
                                onChange={(event) =>
                                    editForm.setData(
                                        'title',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={editForm.errors.title} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`scheduled-${post.id}`}>
                                Release date (optional)
                            </Label>
                            <Input
                                id={`scheduled-${post.id}`}
                                type="datetime-local"
                                value={editForm.data.scheduled_at}
                                onChange={(event) =>
                                    editForm.setData(
                                        'scheduled_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={editForm.errors.scheduled_at}
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor={`body-${post.id}`}>
                            {post.type === 'quote' ? 'Quote' : 'Message'}
                        </Label>
                        <textarea
                            id={`body-${post.id}`}
                            value={editForm.data.body}
                            rows={post.type === 'image' ? 2 : 4}
                            onChange={(event) =>
                                editForm.setData('body', event.target.value)
                            }
                            className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            required={post.type !== 'image'}
                        />
                        <InputError message={editForm.errors.body} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor={`attribution-${post.id}`}>
                            Attribution (optional)
                        </Label>
                        <Input
                            id={`attribution-${post.id}`}
                            value={editForm.data.attribution}
                            onChange={(event) =>
                                editForm.setData(
                                    'attribution',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={editForm.errors.attribution} />
                    </div>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEditing(false)}
                        >
                            <X className="size-4" aria-hidden="true" />
                            Cancel
                        </Button>
                        <Button type="submit" disabled={editForm.processing}>
                            <Pencil className="size-4" aria-hidden="true" />
                            Save changes
                        </Button>
                    </div>
                </form>
            ) : (
                <>
                    {post.title && (
                        <p className="text-sm font-semibold">{post.title}</p>
                    )}
                    {post.type === 'image' &&
                    post.image_url &&
                    !post.image_is_quarantined ? (
                        <img
                            src={post.image_url}
                            alt={post.title ?? 'Inspiration'}
                            className="max-h-48 rounded-md object-cover"
                        />
                    ) : null}
                    {post.image_is_quarantined && (
                        <p className="text-xs text-amber-800">
                            Image is locked until malware scanning completes.
                        </p>
                    )}
                    {post.body && (
                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                            {post.body}
                        </p>
                    )}
                    {post.attribution && (
                        <p className="text-xs text-muted-foreground">
                            - {post.attribution}
                        </p>
                    )}
                    {post.scheduled_at && (
                        <p className="text-xs text-muted-foreground">
                            Release: {formatDateTime(post.scheduled_at)}
                        </p>
                    )}
                </>
            )}

            {!editing && (
                <div className="flex flex-wrap gap-2 pt-1">
                    <Button size="sm" variant="outline" onClick={beginEdit}>
                        <Pencil className="size-4" aria-hidden="true" />
                        Edit
                    </Button>
                    {post.status !== 'published' && (
                        <Button
                            size="sm"
                            onClick={() => action(post.id, 'publish')}
                            disabled={post.image_is_quarantined}
                        >
                            Publish
                        </Button>
                    )}
                    {post.status === 'published' &&
                        !isFutureScheduled(post) &&
                        (post.pinned ? (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => action(post.id, 'unpin')}
                            >
                                <PinOff className="size-4" aria-hidden="true" />
                                Unpin
                            </Button>
                        ) : (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => action(post.id, 'pin')}
                            >
                                <Pin className="size-4" aria-hidden="true" />
                                Pin
                            </Button>
                        ))}
                    {post.status !== 'archived' && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => action(post.id, 'archive')}
                        >
                            Archive
                        </Button>
                    )}
                </div>
            )}
        </SectionCard>
    );
}
