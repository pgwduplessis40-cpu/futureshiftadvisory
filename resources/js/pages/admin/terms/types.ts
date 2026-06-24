export type TermsClause = {
    id: string;
    clause_number: number;
    title: string;
    body: string;
    material: boolean;
};

export type TermsVersion = {
    id: string;
    version: string;
    title: string;
    material: boolean;
    notice_period_days: number;
    reviewer_reference: string | null;
    published_at: string | null;
    published_by_user_id: number | null;
    source_file: {
        original_name: string;
        mime_type: string;
        byte_size: number;
        uploaded_at: string;
    } | null;
    source_download_url: string | null;
    clauses_count?: number;
    material_clauses_count?: number;
    clauses: TermsClause[];
};

export type TermsEnforcementState = {
    active: boolean;
    activated_at: string | null;
    activated_by: {
        id: number;
        name: string;
    } | null;
    can_activate: boolean;
    latest_published_version: {
        id: string;
        version: string;
        published_at: string | null;
    } | null;
};
