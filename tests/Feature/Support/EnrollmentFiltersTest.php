<?php

namespace Tests\Feature\Support;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\EnrollmentFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnrollment(array $studentAttrs = [], array $courseAttrs = [], array $enrollmentAttrs = []): Enrollment
    {
        return Enrollment::factory()->create(array_merge([
            'student_id' => Student::factory()->create($studentAttrs)->id,
            'course_id' => Course::factory()->create($courseAttrs)->id,
        ], $enrollmentAttrs));
    }

    public function test_search_matches_student_nim_name_and_course_code()
    {
        $match = $this->makeEnrollment(['nim' => '10011223', 'name' => 'Budi Santoso'], ['code' => 'IF101']);
        $this->makeEnrollment(['nim' => '10099999', 'name' => 'Lain Saja'], ['code' => 'MK999']);

        $results = EnrollmentFilters::fromRequest(['q' => 'Budi'])->get();

        $this->assertCount(1, $results);
        $this->assertSame($match->id, $results->first()->id);
    }

    public function test_quick_filter_by_status_is_case_insensitive_and_scoped()
    {
        $approved = $this->makeEnrollment([], [], ['status' => 'APPROVED']);
        $this->makeEnrollment([], [], ['status' => 'DRAFT']);

        $results = EnrollmentFilters::fromRequest(['status' => ['approved']])->get();

        $this->assertCount(1, $results);
        $this->assertSame($approved->id, $results->first()->id);
    }

    public function test_advanced_filter_and_logic_requires_all_conditions()
    {
        $sharedCourseId = Course::factory()->create(['code' => 'IF201'])->id;

        $match = $this->makeEnrollment([], [], ['course_id' => $sharedCourseId, 'status' => 'APPROVED']);
        $this->makeEnrollment([], [], ['course_id' => $sharedCourseId, 'status' => 'DRAFT']);
        $this->makeEnrollment([], ['code' => 'MK300'], ['status' => 'APPROVED']);

        $filters = json_encode([
            'logic' => 'and',
            'conditions' => [
                ['field' => 'status', 'op' => 'equal', 'value' => 'APPROVED'],
                ['field' => 'course_code', 'op' => 'contains', 'value' => 'IF2'],
            ],
        ]);

        $results = EnrollmentFilters::fromRequest(['filters' => $filters])->get();

        $this->assertCount(1, $results);
        $this->assertSame($match->id, $results->first()->id);
    }

    public function test_advanced_filter_or_logic_matches_either_condition()
    {
        $rejected = $this->makeEnrollment([], [], ['status' => 'REJECTED', 'semester' => 'GENAP']);
        $ganjil = $this->makeEnrollment([], [], ['status' => 'DRAFT', 'semester' => 'GANJIL']);
        $this->makeEnrollment([], [], ['status' => 'APPROVED', 'semester' => 'GENAP']);

        $filters = json_encode([
            'logic' => 'or',
            'conditions' => [
                ['field' => 'status', 'op' => 'equal', 'value' => 'REJECTED'],
                ['field' => 'semester', 'op' => 'equal', 'value' => 'GANJIL'],
            ],
        ]);

        $results = EnrollmentFilters::fromRequest(['filters' => $filters])->get();

        $this->assertEqualsCanonicalizing([$rejected->id, $ganjil->id], $results->pluck('id')->all());
    }

    public function test_sort_supports_multiple_columns_in_order()
    {
        $this->makeEnrollment([], [], ['academic_year' => '2025/2026', 'semester' => 'GENAP']);
        $this->makeEnrollment([], [], ['academic_year' => '2025/2026', 'semester' => 'GANJIL']);
        $this->makeEnrollment([], [], ['academic_year' => '2024/2025', 'semester' => 'GANJIL']);

        $sort = json_encode([
            ['field' => 'academic_year', 'dir' => 'desc'],
            ['field' => 'semester', 'dir' => 'asc'],
        ]);

        $results = EnrollmentFilters::fromRequest(['sort' => $sort])->get();

        $this->assertSame(
            [['2025/2026', 'GANJIL'], ['2025/2026', 'GENAP'], ['2024/2025', 'GANJIL']],
            $results->map(fn ($r) => [$r->academic_year, $r->semester])->all(),
        );
    }

    public function test_needs_join_is_true_only_when_sorting_by_a_related_column()
    {
        $this->assertFalse(EnrollmentFilters::needsJoin(['sort' => json_encode([['field' => 'status', 'dir' => 'asc']])]));
        $this->assertTrue(EnrollmentFilters::needsJoin(['sort' => json_encode([['field' => 'student_name', 'dir' => 'asc']])]));
        $this->assertFalse(EnrollmentFilters::needsJoin([]));
    }
}
