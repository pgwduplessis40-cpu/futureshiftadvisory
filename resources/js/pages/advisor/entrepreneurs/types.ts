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
    latest_plan: {
        id: string;
        title: string;
        status: string;
        assessment_count: number;
        latest_round: number | null;
        latest_grade: string | null;
        latest_revision: {
            id: string;
            round: number;
            submitted_at: string | null;
            trajectory_percent: number | null;
            overall_delta: number | null;
            biggest_improvements: CriterionDelta[];
            remaining_gaps: CriterionDelta[];
        } | null;
    } | null;
};

export type CriterionDelta = {
    criterion_number: number;
    criterion_name: string;
    previous_score: number | null;
    current_score: number;
    delta: number;
    direction: string;
};
