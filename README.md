# Tricorder

Self-hosted observability for AI coding agents. Capture sessions from Claude Code, Codex, and OpenCode on developer machines; forward normalized events to a Laravel server; review, spectate, and cost-account from a Filament dashboard.

> **Status:** pre-implementation. This commit lays down the proposal, architecture, glossary, and research notes. Phase 1 scaffolds the Laravel server; Phase 2 scaffolds the Bun capture process. See `docs/adr/0001-laravel-filament-bun-stack.md` for the phased plan.

## Credits & prior art

Tricorder stands on the shoulders of two projects. Neither is a dependency — both are sources of design and parsing know-how that we've studied and are building on.

- **[slopwatch](https://github.com/mattpocock/slopwatch)** by Matt Pocock — the product vision, terminology, entity model (Session / Turn / ModelRequest / Subagent / Listener / Server), DRI review concept, and per-session-subprocess capture strategy are lifted directly from slopwatch's design docs. `docs/CONTEXT.md` is a deliberate port of slopwatch's glossary; `docs/research/slopwatch-notes.md` is our condensed reading of its design notes. Slopwatch itself is pre-implementation and written in Bun; Tricorder picks up the same problem with a Laravel + Filament server.

- **[ai-dash](https://github.com/adinhodovic/ai-dash)** by Adin Hodovic — a shipped Go TUI that already parses transcripts from all three providers we target. Our per-provider parsers (Claude Code JSONL walker, Codex envelope decoder, OpenCode SQLite reader) are ported from ai-dash's `internal/sources/` package; file-on-disk paths, JSON shapes, and status-heuristic logic are all documented in `docs/research/ai-dash-notes.md` with links back to the original Go source.

If you maintain either project and you'd rather we attribute differently, open an issue — we're happy to adjust.

## What tricorder is (one paragraph)

A capture process runs on each developer's machine, hooked into the coding agent(s) they use. It tails transcripts (Claude Code, Codex) or reads the local store (OpenCode), normalizes the stream into Sessions → Turns → ModelRequests, and posts to a Laravel server over HTTPS. The server persists to any Laravel-supported database, exposes a Filament admin/review UI, and computes token and cost rollups. Humans log in with Google via Laravel Socialite. The CLI authenticates via a browser-redirect flow that mints a Sanctum personal access token.

## Stack

| Layer | Choice |
|---|---|
| Server framework | Laravel 13 |
| Admin / review UI | Filament 5 |
| Database | Any Laravel-supported driver (SQLite for dev) |
| Queue | Laravel queue + Redis (Horizon) |
| Human auth | Laravel Socialite (Google) |
| Machine auth | Laravel Sanctum personal access tokens |
| CLI auth flow | Custom browser-redirect → localhost callback |
| Capture runtime | Bun + TypeScript |
| Capture distribution | Single binary via `bun build --compile` |

ADRs: `docs/adr/0001-laravel-filament-bun-stack.md`, `docs/adr/0002-sanctum-socialite-auth.md`.

## Repo layout

```
tricorder/
├── apps/
│   ├── server/          # Laravel 13 + Filament 5 (scaffolded in Phase 1)
│   └── capture/         # Bun + TS listener (scaffolded in Phase 2)
├── docs/
│   ├── CONTEXT.md       # canonical glossary — model names & forbidden terms
│   ├── adr/             # architecture decision records
│   └── research/        # notes on slopwatch and ai-dash
└── README.md
```

## Targeted providers (v1)

| Agent | Source | Strategy |
|---|---|---|
| Claude Code | `~/.claude/projects/<slug>/<uuid>.jsonl` | Tail JSONL on `SessionStart` hook |
| Codex | `~/.codex/sessions/YYYY/MM/DD/rollout-<uuid>.jsonl` | Tail JSONL envelopes |
| OpenCode | `~/.local/share/opencode/opencode.db` | Poll SQLite, read-only |

Copilot CLI and Pi are explicitly out of scope for v1.

## Terminology

We mirror slopwatch's glossary verbatim — `Session`, `Turn`, `ModelRequest`, `Subagent`, `Listener`, `Server`. Read `docs/CONTEXT.md` before naming anything. Terms like `conversation`, `chat`, `run`, `message`, `exchange`, `API call`, `inference`, `sidecar`, `adapter`, `daemon`, `backend`, and `hub` are deliberately not used.

## License

TBD.
