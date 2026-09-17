<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $student_nim
 * @property string $student_name
 * @property string $course_code
 * @property string $course_name
 * @property string $semester
 * @property string $academic_year
 * @property string $status
 */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_nim' => $this->student_nim,
            'student_name' => $this->student_name,
            'course_code' => $this->course_code,
            'course_name' => $this->course_name,
            'semester' => $this->semester,
            'academic_year' => $this->academic_year,
            'status' => $this->status,
        ];
    }
}
