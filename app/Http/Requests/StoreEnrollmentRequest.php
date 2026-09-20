<?php

namespace App\Http\Requests;

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

    /**
     * Student and course each come in one of two modes. New data (default): the
     * NIM / code must be unused and every field is required. Existing
     * (`student.existing` / `course.existing` = true): the NIM / code must
     * already exist and only it is used — that is how one student (or course)
     * gets many enrollments.
     */
    public function rules(): array
    {
        $studentExisting = $this->boolean('student.existing');
        $courseExisting = $this->boolean('course.existing');

        return [
            'student.existing' => ['sometimes', 'boolean'],
            'student.nim' => [
                'required',
                'string',
                'regex:/^\d{8,12}$/',
                $studentExisting ? Rule::exists('students', 'nim') : Rule::unique('students', 'nim'),
            ],
            'student.name' => $studentExisting ? ['nullable'] : ['required', 'string', 'min:3', 'max:100'],
            'student.email' => $studentExisting ? ['nullable'] : ['required', 'string', 'email', Rule::unique('students', 'email')],

            'course.existing' => ['sometimes', 'boolean'],
            'course.code' => [
                'required',
                'string',
                'regex:/^[A-Z]{2,4}[0-9]{3}$/',
                $courseExisting ? Rule::exists('courses', 'code') : Rule::unique('courses', 'code'),
            ],
            'course.name' => $courseExisting ? ['nullable'] : ['required', 'string', 'min:3', 'max:120'],
            'course.credits' => $courseExisting ? ['nullable'] : ['required', 'integer', 'min:1', 'max:6'],

            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', Rule::in(Enrollment::SEMESTERS)],
            'status' => ['required', Rule::in(Enrollment::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'student.nim.regex' => 'NIM harus berupa 8-12 digit angka tanpa spasi.',
            'student.nim.unique' => 'NIM sudah terdaftar. Centang "Mahasiswa sudah terdaftar" untuk menambah KRS mahasiswa ini.',
            'student.nim.exists' => 'NIM belum terdaftar. Hilangkan centang "Mahasiswa sudah terdaftar" untuk mendaftarkan mahasiswa baru.',
            'course.code.regex' => 'Kode mata kuliah harus 2-4 huruf besar diikuti 3 angka, contoh IF101.',
            'course.code.unique' => 'Kode mata kuliah sudah terdaftar. Centang "Mata kuliah sudah ada" untuk memakai mata kuliah ini.',
            'course.code.exists' => 'Kode mata kuliah belum terdaftar. Hilangkan centang "Mata kuliah sudah ada" untuk menambah mata kuliah baru.',
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

            $existing = Student::query()
                ->where('nim', $this->input('student.nim'))
                ->first()
                ?->enrollments()
                ->withTrashed()
                ->whereHas('course', fn ($q) => $q->where('code', $this->input('course.code')))
                ->where('academic_year', $this->input('academic_year'))
                ->where('semester', $this->input('semester'))
                ->first();

            if ($existing) {
                $validator->errors()->add('academic_year', $existing->trashed()
                    ? 'KRS untuk mahasiswa, mata kuliah, tahun ajaran, dan semester ini pernah dibuat lalu dihapus dan masih tercatat di sistem, sehingga tidak bisa dibuat ulang.'
                    : 'KRS untuk mahasiswa, mata kuliah, tahun ajaran, dan semester ini sudah ada.');
            }
        });
    }
}
