# ADR 0001 — Laravel 13 + Filament 5 server, Bun capture

- **Status:** Accepted
- **Date:** 2026-04-19
- **Supersedes:** —

## Context

Tricorder needs:

- a server that persists Sessions, Turns, and ModelRequests from many developer machines
- an admin + review UI over those records
- an on-machine capture process that hooks into Claude Code, Codex, and OpenCode and forwards normalized events

[slopwatch](https://github.com/mattpocock/slopwatch) tackles the same problem with Bun on both sides and a bespoke HTTP framework. That's a coherent design (see [slopwatch ADR 0001 "bun everywhere"](https://github.com/mattpocock/slopwatch/blob/main/docs/adr/0001-bun-everywhere.md)) but it's expensive in calendar time — a lot of machinery has to be hand-rolled. We want MVP in weeks, not months, and we want a review UI cheaply.

## Decision

Split the stack:

- **Server:** Laravel 13 + Filament 5, any Laravel-supported database.
- **Capture:** Bun + TypeScript, one binary per developer machine via `bun build --compile`.

### Why Laravel 13 for the server

- Eloquent + migrations let us move fast on the Session / Turn / ModelRequest schema.
- First-party Laravel Sanctum covers machine-to-server auth (see ADR 0002).
- First-party Laravel Socialite covers human-to-server auth via Google.
- Laravel queue + Horizon gives us a credible ingest pipeline day one.
- PHP 8.4 is widely deployable; any managed Postgres / MySQL / SQLite file works.

### Why Filament 5 for the UI

- Filament Resources map 1:1 to our Eloquent models — Session / Turn / ModelRequest / User / Token — so listing and detail pages are a few files each.
- Dashboard widgets and stats widgets handle the cost/token rollup without us building charts from scratch.
- Custom pages cover the live-spectate polling view when we get there.
- Authorization plugs into Laravel policies.

### Why Bun + TypeScript for capture

- Matches slopwatch's ADR 0001: `bun build --compile` produces a single static binary, easy to distribute to developer machines.
- TypeScript lets us share transcript types between the three Listeners.
- Bun's built-in file-watching and subprocess primitives cover the `SessionStart`-hook → capture-subprocess pattern we need.
- Node interop is available if a required library is Node-only, but we prefer Bun-native.

### Database portability

We do **not** lock to Postgres. Slopwatch does, to get JSONB; we sacrifice some JSON-query power to let teams run Tricorder against MySQL or SQLite. Raw event payloads are stored as Laravel `json` columns; we don't write driver-specific JSONB operators anywhere in the app.

## Consequences

**Accepted trade-offs:**

- Two runtimes in the repo (PHP on the server, Bun on the machine) — one more dev-env dependency, but the split lines up with the deployment boundary anyway.
- No JSONB-specific indexing; if Session payload search becomes expensive, we add a searchable projection column instead.
- We do not ship a single binary for the server; ops teams deploy Laravel the usual way (PHP-FPM or Octane).

**Follow-ups:**

- ADR 0002 locks the auth strategy (Sanctum + Socialite).
- Phase 1: scaffold `apps/server` with Laravel 13 and Filament 5, run `filament:install`, create initial migrations.
- Phase 2: scaffold `apps/capture` with Bun + TS, port Claude Code JSONL parsing from ai-dash, implement the `SessionStart`-triggered subprocess pattern.

## Related

- [slopwatch ADR 0001 — bun everywhere](https://github.com/mattpocock/slopwatch/blob/main/docs/adr/0001-bun-everywhere.md) — the design we diverge from on the server side.
- [ai-dash](https://github.com/adinhodovic/ai-dash) — Go reference parser we're porting from.
