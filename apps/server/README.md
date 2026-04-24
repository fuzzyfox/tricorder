# apps/server — Tricorder Server

Laravel 13 application. SQLite by default; switch drivers by editing `.env`.

This is the baseline scaffold only — no Filament panel, no Sanctum or Socialite,
no domain models, no `/ingest` endpoint. Those land in subsequent slices.

## What lives here (eventually)

- HTTP API: `/ingest` (machine, Sanctum-authenticated), `/cli/login` (human, Socialite-backed), `/oauth/callback`.
- Eloquent models: `Session`, `Turn`, `ModelRequest`, `User`, `IngestToken`, `Team` (stub), `ReviewItem` (stub).
- Per-provider normalizers under `app/Ingest/Normalizers/{ClaudeCode,Codex,OpenCode}.php`.
- Filament Resources, Pages, and Widgets under `app/Filament/`.
- Migrations under `database/migrations/`.

## Install

From the repo root:

```
cd apps/server
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Then visit [`http://127.0.0.1:8000/up`](http://127.0.0.1:8000/up) — Laravel's
built-in health endpoint should return `200`.

## Test

```
php artisan test
```

Tests run against an in-memory SQLite database and require no external services
(no Redis, no queue worker, no network).

## References

- [`docs/adr/0001-laravel-filament-bun-stack.md`](../../docs/adr/0001-laravel-filament-bun-stack.md) — why Laravel + Filament + Bun.
- [`docs/adr/0002-sanctum-socialite-auth.md`](../../docs/adr/0002-sanctum-socialite-auth.md) — auth plan for later slices.
- [`docs/prd/0001-laravel-server-scaffold.md`](../../docs/prd/0001-laravel-server-scaffold.md) — the PRD this folder implements.
