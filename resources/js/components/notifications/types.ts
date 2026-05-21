export type NotificationItem = {
    id: string;
    type: string;
    title: string;
    message: string | null;
    url: string | null;
    urgency: 'normal' | 'urgent' | string;
    read_at: string | null;
    created_at: string | null;
    channel_decision: Record<string, unknown>;
    mark_read_url: string;
};

export type NotificationSummary = {
    unread: number;
    urgent: number;
    latest: NotificationItem[];
    index_url: string;
    mark_all_read_url: string;
};
