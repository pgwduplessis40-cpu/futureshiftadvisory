# Authenticated browser quality gate

`npm run browser:e2e` verifies login/onboarding, dashboard, NPO, budget/runway,
and client-screen routes at 1440px and 390px. It fails on same-origin HTTP and
request failures, console errors, unhandled page errors, axe violations, missing
image alt text, horizontal overflow, no keyboard focus target, fake WebRTC
media/signalling failure, and screenshot changes without an approved baseline.

Use isolated test accounts only. The runner requires these environment variables:

```text
E2E_BASE_URL=https://futureshiftadvisory.test
E2E_ADVISOR_EMAIL=
E2E_ADVISOR_PASSWORD=
E2E_ADVISOR_MFA_SECRET=
E2E_CLIENT_EMAIL=
E2E_CLIENT_PASSWORD=
E2E_CLIENT_MFA_SECRET=
E2E_NPO_EMAIL=
E2E_NPO_PASSWORD=
E2E_NPO_MFA_SECRET=
E2E_CLIENT_SCREEN_PATH=/advisor/clients/<uuid>
```

Store every value in GitHub Actions secrets. The CI seeder accepts no defaults,
creates the accounts only in `testing`, and encrypts the test-only TOTP secret.
Never place E2E credentials, MFA secrets, screenshots from production, or an MFA
bypass in source control.

The first CI run intentionally fails because `e2e/snapshots/` has no approved
images. Download `browser-e2e-evidence`, inspect all ten screenshots, and commit
only the approved `*.png` images using these names:

- `login-and-onboarding-{desktop,mobile}.png`
- `interactive-dashboard-{desktop,mobile}.png`
- `npo-module-{desktop,mobile}.png`
- `budget-and-runway-builder-{desktop,mobile}.png`
- `client-screen-{desktop,mobile}.png`

Optional route and expected-text overrides are `E2E_ONBOARDING_PATH`,
`E2E_ADVISOR_DASHBOARD_PATH`, `E2E_NPO_PATH`, `E2E_BUDGET_PATH`, and their
matching `_EXPECT` variables. Do not approve a screenshot until the rendered
page and its accessibility result have been reviewed.
