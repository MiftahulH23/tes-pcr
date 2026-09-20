---
name: add-filterable-column
description: Runbook for adding a new column to the KRS table so it is displayed, sortable, filterable, exported, and seeded consistently across backend and frontend. Use whenever a column, enrollment field, or new filter or sort target is added or changed.
---

# add-filterable-column

A column touches many files that must agree. Missing one gives silent bugs (a column that sorts but doesn't export, an operator the backend ignores). Work through every step; skip a step only if it truly doesn't apply and say why.

First decide the kind:

- **A. Own column** — a new column on `enrollments` (needs a migration).
- **B. Related column** — a column that already exists on `students` or `courses`, shown through the join.
- **C. Editable** — the user can change it in the Create/Edit form (adds the steps at the end).

## Backend

1. **Migration (kind A only).** New migration, never edit an applied one. `enrollments` has ~5M rows in production and deploy auto-runs `migrate --force`: adding a nullable column is cheap; an index must be `CREATE INDEX CONCURRENTLY` with `public $withinTransaction = false;`. Also update `Enrollment::$fillable`, `EnrollmentFactory`, and the seeder `app/Console/Commands/SeedEnrollments.php` (it bulk-inserts explicit column lists — a missing column gives NULLs or NOT NULL failures on the 5M seed).
2. **`EnrollmentFilters::COLUMNS`** — add the whitelist entry. This is the only source of sortable/filterable column names.
   - Own: `'x' => ['own' => true, 'joined' => 'enrollments.x', 'enum' => false]`
   - Related: `'student_x' => ['own' => false, 'fk' => 'student_id', 'table' => 'students', 'column' => 'x', 'joined' => 'students.x', 'enum' => false]`
   - `'enum' => true` only for fixed uppercase values (input gets uppercased so the indexed column stays function-free).
3. **`EnrollmentFilters::baseQuery` select list** — add `'enrollments.x'` or `'students.x as student_x'`. The alias must equal the `COLUMNS` key, because that key is the JSON field name.
4. **`EnrollmentResource::toArray`** and its `@property` docblock.
5. **`ExportEnrollmentsCsv`** — add to `HEADER` and to the `fputcsv` row, in the same position. (Easy to forget: both are hard-coded.)
6. **Index, if useful.** Free-text `contains` → trigram GIN (see the existing `*_add_trigram_search_indexes` migration), created concurrently. Low-cardinality columns rarely benefit from a lone B-tree. Adding the column to quick search (`applySearch`) changes documented behavior (NIM/name/course code) — only do it deliberately, and update the README.
7. **Test** — add cases to `tests/Feature/Support/EnrollmentFiltersTest.php` (filter, and sort if relevant).

## Frontend

8. **`types/enrollment.ts`** — add the field to `Enrollment` and the key to the `FilterableColumn` union.
9. **`components/enrollments/columns.ts`** — add to `ENROLLMENT_COLUMNS` (array order = display order) and `OPERATORS_BY_COLUMN` (typed `Record<FilterableColumn, …>`, so TypeScript fails until you add it). Offer only operators `applyOperator` supports: `contains`, `startsWith`, `equal`, `in`, `between`; enums only `equal`/`in`.
10. **`components/enrollments/enrollment-table.tsx`** — header cells and `colSpan` derive from `ENROLLMENT_COLUMNS`, but the **body cells are hand-written**: add `<TableCell className="whitespace-nowrap">{row.x}</TableCell>` in the same position. A wider table may need a bigger `min-w-*` on `<Table>`; recheck phone, tablet, and desktop widths.

11. **Row detail (if users should see it there).** Add the field to `EnrollmentDetailResource`, to `EnrollmentDetail` in `types/enrollment.ts`, and as a `<Field>` in `components/enrollments/enrollment-detail-dialog.tsx`; extend `test_show_returns_the_full_detail_of_one_enrollment`.

## If editable (kind C)

`StoreEnrollmentRequest` / `UpdateEnrollmentRequest` rules + custom messages, attribute name in `lang/id/validation.php`, `CreateEnrollment` / `UpdateEnrollment` actions, the field and payload in `enrollment-form-dialog.tsx`, the matching rule in `lib/enrollment-validation.ts` (identical messages), and tests including a rollback case if the transaction changed.

## Docs

README: the column list under "Parameter `/enrollments/data`" and the design-decision bullets if behavior changed. `docs/postman_collection.json` if a payload changed.

## Check

Run the `verify` skill. Then exercise the new column against a local server:
`/enrollments/data?sort=[{"field":"x","dir":"asc"}]` and a `filters=` condition using it, and confirm an export includes it. Sorting by a related (`own: false`) column forces a join and is slower (~2s at 5M rows, a documented limitation) — mention it if the column is likely to be sorted.
