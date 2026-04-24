# PRD 0001 — Laravel server scaffold (apps/server)

- **Status:** Proposed
- **Date:** 2026-04-24
- **Related ADRs:** [0001 Laravel + Filament + Bun stack](../adr/0001-laravel-filament-bun-stack.md), [0002 Sanctum + Socialite auth](../adr/0002-sanctum-socialite-auth.md)

## Problem Statement

We have decided the stack (ADR 0001 and 0002) and locked the canonical terminology (`docs/CONTEXT.md`), but `apps/server/` is an empty placeholder — there is no Laravel application, no `composer.json`, nothing for `php artisan migrate` to act on. Every subsequent feature PRD (ingest endpoint, normalizers, Filament Resources, the CLI login flow) is blocked because nobody has yet produced a running Laravel + Filament baseline. A contributor cannot clone the repo and reach a working admin login screen, so work cannot start in parallel on the pieces that depend on a booted server.

## Solution

Scaffold a default-config Laravel 13 application under `apps/server/`, install Filament 5 with a default admin panel at `/admin`, install Laravel Sanctum and Laravel Socialite as packages only (no controllers, routes, or views wired), ship a working `.env.example` and a small idempotent `tricorder:make-admin` Artisan command, and cover the baseline with a handful of Pest smoke tests. After this PRD merges, a new contributor can clone, run a documented install recipe, and reach the Filament login screen on `http://localhost:8000/admin` with a user they created from the CLI. That is the baseline every subsequent feature PRD builds on.

## User Stories

1. As a Tricorder maintainer, I want Laravel 13 scaffolded under `apps/server/` with default configs, so that every subsequent feature PRD has a running application to add routes, models, and migrations to.
2. As a Tricorder maintainer, I want `filament/filament: ^5` installed with a default admin panel generated at `/admin`, so that future Filament Resources have a panel to register against.
3. As a Tricorder maintainer, I want `laravel/sanctum` installed and its `personal_access_tokens` migration published, so that ADR 0002's PAT flow has somewhere to write tokens without a follow-up package install.
4. As a Tricorder maintainer, I want `laravel/socialite` installed and a `services.google` config block present with env-var placeholders, so that the future `LoginController` can call `Socialite::driver('google')` without another setup step.
5. As a contributor, I want a committed `.env.example` with sensible defaults (SQLite path, log mail driver, Google OAuth placeholders, `APP_URL`), so that `cp .env.example .env && php artisan key:generate && php artisan migrate` works on a fresh clone.
6. As a contributor, I want Laravel's standard `.gitignore` entries (vendor, `.env`, `storage/*.key`, etc.) reconciled with the repo-root `.gitignore`, so that Composer install artefacts do not get committed.
7. As an admin, I want a `php artisan tricorder:make-admin {email}` command that creates or promotes a user with panel access and prints a one-time password, so that I can log into Filament without hand-inserting a database row.
8. As a contributor, I want `apps/server/README.md` updated with the actual install recipe (replacing the "not yet scaffolded" placeholder), so that the repo README stays truthful.
9. As a CI job, I want a Pest smoke test that boots the app, hits `/up`, and hits the Filament login route returning a non-5xx status, so that future PRs cannot break the scaffold without it being caught.
10. As a security-conscious operator, I want Sanctum and Socialite installed at current stable versions tracked in `composer.json`, so that CVE fixes arrive via `composer update` without re-deciding the dependency.
11. As a future DRI reviewer, I want Laravel's default migrations (`users`, `cache`, `jobs`, `password_reset_tokens`, `sessions`) plus Sanctum's `personal_access_tokens` applied as the migration baseline, so that every subsequent migration lands on a known state.
12. As a Bun capture maintainer (Phase 2), I want the server to expose a predictable `http://127.0.0.1:8000` dev URL, so that I can point the CLI at it during integration work.
13. As a Tricorder maintainer, I want SQLite chosen as the default dev driver (per ADR 0001) with MySQL and Postgres configs untouched from Laravel defaults, so that a contributor can switch drivers by editing `.env` alone.
14. As a Tricorder maintainer, I want `APP_NAME=Tricorder` and the Filament panel branded "Tricorder", so that the scaffold does not ship with "Laravel" copy that would need replacing later.
15. As a security-conscious maintainer, I want the `/admin` panel to require authentication on every route by default (Filament's standard behaviour), so that we do not expose an empty panel publicly.
16. As a Tricorder maintainer, I want *no* custom controllers, models, or migrations beyond Laravel / Filament / Sanctum defaults in this PRD, so that the first feature PRD has an unambiguous baseline to diff against.
17. As a Tricorder maintainer, I want the `tricorder:make-admin` command to be idempotent (re-running it does not error or duplicate), so that re-running the install recipe during local development is safe.
18. As a CI maintainer, I want the smoke tests runnable with `php artisan test` and zero external services (no Redis, no queue worker, no Google OAuth call), so that CI can stay trivial at this stage.
19. As a reviewer of this PRD's PR, I want the diff shape to be "lots of vendor-generated Laravel/Filament boilerplate plus a small number of Tricorder-owned files", so that review focus lands on the files we actually own.

## Implementation Decisions

- **Application location.** Scaffold into `apps/server/` via `composer create-project laravel/laravel apps/server "^13.0"`. The monorepo root stays the parent; `apps/server/composer.json` is self-contained.
- **Versions.** Laravel `^13.0` (locked by ADR 0001); PHP `^8.4` in `composer.json`'s `require`.
- **Filament.** `filament/filament: ^5.0`, installed via `php artisan filament:install --panels`. Panel id `admin`, path `/admin`, default theme. No custom Resources, widgets, or theming in this PRD.
- **Sanctum.** `composer require laravel/sanctum`, then `php artisan install:api`. The `personal_access_tokens` migration is committed. The `api` middleware stack is left at Laravel 11+ defaults. **No routes under `routes/api.php` are defined in this PRD.**
- **Socialite.** `composer require laravel/socialite`. Add a `google` entry to `config/services.php` reading `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`. **No controller wired; no routes registered.** Placeholders ship as empty strings in `.env.example`.
- **Database driver.** `DB_CONNECTION=sqlite`; `database/database.sqlite` is created (empty) by the install recipe and remains gitignored. MySQL / Postgres entries in `config/database.php` are untouched Laravel defaults.
- **Migration baseline.** Laravel's stock `users`, `cache`, `jobs`, `password_reset_tokens`, `sessions` plus Sanctum's `personal_access_tokens`. No Tricorder-specific migrations (`sessions` for agent sessions, `turns`, `model_requests`, etc.) in this PRD.
- **User model.** Default `App\Models\User`, modified to implement `Filament\Models\Contracts\FilamentUser` with a permissive `canAccessPanel(Panel $panel): bool { return true; }`. Access gating is a later ADR. No additional columns.
- **Admin-user command.** New `App\Console\Commands\MakeAdminUser`, signature `tricorder:make-admin {email} {--name=Admin}`. Hashes a random password, prints the one-time password to stdout, upserts by email. Idempotent.
- **Panel branding.** `AdminPanelProvider::panel()` sets `->brandName('Tricorder')`. No custom logo asset.
- **Queue / cache / session drivers.** `QUEUE_CONNECTION=sync`, `CACHE_STORE=database`, `SESSION_DRIVER=database`. Redis and Horizon are an ADR follow-up, out of scope here.
- **Mail.** `MAIL_MAILER=log` in `.env.example`. Outgoing mail lands in `storage/logs/laravel.log` during local dev.
- **Directory conventions.** Laravel's standard layout; no custom namespaces outside `App\`. Pre-reserve `app/Filament/`, `app/Ingest/Normalizers/`, and keep Laravel's default `app/Models/` so future PRDs do not churn the tree.
- **App name.** `APP_NAME=Tricorder` in `.env.example`.
- **Terminology guardrail.** CONTEXT.md's forbidden-terms list (`backend`, `daemon`, `hub`, `conversation`, `chat`, `run`, `sidecar`, etc.) applies to every file this PRD produces — including PHPDoc, Blade comments, and the admin-user command's help text. Where Laravel's own scaffold uses `session` to mean HTTP session, that is fine; where we might be tempted to use `session` for an agent session, we do not use it in this PRD because we are not modelling agent sessions yet.
- **`apps/server/README.md`.** Replaces the current "Not yet scaffolded" notice with: clone → `composer install` → `cp .env.example .env` → `php artisan key:generate` → `touch database/database.sqlite` → `php artisan migrate` → `php artisan tricorder:make-admin you@example.com` → `php artisan serve` → visit `/admin`. Links back to ADRs 0001 and 0002.
- **Repo `.gitignore`.** Already covers `apps/server/vendor/` and `apps/server/.env`. Verify no drift post `create-project`; do not regenerate.
- **No third-party Filament plugins.** Resources, widgets, and theming are deferred to later PRDs.

## Testing Decisions

- **What makes a good test at scaffold stage.** Tests assert externally-observable behaviour that would regress if the scaffold were deleted or misconfigured — not Laravel's internals. That means HTTP-level checks on the routes Laravel and Filament expose, plus a migration-applied check. We are not unit-testing Laravel itself.
- **Framework.** Pest (Laravel 11+ scaffold default). Tests live under `apps/server/tests/Feature/`.
- **Tests shipped in this PRD:**
  1. `ScaffoldSmokeTest::it_boots_the_health_endpoint` — `get('/up')` returns `200`. Proves the Laravel app boots.
  2. `ScaffoldSmokeTest::it_serves_the_filament_login_page` — `get('/admin/login')` returns a non-5xx status. Proves Filament is installed and the panel is registered.
  3. `ScaffoldSmokeTest::it_runs_every_migration_cleanly` — the `RefreshDatabase` trait applies every migration (including Sanctum's) against SQLite without error. Proves migration baseline is consistent.
  4. `MakeAdminUserCommandTest::it_creates_a_user_by_email` — `artisan('tricorder:make-admin', ['email' => 'dri@example.com'])` exits `0` and the user row exists.
  5. `MakeAdminUserCommandTest::it_is_idempotent` — running the command twice for the same email does not create two users and does not error.
- **Prior art.** Laravel's own `tests/Feature/ExampleTest.php` (shipped by `laravel new`) and the smoke tests inside the [`filamentphp/demo`](https://github.com/filamentphp/demo/tree/main/tests) repo are the reference shapes to follow — HTTP smoke tests asserting status codes, not internals.
- **Out of test scope.** Browser / Dusk tests (no non-trivial JS yet), HTTP fake against real Google OAuth (no controller yet), Sanctum-authenticated request flows (no routes to hit yet), Horizon / queue workers, Pint / Larastan static-analysis runs.

## Out of Scope

- Tricorder-domain Eloquent models: `Session`, `Turn`, `ModelRequest`, `Subagent`, `ReviewItem`. Reserved for the next PRD.
- The `/ingest` HTTP endpoint and per-provider `app/Ingest/Normalizers/{ClaudeCode,Codex,OpenCode}.php` implementations.
- Socialite Google `LoginController` implementation and route wiring. The package is installed; the flow is a later PRD.
- `CliLoginController@show` and `@approve`, plus the consent Blade view from ADR 0002.
- Filament Resources for any entity (including `PersonalAccessToken` management).
- Filament dashboard widgets (cost rollups, token charts, live-spectate page).
- Redis, Horizon, Octane, FrankenPHP, Sail, Docker — any deployment or async story. `QUEUE_CONNECTION=sync` is the dev default for this stage.
- Pint, Larastan, Pest plugins beyond defaults, PHPStan, CI workflow files.
- The `apps/capture/` Bun scaffold — Phase 2 per ADR 0001.
- Retention policy, schema-drift handling, DRI semantics — open questions inherited from `docs/CONTEXT.md`, resolved when real data demands.

## Further Notes

- Expected diff shape is "lots of lines added, almost all of them vendor-generated Laravel / Filament boilerplate". Reviewers should focus on a small set of Tricorder-owned files:
  - `apps/server/app/Console/Commands/MakeAdminUser.php`
  - `apps/server/app/Providers/Filament/AdminPanelProvider.php` (panel branding + `admin` path)
  - `apps/server/app/Models/User.php` (Filament panel contract)
  - `apps/server/.env.example`
  - `apps/server/config/services.php` (added `google` block)
  - `apps/server/tests/Feature/ScaffoldSmokeTest.php`
  - `apps/server/tests/Feature/MakeAdminUserCommandTest.php`
  - `apps/server/README.md`
- When Socialite is wired up in a later PRD, the Google OAuth redirect URI will be `http://127.0.0.1:8000/auth/google/callback` for local dev. `.env.example` should hint at this value so contributors do not guess.
- ADR 0001 and 0002 are load-bearing here. If an implementation detail disagrees with either ADR, raise a new ADR rather than silently drifting.
- This PRD intentionally contains zero product logic. Its only job is to produce a merge-ready scaffold that the next PRD can build on.
