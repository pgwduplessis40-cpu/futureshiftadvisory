export type CapacitySummary = {
    active_count: number;
    limit: number;
    warning_threshold: number;
    remaining: number;
    warning: boolean;
    blocked: boolean;
};

export type EntrepreneurSummary = {
    id: string;
    name: string;
    email: string;
    stage: string;
    stage_label: string;
    assigned_advisor_name: string | null;
};

export type EntrepreneurDetail = EntrepreneurSummary & {
    concept_summary: string | null;
    user_id: number | null;
    invite_accepted_at: string | null;
    created_at: string | null;
};
