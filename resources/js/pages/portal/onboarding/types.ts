export type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type: string;
    engagement_type_label: string;
    data_quality: string;
    nzbn: string | null;
    entity_type: string | null;
    gst_registered: boolean;
    filing_status: string | null;
};

export type WizardStep = {
    number: number;
    slug: string;
    title: string;
    description: string;
    href: string;
    completed: boolean;
    locked: boolean;
    status: 'completed' | 'current' | 'locked';
};

export type WizardState = {
    current_step: number;
    completed_steps: string[];
    steps: Record<string, Record<string, unknown>>;
    submitted_at: string | null;
    updated_at: string | null;
};

export type Progress = {
    completed: number;
    total: number;
    percentage: number;
};

export type Questionnaire = {
    set: string;
    title: string;
    available: boolean;
    phase: string;
    description: string;
};
