export type Source = {
    kind: string;
    id: string;
    location: string;
    version: string;
};
export type Fact = {
    key: string;
    value: string | number | boolean | null;
    entity: string;
    period: string;
    scenario: string;
    unit: string | null;
    basis: string | null;
    as_of: string | null;
    confirmed: boolean;
    sources: Source[];
};
export type Subcheck = {
    id: string;
    result: string;
    severity: string;
    message: string;
    compared: Fact[];
    missing_inputs: string[];
    scope_reason: string | null;
};
export type RuleResult = {
    enabled: boolean;
    result: string | null;
    rule: {
        id: string;
        name: string;
        severity: string;
        minimum_severity: string;
        override_policy: string;
    };
    subchecks: Subcheck[];
};
export type Configuration = {
    thresholds: Record<string, string>;
    severities: Record<string, string>;
    messages: Record<string, string>;
};
/**
 * A confirmed statement the gate is holding.
 *
 * `applies` is false once the plan answer it was confirmed against has been
 * edited: the claim stays on the record but the gate stops reading it, and
 * without this the advisor sees a saved claim and a rule reporting its
 * evidence missing with no way to connect the two.
 */
export type ConfirmedClaim = {
    key: string;
    value: string | number | boolean | null;
    entity: string;
    period: string;
    unit: string | null;
    basis: string | null;
    as_of: string | null;
    revision: number;
    section_title: string | null;
    applies: boolean;
    confirmed_by: string | null;
    confirmed_at: string | null;
    reason: string;
};
export type GateProps = {
    run_id: string;
    can_accept: boolean;
    can_confirm: boolean;
    can_configure: boolean;
    sections: { id: string; title: string; hash: string }[];
    claims: ConfirmedClaim[];
    configuration: Configuration;
    urls: {
        review: string;
        decision: string;
        claim: string;
        policy: string;
        workspace: string;
    };
    report: {
        document: string;
        configuration_hash: string;
        deferred: string[];
        results: RuleResult[];
        findings: {
            rule_id: string;
            subcheck: Subcheck;
            severity: string;
            accepted: boolean;
        }[];
    };
};
