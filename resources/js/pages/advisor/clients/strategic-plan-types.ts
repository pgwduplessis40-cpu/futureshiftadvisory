export type StrategicPlanSection = {
    key: string;
    title: string;
    body: string;
};

export type StrategicPlanEvidenceSource = {
    key: string;
    category: string;
    label: string;
    materiality: 'low' | 'medium' | 'high';
};

export type StrategicPlanEvidenceBinding = {
    source_key: string;
    target_key: string;
    disposition: 'supports' | 'out_of_scope';
    rationale: string;
    source_hash?: string;
    confirmed_at?: string;
};

export type StrategicPlanEvidenceSummary = {
    bindings: number;
    stale_source_signals: number;
    untraced_sources: number;
    intentional_exclusions: number;
};

export type StrategicPlanMilestone = {
    id: string;
    title: string;
    description: string | null;
    owner: 'client' | 'advisor' | 'joint';
    owner_label: string;
    due_offset_days: number;
    due_date: string | null;
    status: 'pending' | 'in_progress' | 'completed' | 'blocked';
    status_label: string;
    progress_percent: number;
    evidence_notes: string | null;
    advisor_notes: string | null;
    metric_label: string | null;
    measurement_unit: string | null;
    target_direction: 'increase' | 'decrease' | null;
    baseline_value: number | null;
    target_value: number | null;
    actual_value: number | null;
    measurement_updated_at: string | null;
    outcome_update_url?: string;
};

export type StrategicPlanSummary = {
    id: string;
    title: string;
    status: string;
    status_label: string;
    duration_months: number;
    duration_label: string;
    complexity_band: string;
    complexity_label: string;
    duration_rationale: string[];
    summary: string | null;
    sections: StrategicPlanSection[];
    evidence_bindings: StrategicPlanEvidenceBinding[];
    evidence_sources: StrategicPlanEvidenceSource[];
    evidence_summary: StrategicPlanEvidenceSummary | null;
    generated_at: string | null;
    deployed_at: string | null;
    progress_percent: number;
    completed_milestones: number;
    total_milestones: number;
    milestones: StrategicPlanMilestone[];
    pdf_url: string;
    update_url: string;
    deploy_url: string;
};

export type StrategicPlanForm = {
    summary: string;
    sections: StrategicPlanSection[];
    evidence_bindings: Array<
        Omit<StrategicPlanEvidenceBinding, 'source_hash' | 'confirmed_at'>
    >;
    confirm_current_sources: boolean;
    milestones: Array<
        Omit<
            StrategicPlanMilestone,
            | 'description'
            | 'advisor_notes'
            | 'metric_label'
            | 'measurement_unit'
            | 'target_direction'
            | 'baseline_value'
            | 'target_value'
            | 'actual_value'
        > & {
            description: string;
            advisor_notes: string;
            metric_label: string;
            measurement_unit: string;
            target_direction: 'increase' | 'decrease';
            baseline_value: number | '';
            target_value: number | '';
            actual_value: number | '';
        }
    >;
};
