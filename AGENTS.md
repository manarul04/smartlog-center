# Project invariants

Facts below are load-bearing. Do not "clean them up".

## Runtime

- Laravel 13 on PHP 8.3+ (this machine runs 8.4).
- Laravel 10 is preserved on branch `laravel-10` and tag `laravel-10-final`.

## Boot sequence

- `APP_KEY` falls back to `storage/framework/app-key` when `.env` has none
  (`app/Support/AppKey.php`). Never delete that file: encrypted values in the
  database become unreadable and every session is invalidated.
- `.env` is re-read on every request by `AppServiceProvider::applyEnvFile()`.
  This is intentional — the setup wizard rewrites `.env` mid-request.
  Consequence: do not leave `php artisan config:cache` in place.
- Settings saved from the Configuration page override `OPENSEARCH_*` from `.env`
  at boot (`AppServiceProvider::applyStoredBackendSettings()`).

## HTTP

- `TrustHosts` validates the `Host` header against `APP_URL`
  (`app/Http/Middleware/TrustHosts.php`). Testing on a host that does not match
  `APP_URL` returns 400 on every page, including login. That is not a bug — use
  the host from `APP_URL` (currently `http://192.168.5.99:8124`).
- CSRF middleware is `App\Http\Middleware\PreventRequestForgery`
  (renamed from `VerifyCsrfToken` in Laravel 13). Do not reintroduce the old name.
- Global middleware that runs before the session: `RequireSetup`
  (`app/Http/Middleware/RequireSetup.php`). It redirects everything to `/setup`
  until the database and indexer are configured.
- Role checks: `EnsureRole` (`role:admin`, `role:admin,analyst`).

## Data

- Wazuh indexer (OpenSearch) is **read-only**. Never write to it.
- Application state lives in PostgreSQL: users, triage, annotations, tickets,
  settings, audit log, KB catalog.
- Alert identity is the OpenSearch document `id`, so annotations survive daily
  index rotation.
- Dashboard queries are cached for `OPENSEARCH_CACHE_TTL` seconds; the cache key
  includes an endpoint fingerprint, so data from a previous endpoint never
  appears after the URL changes.
- `cache.serializable_classes` is `false`: only arrays, strings and scalars may
  be cached. Do not cache Eloquent models.

## Tests

- `php artisan test`, database `wazuh_logging_test` (see `phpunit.xml`).
- Baseline: **58 tests passing**. A change that lowers this number is a
  regression.
- Tests use `http://localhost` regardless of `APP_URL`.

## Conventions

- No build step. CSS and JS live in `public/assets` and are edited directly;
  there is no `@vite` in any view. Do not add npm to this project.
- PDF export uses `barryvdh/laravel-dompdf`, logo from
  `public/assets/img/logo.png`.
- Docker runs PHP 8.4 + Apache (`Dockerfile`). Do not switch the container to
  `php artisan serve`: the setup wizard runs migrations mid-request and the
  single-threaded dev server deadlocks.
- `docker compose exec app php artisan ...` runs as root and leaves
  `storage/` owned by root, which breaks Apache. After running it:
  `docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache`
