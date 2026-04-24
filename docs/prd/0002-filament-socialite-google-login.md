# PRD 0002 — Filament Socialite: Google login for the admin panel

- **Status:** Proposed
- **Date:** 2026-04-24
- **Related ADRs:** [0002 Sanctum + Socialite auth](../adr/0002-sanctum-socialite-auth.md) (amended by this PRD — see Further Notes)
- **Related PRDs:** [0001 Laravel server scaffold](./0001-laravel-server-scaffold.md)

## Problem Statement

PRD 0001 installed `laravel/socialite` as a package only — no controllers, no routes, no UI. The Filament panel at `/admin` still requires a local password (Filament's default `->login()` form), which is the opposite of what ADR 0002 mandates ("Human auth: Laravel Socialite with Google. No local password auth"). A new contributor today logs in by running `tricorder:make-admin`, copy-pasting a generated password, and ignoring the spirit of ADR 0002 entirely.

We need the admin panel to accept "Sign in with Google" as a real login method, with first-Google-login user creation gated by a deployment-configurable email-domain allowlist (Tricorder is self-hosted "one Server per team/org" — most operators want to restrict access to their team's domain). We also need a sane story for first-admin bootstrap and for the case where Google OAuth is misconfigured at 02:00 on a Saturday.

## Solution

Install the [`dutchcodingcompany/filament-socialite`](https://github.com/dutchcodingcompany/filament-socialite) plugin and register it on the existing `AdminPanelProvider` with a single Google provider entry. Publish the plugin's `socialite_users` migration to link OAuth identities to `App\Models\User` rows, make the `users.password` column nullable so passwordless accounts are representable, and gate auto-registration via a `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST` env var (empty list = closed; populated = any verified Google email on those domains auto-creates a `User` row). Filament's native password form stays on the panel as a break-glass admin path; `tricorder:make-admin` gains a `--with-password` flag so day-to-day admins are provisioned passwordless and break-glass admins are explicitly opt-in.

After this PRD merges, a contributor with valid `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` envs can run `tricorder:make-admin you@yourdomain.com`, visit `/admin`, click "Sign in with Google", and land in the panel as that user. With `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST=yourdomain.com` set, a colleague at the same domain can log in without an admin pre-creating their account first.

## User Stories

1. As a Tricorder admin, I want a "Sign in with Google" button on the Filament login page, so that I do not have to manage local passwords for my team.
2. As a developer (per CONTEXT.md role), I want my Google account on my team's domain to log me into the panel without an admin pre-creating my account, so that onboarding is one click.
3. As a Tricorder admin, I want to configure which email domains are allowed to auto-register via `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST` (comma-separated), so that random Google accounts on the public internet cannot log into my self-hosted instance.
4. As a Tricorder admin, I want the empty allowlist to mean "no auto-registration; only pre-created users can log in", so that the safe default is the closed default.
5. As a Tricorder admin, I want a Google login that fails for a domain not on the allowlist to redirect to the login page with a clear error, so that I am not left staring at a 500 page.
6. As a Tricorder admin, I want `tricorder:make-admin {email}` to create a passwordless user by default and print "log in at /admin with Google using {email}", so that I do not have to handle a password I will never use.
7. As a Tricorder admin, I want `tricorder:make-admin {email} --with-password` to preserve today's behaviour (random password, printed to stdout), so that I can mint a break-glass account when Google OAuth is misconfigured.
8. As a Tricorder admin, I want Filament's native password form to stay on the panel, so that a break-glass admin minted via `--with-password` can actually log in.
9. As a Tricorder maintainer, I want a verified Google email matched to an existing `User.email` to log that user in (no duplicate `User` row created), so that admins minted via `tricorder:make-admin` and Google identities are the same row.
10. As a Tricorder maintainer, I want a `socialite_users` table linking `(provider, provider_id) → user_id`, so that we can support multiple OAuth providers later without re-modelling.
11. As a Tricorder maintainer, I want the `users.password` column to be nullable, so that a Google-only user does not have a hashed-empty-string in the database.
12. As a Tricorder maintainer, I want the OAuth callback route registered under the `admin` panel slug (`/admin/oauth/google/callback` per the plugin's defaults), so that we are not maintaining a custom controller when the plugin already ships one.
13. As a contributor, I want `apps/server/.env.example` updated with `GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/admin/oauth/google/callback` and a populated `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST` placeholder, so that `cp .env.example .env` plus a Google Console client ID/secret is enough to exercise the flow locally.
14. As a contributor, I want `apps/server/README.md` to document Google OAuth setup (Google Console redirect URI, env vars, allowlist), so that the install recipe stays truthful.
15. As a CI job, I want Pest tests that mock Socialite's HTTP call (no network) and assert the end-to-end login flow, so that future PRs cannot break Google login without it being caught.
16. As a security-conscious operator, I want the `Login` and `Registered` events the plugin fires to land in `storage/logs/laravel.log` at INFO and `UserNotAllowed` to log at WARNING via the default Laravel listener registration, so that I can audit who logged in and who was blocked.
17. As a future DRI reviewer of this PRD's PR, I want the only Tricorder-owned files changed to be the `AdminPanelProvider`, `User` model + migration, `MakeAdminUser` command, `SocialiteUser` model, env files, README, and tests, so that review focus lands on policy decisions, not vendor code.
18. As a Tricorder maintainer, I want this PRD to ship with an in-repo amendment to ADR 0002 (or a new ADR superseding it on the password-form question), so that the decision log does not silently drift.

## Implementation Decisions

- **Plugin choice.** [`dutchcodingcompany/filament-socialite`](https://github.com/dutchcodingcompany/filament-socialite) (the package surfaced on Filament's plugin directory under the `dododedodonl-socialite` slug). Installed via `composer require dutchcodingcompany/filament-socialite`. Pin to `^2.x` (current stable).
- **Migrations.** `php artisan vendor:publish --tag=filament-socialite-migrations` then `php artisan migrate`. Adds the `socialite_users` table with `(user_id, provider, provider_id, created_at, updated_at)`. Plus a Tricorder-owned migration `20260424_000000_make_users_password_nullable.php` that runs `$table->string('password')->nullable()->change()` on `users`.
- **Config.** `php artisan vendor:publish --tag=filament-socialite-config` to pin the published `config/filament-socialite.php` in-repo. Registration happens on the panel provider (below) — the published config is mostly defaults; we keep it for visibility and to avoid surprise upgrades.
- **Views.** Do **not** publish the plugin's views in this PRD. The default templates render an acceptable Google button on the panel login page; theming is a follow-up if/when we touch Filament theming.
- **`SocialiteUser` model.** Tricorder-owned class `App\Models\SocialiteUser` extending the plugin's base model and implementing `DutchCodingCompany\FilamentSocialite\Contracts\FilamentSocialiteUser`. Default behaviour from the plugin is sufficient; we own the file so we can override later without churning the migration.
- **`User` model.** Add a `socialiteUsers(): HasMany` relation. `canAccessPanel(Panel $panel): bool` stays permissive (`return true`) — access gating is still a later ADR. `password` becomes nullable in Eloquent (no `'required'` validation rule because we have no controller writing this column directly).
- **`AdminPanelProvider` registration.**
  ```php
  use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
  use DutchCodingCompany\FilamentSocialite\Provider;

  ->plugin(
      FilamentSocialitePlugin::make()
          ->providers([
              Provider::make('google')
                  ->label('Google')
                  ->icon('heroicon-o-globe-alt')
                  ->color(Color::Gray),
          ])
          ->slug('admin')
          ->registration(true)
          ->domainAllowList(
              array_filter(
                  explode(',', env('FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST', ''))
              )
          )
          ->userModelClass(\App\Models\User::class)
          ->socialiteUserModelClass(\App\Models\SocialiteUser::class)
  )
  ```
  - **Icon.** `heroicon-o-globe-alt` keeps us inside Filament's bundled iconset; we are not adding `owenvoke/blade-fontawesome` for one button.
  - **`->registration(true)`** is set unconditionally; gating happens via the allowlist. An empty allowlist combined with `domainAllowList: []` blocks all unrecognised emails, so empty-list = closed-by-default holds.
- **Provider scope.** Google only. The `providers([])` array has one entry. Adding GitHub/Microsoft later is one line plus a Socialite-Providers package; we are not abstracting for that today.
- **Env vars.** `apps/server/.env.example`:
  - `GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/admin/oauth/google/callback` (replaces the `/auth/google/callback` hint left by PRD 0001 — that placeholder pre-dated the plugin choice).
  - New: `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST=` with a comment showing comma-separated example.
- **`tricorder:make-admin` change.** Default behaviour: upsert by email with `password = null`, print `Log in at http://127.0.0.1:8000/admin with Google using {email}`. New `--with-password` flag preserves today's behaviour (random password hashed and stored, plaintext printed to stdout). The command stays idempotent in both modes.
- **Filament native login form.** Keep `->login()` on the panel. ADR 0002 amendment in this PRD's "Further Notes" records the change in stance: native form is retained as a break-glass admin path, not as a primary login.
- **Routes.** No Tricorder-owned controllers added. The plugin registers `/{panel}/oauth/{provider}/redirect` and `/{panel}/oauth/{provider}/callback` itself.
- **Logging.** Wire a small `App\Listeners\LogSocialiteEvents` registered in `AppServiceProvider::boot()` mapping the plugin's events to Laravel's default logger:
  - `Login` → `Log::info('socialite.login', [...])`
  - `Registered` → `Log::info('socialite.registered', [...])`
  - `UserNotAllowed` → `Log::warning('socialite.denied', [...])`
  - `InvalidState` → `Log::warning('socialite.invalid_state', [...])`
  - `RegistrationNotEnabled` → `Log::warning('socialite.registration_disabled', [...])`
- **Terminology guardrail.** CONTEXT.md applies as before. We use "Server" (not backend/hub), "Listener" (not sidecar) — neither term shows up in this PRD's code surface, but the listener name `LogSocialiteEvents` is a Laravel event listener, not a Tricorder Listener; we accept the framework's term in framework context.

## Testing Decisions

- **Framework.** Pest, same conventions as PRD 0001's `ScaffoldSmokeTest`. Tests live under `apps/server/tests/Feature/Auth/`.
- **Mocking strategy.** `Laravel\Socialite\Facades\Socialite::shouldReceive('driver->stateless->user')` to return a fake OAuth user object. No real HTTP. No real Google.
- **Tests shipped in this PRD:**
  1. `GoogleLoginTest::it_renders_a_google_button_on_the_panel_login_page` — `get('/admin/login')` HTML contains the plugin's Google login link.
  2. `GoogleLoginTest::it_logs_in_an_existing_user_matched_by_email` — pre-create `User('alice@example.com')`, mock OAuth, hit the callback, assert `Auth::user()->is($alice)` and the request redirects into the panel.
  3. `GoogleLoginTest::it_auto_registers_a_user_when_email_domain_is_on_the_allowlist` — set `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST=example.com`, mock OAuth for `bob@example.com` (no pre-existing row), assert a new `User` and `SocialiteUser` row exist after callback.
  4. `GoogleLoginTest::it_blocks_login_when_email_domain_is_not_on_the_allowlist` — set allowlist to `example.com`, mock OAuth for `mallory@evil.com`, assert no user created and the response redirects to login with an error flash.
  5. `GoogleLoginTest::it_blocks_auto_registration_when_allowlist_is_empty` — empty allowlist, mock OAuth for an unknown email, assert no user created and login fails closed.
  6. `GoogleLoginTest::it_re_uses_the_socialite_user_row_on_subsequent_logins` — log in once, log out, log in again, assert exactly one `socialite_users` row for that `(provider, provider_id)` pair.
  7. `MakeAdminUserCommandTest::it_creates_a_passwordless_user_by_default` — running the command without `--with-password` results in `password === null` on the row.
  8. `MakeAdminUserCommandTest::it_creates_a_passworded_user_with_the_flag` — `--with-password` produces a hashed password and prints a plaintext one (existing test, retained, renamed).
- **Prior art.** PRD 0001's `tests/Feature/ScaffoldSmokeTest.php` is the shape to mirror — HTTP-level checks asserting status codes and DB state, not Filament/Socialite internals. The plugin's own [tests](https://github.com/dutchcodingcompany/filament-socialite/tree/main/tests) confirm the `Socialite::shouldReceive` mocking pattern works against this package.
- **Out of test scope.** Browser/Dusk against a real `accounts.google.com` (no live OAuth in CI), Filament theming snapshot tests (no theming changes), Sanctum-PAT flows (separate PRD), the CLI login flow described in ADR 0002 (separate PRD).

## Out of Scope

- The CLI login flow (`/cli/login` consent page, `CliLoginController@show`/`@approve`, the `tricorder login` capture-CLI command). That is the next slice of ADR 0002 and gets its own PRD. This PRD only handles **human auth into the Filament panel**.
- The Filament Resource over `personal_access_tokens` (admin/user revocation UI). Same follow-up PRD as the CLI login flow.
- Additional OAuth providers (GitHub, Microsoft, GitLab). One-line config add when the need is real.
- Custom views / theming of the login page. Default plugin templates ship.
- Multi-tenant team scoping. Every authenticated user sees the same panel; team-scoped Sessions are a later PRD.
- Email-verification flows on top of Google's claim. `email_verified` from Google is taken as authoritative.
- Account-link UI ("link my GitHub on top of my Google login"). Separate `socialite_users` rows are supported by the schema but no UI.
- Rate limiting on the OAuth callback. Laravel's default route throttling applies; a dedicated limiter is overkill for v1.
- Replacing Filament's password login form. Per the user-confirmed decision, it stays as a break-glass admin path.
- Audit log Filament Resource over the events emitted by `LogSocialiteEvents`. Logs land in `laravel.log`; surfacing them in the panel is later.

## Further Notes

- **ADR 0002 amendment.** ADR 0002 reads "Human auth: Laravel Socialite with Google. No local password auth." This PRD intentionally keeps Filament's native password form on the panel as a break-glass path so an admin minted via `tricorder:make-admin --with-password` can still log in if Google OAuth is misconfigured. That is a real behavioural change vs. the ADR. Track this as a discrete issue (see Issue Plan below); resolution is either an edit to ADR 0002 or a new ADR 0003 superseding the relevant section. Do **not** merge the implementation issues until that ADR work is also done — otherwise the decision log silently drifts.
- **Redirect-URI hint in `.env.example`.** PRD 0001 left a comment claiming the redirect URI would be `/auth/google/callback`. That pre-dated the plugin choice; the plugin actually mounts the callback under the panel slug at `/admin/oauth/google/callback`. Update the comment as part of this PRD; do not leave both URIs in the file.
- **Plugin naming on Filament's directory.** Filament's plugin marketplace lists this as `dododedodonl-socialite` (the maintainer's username). The composer package is `dutchcodingcompany/filament-socialite`. Same plugin; we use the composer name everywhere in code, the marketplace slug appears nowhere in the repo.
- **Why not a custom `LoginController`?** ADR 0002's CLI login flow does need a Tricorder-owned `CliLoginController`, but that controller is for **minting Sanctum PATs after Google login**, not for the Google login itself. The plugin's controller is sufficient for the panel-login case; rolling our own would duplicate it.
- **Diff shape.** Like PRD 0001, expect the diff to be small in Tricorder-owned files (`AdminPanelProvider.php`, `User.php`, `MakeAdminUser.php`, `SocialiteUser.php`, the nullable-password migration, `.env.example`, `README.md`, the listener, and tests) and modest in vendor-published files (one config, one migration). Reviewers should focus on the panel provider and the listener.

## Issue Plan

Five vertical slices, presented for confirmation before issues are filed:

1. **ADR amendment: keep Filament native password form as break-glass** — HITL.
   *Update ADR 0002 (or add ADR 0003 superseding the relevant section) to record that local password auth is retained as a break-glass admin path. Blocks every other slice in this PRD because the implementation contradicts the ADR as currently written.*

2. **Install plugin, schema, end-to-end Google login for pre-existing users** — AFK. Blocked by #1.
   *`composer require`; publish & migrate `socialite_users`; add Tricorder-owned `users.password nullable` migration; create `App\Models\SocialiteUser`; register `FilamentSocialitePlugin` on `AdminPanelProvider` with one Google provider entry and an empty `domainAllowList`; ship tests 1, 2, and 6 from "Tests shipped". After this slice merges, an admin minted by today's `tricorder:make-admin` can log in via Google.*

3. **Domain allowlist + auto-registration** — AFK. Blocked by #2.
   *Add `FILAMENT_SOCIALITE_DOMAIN_ALLOWLIST` env var; wire `->domainAllowList(...)` on the plugin; ship tests 3, 4, 5 from "Tests shipped". After this slice, a colleague at an allowlisted domain can log in without admin pre-provisioning.*

4. **`tricorder:make-admin --with-password` flag + passwordless default** — AFK. Blocked by #2 (needs nullable password column).
   *Flip the default to passwordless; add `--with-password` flag; update output messaging; ship tests 7 and 8 from "Tests shipped".*

5. **Socialite event logging + docs + `.env.example` cleanup** — AFK. Can ship in parallel with #3 and #4 once #2 is in.
   *Add `App\Listeners\LogSocialiteEvents` and register it; update `apps/server/README.md` install recipe with Google Console / env-var instructions; fix the `.env.example` redirect-URI comment.*
