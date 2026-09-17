<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentExists = Student::where('nim', $this->input('student.nim'))->exists();
        $courseExists = Course::where('code', $this->input('course.code'))->exists();

        return [
            'student.nim' => ['required', 'string', 'regex:/^\d{8,12}$/'],
            'student.name' => [$studentExists ? 'nullable' : 'required', 'string', 'min:3', 'max:100'],
            'student.email' => [
                $studentExists ? 'nullable' : 'required',
                'string',
                'email',
                Rule::unique('students', 'email'),
            ],

            'course.code' => ['required', 'string', 'regex:/^[A-Z]{2,4}[0-9]{3}$/'],
            'course.name' => [$courseExists ? 'nullable' : 'required', 'string', 'min:3', 'max:120'],
            'course.credits' => [$courseExists ? 'nullable' : 'required', 'integer', 'min:1', 'max:6'],

            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', Rule::in(Enrollment::SEMESTERS)],
            'status' => ['required', Rule::in(Enrollment::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'student.nim.regex' => 'NIM harus berupa 8-12 digit angka tanpa spasi.',
            'course.code.regex' => 'Kode mata kuliah harus 2-4 huruf besar diikuti 3 angka, contoh IF101.',
            'academic_year.regex' => 'Tahun ajaran harus berformat YYYY/YYYY, contoh 2025/2026.',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $year = $this->input('academic_year');

            if (is_string($year) && preg_match('/^(\d{4})\/(\d{4})$/', $year, $m) && (int) $m[2] !== (int) $m[1] + 1) {
                $validator->errors()->add('academic_year', 'Tahun ajaran harus berurutan, contoh 2025/2026.');
            }

            $duplicate = Student::query()
                ->where('nim', $this->input('student.nim'))
                ->whereHas('enrollments', function ($query) {
                    $query->whereHas('course', fn ($q) => $q->where('code', $this->input('course.code')))
                        ->where('academic_year', $this->input('academic_year'))
                        ->where('semester', $this->input('semester'));
                })
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('academic_year', 'KRS untuk mahasiswa, mata kuliah, tahun ajaran, dan semester ini sudah ada.');
            }
        });
    }
}
