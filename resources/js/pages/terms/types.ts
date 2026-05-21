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
    clauses: TermsClause[];
};
