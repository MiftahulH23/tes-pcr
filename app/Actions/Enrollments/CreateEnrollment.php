<?php

namespace App\Actions\Enrollments;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the student and course (create-if-missing by nim/code), then
 * creates the enrollment — all inside a single DB transaction so a
 * failure at any step rolls back the other inserts. Whether a nim/code may
 * already exist (reuse) or must be new is decided by StoreEnrollmentRequest.
 */
class CreateEnrollment
{
    /**
     * @param  array{
     *     student: array{nim: string, name?: string|null, email?: string|null},
     *     course: array{code: string, name?: string|null, credits?: int|null},
     *     academic_year: string,
     *     semester: string,
     *     status: string,
     * }  $data
     */
    public function handle(array $data): Enrollment
    {
        return DB::transaction(function () use ($data) {
            $student = Student::firstOrCreate(
                ['nim' => $data['student']['nim']],
                [
                    'name' => $data['student']['name'] ?? null,
                    'email' => $data['student']['email'] ?? null,
                ]
            );

            $course = Course::firstOrCreate(
                ['code' => $data['course']['code']],
                [
                    'name' => $data['course']['name'] ?? null,
                    'credits' => $data['course']['credits'] ?? null,
                ]
            );

            return Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'academic_year' => $data['academic_year'],
                'semester' => $data['semester'],
                'status' => $data['status'],
            ]);
        });
    }
}
