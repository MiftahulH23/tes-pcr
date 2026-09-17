<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->route('enrollment');

        return [
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', Rule::in(Enrollment::SEMESTERS)],
            'status' => ['required', Rule::in(Enrollment::STATUSES)],

            'student.name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'student.email' => [
                'sometimes',
                'string',
                'email',
                Rule::unique('students', 'email')->ignore($enrollment->student_id),
            ],

            'course.name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'course.credits' => ['sometimes', 'integer', 'min:1', 'max:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_year.regex' => 'Tahun ajaran harus berformat YYYY/YYYY, contoh 2025/2026.',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            /** @var Enrollment $enrollment */
            $enrollment = $this->route('enrollment');

            $year = $this->input('academic_year');

            if (is_string($year) && preg_match('/^(\d{4})\/(\d{4})$/', $year, $m) && (int) $m[2] !== (int) $m[1] + 1) {
                $validator->errors()->add('academic_year', 'Tahun ajaran harus berurutan, contoh 2025/2026.');
            }

            $duplicate = Enrollment::query()
                ->where('student_id', $enrollment->student_id)
                ->where('course_id', $enrollment->course_id)
                ->where('academic_year', $this->input('academic_year'))
                ->where('semester', $this->input('semester'))
                ->where('id', '!=', $enrollment->id)
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('academic_year', 'KRS untuk mahasiswa, mata kuliah, tahun ajaran, dan semester ini sudah ada.');
            }
        });
    }
}
