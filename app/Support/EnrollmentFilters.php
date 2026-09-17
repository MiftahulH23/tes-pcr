<?php

namespace App\Support;

use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class EnrollmentFilters
{
    /**
     * Whitelist of filterable/sortable columns exposed to the client. Columns
     * reached through the students/courses join carry the related table's FK
     * column on `enrollments` plus the target table/column, so search and
     * advanced filters can reach them via a `whereIn` subquery instead of an
     * actual join — that lets Postgres use the students/courses trigram
     * indexes directly instead of a per-row nested loop (see README:
     * performance). Sorting still needs the real join since ORDER BY across
     * tables can't be expressed as a subquery.
     */
    public const COLUMNS = [
        'student_nim' => ['own' => false, 'fk' => 'student_id', 'table' => 'students', 'column' => 'nim', 'joined' => 'students.nim', 'enum' => false],
        'student_name' => ['own' => false, 'fk' => 'student_id', 'table' => 'students', 'column' => 'name', 'joined' => 'students.name', 'enum' => false],
        'course_code' => ['own' => false, 'fk' => 'course_id', 'table' => 'courses', 'column' => 'code', 'joined' => 'courses.code', 'enum' => false],
        'course_name' => ['own' => false, 'fk' => 'course_id', 'table' => 'courses', 'column' => 'name', 'joined' => 'courses.name', 'enum' => false],
        'semester' => ['own' => true, 'joined' => 'enrollments.semester', 'enum' => true],
        'academic_year' => ['own' => true, 'joined' => 'enrollments.academic_year', 'enum' => false],
        'status' => ['own' => true, 'joined' => 'enrollments.status', 'enum' => true],
    ];

    public static function baseQuery(bool $withJoin = true): Builder
    {
        $query = Enrollment::query();

        if ($withJoin) {
            $query->join('students', 'students.id', '=', 'enrollments.student_id')
                ->join('courses', 'courses.id', '=', 'enrollments.course_id')
                ->select([
                    'enrollments.id',
                    'students.nim as student_nim',
                    'students.name as student_name',
                    'courses.code as course_code',
                    'courses.name as course_name',
                    'enrollments.semester',
                    'enrollments.academic_year',
                    'enrollments.status',
                    'enrollments.created_at',
                ]);
        }

        return $query;
    }

    /** Only sorting by a joined column forces a real join — search/filters use subqueries instead. */
    public static function needsJoin(array $params): bool
    {
        foreach (self::decode($params['sort'] ?? null) ?? [] as $sort) {
            $field = $sort['field'] ?? null;
            if (isset(self::COLUMNS[$field]) && ! self::COLUMNS[$field]['own']) {
                return true;
            }
        }

        return false;
    }

    private static function decode(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : null;
    }

    /** Live search across the 3 required columns (NIM, student name, course code) via indexed subqueries. */
    public static function applySearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->whereIn('enrollments.student_id', function ($sub) use ($term) {
                $sub->select('id')->from('students')
                    ->where('nim', 'ilike', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%");
            })->orWhereIn('enrollments.course_id', function ($sub) use ($term) {
                $sub->select('id')->from('courses')->where('code', 'ilike', "%{$term}%");
            });
        });
    }

    public static function applyQuickFilters(Builder $query, array $params): Builder
    {
        if (! empty($params['status'])) {
            $query->whereIn('enrollments.status', self::toEnumCase((array) $params['status']));
        }

        if (! empty($params['semester'])) {
            $query->whereIn('enrollments.semester', self::toEnumCase((array) $params['semester']));
        }

        return $query;
    }

    /**
     * Applies one filter group: a list of column conditions combined with a
     * single AND/OR logic operator, per the "advanced order AND/OR" scenario
     * (interpreted as a combined filter group, as the spec allows).
     */
    public static function applyAdvanced(Builder $query, ?array $group): Builder
    {
        if (! $group || empty($group['conditions'])) {
            return $query;
        }

        $logic = strtolower($group['logic'] ?? 'and') === 'or' ? 'orWhere' : 'where';

        $query->where(function (Builder $q) use ($group, $logic) {
            foreach ($group['conditions'] as $condition) {
                $q->{$logic}(fn (Builder $inner) => self::applyCondition($inner, $condition));
            }
        });

        return $query;
    }

    protected static function applyCondition(Builder $query, array $condition): void
    {
        $field = $condition['field'] ?? null;
        $op = $condition['op'] ?? 'equal';
        $value = $condition['value'] ?? null;

        if (! isset(self::COLUMNS[$field]) || $value === null || $value === '') {
            return;
        }

        $meta = self::COLUMNS[$field];

        if ($meta['own']) {
            self::applyOperator($query, $meta['joined'], $op, $value, $meta['enum']);

            return;
        }

        // Joined column: filter via a subquery on the related table so the
        // trigram/B-tree index on that table can be used directly, instead
        // of forcing a join + per-row filter across all of `enrollments`.
        $query->whereIn("enrollments.{$meta['fk']}", function ($sub) use ($meta, $op, $value) {
            $sub->select('id')->from($meta['table']);
            self::applyOperator($sub, $meta['column'], $op, $value, $meta['enum']);
        });
    }

    /**
     * Case-insensitive everywhere a user can type free text, without ever
     * wrapping an indexed column in LOWER()/a function — doing that on
     * `enrollments.status` (also used for ORDER BY and already B-tree
     * indexed) made Postgres misjudge selectivity so badly it picked a
     * nested-loop plan that took ~10s instead of ~800ms. `semester`/`status`
     * are a fixed enum written in uppercase by validation, so normalizing
     * the *value* to match is lossless and keeps a plain, indexable
     * equality/IN. Free-text columns (names, nim, code) use ILIKE instead,
     * which the trigram indexes already handle well.
     */
    private static function applyOperator(Builder|QueryBuilder $query, string $column, string $op, mixed $value, bool $enum = false): void
    {
        if ($enum) {
            match ($op) {
                'in' => $query->whereIn($column, self::toEnumCase(is_array($value) ? $value : [$value])),
                default => $query->where($column, '=', self::toEnumCase([$value])[0]),
            };

            return;
        }

        match ($op) {
            'contains' => $query->where($column, 'ilike', '%'.$value.'%'),
            'startsWith' => $query->where($column, 'ilike', $value.'%'),
            'in' => $query->where(function (Builder|QueryBuilder $q) use ($column, $value) {
                foreach ((array) $value as $v) {
                    $q->orWhere($column, 'ilike', $v);
                }
            }),
            'between' => is_array($value) && count($value) === 2
                ? $query->whereBetween($column, $value)
                : null,
            default => $query->where($column, 'ilike', $value),
        };
    }

    /** @return list<string> */
    private static function toEnumCase(array $values): array
    {
        return array_map(fn ($v) => mb_strtoupper((string) $v), $values);
    }

    public static function applySort(Builder $query, array $sorts): Builder
    {
        $applied = false;

        foreach ($sorts as $sort) {
            $field = $sort['field'] ?? null;
            $dir = strtolower($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            if (isset(self::COLUMNS[$field])) {
                $query->orderBy(self::COLUMNS[$field]['joined'], $dir);
                $applied = true;
            }
        }

        if (! $applied) {
            $query->orderBy('enrollments.id', 'desc');
        }

        return $query;
    }

    public static function fromRequest(array $params, bool $withJoin = true): Builder
    {
        $query = self::baseQuery($withJoin);

        self::applySearch($query, $params['q'] ?? null);
        self::applyQuickFilters($query, $params);
        self::applyAdvanced($query, self::decode($params['filters'] ?? null));
        self::applySort($query, self::decode($params['sort'] ?? null) ?? []);

        return $query;
    }
}
