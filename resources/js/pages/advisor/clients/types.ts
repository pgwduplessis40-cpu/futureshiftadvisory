export type ClientSummary = {
    id: string;
    engagement_type: string;
    engagement_type_label: string;
    nzbn: string | null;
    legal_name: string;
    trading_name: string | null;
    entity_type: string | null;
    gst_registered: boolean;
    filing_status: string | null;
    data_quality: string;
};

export type RegistryLookup = {
    lookup_key: string;
    summary: {
        legal_name: string | null;
        entity_type: string | null;
        status: string | null;
        gst_registered: boolean;
        directors: Array<Record<string, string | null>>;
        filing_status: string | null;
    };
    source_badges: Record<string, string>;
    degraded: boolean;
};

export type EngagementTypeOption = {
    value: string;
    label: string;
    description: string;
};
