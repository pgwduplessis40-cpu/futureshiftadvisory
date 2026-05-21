# Entrepreneur profiles

WO-15 adds the Phase 1 entrepreneur slice: advisors can create a basic entrepreneur profile, issue the invite-only account flow, and route accepted entrepreneurs to a placeholder portal.

## Storage contract

`entrepreneur_profiles` is intentionally small:

- `user_id` links to the accepted entrepreneur account once the invite is used.
- `assigned_advisor_id` owns Phase 1 capacity and advisor visibility.
- `invite_token_id` links the profile to the WO-08 one-shot invite.
- `name`, `email`, and `concept_summary` capture the basic profile.
- `stage` uses `App\Enums\EntrepreneurStage`.

The full stage enum is present for forward compatibility with the Phase 3 module, but only `invited` and `onboarding` are reachable in Phase 1. Advisor creation always stores `invited`; invite acceptance moves the linked profile to `onboarding`.

## Invite handoff

`Advisor\EntrepreneurController::store()` calls `InviteIssuer` with `target_user_type=entrepreneur` and `target_role=entrepreneur`. `InviteAcceptController` now checks whether the accepted invite is linked to an entrepreneur profile; when it is, the controller writes `user_id` and advances the stage to `onboarding` before the user continues through MFA and terms.

After MFA and terms gates, entrepreneur users hitting `/dashboard` are redirected to `/portal/entrepreneur`. This page is a Phase 1 placeholder and does not expose readiness assessment, idea validation, plan building, scoring, or mentor workflow surfaces.

## Capacity

`AdvisorEntrepreneurCapacity` owns the Phase 1 capacity check:

- default hard limit: 30 active entrepreneurs per advisor
- warning threshold: 24 active entrepreneurs
- active Phase 1 stages: `invited`, `onboarding`

The limit and warning threshold are config-backed via `config/entrepreneurs.php` so local or future plan changes do not require controller edits.

## Authorization

Routes live under `/advisor/entrepreneurs` and use the WO-07 permissions:

- `entrepreneurs.view` for index/show
- `entrepreneurs.assess` for create/store

`EntrepreneurProfilePolicy` allows super-admins to view all profiles, assigned advisors/mentors to view their profiles, and entrepreneur users to view their own linked profile. Create access follows the `entrepreneurs.assess` permission.
