# HR And People Analysis

WO-48 adds the HR and people analysis adapter on the shared analysis spine.

## Module Shape

`HrAnalysis` implements `AnalysisModule` with prompt id `analysis.hr`. It reads
questionnaire-supplied HR evidence, verified HR documents, and wage benchmarks
from `economic_indicators`.

The module emits four findings:

- HR evidence and staff structure
- wage compliance benchmark
- Holidays Act liability exposure
- people remediation plan

## Wage Benchmarking

The module reads the latest `minimum_wage` and `living_wage` economic indicators.
Findings cite benchmark rows as:

- `economic_indicator:{id}:minimum_wage`
- `economic_indicator:{id}:living_wage`

Supplied hourly rates below the current minimum wage are severity high.

## Document Cross-Reference

Verified HR documents are clean `documents` rows in category `hr_record` whose
verification rows are all `verified`. When present, HR findings are stamped with
`document_support=verified` and cite:

- `document:{id}`

Outstanding advisory flags or discrepancies still block the run through the
shared document-verification gate before any finding is persisted.

## Holidays Act Liability

`HolidaysActLiabilityCalculator` quantifies estimated remediation as:

- underpaid hours times hourly remediation rate
- plus a configurable remediation buffer, defaulting to 15 percent

The calculator is deterministic and does not replace legal review; it gives the
advisor a quantified exposure to validate.

## Boundaries

WO-48 adds no schema. It does not create employment-law tasks or referrals, and
it does not bypass legal/advisor review of Holidays Act calculations.
