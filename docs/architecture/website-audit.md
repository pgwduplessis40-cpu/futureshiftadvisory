# Website Audit Module

WO-45 adds the Phase 2 website audit adapter on the shared analysis spine.

## Module Shape

`WebsiteAudit` implements `AnalysisModule` with prompt id
`analysis.website_audit`. The shared `AnalysisRunner` still owns data-quality,
document-verification, prompt, AI, attribution, bias, red-flag, and audit rules.

The module emits all four lenses:

- Descriptive: whether website audit evidence and a nominated URL/domain exist.
- Diagnostic: SEO, content clarity, UX, calls to action, mobile usability, and
  NZ-local search gaps.
- Predictive: risk to future NZ search visibility and website lead generation.
- Prescriptive: a practical action plan for mobile, content, NZ-local search,
  and enquiry CTAs.

## Evidence And Citations

WO-45 does not crawl websites or run live search-ranking checks. It uses
advisor/client-supplied website evidence from questionnaire answers and cites each
answer as:

- `questionnaire_answer:{id}`

If no website-specific answer exists, the module falls back to the client profile
as the audit subject, but data-quality/document gates still decide whether the run
can proceed.

## Gate Behaviour

Website audit runs through the same document-verification gate as every Phase 2
analysis. Outstanding `advisory_flag` or `accuracy_discrepancy` rows block the
run before AI is called or findings are persisted.

## Boundaries

WO-45 does not implement continuous website monitoring, live SEO crawling, or
cross-client industry alerts. Those remain future work. WO-45 adds no schema.
