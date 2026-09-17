<?php

namespace App\Support;

use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentFilters
{
    /**
     * Whitelist of filterable/sortable columns exposed to the client,
     * mapped to their qualified SQL column to prevent arbitrary column injection.
     */
    public const COLUMNS = [
        'student_nim' => 'students.nim',
        'student_name' => 'students.name',
        'course_code' => 'courses.code',
        'course_name' => 'courses.name',
        'semester' => 'enrollments.semester',
        'academic_year' => 'enrollments.academic_year',
        'status' => 'enrollments.status',
    ];

    public static function baseQuery(): Builder
    {
        return Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
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

    public static function applySearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('students.nim', 'like', "%{$term}%")
                ->orWhere('students.name', 'like', "%{$term}%")
                ->orWhere('courses.code', 'like', "%{$term}%");
        });
    }

    public static function applyQuickFilters(Builder $query, array $params): Builder
    {
        if (! empty($params['status'])) {
            $query->whereIn('enrollments.status', (array) $params['status']);
        }

        if (! empty($params['semester'])) {
            $query->whereIn('enrollments.semester', (array) $params['semester']);
        }

        return $query;
    }

    public static function applyAdvanced(Builder $query, ?array $group): Builder
    {
        if (! $group || (empty($group['conditions']) && empty($group['groups']))) {
            return $query;
        }

        $query->where(function (Builder $q) use ($group) {
            self::applyGroup($q, $group);
        });

        return $query;
    }

    protected static function applyGroup(Builder $query, array $group): void
    {
        $logic = strtolower($group['logic'] ?? 'and') === 'or' ? 'orWhere' : 'where';

        foreach ($group['conditions'] ?? [] as $condition) {
            $query->{$logic}(function (Builder $q) use ($condition) {
                self::applyCondition($q, $condition);
            });
        }

        foreach ($group['groups'] ?? [] as $sub) {
            $query->{$logic}(function (Builder $q) use ($sub) {
                self::applyGroup($q, $sub);
            });
        }
    }

    protected static function applyCondition(Builder $query, array $condition): void
    {
        $field = $condition['field'] ?? null;
        $op = $condition['op'] ?? 'equal';
        $value = $condition['value'] ?? null;

        if (! isset(self::COLUMNS[$field]) || $value === null || $value === '') {
            return;
        }

        $column = self::COLUMNS[$field];

        match ($op) {
            'contains' => $query->where($column, 'like', '%'.$value.'%'),
            'startsWith' => $query->where($column, 'like', $value.'%'),
            'in' => $query->whereIn($column, is_array($value) ? $value : [$value]),
            'between' => is_array($value) && count($value) === 2
                ? $query->whereBetween($column, $value)
                : null,
            default => $query->where($column, '=', $value),
        };
    }

    public static function applySort(Builder $query, array $sorts): Builder
    {
        $applied = false;

        foreach ($sorts as $sort) {
            $field = $sort['field'] ?? null;
            $dir = strtolower($sort['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            if (isset(self::COLUMNS[$field])) {
                $query->orderBy(self::COLUMNS[$field], $dir);
                $applied = true;
            }
        }

        if (! $applied) {
            $query->orderBy('enrollments.id', 'desc');
        }

        return $query;
    }

    public static function fromRequest(array $params): Builder
    {
        $query = self::baseQuery();

        self::applySearch($query, $params['q'] ?? null);
        self::applyQuickFilters($query, $params);

        $advanced = $params['filters'] ?? null;
        if (is_string($advanced)) {
            $advanced = json_decode($advanced, true);
        }
        self::applyAdvanced($query, is_array($advanced) ? $advanced : null);

        $sort = $params['sort'] ?? null;
        if (is_string($sort)) {
            $sort = json_decode($sort, true);
        }
        self::applySort($query, is_array($sort) ? $sort : []);

        return $query;
    }
}
