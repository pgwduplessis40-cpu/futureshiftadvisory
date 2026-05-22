# Founding Rating Matrix — structured transcription

> Authoritative transcription of `Business_Plan_Rating_Matrix.pdf` ("Annexure A: Rating Sheet of All-inclusive Business Plan"). This is the source the **WO-87b** seeder loads into `rating_frameworks` / `rating_criteria`. The PDF remains the canonical source; this file is the machine-friendly extraction.

## ⚠️ Discrepancy with spec §17.6 / Appendix C — owner confirmation needed

Spec V2.4 §17.6 / Appendix C lists **11 "founding criteria"**: Type of business, Location, Means of doing business, Discuss the industry, What sets the business apart from its competitors, Describe unique success factors, Mission and Vision statement, Intellectual property, Goals and objectives, Culture, Legal Environment.

The **actual PDF** shows those 11 items are the **supporting aspects of one 5%-weighted main aspect — "Business Overview"** — not the whole framework. The real rating sheet scores **10 weighted Main Aspects** (summing to 100%), each rated on a 4-point scale across multiple supporting aspects.

**Action:** WO-87a builds the engine to the *actual* matrix structure below. Before entrepreneur go-live (WO-87b), the owner must confirm whether the framework should follow the **real PDF** (10 weighted aspects — recommended, it's the source document) or the **spec's narrower 11-item list**. Tracked as risk **P3-R3a**.

## Rating scale (per supporting aspect)

A 4-point scale; each point maps to a percentage score:

| Rating | Score |
|---|---|
| 1 | 30% |
| 2 | 50% |
| 3 | 60% |
| 4 | 80% |

## Scoring model

For each Main Aspect, the assessor rates its supporting aspects on the 1–4 (30/50/60/80%) scale. The aspect's mean (or assessor-entered) rating × the aspect **Weighting** contributes to the overall **Success Achieved %**. The ten weightings sum to 100%.

## Main Aspects, weightings, and supporting aspects

### 1. Executive Summary — 5%
- Fundamentals of the proposed business.
- What is the product/service being supplied?
- Who is the customer?
- Who is the owners of the business?
- What does the future hold for the business and the industry?
- Funding required?

### 2. Product / Service — 11%
- The Competitive Advantage?
- Explain the uniqueness of product/service.
- Details concerning the product life cycle.
- The way it will affect the customer.
- Research and development activities planned.
- Key technologies employed.
- Information of quality assurance systems and procedures, and certification.

### 3. Competitive Analysis — 11%
- Analysis of the SIX Forces.
- Analysing the strength and weaknesses of direct and indirect competitors.
- Identify own competitive advantages.
- Describe the competition by listing them in a competitor matrix.
- Describe shortly potential future plans — reflects where the business will be heading to in the long run.
- Innovation — highlight the way in which the business will be original and ground-breaking.
- Skills Development plan.

> Page-break note: the last three items (future plans / innovation / skills development) sit at the page-1→2 boundary; they read as Competitive Analysis but the owner should confirm during WO-87b.

### 4. Risk Analysis — 11%
- List key assumptions and risks associated with the business.
- Analysis of the company's weaknesses and threats.
- Describe anticipated events or conditions that could affect the business success.
- Describe the steps to be taken to limit the impact of the aforementioned events/conditions.
- Identify and isolate areas of business plan where something could go wrong.
- PESTEL Analysis.

### 5. Market Analysis — 11%
- Specific data and charts/graphs.
- What market will be pursued?
- Who will the customer be?
- How will the customer be attracted?
- How will the customer be retained?
- Customer's need that needs to be solved?
- Identify the Total Available Market (TAM).
- Identify the Segmented Addressable Market (SAM).
- Identify the Share of the Market (SOM).

### 6. Sales & Marketing — 11%
- Explain the desired brand positioning.
- Promotional plan — company/product awareness.
- Pricing — message to customer.
- Social media strategy.
- Market penetration strategy.
- Growth strategy once market has been penetrated.
- Distribution plan.

### 7. Management Team — 10%
- Pertinent, concise background information on all key personnel.
- Prove why key personnel are eminently "qualified" to execute the business model.
- Employee engagement tactics.
- A commitment to follow the business plan.
- Business Organigram.
- Are standard operating procedures and job descriptions drafted?
- Succession plan.
- Continuation plan for key personnel.
- Biographical data / Knowledge of Actor.

### 8. Operational Plan — 10%
- How will the business function, including physical setup?
- Responsibilities for specific tasks.
- Strategy and specific actions planned to be implemented.
- Detail key operational processes to be accomplished daily to achieve success.
- Identify milestones that needs to be accomplished over the next 1–3 years to achieve success.
- Metrics — the numbers that will be reviewed on a regular basis to judge the health of the business.
- A contingency plan that allows one to make any necessary business-model changes should it be required.
- Preparation for success — account for highly successful, best-case scenarios.
- An exit strategy — provide for should the business be sold later on.
- Production — explain production techniques, costs, quality control, customer service, inventory control, production development.
- Inventory policy.
- Identify key suppliers — history, reliability and list terms.
- Credit policies.
- Facilities and equipment required.

### 9. Financial Plan — 15%
- Income Statement (3–5 years) + ratios.
- Provide for contingencies — should equate to 20% of all start-up expenses.
- Projected Balance Sheet.
- Cash Flow Statement (3–5 years).
- Break-even analysis.
- Funds Required.
- Highlight the assumptions which govern the estimations/projections.
- Sales forecast (3–5 years).
- Employee plan — detail on how much employees will be paid.

### 10. Business Overview — 5%
*(These supporting aspects are what spec §17.6 / Appendix C mislabelled as "the 11 founding criteria".)*
- Legal Structure.
- Business formation history.
- Type of business.
- Location.
- Means of doing business.
- Discuss the industry.
- What sets the business apart from its competitors.
- Describe unique success factors.
- Mission and Vision statement.
- Intellectual property to be listed.
- List goals and objectives.
- Culture.
- Legal Environment — Licenses, permits, health and environmental regulations, special regulations w.r.t industry, zoning/building requirements, trademarks, copyrights, pending patents, patents purchased, insurance coverage.

## Notes for the WO-87b seeder

- Seed the 10 Main Aspects as the weighted scoring criteria (`rating_criteria.number` 1–10, `weight` as above), each with its supporting aspects (store under a `supporting_aspects` jsonb on the criterion, or as child rows — implementer's choice).
- The 4-point band scale (30/50/60/80%) is the scoring descriptor scale; capture it in `rating_criteria.descriptors` or framework config.
- Everything stays **admin-managed** — the seeder writes the founding configuration; admins edit it thereafter via the WO-87a editor; learning-driven changes go through the governed queue.
- Clear the WO-87a `is_placeholder` flags and set `rating_frameworks.production_ready = true` only after the owner confirms the spec-vs-PDF reconciliation (P3-R3a).
