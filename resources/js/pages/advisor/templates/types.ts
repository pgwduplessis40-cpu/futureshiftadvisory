export type TemplateOption = {
    value: string;
    label: string;
};

export type TemplateSummary = {
    id: string;
    category: string;
    category_label: string;
    title: string;
    body_excerpt: string;
    status: string;
    version: number;
    source_reference: string | null;
    uploaded_file: {
        original_name: string;
        mime_type: string;
        extension: string;
        byte_size: number;
        uploaded_at: string | null;
    } | null;
    download_url: string | null;
    updated_at: string | null;
    show_url: string;
    update_url: string;
};

export type TemplateDetail = TemplateSummary & {
    body: string;
    structure: Record<string, unknown> | null;
    creator_name: string | null;
    created_at: string | null;
    learning_update_implementation_id: string | null;
};

export type TemplateFormData = {
    category: string;
    title: string;
    body: string;
    status: string;
    file: File | null;
};
