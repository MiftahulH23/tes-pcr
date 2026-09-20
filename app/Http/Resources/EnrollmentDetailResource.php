<?php

namespace App\Http\Resources;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Everything about one enrollment: its own fields plus the full student and
 * course records. Expects the `student` and `course` relations to be loaded.
 *
 * @property int $id
 * @property string $academic_year
 * @property string $semester
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Student $student
 * @property Course $course
 */
class EnrollmentDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year' => $this->academic_year,
            'semester' => $this->semester,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'student' => [
                'nim' => $this->student->nim,
                'name' => $this->student->name,
                'email' => $this->student->email,
            ],
            'course' => [
                'code' => $this->course->code,
                'name' => $this->course->name,
                'credits' => $this->course->credits,
            ],
        ];
    }
}
