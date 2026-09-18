<?php

namespace Tests\Feature\Actions;

use App\Actions\Enrollments\CreateEnrollment;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_student_course_and_enrollment_when_none_exist()
    {
        $enrollment = (new CreateEnrollment)->handle([
            'student' => ['nim' => '10000001', 'name' => 'Budi Santoso', 'email' => 'budi@example.com'],
            'course' => ['code' => 'IF101', 'name' => 'Algoritma', 'credits' => 3],
            'academic_year' => '2025/2026',
            'semester' => 'GANJIL',
            'status' => 'DRAFT',
        ]);

        $this->assertDatabaseHas('students', ['nim' => '10000001', 'name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        $this->assertDatabaseHas('courses', ['code' => 'IF101', 'name' => 'Algoritma', 'credits' => 3]);
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'academic_year' => '2025/2026',
            'semester' => 'GANJIL',
            'status' => 'DRAFT',
        ]);
        $this->assertSame('10000001', $enrollment->student->nim);
        $this->assertSame('IF101', $enrollment->course->code);
    }

    public function test_reuses_existing_student_and_course_by_nim_and_code()
    {
        $student = Student::factory()->create(['nim' => '10000002', 'name' => 'Existing Name', 'email' => 'existing@example.com']);
        $course = Course::factory()->create(['code' => 'IF102', 'name' => 'Existing Course', 'credits' => 4]);

        $enrollment = (new CreateEnrollment)->handle([
            'student' => ['nim' => '10000002', 'name' => 'Ignored Name', 'email' => 'ignored@example.com'],
            'course' => ['code' => 'IF102', 'name' => 'Ignored Course', 'credits' => 1],
            'academic_year' => '2025/2026',
            'semester' => 'GENAP',
            'status' => 'SUBMITTED',
        ]);

        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($course->id, $enrollment->course_id);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'Existing Name']);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name' => 'Existing Course']);
    }

    public function test_rolls_back_all_inserts_when_the_enrollment_insert_fails()
    {
        try {
            (new CreateEnrollment)->handle([
                'student' => ['nim' => '90000001', 'name' => 'Rollback Test', 'email' => 'rollback@example.com'],
                'course' => ['code' => 'RB100', 'name' => 'Rollback Course', 'credits' => 3],
                'academic_year' => '2025/2026',
                'semester' => 'INVALID_SEMESTER',
                'status' => 'DRAFT',
            ]);

            $this->fail('Expected a database exception for the invalid semester value.');
        } catch (\Throwable) {
            // expected — the DB rejects the enum value.
        }

        $this->assertDatabaseMissing('students', ['nim' => '90000001']);
        $this->assertDatabaseMissing('courses', ['code' => 'RB100']);
        $this->assertDatabaseCount('enrollments', 0);
    }
}
