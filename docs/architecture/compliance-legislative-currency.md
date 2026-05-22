# NZ Compliance Checker And Legislative Currency

WO-50 adds the NZ compliance checker and legislative-currency monitor.

## Compliance Checker

`ComplianceChecker` implements `AnalysisModule` with prompt id
`analysis.compliance`. It checks evidence against:

- Employment Relations Act 2000
- Health and Safety at Work Act 2015
- Holidays Act 2003
- Privacy Act 2020
- Companies Act 1993

Findings are severity-rated and cite both client evidence and statute references,
for example:

- `questionnaire_answer:{id}`
- `document:{id}`
- `statute:nz:era`
- `statute:nz:hswa`

Verified compliance, HR, contract, and insurance documents stamp findings with
`document_support=verified`. Outstanding advisory flags or discrepancies still
block through the shared document-verification gate before findings are written.

## Legislative Currency

`LegislativeCurrencyMonitor` reads legislative-change feed contracts from:

- NZ Parliament
- WorkSafe
- IRD

The monitor records layer id `14` in `learning_layer_runs`. Each new change
creates a governed `learning_updates` candidate with:

- `status=detected`
- `proposed_change.action=review_compliance_checker_statute_currency`
- `automatic_application=false`

No code path auto-applies legislative updates or creates implementation rows.

## Command

`legislative-currency:monitor` runs the monitor and is deterministic under
`--ran-at` for test and operational replay.

## Boundaries

WO-50 does not implement DD regulatory workstreams or apply legislative changes
to analysis outputs automatically.
