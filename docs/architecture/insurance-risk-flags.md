# Insurance Risk Flags

WO-52 adds the insurance risk flag module on the shared analysis spine.

## Module Shape

`InsuranceRiskFlags` implements `AnalysisModule` with prompt id
`analysis.insurance_risk` and module enum `insurance_risk`.

The module emits four findings:

- insurance evidence captured
- insurance coverage gaps
- insurance exposure trajectory
- insurance remediation actions

## Evidence And Verification

Insurance evidence comes from questionnaire answers and verified insurance
certificate documents. Citations use:

- `questionnaire_answer:{id}`
- `document:{id}`

Clean `insurance_certificate` documents whose verification rows are all
`verified` stamp findings with `document_support=verified`. Outstanding advisory
flags or discrepancies still block the run through the shared analysis spine.

## Gap Detection

The module flags:

- missing or low public liability coverage
- missing professional indemnity evidence
- missing key person evidence
- expired certificate evidence

Flags are recorded as governed analysis findings for future broker-referral
workflows. WO-52 does not create broker referrals.

## Boundaries

Broker referral workflow and referral consent remain Phase 3.
