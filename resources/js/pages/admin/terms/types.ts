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
    clauses_count?: number;
    material_clauses_count?: number;
    clauses: TermsClause[];
};
