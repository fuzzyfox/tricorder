# Research notes — slopwatch

Condensed reading of [mattpocock/slopwatch](https://github.com/mattpocock/slopwatch) as of 2026-04-19. Slopwatch is pre-implementation (no shipped source; 15 stars, 0 releases); everything below is from its design docs, which are useful because our scope overlaps almost entirely.

## Source files we leaned on

- [`README.md`](https://github.com/mattpocock/slopwatch/blob/main/README.md) — just the title; all real content is in the docs below.
- [`CONTEXT.md`](https://github.com/mattpocock/slopwatch/blob/main/CONTEXT.md) — canonical glossary. Ported to `docs/CONTEXT.md`.
- [`docs/adr/0001-bun-everywhere.md`](https://github.com/mattpocock/slopwatch/blob/main/docs/adr/0001-bun-everywhere.md) — runtime rationale.
- [`research/v1-architecture-decisions.md`](https://github.com/mattpocock/slopwatch/blob/main/research/v1-architecture-decisions.md) — the meat; 9 resolved decisions + open questions + an ASCII architecture diagram.
- [`research/coding-agent-ingestion.md`](https://github.com/mattpocock/slopwatch/blob/main/research/coding-agent-ingestion.md) — per-agent capture surfaces; the reference for what hooks/paths exist.

## What slopwatch is, in one sentence

A self-hosted, on-prem observability platform for coding agents (Claude Code, Codex, Pi, OpenCode, Copilot CLI) aimed at engineering orgs that need cost visibility and review over agent sessions running on developer machines.

## Design decisions we're adopting wholesale

- **Terminology.** `Session`, `Turn`, `ModelRequest`, `Subagent`, `Listener`, `Server`. See `docs/CONTEXT.md`. The forbidden-terms list is ported verbatim.
- **Turn is a DAG, not a list.** `parent_turn_id` column exists from day one even though v1 Turns are always linear. Prevents a migration later.
- **Subagents are Sessions.** A spawned subagent is modelled as a `Session` with `parent_session_id` + `spawned_by_turn_id` populated, not as a separate entity.
- **Per-session capture subprocess, not an always-on daemon.** The agent's `SessionStart` hook spawns our capture binary; the binary tails the transcript, POSTs events over HTTP, and exits on `SessionEnd`. Devs can't forget to "turn it on."
- **Live-spectate by polling, not push.** Client `GET /sessions/:id/events?since_seq=N` every ~5s. No SSE, no WebSockets, no LISTEN/NOTIFY. Simpler infra; good-enough UX.
- **One Server per org.** Horizontal scale is deferred but not precluded; Server is stateless apart from the DB.
- **Admin-minted long-lived tokens for CLIs.** We chose Sanctum PATs, slopwatch chose bespoke bearers; same shape.
- **ReviewItem is orthogonal to Session state.** Schema-stub in v1, UI later. The DRI semantics question is the #1 open item we're inheriting.
- **Defer resumes.** `codex resume`, Claude Code compaction-continue, Pi resume — model them once we have real data.

## Design decisions we're deliberately diverging from

| Slopwatch | Tricorder | Why |
|---|---|---|
| Bun on both sides of the wire | Laravel on the server, Bun on capture | Laravel+Filament ships the review UI and auth in weeks instead of months. |
| Postgres-only (for JSONB) | Any Laravel-supported driver | Team DB choice > fancy JSON ops. Raw payloads live in `json` columns. |
| Admin-minted opaque bearer tokens (no OAuth) | Sanctum PATs + browser-redirect flow backed by Google Socialite | Devs shouldn't have to paste admin-issued strings; see ADR 0002. |
| Single compiled binary for the Server | Laravel deployed as PHP-FPM / Octane | Different ops story; intentional. |
| Live-spectate ~5s polling | Same | No divergence. |

## Per-agent capture — what slopwatch knows

Slopwatch's `research/coding-agent-ingestion.md` enumerates capture surfaces. For our v1 targets (Claude Code, Codex, OpenCode):

- **Claude Code** — JSONL under `~/.claude/projects/<hash>/<session-uuid>.jsonl`; 24+ hooks (`SessionStart`, `PostToolUse`, …); OTEL spans (`claude_code.interaction`, `llm_request`) also carry rich data. Strategy: JSONL tail + hooks as triggers.
- **Codex CLI** — JSONL under `$CODEX_HOME/sessions/YYYY/MM/DD/rollout-<uuid>.jsonl`; `--json` stdout mirrors the schema. Hooks are flag-gated (`codex_hooks`, off by default). Strategy: JSONL tail (always on).
- **OpenCode** — migrating from JSON to SQLite at `~/.local/share/opencode/storage/`. Plugin system (JS/TS) + SSE `/event` stream exist. Slopwatch recommends **not** tailing files and instead using the plugin API. We're doing SQLite reads (ai-dash's approach) because it's simpler to port and doesn't require maintaining a plugin.

## Open questions slopwatch leaves unresolved — and what we'll do

| Question | Our v1 stance |
|---|---|
| DRI shape | Stub `ReviewItem` schema; defer UI. |
| Schema drift per agent | Fail open with a warning in v1; log the unparseable line verbatim; revisit when we have real drift data. |
| Retention | No auto-purge in v1. Manual DB cleanup only. |
| Frontend framework | Filament (Livewire under the hood) — settled by our stack choice. |
| Resumes | Deferred until v1 is live. |

## Things we should NOT copy

- **The strict Bun-only runtime constraint.** Slopwatch's argument (single binary, one language across sidecar and server) is real but doesn't pay off if the server stack is Laravel — we can't produce a Laravel single-binary anyway.
- **Push-based live events.** Slopwatch already downgraded this; we shouldn't re-introduce it.
- **Proxy/MITM capture.** Rejected for good reasons (cert pinning, OAuth, loses tool results). Don't revisit.
