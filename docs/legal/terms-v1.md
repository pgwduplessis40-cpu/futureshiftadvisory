# Terms and Conditions — Version 1

> **Status:** PLACEHOLDER. Final 14-clause text to be supplied by the owner and reviewed by a qualified NZ commercial lawyer before WO-11 acceptance. This file documents the **required clause structure** (per spec §18) so the database schema, seeder, and acceptance gate can be built against a known shape. Replace the placeholder body of each clause with the lawyer-reviewed text before publishing version 1 to any user.

**Document metadata** (populated when the lawyer-reviewed text is ready):

| Field | Value |
|---|---|
| Version | 1 |
| Material | true (initial publish) |
| Effective date | _to be set on publish_ |
| Published by | Future Shift Advisory — Principal Advisor |
| Notice period | 30 days (material) |
| Review reference | _NZ commercial lawyer name, firm, review date_ |

---

## Clause 1 — Acceptance

_Placeholder. Lawyer-reviewed text required._

The acceptance of these terms is recorded by the platform when the user scrolls to the end of the document, clicks Accept, and a signed PDF is generated. By accepting, the user agrees to be bound by these terms in their entirety. Refusal to accept results in account suspension (the account is preserved, not deleted; the user may return and accept at any time).

## Clause 2 — Nature of the advisory service

_Placeholder. Lawyer-reviewed text required._

Future Shift Advisory provides business advisory services to NZ SMEs across four engagement types (Standard Advisory, Due Diligence, Post-Acquisition Advisory, Entrepreneur Module). Outputs are advisory and supportive only and do not constitute legal, tax, accounting, or investment advice.

## Clause 3 — Client obligations

_Placeholder. Lawyer-reviewed text required._

The client agrees to provide truthful, complete, and timely information; to comply with platform security requirements (MFA, invite-only access); to declare any conflicts of interest; and to acknowledge that the quality of advisory outputs depends on the quality of inputs.

## Clause 4 — Scope of services

_Placeholder. Lawyer-reviewed text required._

Scope is defined by the signed engagement proposal. Services outside the scope require a new or amended proposal. The platform may decline a service request that falls outside professional scope or that raises an unmanaged conflict of interest.

## Clause 5 — Limitation of liability (with CGA contracting-out)

_Placeholder. Lawyer-reviewed text required. **Special attention from the reviewing lawyer.**_

Both parties operate in trade for the purposes of the Consumer Guarantees Act 1993 section 43, and agree that the CGA does not apply to services supplied under this agreement. Liability is limited to the lesser of (a) the fees paid under the relevant engagement, and (b) a stated cap. Future Shift Advisory accepts no liability for decisions made by the client in reliance on platform outputs, including but not limited to acquisition decisions, investment decisions, hiring decisions, and pricing decisions.

## Clause 6 — AI disclosure

_Placeholder. Lawyer-reviewed text required._

Significant parts of the platform's analysis, scoring, document verification, and guidance are generated or assisted by AI (Anthropic Claude). All AI outputs are subject to the AI Integrity Principle (honest, evidence-based, accurate, free from bias, truthful). The user acknowledges that AI is fallible and that final decisions rest with the user and their professional advisors. Source attribution is provided on every factual AI claim; accuracy discrepancies between AI claims and supporting documents are surfaced to the user and never suppressed.

## Clause 7 — Intellectual property

_Placeholder. Lawyer-reviewed text required._

The platform, its methodologies, prompts, rating frameworks, and analytical models are the intellectual property of Future Shift Advisory. The client retains ownership of data they upload. Anonymous aggregated benchmarks may be derived from client data subject to minimum cohort sizes and the client's consent given through these terms.

## Clause 8 — Confidentiality

_Placeholder. Lawyer-reviewed text required._

Each party agrees to keep confidential the other's non-public information shared in connection with the engagement, except as required by law or with explicit consent. Confidentiality survives termination.

## Clause 9 — Payment terms

_Placeholder. Lawyer-reviewed text required._

Fees are specified in the signed proposal. Payment may be by credit card or direct debit through the platform's PCI-DSS-compliant gateway (Stripe primary, Windcave fallback). Failed payments trigger immediate notification; persistent failures may result in service suspension.

## Clause 10 — Termination

_Placeholder. Lawyer-reviewed text required. **Special attention from the reviewing lawyer.**_

Either party may terminate on written notice per the engagement proposal. Future Shift Advisory may terminate immediately, without notice, in the event of fraud, theft, or breach of NZ company law by the client or any director/officer of the client, and may report such conduct to the Serious Fraud Office and NZ Police as legally appropriate.

## Clause 11 — Platform use

_Placeholder. Lawyer-reviewed text required._

The user agrees not to attempt to bypass platform security (including MFA, invite-only access, file scanning, audit logging), not to upload malicious files, and not to use the platform for any unlawful purpose. The user agrees to keep their credentials confidential and to notify Future Shift Advisory immediately of any suspected compromise.

## Clause 12 — Privacy

_Placeholder. Lawyer-reviewed text required._

Personal information is collected, stored, used, and disclosed in accordance with the Privacy Act 2020. The platform's privacy notice (linked from the portal) explains specific practices. The user has the right to access and correct their personal information. Notifiable privacy breaches are reported to the Office of the Privacy Commissioner per the Act.

## Clause 13 — Dispute resolution

_Placeholder. Lawyer-reviewed text required._

Disputes are first addressed by direct discussion between the parties, then by mediation, and only thereafter by litigation. New Zealand law governs this agreement. The New Zealand courts have exclusive jurisdiction.

## Clause 14 — General

_Placeholder. Lawyer-reviewed text required._

Severability, waiver, entire-agreement, assignment, and notices clauses per standard NZ commercial contract practice. Electronic signatures captured by the platform are valid under the Contract and Commercial Law Act 2017.

---

## Notes for the platform implementation

When persisting this document via the `TermsVersionSeeder` (WO-10):

- Each `##` heading becomes a `terms_clauses` row.
- `clause_number` is derived from the heading text (the integer before the em dash).
- `material` is true for clauses 1, 5, 6, 10, 12 by default — material status is editable in the admin UI per clause.
- The `body` field stores the markdown body of the clause (renderer converts to HTML for the gate and PDF).
- The version-level metadata (effective date, notice period, material flag, reviewer reference) is set at publish time in the admin UI.

## Confidentiality

Future Shift Advisory proprietary contract terms. Do not share outside the lawyer review process.
