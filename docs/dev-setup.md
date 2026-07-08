# Developer setup

First-boot instructions for working on the Future Shift Advisory codebase locally on Windows with Laravel Herd. This file expands [`PLAN.md` §5](../PLAN.md#5-environment-setup-one-time-codex-local-on-windows).

> If you discover a setup quirk that isn't here, add it via PR. Future contributors will thank you.

---

## 1. Tooling

Confirm versions before starting:

```pwsh
php -v          # expect 8.3+
composer -V
node -v         # expect 22+
npm -v
herd status     # Herd should be running
```

Install [Laravel Herd](https://herd.laravel.com/) if not present. The Pro edition includes PostgreSQL — required for Phase 1.

---

## 2. PostgreSQL (via Herd)

Herd Pro ships with PostgreSQL. Enable the service from the Herd menu (Services → PostgreSQL → Start).

Create the two databases (development and test):

```pwsh
# The Herd-bundled psql is on the Herd binaries path. If `psql` is not on PATH:
& "$env:USERPROFILE\.config\herd\bin\psql.exe" -U herd -c "CREATE DATABASE futureshift_dev;"
& "$env:USERPROFILE\.config\herd\bin\psql.exe" -U herd -c "CREATE DATABASE futureshift_test;"
```

Default Herd Postgres credentials:

| Setting | Value |
|---|---|
| Host | `127.0.0.1` |
| Port | `5432` |
| User | `herd` |
| Password | _(empty)_ |

If the bundled `psql` path differs on your install, run `Get-Command psql` to locate it; document the actual path here when you find it.

---

## 3. `.env` configuration

`.env.example` is checked in. Copy it once and adjust:

```pwsh
Copy-Item .env.example .env
```

The keys below are Phase 1-relevant. Anything not listed here keeps its `.env.example` default.

```ini
APP_NAME="Future Shift Advisory"
APP_ENV=local
APP_URL=https://futureshiftadvisory.test      # Herd auto-serves this hostname from the folder name

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=futureshift_dev
DB_USERNAME=herd
DB_PASSWORD=

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=secure_local
MAIL_MAILER=log

# AI — scaffolded only in Phase 1; if empty, AiClient falls back to FakeAiClient
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6

# NZ external integrations — all default OFF in Phase 1 (use stubs)
FEATURE_NZBN_LIVE=false
FEATURE_COMPANIES_OFFICE_LIVE=false
FEATURE_IRD_LIVE=false
FEATURE_VIRUS_SCAN_LIVE=false
VIRUS_SCAN_ALLOW_NOOP=true
VIRUS_SCAN_FAIL_OPEN_ON_ERROR=true

# Security
MFA_REQUIRED=true
SESSION_TIMEOUT_DEFAULT_MINUTES=30
INVITE_TOKEN_TTL_HOURS=72
```

Test environment uses `.env.testing` (create if not present):

```ini
APP_ENV=testing
DB_DATABASE=futureshift_test
ANTHROPIC_API_KEY=        # leave empty — forces FakeAiClient binding
MAIL_MAILER=array
QUEUE_CONNECTION=sync
```

---

## 4. First boot

```pwsh
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
composer dev      # starts server + queue listener + Vite concurrently
```

Open `https://futureshiftadvisory.test`. If you see the starter page, you're ready.

### PDF reports (Browsershot / Puppeteer)

Report generation renders HTML to PDF with [Spatie Browsershot](https://github.com/spatie/browsershot), which drives a headless Chrome via Puppeteer. `npm install` pulls in the `puppeteer` package, but Puppeteer does **not** download a browser automatically in this repo — you must fetch both Chrome and the headless shell once:

```pwsh
npx puppeteer browsers install chrome
npx puppeteer browsers install chrome-headless-shell
```

Without these you'll hit a `ProcessFailedException` (`Cannot find module 'puppeteer'` or `Could not find Chrome`) the first time you open a report. Verify the render path end-to-end:

```pwsh
php artisan tinker --execute="echo substr(app(App\Services\Pdf\PdfRenderer::class)->render('<h1>ok</h1>'), 0, 5);"
# expect: %PDF-
```

If your Chrome lives in a non-default location, point at it via `config/services.php` (`services.browsershot.chrome_path`, `node_binary`, `npm_binary`).

---

## 5. Test loop

```pwsh
composer test            # runs Pint + PHPUnit against futureshift_test
npm run lint:check
npm run types:check
npm run format:check     # Prettier
```

CI gate (run before opening a PR):

```pwsh
composer ci:check        # full chain: lint + format + types + test
```

---

## 6. Useful commands

```pwsh
# Reset and re-seed the dev database
php artisan migrate:fresh --seed

# Tail the Laravel log
Get-Content storage/logs/laravel.log -Wait -Tail 50

# Open psql against the dev DB
& "$env:USERPROFILE\.config\herd\bin\psql.exe" -U herd -d futureshift_dev

# Confirm Wayfinder type generation (the vite plugin handles this in dev)
php artisan wayfinder:generate
```

---

## 7. Common gotchas

- **Line endings.** The repo expects LF. Git will convert on commit on Windows; ensure your editor is not writing CRLF for new files.
- **Herd hostname.** The folder name (`futureshiftadvisory`) determines the local hostname (`futureshiftadvisory.test`). Don't rename the folder casually.
- **Postgres extensions.** First migration enables `pgcrypto` and `uuid-ossp`. The Herd-bundled Postgres role has the needed privileges by default.
- **MFA in dev.** `MFA_REQUIRED=true` enforces TOTP enrolment for every user, even in local dev. To skip during early prototyping you may set it false, but never commit that change.
- **AI calls in dev.** Leave `ANTHROPIC_API_KEY` empty unless you're explicitly testing the live client; the `FakeAiClient` is sufficient for everything except smoke testing actual prompts.

---

## 8. After pulling or merging changes

When a merge brings in new work (e.g. a feature batch or a new module), sync your **dev** database before using the running app. The test suite uses `RefreshDatabase`, which only ever migrates the **test** DB (`futureshift_test`) — your dev database (`DB_DATABASE` in `.env`, currently `futureshiftadvisory_db`) is **not** touched by running tests, so new migrations and seed content won't appear in the app until you apply them yourself.

```pwsh
# Apply any new migrations to the dev DB (forward-only, additive — no data loss)
php artisan migrate

# Re-run seeders that ship new published content (idempotent). Example: the
# v2 base questionnaires (full Standard Advisory + Due Diligence sets) are
# additive seeders — run them so the app resolves the latest published set:
php artisan db:seed --class=StandardAdvisoryQuestionnaireV2Seeder
php artisan db:seed --class=DdSpecificQuestionnaireV2Seeder
```

Symptom of skipping the migrate step: a `QueryException` / `SQLSTATE[42P01] Undefined table` in the app for a table that exists in tests but not your dev DB (e.g. `report_section_revisions`). The fix is always `php artisan migrate` — never `migrate:fresh` on a dev DB you care about (that wipes it).

> **Running the full test suite.** The suite has grown well past its original size and now needs a raised memory limit; the default 128M OOMs (exit 255) and `composer test` can hang in this environment. Run the binary directly:
>
> ```pwsh
> php -d memory_limit=2048M vendor/phpunit/phpunit/phpunit --no-coverage
> ```
>
> If the test DB credentials in `.env.testing` are stale for your machine, override per-run: `$env:DB_USERNAME='postgres'; $env:DB_PASSWORD='...'; $env:DB_DATABASE='futureshift_test'` before the command. After Codex/Vite changes, run `npm run build` first if a feature test 500s on a missing Vite manifest entry.

---

## 9. What you still need from the owner

Phase 1 cannot fully run without these:

| Item | Required for | Notes |
|---|---|---|
| Meridian Warm brand kit (colours, type, logo) | Any client-facing UI | Drop into `docs/brand/` |
| 14-clause T&C source text | T&C acceptance gate (WO-10/11) | Drop into `docs/legal/terms-v1.md` |
| `Business_Plan_Rating_Matrix.pdf` | Phase 3 (entrepreneur module) | Drop into `docs/rating-criteria/` |
| FSA logo files (vector + favicon) | Branded shell | Drop into `docs/brand/` |
| Anthropic API key | Live AI testing | Add to `.env` only; never commit |

See [`PLAN.md` Appendix B](../PLAN.md#appendix-b--things-the-owner-must-provide-before--during-phase-1) for the full owner-input checklist.
