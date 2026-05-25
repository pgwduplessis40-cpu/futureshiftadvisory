# Methodology Registry

The methodology registry is the internal source of truth for platform-owned formulas, scores, thresholds, and valuation methods. It is code-owned, read-only in the app, and surfaced only to users with `knowledge.view`.

## Confidentiality Rule

Methodology entries are FSA internal IP. They may appear in advisor/admin-facing payloads through a `methodology_id`, but they must not appear in portal, client, entrepreneur, broker, coach, public, report export, PDF, or PPTX payloads.

`MethodologyDriftGuardTest` enforces the static rule and specifically protects the client portal business-health radar payload.

## Source Files

- Registry data: `config/methodologies.php`
- Registry API: `app/Support/Methodology/MethodologyRegistry.php`
- Entry DTO: `app/Support/Methodology/MethodologyEntry.php`
- Marker interface: `app/Support/Methodology/ProvidesMethodology.php`
- Internal surface: `app/Http/Controllers/Advisor/MethodologyController.php`

## Adding Or Updating A Methodology

1. Put the calculation in a concrete service class, not an enum or model. If an enum or model represents the state, mark the service that applies it.
2. Add `ProvidesMethodology` to the owning class and return every owned id from `methodologyIds()`.
3. Add or update the matching keyed entry in `config/methodologies.php`.
4. Keep the `id` lowercase dotted, matching `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$`.
5. Write the formula parametrically. Do not hard-code values that are read from config.
6. Add every live parameter key to `config_refs`. Only non-secret allowlisted keys are permitted.
7. Add every `where_used` key to `feature_labels`.
8. If an internal advisor/admin payload references the method, add `methodology_id` and make sure the id exists in the registry.
9. Do not add `methodology_id` to portal/export/client-facing payloads.

## Drift Guard Scope

The guard has two layers:

- Every marked class must declare non-empty, unique, valid ids, and every declared id must exist in the registry.
- Every registry entry must point back to a marked owner class that declares that exact id.
- Designated calculation namespaces are scanned so a new concrete calculation class must be either marked or explicitly excluded in the guard test.

The guard verifies structure, not prose accuracy. Reviewers still need to compare registry wording with the owning service implementation.

## Verification

Run the focused guard set after changing the registry:

```bash
vendor/bin/pint --test app/Support/Methodology config/methodologies.php tests/Unit/Methodology
php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit --filter MethodologyRegistryTest --no-coverage
php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit --filter MethodologyDriftGuardTest --no-coverage
php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit --filter MethodologyRouteTest --no-coverage
```

If routes or controllers change, regenerate Wayfinder:

```bash
php artisan wayfinder:generate --with-form
```
