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
php artisan tricorder:make-admin you@example.com
php artisan serve
```

`tricorder:make-admin` upserts a Filament-panel user for the given email. By
default the user is created **passwordless** — sign-in goes through Google OAuth
(see below). Pass `--with-password` to print a one-time password to stdout for
the break-glass case where Google OAuth is unavailable. The command is
idempotent: re-running it for the same email leaves the user row in place.

Visit [`http://127.0.0.1:8000/up`](http://127.0.0.1:8000/up) to hit Laravel's
built-in health endpoint — it should return `200`.

## Google OAuth setup

The admin panel signs users in with Google via the
[`dutchcodingcompany/filament-socialite`](https://github.com/dutchcodingcompany/filament-socialite)
plugin. To enable the **Sign in with Google** button on `/admin/login`:

1. In the [Google Cloud Console](https://console.cloud.google.com/apis/credentials),
   create an **OAuth 2.0 Client ID** of type **Web application**.
2. Add `http://127.0.0.1:8000/admin/oauth/callback/google` to the client's
   **Authorised redirect URIs** for local dev (add your production URL when you
   deploy).
3. Copy the client ID and secret into `.env`:

   ```
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/admin/oauth/callback/google
   ```

4. Set `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST` to the comma-separated email
   domains you want to auto-register on first login (e.g.
   `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST=yourcompany.com`). Leave it empty to
   disable auto-registration — only users pre-created with `tricorder:make-admin`
   will be able to sign in.
5. Bootstrap your account passwordlessly:

   ```
   php artisan tricorder:make-admin you@yourdomain.com
   ```

   Then visit [`http://127.0.0.1:8000/admin`](http://127.0.0.1:8000/admin) and
   click **Sign in with Google**.
6. Break-glass: if Google OAuth is unavailable (revoked credentials, offline
   network, etc.), bootstrap a password-bearing user instead:

   ```
   php artisan tricorder:make-admin you@yourdomain.com --with-password
   ```

   The command prints a one-time password to stdout. Sign in at `/admin/login`
   with email + password.

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
