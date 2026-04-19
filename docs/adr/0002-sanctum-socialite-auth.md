# ADR 0002 — Auth: Sanctum PATs for CLI, Socialite (Google) for humans

- **Status:** Accepted
- **Date:** 2026-04-19
- **Supersedes:** —

## Context

Two distinct auth flows:

1. **Humans** log into the Filament UI to review Sessions and manage tokens. They should log in with Google; we should not manage passwords.
2. **CLIs** (`tricorder-capture` on developer machines) need a durable credential to post events to the Server. It must be mintable without a human password and revocable per-device.

We considered **Laravel Passport** for the CLI case. Passport gives us standard OAuth2 (authorization_code + PKCE) and refresh tokens for free. But:

- Our only API consumer in v1 is our own CLI. Third-party API clients aren't on the roadmap.
- PKCE + OAuth2 clients + scopes + refresh-token rotation is more surface area than we need.
- Sanctum personal access tokens (PATs) plus a single browser-redirect flow ship in a fraction of the code.

## Decision

- **Human auth:** Laravel Socialite with Google. No local password auth. A user is created on first Google login; matching is by verified email.
- **Machine auth:** Laravel Sanctum personal access tokens.
- **CLI login flow:** custom browser-redirect flow that mints a Sanctum PAT.

### CLI login flow — `tricorder login` (aka `bunx tricorder install`)

```
1. CLI generates a random `state` and a device-friendly token name
     (default: `cli:${hostname}:${isoDate}`).
2. CLI starts a local HTTP listener on http://127.0.0.1:<random_port>/callback.
3. CLI opens the system browser to:
     https://<server>/cli/login?callback=http://127.0.0.1:<port>/callback&state=<state>
4. Server checks Filament session.
     - If not logged in → redirect to Socialite Google flow, then back.
5. Server shows a consent page:
     "`cli:laptop:2026-04-19` wants API access as you@team.com.  [Approve] [Deny]"
6. On Approve:
     - $user->createToken('cli:laptop:...', ['capture:write'])
     - HTTP 302 to http://127.0.0.1:<port>/callback?token=<pat>&state=<state>
7. CLI's local listener receives the request, verifies state, stores the PAT
   in the OS keychain (with a 0600 file fallback on headless Linux), and shuts
   the listener down.
8. Browser tab lands on a "You can close this tab" page.
```

### Notes on the flow

- **State check** protects against CSRF on the localhost callback.
- **Ports are random** so nothing collides; CLI picks any free port.
- **Token transport** is via a redirect query string, over loopback only. Tokens never leave the machine to a third party; the only non-loopback hop is the user's browser talking to our Server.
- **Scopes:** start with a single `capture:write` ability. Adding read-scoped tokens later (for dashboards or CI) is additive.
- **Revocation:** Filament Resource over `personal_access_tokens` lets the user revoke individual device tokens; admins can revoke any token.

### What we deliberately skip (for now)

- **Refresh tokens.** PATs don't expire by default. If we want rotation later, we add it (Sanctum supports expiry).
- **Passport / OAuth2 clients.** If we ever need third-party integrations, we migrate the CLI path to Passport at that point. The redirect flow described above is almost shape-compatible with OAuth2 authorization_code — the primary difference is that our callback receives a raw PAT instead of an auth code. Migration cost is bounded.
- **Device authorization grant (RFC 8628).** The browser-redirect flow is nicer UX than entering a user code.

## Consequences

**Accepted trade-offs:**

- No native refresh-token rotation.
- No third-party OAuth clients.
- We maintain one small controller (`CliLoginController`) and one consent page.

**Follow-ups:**

- Phase 1: install Sanctum, install Socialite, wire `LoginController` to Socialite Google.
- Phase 1: add `CliLoginController@show` and `@approve`; add a consent Blade view.
- Phase 1: Filament Resource for `PersonalAccessToken` so users/admins can revoke.
- Phase 2: capture CLI implements the client side of the flow.

## Related

- [Laravel Sanctum docs](https://laravel.com/docs/sanctum)
- [Laravel Socialite docs](https://laravel.com/docs/socialite)
- [GitHub CLI's equivalent flow](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/about-authentication-to-github#authenticating-with-the-command-line) — the browser-redirect-to-localhost pattern we're mirroring.
