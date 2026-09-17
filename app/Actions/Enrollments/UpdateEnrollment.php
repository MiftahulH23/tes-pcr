<?php

namespace App\Actions\Enrollments;

use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * Updates the enrollment's own fields and, optionally, the related
 * student/course fields the caller chose to include — all inside a
 * single DB transaction.
 */
class UpdateEnrollment
{
    /**
     * @param  array{
     *     academic_year: string,
     *     semester: string,
     *     status: string,
     *     student?: array{name?: string, email?: string},
     *     course?: array{name?: string, credits?: int},
     * }  $data
     */
    public function handle(Enrollment $enrollment, array $data): Enrollment
    {
        DB::transaction(function () use ($enrollment, $data) {
            $enrollment->update([
                'academic_year' => $data['academic_year'],
                'semester' => $data['semester'],
                'status' => $data['status'],
            ]);

            if (! empty($data['student'])) {
                $enrollment->student()->update($data['student']);
            }

            if (! empty($data['course'])) {
                $enrollment->course()->update($data['course']);
            }
        });

        return $enrollment->refresh();
    }
}
