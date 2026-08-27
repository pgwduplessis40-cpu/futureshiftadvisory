# Production-quality release control

Production quality is a release decision, not a label attached to a merge.
The checks below must be required branch-protection checks for `main`:

- `ci (8.4)` and `ci (8.5)` from `tests.yml`
- `quality` from `lint.yml`
- `authenticated-browser-e2e` from `browser-e2e.yml`

Repository workflows cannot set GitHub branch-protection policy themselves.
The repository owner must require the three checks above, require pull requests,
and prevent direct pushes before this control is operational.

## Enforced controls

| Gate | Enforcement |
| --- | --- |
| PHPStan | Level 6, baseline occurrence count published, no increase from the pull-request base, ratchet starts at 1,839; `<1,000` is the burn-down milestone and `0` is required for a production-quality declaration. |
| Browser quality | Isolated secret-backed advisor, client, and NPO accounts; 1440px and 390px authenticated flows; fake WebRTC media/peer signalling; HTTP, console, request, axe, alt text, overflow, focus, and approved screenshot checks. |
| Mass assignment | `config/production_quality.php` registry requires an explicit `$fillable` list and rejects `$guarded = []`; changed models may not introduce a new unrestricted declaration. |
| Client errors | Closed six-field browser/server telemetry contract, prohibited-content validation, deduplicated new-fingerprint alert event, and client/server contract tests. |
| Structural boundaries | No-growth ceilings for the identified controllers/pages/composer, typed-boundary diff guard, accessible-checkbox guard, and contract-test change requirement for every target extraction. |
| Coverage/build | Overall 85%, module 80%, critical-path 90% hard floors; comparison against the committed main coverage baseline; PCOV on 8.4 and non-coverage tests on 8.5; build/Wayfinder cleanliness checks. |

The 92% then 95% targets for payments, dates, and reports are deliberately not
declared complete by a configuration toggle. Raise those hard floors only in the
same pull requests that add the corresponding boundary-contract coverage and
update the reviewed coverage baseline upward.

The starting PHPStan count was normalized from the actual level-6 analysis:
the predecessor had 1,628 suppressions, four stale suppressions were removed,
and 215 pre-existing level-6 diagnostics were captured. The single bootstrap
is bound to its predecessor commit and the exact baseline hash in
`quality/phpstan-level6-bootstrap.json`; it cannot authorize any later
baseline growth or new suppression entries.

## Current release state

The current checkout remains ineligible for a production-quality declaration:
the PHPStan baseline and the identified monolith ceilings are active debt, and
the E2E gate requires the CI secrets plus reviewed screenshot baselines. A green
unit suite or a passing build does not override any of those blockers.
