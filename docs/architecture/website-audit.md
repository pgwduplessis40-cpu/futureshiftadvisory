# Website Audit Module

WO-45 adds the Phase 2 website audit adapter on the shared analysis spine.

## Module Shape

`WebsiteAudit` implements `AnalysisModule` with prompt id
`analysis.website_audit`. The shared `AnalysisRunner` still owns data-quality,
document-verification, prompt, AI, attribution, bias, red-flag, and audit rules.

The module emits all four lenses:

- Descriptive: whether website audit evidence and a nominated URL/domain exist.
- Diagnostic: product/service content accuracy, SEO, GEO (generative-engine
  optimisation), AEO (answer-engine optimisation), AIO (AI-overview / AI-search
  optimisation), structured-data extractability, UX, calls to action, mobile
  usability, and NZ-local search gaps.
- Predictive: risk to future NZ search, answer-engine, generative-engine, and
  AI-search visibility for the products/services the client actually sells.
- Prescriptive: a practical action plan for accurate product/service pages,
  structured data, answer blocks, mobile, NZ-local search, and enquiry CTAs.

## Evidence And Citations

WO-45 does not crawl websites or run live search-ranking checks. It uses
advisor/client-supplied website and product/service evidence from questionnaire
answers, then checks whether the supplied website content appears aligned to what
the client says it sells and whether the content is likely to be machine-readable
for search, answer, and AI surfaces. It cites each answer as:

- `questionnaire_answer:{id}`

If no website-specific answer exists, the module falls back to the client profile
as the audit subject, but data-quality/document gates still decide whether the run
can proceed.

## Gate Behaviour

Website audit runs through the same document-verification gate as every Phase 2
analysis. Outstanding `advisory_flag` or `accuracy_discrepancy` rows block the
run before AI is called or findings are persisted.

## Downstream Flow

Diagnostic website-audit findings render into the client report's "What is
wrong" section. Prescriptive website-audit findings can flow into proposal focus
areas, render under "What needs to be fixed", and carry into signed-proposal
strategic plan priorities and milestones.

## Boundaries

WO-45 does not implement continuous website monitoring, live SEO crawling, live
GEO/AEO/AIO testing, or cross-client industry alerts. Those remain future work.
WO-45 adds no schema.
