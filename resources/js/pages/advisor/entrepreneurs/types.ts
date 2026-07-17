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
    invite_expires_at: string | null;
    invite_delivery_label: string;
    invite_update_url: string | null;
    invite_resend_url: string | null;
    invite_cancel_url: string | null;
    intended_package_scope: string;
    intended_package_scope_label: string;
    created_at: string | null;
    documents: EntrepreneurDocument[];
    messages: EntrepreneurMessageSummary;
    gamification: EntrepreneurGamificationPayload;
    latest_plan: {
        id: string;
        title: string;
        status: string;
        assessment_count: number;
        latest_round: number | null;
        latest_grade: string | null;
        latest_assessment: {
            id: string;
            round: number;
            status: string;
            overall_grade: string;
            weighted_score: number;
            finalised_at: string | null;
            rating_framework: {
                id: string | null;
                version: number | null;
                criteria_count: number;
                published_at: string | null;
                is_current: boolean;
                current_version: number | null;
                current_criteria_count: number | null;
                current_published_at: string | null;
                current_has_budget: boolean;
            };
            url: string;
            finalise_url: string;
        } | null;
        budget: {
            status: string;
            expected_runway_months: number | null;
            calculated_runway_months: number | null;
            runway_open_ended: boolean;
            break_even_month: number | null;
            available_after_launch: number | null;
            active_flags: {
                key: string;
                title: string;
                message: string;
                severity: string;
            }[];
        };
        assess_url: string;
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
    readiness: {
        completed: boolean;
        score: number | null;
        outcome: string | null;
        assessed_at: string | null;
        action_label: string;
        action_url: string;
    };
    idea_validation: {
        id: string;
        revision_number: number;
        summary: string;
        problem: string;
        target_customer: string;
        solution: string;
        value_proposition: string;
        demand_signal: string;
        revenue_model: string;
        proposed_change_request: string;
        viability_alerts: {
            message?: string;
            severity?: string;
            type?: string;
            blocking?: boolean;
        }[];
        viability_gate: {
            status: 'red' | 'amber' | 'green';
            label: string;
            summary: string;
            reasons: string[];
            approval_available: boolean;
        };
        uncertainty: string | null;
        past_plan_pattern: {
            source_reference?: string;
            cohort?: number;
            industry?: string;
            note?: string;
        };
        evaluated_at: string | null;
        ai_deferred: boolean;
        advisor_gate_status:
            | 'approved'
            | 'changes_requested'
            | 'recalled'
            | 'gate_needed'
            | string;
        change_request_note: string | null;
        changes_requested_at: string | null;
        recalled_at: string | null;
        restored_from_revision_number: number | null;
        refresh_status: 'queued' | 'completed' | 'failed' | string | null;
        refresh_stale: boolean;
        refresh_requested_at: string | null;
        refresh_started_at: string | null;
        refresh_completed_at: string | null;
        refresh_failed_at: string | null;
        refresh_failure: string | null;
        advisor_gate_passed_at: string | null;
        advisor_gate_note: string | null;
        gate_url: string;
        request_changes_url: string;
        refresh_url: string;
    } | null;
    advisory_readiness: {
        id: string;
        score: number;
        surfaced_at: string | null;
    } | null;
    reports: {
        id: string;
        title: string;
        generated_at: string | null;
        view_url: string;
        download_url: string;
    }[];
    conversion: {
        available: boolean;
        converted: boolean;
        client_id: string | null;
        convert_url: string;
    };
};

export type EntrepreneurGamificationPayload = {
    enabled: boolean;
    toggle_url: string;
    current_level?: {
        stage: string;
        stage_label: string;
        phase: number | null;
        label: string;
    };
    plan_completion?: {
        total: number;
        completed: number;
        percent: number;
    };
    current_streak?: number;
    last_active_at?: string | null;
    new_badge_count?: number;
    badges?: {
        id: string;
        key: string;
        label: string;
        earned_at: string | null;
        earned_at_estimated: boolean;
        seen_at: string | null;
    }[];
    next_milestone?: {
        key: string;
        label: string;
        progress_percent: number;
    } | null;
};

export type EntrepreneurDocument = {
    id: string;
    original_filename: string;
    category: string;
    scanner_result: string;
    uploaded_at: string | null;
    uploaded_by_name: string | null;
    url: string;
};

export type EntrepreneurMessageSummary = {
    threads_count: number;
    unread_count: number;
    latest_activity_at: string | null;
    url: string;
};

export type CriterionDelta = {
    criterion_number: number;
    criterion_name: string;
    previous_score: number | null;
    current_score: number;
    delta: number;
    direction: string;
};

export type ServiceOption = {
    value: string;
    label: string;
    description: string;
};
