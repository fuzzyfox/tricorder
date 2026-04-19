# apps/capture — Tricorder Capture

Bun + TypeScript. Not yet scaffolded.

## What lives here

- `tricorder` CLI: `login`, `install`, `sync`, and the per-session subprocess spawned by agent hooks.
- Per-agent Listeners under `src/agents/{claude-code,codex,opencode}/`.
- The browser-redirect client side of the Sanctum PAT login flow from `docs/adr/0002-sanctum-socialite-auth.md`.
- A `bun build --compile` script that produces a single-binary release per platform.

## Scaffold (Phase 2 — after the server can accept ingests)

```
cd apps/capture
bun init -y
bun add -d typescript @types/bun
```

Single-file entry at `src/index.ts`; providers under `src/agents/<name>/listener.ts`; shared event schema under `src/schema.ts`.

## Per-provider strategy

| Agent | Trigger | Source | Notes |
|---|---|---|---|
| Claude Code | `SessionStart` hook | `~/.claude/projects/<slug>/<uuid>.jsonl` | Tail JSONL until `SessionEnd`. |
| Codex | Always on (no hooks) | `~/.codex/sessions/YYYY/MM/DD/rollout-<uuid>.jsonl` | Watch the day's folder. |
| OpenCode | Always on | `~/.local/share/opencode/opencode.db` | Poll SQLite read-only every ~5s. |

See [`docs/research/ai-dash-notes.md`](../../docs/research/ai-dash-notes.md) for the parsing spec we're porting from Go.

## Packaging

```
bun build src/index.ts --compile --outfile=dist/tricorder
```

Target platforms: `bun-linux-x64`, `bun-darwin-arm64`, `bun-darwin-x64`.
