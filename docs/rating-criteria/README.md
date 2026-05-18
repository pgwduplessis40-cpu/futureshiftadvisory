# Entrepreneur Module — founding rating criteria

This folder holds the founding rating matrix used by the Entrepreneur Module assessment engine. The matrix is owner-supplied and forms the seed configuration for the admin-managed rating framework described in [spec §17.4](../spec) and [Appendix C](../spec).

## Status

**Placeholder.** Owner-supplied PDF not yet present. Phase 1 does not consume this asset — full Entrepreneur Module is Phase 3 work. Drop the file in any time before Phase 3 begins.

## Required file

`Business_Plan_Rating_Matrix.pdf` — the source rating matrix authored by the principal advisor.

## What gets read from this PDF

When Phase 3 (WO TBD, in Phase 3 plan) implements the rating framework, the seeder will read this PDF and persist:

1. The 11 founding criteria (already enumerated in [`PLAN.md` Appendix A spec §17 row](../../PLAN.md#17-entrepreneur-module--new-in-version-24) and spec §17.6):
   1. Type of business
   2. Location
   3. Means of doing business
   4. Discuss the industry
   5. What sets the business apart from its competitors
   6. Describe unique success factors
   7. Mission and Vision statement
   8. Intellectual property
   9. Goals and objectives
   10. Culture
   11. Legal Environment (licences, permits, health/environmental regulations, industry-specific regulations, zoning/building, trademarks, copyrights, patents, insurance coverage)
2. Default weightings per criterion.
3. Scoring descriptors per band (Exceptional / Strong / Developing / Needs Work).
4. Any industry-specific variants the principal advisor encodes.

All values seeded from the PDF are **admin-managed**, not hardcoded. Once seeded, they evolve through the governed learning update process — never silent updates. Three learning dimensions apply (spec §17.4): criterion weighting evolution, scoring descriptor calibration, industry-specific rating variants.

## Confidentiality

This document is Future Shift Advisory proprietary methodology. Do not duplicate or share outside the platform.
