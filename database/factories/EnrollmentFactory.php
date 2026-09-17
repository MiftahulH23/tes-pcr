<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = $this->faker->numberBetween(2020, 2026);

        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'academic_year' => sprintf('%d/%d', $year, $year + 1),
            'semester' => $this->faker->randomElement(['GANJIL', 'GENAP']),
            'status' => $this->faker->randomElement(['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED']),
        ];
    }
}
