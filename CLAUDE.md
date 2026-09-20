@AGENTS.md

## Claude Code specifics

**Subagents** (`.claude/agents/`)

- `frontend` / `backend` — implement work confined to one side. `frontend` owns `resources/js` and `resources/css`; `backend` owns `app/`, `routes/`, `database/`, `lang/`, `config/`, `tests/`.
- `reviewer` (read-only) — run it on any non-trivial diff before calling the work done, always when a change spans both sides.
- `db-performance` (read-only) — run it whenever a query, index, migration, or filter/sort target changes.
- `qa-mobile` — run it after any UI or layout change; it needs a browser tool and says so if it has none.

**Skills** (`.claude/skills/`): `verify`, `add-filterable-column`, `add-shadcn-component`, and `deploy-and-watch` (user-invoked only, because it pushes to production).

**Working a feature that spans both sides:** settle the JSON contract first (route, request payload, response shape), do or delegate each side, then run `reviewer`, then `verify`.

Don't push, deploy, or mutate production data unless the user explicitly asks.
