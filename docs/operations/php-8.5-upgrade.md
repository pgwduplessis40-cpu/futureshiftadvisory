# PHP 8.5 upgrade (production)

Production runs PHP 8.4 from the Remi module stream on AlmaLinux. PHP 8.4
receives bug fixes until 31 December 2026 and security fixes until
31 December 2028. PHP 8.5 receives security fixes until 31 December 2029.
Complete this upgrade before 31 December 2026.

This is a planned change, not a security patch. Security comes from running the
latest patch release of whichever branch is installed, so keep applying
`dnf upgrade` patches on 8.4 until the upgrade window.

## Readiness evidence

- CI runs the full PHPUnit suite on PHP 8.4 and 8.5 for every change
  (`tests.yml`).
- The authenticated browser gate and the lint/static-analysis job run on PHP
  8.5 (`browser-e2e.yml`, `lint.yml`).
- `composer.json` allows `^8.3`. No PHP 8.5 deprecated syntax (non-canonical
  casts, `__sleep`/`__wakeup`, `case` labels ending in `;`) exists in `app/`,
  `config/`, `routes/`, `database/`, or `bootstrap/`.
- Every PHP process uses `/usr/bin/php` or the `php-fpm` unit: PHP-FPM, the
  `inertia-ssr` service (`/usr/bin/php artisan inertia:start-ssr`), and the
  scheduler timer. A Remi module-stream switch keeps those paths and unit
  names, so no systemd unit or nginx change is required.

## 1. Pre-flight (any day before; read-only, about 10 minutes)

Run on the VPS:

```bash
cat /etc/os-release
php -v
dnf module list php
```

Continue only if `dnf module list php` lists a `remi-8.5` stream and marks
`remi-8.4` as enabled (`[e]`). If dnf reports that modules are unavailable
(AlmaLinux 10), stop: the switch commands below do not apply.

Record the current state so the upgrade can be compared against it:

```bash
php -m > ~/php-8.4-modules.txt
rpm -qa 'php*' | sort > ~/php-8.4-packages.txt
php -i | grep -E '^(memory_limit|upload_max_filesize|post_max_size|max_execution_time|date.timezone) ' > ~/php-8.4-settings.txt
sudo tar czf ~/php-8.4-config.tgz /etc/php.ini /etc/php.d /etc/php-fpm.conf /etc/php-fpm.d
```

List every long-running PHP process that must be restarted after the switch:

```bash
systemctl list-units --type=service --state=running | grep -iE 'php|ssr|queue|worker|reverb|supervisor'
```

Expect `php-fpm.service` and `inertia-ssr.service`. Note any other match, such
as a queue worker; it must also be restarted in step 3.

Preview the switch without changing anything:

```bash
sudo dnf module switch-to php:remi-8.5 --assumeno
```

Read the transaction summary. Installed `php-*` packages should appear under
Upgrading. If a PHP extension package appears under Removing without a
replacement, stop: that extension has no PHP 8.5 build and must be resolved
before the window.

## 2. Before the window

- Pick a quiet NZ evening. Do not merge to `main` during the window: a merge
  starts the automatic production deploy.
- Take a VPS snapshot in the Voyager control panel if it offers one. Restoring
  a snapshot also rolls back the database, so it is the last resort only (see
  Rollback).
- Expect a public-edge alert from the five-minute GitHub probe while PHP is
  replaced (see [production-availability.md](../production-availability.md)).

## 3. Upgrade window (about 30 minutes)

Run from the site root, the directory that contains `deploy.sh`.

Pause the scheduler and the public-edge watchdog so neither runs PHP or
restarts PHP-FPM mid-switch:

```bash
sudo systemctl stop futureshiftadvisory-scheduler.timer futureshiftadvisory-edge-watchdog.timer
```

Put the application in maintenance mode:

```bash
php artisan down
```

Switch the PHP stream:

```bash
sudo dnf module switch-to php:remi-8.5 -y
```

Verify PHP and its extensions:

```bash
php -v
php -m > ~/php-8.5-modules.txt
diff ~/php-8.4-modules.txt ~/php-8.5-modules.txt
```

`php -v` must report 8.5. Lines starting with `>` are new modules and are
expected. A line starting with `<` is a module that was lost: install its
`php-<name>` package before continuing, or roll back.

Check that configuration carried over:

```bash
sudo find /etc/php.ini /etc/php.d /etc/php-fpm.conf /etc/php-fpm.d -name '*.rpm*'
php -i | grep -E '^(memory_limit|upload_max_filesize|post_max_size|max_execution_time|date.timezone) ' | diff ~/php-8.4-settings.txt -
```

Package updates keep edited configuration files and place new defaults beside
them as `.rpmnew`, so `.rpmnew` files need no action. An `.rpmsave` file means
an edited file was replaced: copy the settings back from it. The settings diff
must print nothing.

Validate the PHP-FPM configuration, then restart the PHP services and any extra
process noted in pre-flight:

```bash
sudo php-fpm -t
sudo systemctl restart php-fpm inertia-ssr
systemctl is-active php-fpm inertia-ssr
```

Confirm the installed dependencies accept PHP 8.5 and its extensions. Every
line must report `success`:

```bash
composer check-platform-reqs --no-dev
```

Bring the site back and run a normal deploy. It rebuilds the Laravel caches,
verifies the production security configuration, runs the authenticated health
checks, re-enables the scheduler and watchdog timers, restarts PHP-FPM and SSR,
and verifies the live release:

```bash
php artisan up
./deploy.sh
```

`deploy.sh` must finish with `Deploy complete`. Confirm both timers are active:

```bash
systemctl is-active futureshiftadvisory-scheduler.timer futureshiftadvisory-edge-watchdog.timer
```

## 4. Check the live site

- Sign in with MFA as an advisor and as a client.
- Upload a small document and confirm it is scanned and stored.
- Generate a PDF report.
- Submit the public contact form.
- Confirm `https://futureshiftadvisory.nz/api/deployment` reports the release.

Watch the logs for the next 24 hours:

```bash
sudo journalctl -u php-fpm -u inertia-ssr --since '1 hour ago' --no-pager
tail -n 100 storage/logs/laravel.log
```

## Rollback

If the site fails and the cause is not clear within 15 minutes, return to
PHP 8.4 and restore the recorded configuration:

```bash
sudo dnf module switch-to php:remi-8.4 -y
sudo tar xzf ~/php-8.4-config.tgz -C /
sudo php-fpm -t
sudo systemctl restart php-fpm inertia-ssr
php artisan up
./deploy.sh
```

Restore the VPS snapshot only if the dnf rollback fails. A snapshot also
returns the database to the snapshot time, so data written since then is lost.

## After a stable week

- Move the coverage gates in `tests.yml` from the 8.4 matrix entry to 8.5, then
  remove 8.4 from CI.
- Raise `composer.json` to `"php": "^8.5"` so no environment installs on an
  older PHP.
- Switch local Herd to PHP 8.5 and update `docs/dev-setup.md`.
