# Research notes — ai-dash

Condensed reading of [adinhodovic/ai-dash](https://github.com/adinhodovic/ai-dash) as of 2026-04-19. Ai-dash is a shipped Go TUI that already parses transcripts from Claude Code, Codex, and OpenCode. We are porting its parsing logic to PHP for the Tricorder server; this document is the per-provider spec.

## Source files we'll be porting from

| Provider | File | Key functions |
|---|---|---|
| Claude Code | [`internal/sources/claude/source.go`](https://github.com/adinhodovic/ai-dash/blob/main/internal/sources/claude/source.go) | `discoverTranscripts`, `parseClaudeTranscript`, `claudeCurrentState` |
| Codex | [`internal/sources/codex/source.go`](https://github.com/adinhodovic/ai-dash/blob/main/internal/sources/codex/source.go) | `discoverCandidates`, `parseCodexSession` |
| OpenCode | [`internal/sources/opencode/source.go`](https://github.com/adinhodovic/ai-dash/blob/main/internal/sources/opencode/source.go) | `loadFromDB`, `openCodeStatus` |
| Shared | [`internal/sources/shared/`](https://github.com/adinhodovic/ai-dash/tree/main/internal/sources/shared) | `SessionProvider` interface, `DiscoverCandidateFiles` |
| Orchestrator | [`internal/sources/discovery.go`](https://github.com/adinhodovic/ai-dash/blob/main/internal/sources/discovery.go) | `Discover(cfg)`, `classifySessions` (subagent detection) |
| Unified type | [`internal/session/session.go`](https://github.com/adinhodovic/ai-dash/blob/main/internal/session/session.go) | `Session` struct |

## Ai-dash's unified Session struct (reference for our Eloquent model)

```go
type Session struct {
    ID, ParentID, Slug, Tool, Project, Repo, Branch, Status, CurrentState string
    StartedAt, EndedAt time.Time
    Model, Summary, TranscriptPath string
    TokensIn, TokensOut int
    CostUSD float64
    Tags []string
    Meta map[string]string
}
```

- `Status` is one of `active | completed | aborted`.
- `CurrentState` is finer: `running | waiting | tool call | max tokens | done | aborted | unknown`.
- `CostUSD` exists but is never populated in ai-dash. **Tricorder will actually populate it.**

Ai-dash is stateless — every poll rebuilds the slice from disk / SQLite. Tricorder persists, so the shape above is our *event* shape; our `sessions` table will also carry bookkeeping fields (user_id, team_id, ingest_token_id, timestamps, etc.).

## Provider 1 — Claude Code

### On disk

- Root: `~/.claude/projects/` (configurable via `claude_path` in ai-dash; we'll expose the same env var).
- Session: `~/.claude/projects/<slug-encoded-project>/<session-uuid>.jsonl`.
- Subagent: `~/.claude/projects/<slug-encoded-project>/<session-uuid>/subagents/<sub-uuid>.jsonl`. Parent is the path segment before `subagents`.

### File format

JSONL. One JSON object per line. Two flavours of line: `user` and `assistant`.

```json
{"parentUuid":null,"cwd":"/home/user/projects/webapp",
 "sessionId":"a1b2c3d4-...","version":"2.1.59","gitBranch":"feature/auth",
 "type":"user","message":{"role":"user","content":"add rate limiting..."},
 "uuid":"...","timestamp":"2026-03-17T11:35:00.508Z"}
{"type":"assistant","sessionId":"...","message":{"model":"claude-sonnet-4-6",
   "content":[{"type":"text","text":"..."}],"stop_reason":"tool_use",
   "usage":{"input_tokens":1200,"cache_creation_input_tokens":3500,
            "cache_read_input_tokens":800,"output_tokens":150}},
 "timestamp":"..."}
```

### Fields we parse

From each line: `type`, `sessionId`, `slug`, `version`, `cwd`, `gitBranch`, `timestamp`, `message.{role,model,content,stop_reason,usage.{input_tokens,output_tokens,cache_creation_input_tokens,cache_read_input_tokens}}`.

### Derived fields

- `Summary` ← first user message's `content` text (or first `{type:"text",text:...}` item), capped at 120 characters.
- `Model` ← most recent assistant `message.model`.
- `TokensIn`/`TokensOut` ← sum of `usage.input_tokens` / `usage.output_tokens` across all assistant lines. **Ai-dash ignores cache tokens; we will not** — we store `cache_creation_input_tokens` and `cache_read_input_tokens` separately so cost calculations stay honest.
- `StartedAt`/`EndedAt` ← min/max of line `timestamp`s.
- `Repo`/`Project` ← `cwd` (preferred); slug-encoded dir name is a fallback.
- `Status` / `CurrentState` ← derived from last `stop_reason`:
  - `end_turn` → `completed` / `done`
  - `tool_use` → `active` / `tool call`
  - `max_tokens` → `active` / `max tokens`
  - `pause_turn` → `active` / `waiting`
  - otherwise, if file mtime < 5 min → `active` / `running`

### Reference parse loop ([claude/source.go:246-257](https://github.com/adinhodovic/ai-dash/blob/main/internal/sources/claude/source.go#L246))

```go
if line.Type == "assistant" && line.Message != nil {
    if line.Message.Model != ""      { s.Model = line.Message.Model }
    if line.Message.StopReason != "" { lastStopReason = line.Message.StopReason }
    if line.Message.Usage != nil {
        tokensIn  += line.Message.Usage.InputTokens
        tokensOut += line.Message.Usage.OutputTokens
    }
}
```

## Provider 2 — Codex

### On disk

- Config: `~/.codex/config.toml` (configurable via `codex_path`).
- Sessions: derived as `~/.codex/sessions/` — plus `~/.codex/` and `~/.codex/logs/` walked for files whose base name matches `session|history|transcript|chat|conversation|messages`.
- Actual session files: `~/.codex/sessions/YYYY/MM/DD/rollout-<uuid>.jsonl`.

### File format

JSONL. Each line is a typed envelope:

```json
{"timestamp":"...","type":"session_meta","payload":{...}}
{"timestamp":"...","type":"turn_context","payload":{...}}
{"timestamp":"...","type":"response_item","payload":{"type":"message","role":"user","content":[{"type":"input_text","text":"..."}]}}
{"timestamp":"...","type":"event_msg","payload":{"type":"task_started"|"turn_aborted"|"user_message",...}}
```

### Fields we parse

- `session_meta.{id, timestamp, cwd, cli_version, model_provider}`
- `turn_context.{cwd, model, effort}`
- `response_item.{type, role, content[].type, content[].text}`
- `event_msg.{type, message, reason}`

### Derived fields

- `ID` ← `session_meta.id`.
- `Model` ← `turn_context.model` (fallback to `session_meta.model_provider`).
- `Repo`/`Project` ← `cwd`.
- `Summary` ← first user `input_text`, stripped of `<environment_context>` and AGENTS.md boilerplate.
- `Status` ← `aborted` on `event_msg.type == turn_aborted`; `active` on `task_started`; else inferred.

### What ai-dash doesn't do but we will

**No token or cost parsing.** Ai-dash leaves Codex tokens at zero. We need to compute them — Codex payloads do carry token counts in some envelope types (need to re-read real rollouts to confirm exact field names during Phase 3 implementation).

## Provider 3 — OpenCode

### On disk — SQLite, not JSONL

- Linux: `~/.local/share/opencode/opencode.db`
- macOS: `~/Library/Application Support/opencode/opencode.db`
- XDG fallback: `$XDG_DATA_HOME/opencode/opencode.db`
- Configurable via `opencode_path`.

**Open read-only:** `sqlite:<path>?mode=ro`. Never write.

### Schema (inferred from ai-dash test fixtures)

Three tables:

- `session(id, project_id, parent_id, slug, directory, title, version, share_url, summary_additions, summary_deletions, summary_files, summary_diffs, revert, permission, time_created, time_updated, time_compacting, time_archived, workspace_id)` — timestamps are unix-millis.
- `message(id, session_id, time_created, time_updated, data TEXT)` — `data` is a JSON blob per message.
- `part(id, message_id, session_id, time_created, time_updated, data TEXT)` — `data` holds message parts.

### JSON inside `message.data`

Accessed via `json_extract`:

- `$.role` — `user` / `assistant`
- `$.model.modelID`, `$.model.providerID`
- `$.finish` — `stop` / `tool-calls`
- `$.time.created`, `$.time.completed`
- `$.error.name` — e.g. `MessageAbortedError`

### JSON inside `part.data`

- `$.type` — e.g. `tool`
- `$.state.status` — e.g. `running`

### Reference query ([opencode/source.go:101-160](https://github.com/adinhodovic/ai-dash/blob/main/internal/sources/opencode/source.go#L101))

```sql
SELECT s.id, s.directory, s.title, s.version, s.parent_id, …,
  COALESCE((
    SELECT json_extract(m.data, '$.model.modelID')
    FROM message m WHERE m.session_id = s.id
    ORDER BY m.time_created ASC LIMIT 1
  ), '') AS first_model,
  … (several more correlated subqueries: latest message role/finish/error,
     latest completed assistant finish, latest part type/status) …
FROM session s
```

### Status heuristic

Priority order: aborted-error → pending-assistant (plus part type/status, or previous finish) → `finish=tool-calls|stop` → mtime<5min fallback.

## Cross-provider discovery & ingestion

Ai-dash orchestrates every poll in [`internal/sources/discovery.go`](https://github.com/adinhodovic/ai-dash/blob/main/internal/sources/discovery.go):

```go
func Discover(cfg Config) []session.Session {
    var out []session.Session
    for _, p := range providers {
        out = append(out, p.Discover(cfg)...)
    }
    classifySessions(out)   // set ParentID for subagents, tag "subagent"
    sort.Sort(byStartedAt(out))
    return out
}
```

### Implications for Tricorder

- Our **capture CLI** can keep this "poll all providers" shape for a one-shot `tricorder sync` command.
- Our **per-session subprocess** (triggered by Claude Code's `SessionStart` hook) only cares about the single session it was spawned for — not a full rescan.
- Subagent classification runs post-parse; port `classifySessions` directly.

## Things to remember when porting to PHP

- **Stream JSONL, don't slurp.** Ai-dash uses `bufio.Scanner` with a 2 MiB line buffer. In PHP use `fopen` + `fgets`, and raise `ini_set('memory_limit', ...)` in the worker.
- **SQLite read-only mode matters.** Use `sqlite:<path>;mode=ro` DSN or `PDO::ATTR_DEFAULT_FETCH_MODE` with a read-only flag; we do not want to ever hold a write lock on an OpenCode-user's DB.
- **Timestamps.** Claude Code uses ISO-8601 strings, Codex uses ISO-8601 strings, OpenCode uses unix-millis integers. Normalize to `datetime` on the way in.
- **Token fields per provider are not equivalent.** Claude has input/output + cache creation/read; Codex has whatever we discover in Phase 3; OpenCode per-message JSON has to be sampled. Keep them distinct in the `model_requests` table; only normalize in the rollup queries.
- **Missing `CostUSD`.** Ai-dash leaves cost computation as an exercise for the reader. We'll need per-model pricing tables for Anthropic and OpenAI, parameterized so they can be updated without a code change.

## Credits

All parsing logic credit belongs to [Adin Hodovic](https://github.com/adinhodovic) and [ai-dash](https://github.com/adinhodovic/ai-dash). This file is our reading of that code; any bug in our port is ours.
