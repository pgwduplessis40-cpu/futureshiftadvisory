export type CategoryOption = {
    value: string;
    label: string;
};

export type ClientOption = {
    id: string;
    label: string;
};

export type KnowledgeEntrySummary = {
    id: string;
    title: string;
    category: string;
    category_label: string;
    body_excerpt: string;
    tags: string[];
    client: {
        id: string;
        legal_name: string;
    } | null;
    search_rank: number | null;
    updated_at: string | null;
    show_url: string;
    edit_url: string;
};

export type KnowledgeEntryDetail = KnowledgeEntrySummary & {
    body: string;
    author_name: string | null;
    created_at: string | null;
    update_url: string;
    delete_url: string;
    tags_string?: string;
};

export type KnowledgeDraftSummary = {
    id: string;
    title: string;
    category: string;
    category_label: string;
    body_excerpt: string;
    tags: string[];
    client: {
        id: string;
        legal_name: string;
    } | null;
    state: string;
    source_reference: string | null;
    updated_at: string | null;
    review_url: string;
    discard_url: string;
};

export type KnowledgeDraftDetail = KnowledgeDraftSummary & {
    client_id: string | null;
    body: string;
    tags_string?: string;
    source_attribution: Record<string, unknown>;
};

export type KnowledgeFormData = {
    client_id: string;
    category: string;
    title: string;
    body: string;
    tags: string;
};
