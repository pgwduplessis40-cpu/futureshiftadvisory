# Entrepreneur Module — founding rating criteria

This folder holds the founding rating matrix used by the Entrepreneur Module assessment engine. The matrix is owner-supplied and forms the seed configuration for the admin-managed rating framework described in [spec §17.4](../spec) and [Appendix C](../spec).

## Status

**Present.** `Business_Plan_Rating_Matrix.pdf` is in this folder (owner-supplied 2026-05-23). Risk **P3-R3** (PDF missing) is cleared. The structured transcription the WO-87b seeder uses is [`founding-rating-matrix.md`](./founding-rating-matrix.md).

## Files

- `Business_Plan_Rating_Matrix.pdf` — canonical source ("Annexure A: Rating Sheet of All-inclusive Business Plan"), authored by the principal advisor.
- `founding-rating-matrix.md` — authoritative machine-friendly transcription the WO-87b seeder loads.

## ⚠️ The PDF differs from spec §17.6 / Appendix C — owner confirmation needed (P3-R3a)

The spec listed **11 "founding criteria"** (Type of business, Location, … Legal Environment). The actual PDF shows those 11 are the **supporting aspects of one 5%-weighted main aspect, "Business Overview"** — not the whole framework. The real rating sheet scores **10 weighted Main Aspects** (sum = 100%), each on a 4-point scale (30/50/60/80% = 1/2/3/4):

| # | Main Aspect | Weight |
|---|---|---|
| 1 | Executive Summary | 5% |
| 2 | Product / Service | 11% |
| 3 | Competitive Analysis | 11% |
| 4 | Risk Analysis | 11% |
| 5 | Market Analysis | 11% |
| 6 | Sales & Marketing | 11% |
| 7 | Management Team | 10% |
| 8 | Operational Plan | 10% |
| 9 | Financial Plan | 15% |
| 10 | Business Overview | 5% |

WO-87a builds the engine to the **actual** matrix; WO-87b seeds these values **after** the owner confirms whether to follow the real PDF (recommended) or the spec's narrower 11-item list. Full breakdown + supporting aspects in `founding-rating-matrix.md`.

All values are **admin-managed**, not hardcoded. Once seeded, they evolve through the governed learning update process — never silent updates. Three learning dimensions apply (spec §17.4): criterion weighting evolution, scoring descriptor calibration, industry-specific rating variants.

## Confidentiality

This document is Future Shift Advisory proprietary methodology. Do not duplicate or share outside the platform.
