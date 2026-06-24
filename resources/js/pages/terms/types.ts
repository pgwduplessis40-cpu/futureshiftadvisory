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
    published_at: string | null;
    source_preview_html: string | null;
    clauses: TermsClause[];
};
