# Service Quality Test Baseline - 2026-07-07

## Scope

Baseline captured for the service-quality implementation rollout in the local test environment.

## Source State

- Branch: `main`
- Commit: `08ad927e324d331e1a965ed23109599465591615`
- Worktree: dirty before this note; changes from the service-quality rollout and generated Wayfinder/build assets were already present.
- Test database: `futureshift_test`
- Migrations: no pending migrations after `php artisan migrate:status --pending`

## Runtime

- PHP: `8.4.23`
- Composer: `2.10.1`
- Node: `v26.1.0`
- npm: `11.13.0`

## Test Environment Checks

- `.env.testing` declares `APP_ENV=testing`, `DB_CONNECTION=pgsql`, `DB_DATABASE=futureshift_test`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`.
- `.env.testing` has `ANTHROPIC_API_KEY` empty.
- Laravel resolved the test database connection through cached/local config as `postgres` with a password present; full-suite runs need the resolved DB credentials injected into the process until `.env.testing` is reconciled.

## Restore Point

- Test database backup: `storage/app/test-backups/futureshift_test-20260707-170555.dump`
- Format: PostgreSQL custom dump, no owner, no privileges.
- Size: `957082` bytes.

## Verification Results

- `npm run lint:check`: initially failed on 20 frontend lint issues; after lint cleanup, passed.
- `npm run types:check`: passed.
- `npm run build`: passed. Vite reported existing large-chunk and `module.register()` deprecation warnings.
- `php -d memory_limit=2048M vendor/phpunit/phpunit/phpunit --no-coverage`: failed current full-suite baseline after DB credentials were injected.

## Full PHP Suite Baseline Failures

The credential-corrected full-suite run reported:

- Tests run before abort: `764`
- Passed: `729`
- Failures: `8`
- Errors: `27`
- Fatal abort: PHP maximum execution time exceeded during `Tests\Feature\Payments\PaymentGatewayTest::test_live_stripe_client_uses_resilient_http_when_enabled`

Primary failure groups:

- Learning cadence expectations still assume 37 layers while the registry currently exposes 40.
- Several admin/reference-data/NPO tests fail on pre-existing fixture or prerequisite assumptions.
- Some advisor/portal tests have stale expectation values (`Invite accepted` vs `Active`, pending terms count, website audit finding presence).
- Testing seed data enum hydration/provider cases error across multiple datasets.
- Calendar and payment gateway tests need separate focused repair before the full suite can be a release gate.

Focused service-quality suites should be used as the immediate regression signal while these full-suite baseline issues are burned down.
