# CONTEXT — canonical terminology

Ported from [slopwatch/CONTEXT.md](https://github.com/mattpocock/slopwatch/blob/main/CONTEXT.md). The slopwatch project deliberately constrains vocabulary; we do the same so our data model, UI copy, and conversations all agree. If a word isn't in this file, check whether we've already defined the concept under a different name before inventing one.

---

## Core entities

### Session
One logical run of one coding agent for one developer in one working directory. Contains a DAG of Turns (linear for most agents; potentially branching if we later support Pi). When the user starts Claude Code in `~/projects/foo`, that's one Session. Quitting and re-opening later starts a new Session (resumes are deferred; see "Open questions").

Eloquent model: `App\Models\Session`.

### Turn
One user message plus the full assistant response — including every tool-use loop that happens before the assistant stops. Turns are nodes in a DAG (`parent_turn_id`), not a flat list. In v1 the DAG is always a straight line; the column is there so we don't have to migrate later.

Eloquent model: `App\Models\Turn`.

### ModelRequest
One HTTP call the agent made to a model provider (Anthropic, OpenAI, etc.) during a Turn. A single Turn contains many ModelRequests (one per tool-use iteration, reasoning step, etc.). **Cost and token dashboards sum ModelRequests.** Reviewers review Turns, not ModelRequests.

Eloquent model: `App\Models\ModelRequest`.

### Subagent
A Session that was spawned by another Session — e.g. Claude Code's `Task` tool. Modelled as a regular Session row with `parent_session_id` and `spawned_by_turn_id` set. Queried and reviewed independently of the parent.

No separate model; just a `Session` with parents populated.

### Listener
The on-machine capture component for one specific agent. One Listener per supported agent (a Claude Code Listener, a Codex Listener, an OpenCode Listener). Shipped inside the `tricorder-capture` Bun binary. Talks to the Server over HTTPS; never touches the database directly.

### Server
The single self-hosted Laravel process that accepts events from Listeners, persists them, and serves the Filament dashboard and admin plane. **The Server is the only component that talks to the database.**

One Server per team/org. Horizontal scaling is deferred but not precluded.

### ReviewItem (proposed, v2)
Orthogonal to Session state; holds the DRI review workflow (assignments, status, turn-level comments). Schema stubbed in Phase 1 migrations, UI deferred. Shape is tentative — this is still the #1 open question carried over from slopwatch.

---

## Roles

### DRI — Directly Responsible Individual
The human who reviews Sessions on behalf of a team. First-class role, not a generic user flag. Exact semantics (queryer vs recipient, per-team vs per-org, permission vs accountability) are deliberately unresolved until v1 is running against real data.

### Admin
Mints and revokes Sanctum tokens, manages users, configures retention, sees system health.

### Developer
A human whose coding-agent Sessions are captured. Logs into the Filament UI via Google (Socialite) to see their own Sessions.

---

## Forbidden / reserved terms

**Do not use these in code, migrations, Filament resource names, docs, or commit messages** — they lead to wrong mental models:

| Don't use | Use instead |
|---|---|
| conversation, chat, run | Session |
| message, exchange | Turn |
| API call, inference | ModelRequest |
| sidecar, adapter, probe | Listener |
| backend, hub, daemon | Server |
| agent / bot (as a noun for the AI) | the coding agent, or name it: Claude Code / Codex / OpenCode |

If you catch this language creeping into a PR, push back. Naming drift is how products stop making sense.

---

## Open questions (inherited from slopwatch)

These are deliberately left open until v1 is collecting real data:

1. **DRI shape** — per-team, per-org, or assignable? Permission vs accountability?
2. **Resumes** — how do we represent `codex resume`, Claude Code compaction-continue, etc.? Same Session with a gap, or parent/child Sessions?
3. **Retention** — auto-purge raw event payloads after N days? Configurable per team?
4. **Schema drift** — when an agent's JSONL format changes, do we fail closed, fail open with a warning, or partial-parse?
5. **Review state vs Session state** — keep them orthogonal (slopwatch's `ReviewItem`) or collapse?

---

## Credits

Terminology and the forbidden-terms list are ported from [slopwatch/CONTEXT.md](https://github.com/mattpocock/slopwatch/blob/main/CONTEXT.md). Any inconsistency between this file and the original is unintentional — flag it.
