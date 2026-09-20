---
name: verify
description: Run this project's pre-push verification scoped to what actually changed — PHP tests and Pint, frontend build, eslint, prettier, and a tsc check limited to touched files. Use before saying work is done, before committing, or when asked to verify or check everything.
---

# verify

Run only the checks that match the changes, then report pass/fail per check with the real output for any failure. Never claim a check passed if you didn't run it.

CI parity: CI runs `vendor/bin/pint`, `npm run format`, `npm run lint`, `vendor/bin/phpunit`. The steps below are the read-only equivalents.

## 1. Find what changed

```bash
git status --short
git diff --name-only HEAD
```

Include untracked files from `git status`. Sort the changes into PHP side (`app/`, `routes/`, `database/`, `lang/`, `config/`, `tests/`) and frontend side (`resources/js`, `resources/css`).

## 2. PHP side changed

```bash
php artisan test
vendor/bin/pint --test
```

- If Pint fails, run `vendor/bin/pint <reported files>` and re-run `--test`. (Pint style here: `new Foo`, not `new Foo()`.)
- Tests need the Postgres database `tes_pcr_testing`. If the connection fails, report "not verified: test DB unreachable" — do not skip silently.

## 3. Frontend side changed

```bash
npm run build
npx eslint <changed .ts/.tsx files>
npx prettier --check <changed files under resources/, except resources/js/components/ui/*>
npx tsc --noEmit 2>&1 | grep -E "<changed file paths joined with |>"
```

- `resources/js/components/ui/*` is in `.prettierignore` (generated shadcn style); don't reformat those.
- Prettier failure → `npx prettier --write <files>`, then re-check.
- `tsc` must print **nothing** for touched files. The whole project has known pre-existing errors in the auth pages and `welcome.tsx`; ignore those and don't add new ones.

## 4. Contract and docs

If a route, payload, column, enum, or validation rule changed, confirm the "Keep-in-sync points" in `AGENTS.md` were all updated, and that the README (API section, design decisions) still matches.

## 5. Report

A table of `check | result`, then a **Not verified** list. Visual behavior at phone, tablet (`sm`–`lg`), and desktop widths, plus light/dark mode, can't be confirmed without a browser — say so explicitly (the `qa-mobile` subagent covers it when a browser tool exists).
