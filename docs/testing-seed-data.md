# Testing Seed Data

Run the comprehensive testing fixture directly:

```bash
php artisan db:seed --class=TestingSeedDataSeeder
```

Or include it in the normal database seed run:

```bash
SEED_TESTING_DATA=true php artisan db:seed
```

All seeded users use the password `password`. MFA enrolment is intentionally cleared for these profiles so local manual testing can sign in without a 6-digit authenticator code when `MFA_REQUIRED=false`.

| Role | Email |
| --- | --- |
| Super admin | `seed.admin@futureshiftadvisory.test` |
| Lead advisor | `seed.advisor@futureshiftadvisory.test` |
| Junior advisor | `seed.junior@futureshiftadvisory.test` |
| Client principal | `seed.client.primary@futureshiftadvisory.test` |
| Client team | `seed.client.team@futureshiftadvisory.test` |
| Buyer principal | `seed.buyer.primary@futureshiftadvisory.test` |
| Buyer analyst | `seed.buyer.analyst@futureshiftadvisory.test` |
| Entrepreneur | `seed.entrepreneur@futureshiftadvisory.test` |
| Idea Validation — ready to start | `seed.idea.start@futureshiftadvisory.test` |
| Idea Validation — advisor review | `seed.idea.review@futureshiftadvisory.test` |
| Idea Validation — approved | `seed.idea.approved@futureshiftadvisory.test` |
| Idea Validation — cancellation eligible | `seed.idea.cancel@futureshiftadvisory.test` |
| BP&B — paid, workspace at start | `seed.plan-budget.start@futureshiftadvisory.test` |
| BP&B — paid, Financial budget ready to complete | `seed.plan-budget.financials@futureshiftadvisory.test` |
| BP&B — paid, submitted for advisor review | `seed.plan-budget.review@futureshiftadvisory.test` |
| BP&B — paid, assessment approved | `seed.plan-budget.approved@futureshiftadvisory.test` |
| Advisory — BP&B approved, ready to request | `seed.idea-advisory.start@futureshiftadvisory.test` |
| Advisory — BP&B approved, proposal released | `seed.idea-advisory.proposal@futureshiftadvisory.test` |
| Broker | `seed.broker@futureshiftadvisory.test` |
| Coach | `seed.coach@futureshiftadvisory.test` |
| Mentor | `seed.mentor@futureshiftadvisory.test` |

The four BP&B fixtures all have an advisor-approved Idea Validation, a successful one-off BP&B payment, and an activated BP&B entitlement. Their payment amount is snapshotted from the active BP&B Service Rate whenever the fixture is seeded. The start fixture is building, the Financials fixture is positioned at the Financial > Budget requirement with 17 of 18 requirements complete, the review fixture is submitted and awaiting advisor review, and the approved fixture has a finalised assessment. The Financials fixture includes editable launch costs, fixed costs, revenue, and assumptions; its funding source is deliberately blank so you can test autosave, recalculation, and completion.

The Advisory request fixture has the same paid, completed, advisor-approved BP&B baseline and its readiness signal, but has not yet been converted to Advisory Services: sign in as that founder and request advisory support. The Advisory proposal fixture uses the production Founding Advisory conversion and proposal services. It contains a converted Founding Advisory client and an unrecalled released proposal; sign in as the lead advisor to review the advisory and proposal workflow. Both fixtures are test-only and are safe to recreate with the seed command above.

The fixture covers active, paused, suspended, offboarded, due-diligence, post-acquisition, and entrepreneur workflows. It is idempotent, so it can be re-run while developing without duplicating the seeded records.
