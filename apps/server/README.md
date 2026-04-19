# apps/server — Tricorder Server

Laravel 13 + Filament 5. Not yet scaffolded.

## What lives here

- HTTP API: `/ingest` (machine, Sanctum-authenticated), `/cli/login` (human, Socialite-backed), `/oauth/callback`.
- Eloquent models: `Session`, `Turn`, `ModelRequest`, `User`, `IngestToken`, `Team` (stub), `ReviewItem` (stub).
- Per-provider normalizers under `app/Ingest/Normalizers/{ClaudeCode,Codex,OpenCode}.php`.
- Filament Resources, Pages, and Widgets under `app/Filament/`.
- Migrations under `database/migrations/`.

## Scaffold (Phase 1 — runs next)

```
composer create-project laravel/laravel . "^13.0"
composer require filament/filament:"^5.0"
composer require laravel/sanctum laravel/socialite
php artisan filament:install --panels
php artisan migrate
```

See [`docs/adr/0001-laravel-filament-bun-stack.md`](../../docs/adr/0001-laravel-filament-bun-stack.md) and [`docs/adr/0002-sanctum-socialite-auth.md`](../../docs/adr/0002-sanctum-socialite-auth.md) for the decisions this folder will implement.

## Running (once scaffolded)

```
cd apps/server
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```
