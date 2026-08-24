# Browser smoke validation

`npm run browser:smoke` verifies the authenticated dashboard, NPO, budget/runway, and client-screen routes at desktop and mobile viewports. It fails on HTTP failures, browser console errors, missing image alt attributes, and horizontal overflow.

Use isolated test accounts only. The runner requires these environment variables:

```text
E2E_BASE_URL=https://futureshiftadvisory.test
E2E_ADVISOR_EMAIL=
E2E_ADVISOR_PASSWORD=
E2E_CLIENT_EMAIL=
E2E_CLIENT_PASSWORD=
E2E_CLIENT_SCREEN_PATH=/advisor/clients/<uuid>
```

Optional route and expected-text overrides are `E2E_ADVISOR_DASHBOARD_PATH`, `E2E_CLIENT_DASHBOARD_PATH`, `E2E_NPO_PATH`, `E2E_BUDGET_PATH`, and their matching `_EXPECT` variables. Accounts that require MFA must use a test-only authentication path already accepted by the deployed environment; never disable production MFA to run this suite.
