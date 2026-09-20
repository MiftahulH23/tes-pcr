# AGENTS.md

Single-page KRS (course enrollment) CRUD, built for a technical test and live at https://tes.miftahulhuda.site. It must stay fast at 5M+ `enrollments` rows.
This file is the working rules. `README.md` (Indonesian) is the fuller human doc — read the relevant section before changing behavior:
"Keputusan Desain" before touching create/update/delete/filter semantics, "Strategi Performa" before touching queries or indexes, "API / Routes" for request parameters, "Seeding 5 Juta Baris" and "Deployment" for those workflows.

## Stack

Laravel 13 (PHP 8.4) · Inertia.js + React 19 + TypeScript · Vite · Tailwind CSS v4 (tokens live in `resources/css/app.css`, there is no tailwind.config) · shadcn/ui (Radix) · PostgreSQL with `pg_trgm` · PHPUnit/Pest.

## Domain

- `students`: `nim` (unique, 8–12 digits), `name` (3–100), `email` (unique). `courses`: `code` (unique, `[A-Z]{2,4}[0-9]{3}`), `name` (3–120), `credits` (1–6).
- `enrollments` (the KRS): FKs `student_id` and `course_id`; `academic_year` `YYYY/YYYY` with the second year = first + 1; `semester` GANJIL|GENAP; `status` DRAFT|SUBMITTED|APPROVED|REJECTED (default DRAFT). Unique on (student, course, academic_year, semester). Soft-deleted.
- **Create** upserts the student and course by `nim`/`code` (existing rows are reused and the submitted name/email are ignored) and inserts the enrollment, all in one transaction. **Update** edits the enrollment plus optional student name/email and course name/credits. **Delete** is a soft delete and leaves students and courses alone.
- **Table:** server-side pagination (`page`, `page_size` ≤ 200), multi-column sort, quick filters (status, semester), live search over NIM / student name / course code (400 ms debounce), one advanced-filter group with AND/OR (`contains`, `startsWith`, `equal`, `in`, `between`), and a streamed CSV export of everything matching the current filters.
- **Seeding:** `php artisan academic:seed-enrollments --fresh` builds the 5M rows (about 20–25 minutes).

## How the app is wired (non-obvious)

- One Inertia page: `GET /enrollments` → `resources/js/pages/enrollments/index.tsx`. Its only props are the enum lists.
- Data and mutations are plain JSON endpoints in `routes/web.php`, called with `apiFetch` (`resources/js/lib/api.ts`: XSRF cookie + `ApiError`), not Inertia visits.
- Use named routes through Ziggy, `route('enrollments.data')` — never hardcode URLs.
- `/enrollments` and `/enrollments/*` are exempt from CSRF (`bootstrap/app.php`, `preventRequestForgery(except: …)`) because the page is public with no login, so plain `curl`/Postman work. Every other route keeps CSRF. Keep the exemption that narrow — `EnrollmentControllerTest` fails if it widens. `apiFetch` still sends the XSRF header; that's harmless.
- Auth, settings, dashboard and welcome pages are unused starter-kit leftovers. KRS pages need no login.

## Backend rules (Laravel)

Controllers are thin connectors: bind the request → call one class → return the response. No validation, queries, or business logic in controllers.

| Concern | Where |
|---|---|
| Validation + error messages | `app/Http/Requests/*Request.php` (FormRequest). Indonesian messages and attribute names in `lang/id/validation.php` |
| Business logic, transactions | `app/Actions/<Domain>/<VerbNoun>.php` with one public `handle()`; wrap multi-table writes in `DB::transaction` |
| Query building (filter/sort/search) | `app/Support/EnrollmentFilters.php` — shared by the list and export endpoints |
| JSON response shape | `app/Http/Resources/*Resource.php` |
| Schema | a new migration in `database/migrations/` — never edit an applied one |
| Enum-like constants | on the model (`Enrollment::STATUSES`, `Enrollment::SEMESTERS`) |

A new feature is: FormRequest + Action + Resource + controller method + named route + test.

### Performance rules (5M rows — learned the hard way, see README "Strategi Performa")

- Never wrap an indexed column in a function (`LOWER(status)`); normalize the input value instead.
- Filter on related tables (students/courses) with `whereIn` subqueries, not JOINs. Only sorting by a related column may join (`EnrollmentFilters::needsJoin`).
- Free text uses `ILIKE` (trigram GIN indexes exist). Column names used for sort/filter come only from the `EnrollmentFilters::COLUMNS` whitelist, never from raw input.
- Export streams with `lazyById()`; never load the whole set. `fputcsv` needs the explicit `$escape` argument (PHP 8.4). The stream closure calls `set_time_limit(0)`: without it PHP-FPM's 30s limit silently truncated the 5M-row export at ~11% (HTTP 200, no error) on production. Test long-running endpoints against production-sized data, not a small local set.
- Deploy runs `php artisan migrate --force` automatically. A migration touching `enrollments` must not hold a long table lock (create indexes `CONCURRENTLY`, outside a transaction).

## Frontend rules (`resources/js`)

```
components/ui/           shadcn primitives — the base for ALL UI; don't hand-roll buttons/inputs/dialogs
components/enrollments/  feature components (table, toolbar, dialogs, pagination, columns.ts)
hooks/                   state + data hooks (use-enrollment-table.ts owns list state and fetching)
lib/                     api.ts (fetch wrapper), enrollment-validation.ts, utils.ts (cn)
pages/                   Inertia pages — thin, they compose feature components
types/                   shared TS types (types/enrollment.ts)
layouts/                 starter-kit layouts
```

- Compose from `@/components/ui/*` and style with semantic Tailwind tokens (`bg-background`, `text-muted-foreground`, `border-border`) — no hard-coded colors. Merge classes with `cn()`. Import with `@/`.
- Dark mode is the manual `.dark` class on `<html>` (`hooks/use-appearance.tsx`); tokens flip on their own. Don't rely on a library's own theme detection (e.g. sonner's `theme` prop).
- UI copy is Indonesian. Notifications use `toast` from `sonner` (the `<Toaster/>` is mounted once in `app.tsx`).
- Logic goes in hooks/lib, not inline in pages or components.

### Layout gotchas that already bit us

- `html, body` are `overflow-x: hidden` (`app.css`), so a too-wide element is silently clipped, not scrollable. Make every new component fit a ~360px phone on its own; give flex/grid children that hold wide content `min-w-0`.
- `ui/table.tsx` already wraps `<table>` in a scroll container. Don't add another `overflow-x-auto` wrapper; use its `containerRef`.
- Focus indicators use `outline-*`, not `ring-*` (box-shadow rings left artifacts inside animated Radix dialogs).
- Check three widths: phone, tablet (`sm`–`lg`), desktop. The toolbar and table hint switch at `lg`; pagination and dialog padding switch at `sm`.

## Keep-in-sync points (change one → change all)

- **Filterable/sortable column:** `EnrollmentFilters::COLUMNS` ⇄ `types/enrollment.ts` (`FilterableColumn`) ⇄ `components/enrollments/columns.ts` (`ENROLLMENT_COLUMNS`, `OPERATORS_BY_COLUMN`) ⇄ cells in `enrollment-table.tsx` ⇄ README API section.
- **Form validation:** `StoreEnrollmentRequest` / `UpdateEnrollmentRequest` ⇄ `resources/js/lib/enrollment-validation.ts`, with identical messages. The backend is authoritative; the frontend only gives early feedback.
- **Enum values:** `Enrollment::STATUSES` / `SEMESTERS` ⇄ the migration ⇄ `types/enrollment.ts`.
- **Update semantics:** NIM and course code are immutable on edit (documented in README); student name/email and course name/credits are optional.

## Runbooks

Recurring multi-step tasks are written as plain-markdown runbooks in `.claude/skills/<name>/SKILL.md`. Follow them even if your tool doesn't load skills natively:

- `verify` — the scoped pre-push checks.
- `add-filterable-column` — adding or changing a table column across backend and frontend (it touches more files than you'd expect).
- `add-shadcn-component` — adding a UI primitive without clobbering the customized `ui/` files.
- `deploy-and-watch` — pushing to production and watching CI and deploy through to a smoke test.

## Commands

```bash
composer install && npm install && cp .env.example .env && php artisan key:generate && php artisan migrate
composer run dev              # server + vite. On Windows the "logs"/pail panel always fails — ignore it
php artisan test              # needs Postgres database `tes_pcr_testing` (see phpunit.xml)
vendor/bin/pint               # PHP style, CI runs it
npx eslint <files>            # frontend lint
npx prettier --check <files>  # 4 spaces, single quotes, width 150
npm run build                 # must pass
```

`npx tsc --noEmit` reports pre-existing errors in starter-kit files (auth pages, `welcome.tsx`). Ignore those; just don't add new ones in files you touch.
Tests are PHPUnit-style classes in `tests/Feature/...` mirroring `app/` paths, using `RefreshDatabase`. Pint style: `new Foo`, not `new Foo()`.

## Git & deploy

- Push to `main` → CI (`tests`, `linter`) → the `deploy` workflow SSHes into the VPS (`git reset --hard`, composer, npm ci/build, migrate, optimize, restart php8.4-fpm). **Pushing deploys to production** — only push when asked.
- To watch a deploy, record the previous `deploy` run id first and wait for a *newer* id; "latest run" can be the stale previous one.
- Never commit `.env`, keys, or secrets. Don't touch production data except deliberate smoke tests that clean up after themselves.

## Definition of done

- Backend change: `php artisan test` passes and `vendor/bin/pint --test <files you touched>` passes. A repo-wide Pint run reports pre-existing style diffs in untouched files — leave those alone.
- Frontend change: `npm run build`, eslint and `prettier --check` on touched files pass. If you couldn't view the UI, say so instead of claiming it works.
- Behavior or contract change: update README and every keep-in-sync point above.
