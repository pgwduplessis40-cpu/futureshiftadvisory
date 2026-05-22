# Report engine

WO-57 introduces the shared report composer plus the first two report types:
Client and Advisor. WO-58 fills the Stakeholder profile and PowerPoint export.
WO-59 fills the Trajectory profile and advisor review gate. Due-diligence and
entrepreneur assessment report types remain forward-compatible enum values for
Phase 3.

## Composition

`ReportComposer` reads persisted `analysis_findings`, the current PV waterfall,
latest valuation row, and latest proposal. It writes a `reports` header and
ordered `report_sections`, then renders a branded PDF through the shared
`PdfRenderer` contract onto the encrypted `secure_local` disk.

Every section carries:

- at least one attribution with a `source_reference`
- `document_support` plus a rendered document-support note
- a data-quality note, either inherited from the finding disclaimer or generated
  for platform-derived sections

The composer creates generated sections for valuation, PV waterfall,
implementation plan, and proposal ROI using deterministic persisted data rather
than new AI output.

## Type Rules

Client reports include the current valuation range and non-prescriptive findings
only. They deliberately exclude recommendations, implementation plan content,
fee detail, and proposal ROI.

Advisor reports include the full finding set, PV waterfall, implementation plan,
and latest fee proposal with ROI.

## Surfaces

The advisor client detail page can generate Client or Advisor reports and shows
recent generated reports. The client portal exposes only `client` report
summaries. PDF download endpoints are left for a later export/read-tracking
slice; WO-57 stores the branded PDF artifact and metadata.

## RLS

`reports` and `report_sections` are client-scoped tables. PostgreSQL RLS allows
`system`/`super_admin` or rows whose `client_id` is present in
`fsa_current_client_ids()`.
