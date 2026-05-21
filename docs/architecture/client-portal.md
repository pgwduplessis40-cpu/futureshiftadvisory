# Client portal and onboarding

WO-16 introduces the authenticated client portal shell and the seven-step onboarding wizard.

## Portal routing

Client portal routes live in `routes/portal.php` under `/portal` and use the same `auth`, `verified`, and `mfa` middleware as the rest of the authenticated app. `DashboardController` redirects client users with an assigned client scope from `/dashboard` to `/portal`; advisors and admins keep the existing app dashboard.

`ClientPortalResolver` is the central resolver for Phase 1 portal routes. It only allows `client_primary` and `client_team` users, reads `User::accessibleClientIds()`, and returns the latest scoped client visible through the WO-14 `client_team` RLS path.

## Portal layout

`resources/js/layouts/PortalLayout.tsx` provides the client portal shell:

- top navigation
- keyboard-visible skip link
- notification button stub
- mobile menu with `aria-expanded` and `aria-controls`
- Portal-specific dashboard/onboarding/messages links

Entrepreneur placeholder pages also use this layout, but the layout detects `user_type=entrepreneur` and only shows the entrepreneur portal dashboard link.

## Wizard state

WO-16 adds `clients.onboarding_wizard_state` as JSONB. The persisted shape is:

- `current_step`
- `completed_steps`
- `steps.{step_slug}` payloads
- `submitted_at`
- `updated_at`

`OnboardingWizard` owns the step list, progress calculation, questionnaire set selection, current-step resolution, and state writes. `OnboardingController` enforces step order server-side by redirecting any request for a future step back to the current step.

## Seven steps

The Phase 1 wizard steps are:

1. Welcome
2. Identity verification
3. Business snapshot
4. Goals
5. Questionnaire
6. Documents
7. Review and submit

Step 5 maps engagement type to questionnaire path:

- `standard_advisory` -> `standard_advisory`, available in Phase 1 through the WO-17 questionnaire engine.
- `due_diligence` -> `dd_specific`, gated to Phase 3.
- `post_acquisition_advisory` -> `post_acquisition_gap`, gated to Phase 3.
- `entrepreneur_module` -> `entrepreneur_readiness`, gated to Phase 3.

Actual questionnaire rendering is owned by WO-17. Document upload, milestone tracking, notification centre, and messaging all remain owned by later WOs.
