<?php

namespace Tests\Feature\Http;

use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'student' => ['nim' => '10000001', 'name' => 'Budi Santoso', 'email' => 'budi@example.com'],
            'course' => ['code' => 'IF101', 'name' => 'Algoritma', 'credits' => 3],
            'academic_year' => '2025/2026',
            'semester' => 'GANJIL',
            'status' => 'DRAFT',
        ], $overrides);
    }

    private function createEnrollment(array $overrides = []): int
    {
        return $this->postJson('/enrollments', $this->payload($overrides))->assertCreated()->json('data.id');
    }

    /** The framework skips CSRF checks while running tests; force them on to exercise the real exemption list. */
    private function enforceCsrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app->make(Encrypter::class)) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    // --- Behavior that already worked: must keep working -------------------

    public function test_store_creates_student_course_and_enrollment()
    {
        $this->postJson('/enrollments', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'KRS berhasil disimpan.');

        $this->assertDatabaseHas('students', ['nim' => '10000001']);
        $this->assertDatabaseHas('courses', ['code' => 'IF101']);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_store_rejects_an_invalid_payload_with_field_errors()
    {
        $this->postJson('/enrollments', $this->payload([
            'student' => ['nim' => 'abc', 'name' => '', 'email' => 'bad'],
            'course' => ['code' => 'zz', 'name' => '', 'credits' => 99],
            'academic_year' => '2025-2026',
            'semester' => 'X',
            'status' => 'X',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student.nim', 'student.name', 'student.email', 'course.code', 'course.name', 'course.credits', 'academic_year', 'semester', 'status']);

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_store_rejects_a_duplicate_active_enrollment()
    {
        $this->createEnrollment();

        $response = $this->postJson('/enrollments', $this->payload(['student' => ['email' => 'other@example.com']]))->assertStatus(422);

        $this->assertStringContainsString('sudah ada', $response->json('errors.academic_year.0'));
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_store_rejects_a_new_student_whose_email_belongs_to_someone_else()
    {
        Student::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/enrollments', $this->payload(['student' => ['email' => 'taken@example.com']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student.email']);
    }

    public function test_update_changes_the_enrollment_and_the_optional_student_and_course_fields()
    {
        $id = $this->createEnrollment();

        $this->putJson("/enrollments/$id", [
            'academic_year' => '2026/2027',
            'semester' => 'GENAP',
            'status' => 'APPROVED',
            'student' => ['name' => 'Nama Baru'],
            'course' => ['name' => 'Nama MK Baru', 'credits' => 4],
        ])->assertOk();

        $this->assertDatabaseHas('enrollments', ['id' => $id, 'academic_year' => '2026/2027', 'semester' => 'GENAP', 'status' => 'APPROVED']);
        $this->assertDatabaseHas('students', ['nim' => '10000001', 'name' => 'Nama Baru']);
        $this->assertDatabaseHas('courses', ['code' => 'IF101', 'name' => 'Nama MK Baru', 'credits' => 4]);
    }

    public function test_update_rejects_moving_into_an_existing_active_combination()
    {
        $this->createEnrollment();
        $second = $this->createEnrollment(['semester' => 'GENAP', 'student' => ['email' => 'second@example.com']]);

        $response = $this->putJson("/enrollments/$second", ['academic_year' => '2025/2026', 'semester' => 'GANJIL', 'status' => 'DRAFT'])
            ->assertStatus(422);

        $this->assertStringContainsString('sudah ada', $response->json('errors.academic_year.0'));
    }

    public function test_destroy_soft_deletes_and_leaves_the_student_and_course_alone()
    {
        $id = $this->createEnrollment();

        $this->deleteJson("/enrollments/$id")->assertOk();

        $this->assertSoftDeleted('enrollments', ['id' => $id]);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('courses', 1);
    }

    public function test_data_endpoint_returns_a_page_of_rows_with_pagination_meta()
    {
        Enrollment::factory()->count(3)->create();

        $this->getJson('/enrollments/data?page=1&page_size=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_export_streams_csv_for_the_active_filter_only()
    {
        $approved = Enrollment::factory()->create(['status' => 'APPROVED']);
        Enrollment::factory()->create(['status' => 'DRAFT']);

        $response = $this->get('/enrollments/export?status[]=APPROVED')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('NIM,"Nama Mahasiswa"', $csv);
        $this->assertStringContainsString($approved->student->nim, $csv);
        $this->assertCount(2, array_filter(explode("\n", trim($csv))));
    }

    // --- Bugs found in production: these fail before the fixes -------------

    public function test_store_accepts_an_existing_student_using_their_own_email()
    {
        Student::factory()->create(['nim' => '10000002', 'email' => 'own@example.com']);

        $this->postJson('/enrollments', $this->payload(['student' => ['nim' => '10000002', 'email' => 'own@example.com']]))
            ->assertCreated();

        $this->assertDatabaseCount('students', 1);
    }

    public function test_store_returns_a_clear_422_when_recreating_a_deleted_enrollment()
    {
        $id = $this->createEnrollment();
        $this->deleteJson("/enrollments/$id")->assertOk();

        $response = $this->postJson('/enrollments', $this->payload(['student' => ['email' => 'again@example.com']]))->assertStatus(422);

        $this->assertStringContainsString('dihapus', $response->json('errors.academic_year.0'));
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_update_returns_a_clear_422_when_moving_into_a_deleted_combination()
    {
        $first = $this->createEnrollment();
        $second = $this->createEnrollment(['semester' => 'GENAP', 'student' => ['email' => 'second@example.com']]);
        $this->deleteJson("/enrollments/$first")->assertOk();

        $response = $this->putJson("/enrollments/$second", ['academic_year' => '2025/2026', 'semester' => 'GANJIL', 'status' => 'DRAFT'])
            ->assertStatus(422);

        $this->assertStringContainsString('dihapus', $response->json('errors.academic_year.0'));
        $this->assertDatabaseHas('enrollments', ['id' => $second, 'semester' => 'GENAP']);
    }

    public function test_enrollment_endpoints_skip_csrf_while_every_other_route_still_requires_it()
    {
        $this->enforceCsrf();

        // Reaches validation (422) / routing (404) instead of being stopped with 419.
        $this->postJson('/enrollments', [])->assertStatus(422);
        $this->putJson('/enrollments/999999', ['academic_year' => '2025/2026', 'semester' => 'GANJIL', 'status' => 'DRAFT'])->assertNotFound();
        $this->deleteJson('/enrollments/999999')->assertNotFound();

        // Anything outside /enrollments keeps CSRF protection.
        $this->postJson('/login', ['email' => 'a@example.com', 'password' => 'x'])->assertStatus(419);
    }

    public function test_export_lifts_the_php_time_limit_so_large_exports_are_not_cut_off()
    {
        Enrollment::factory()->create();
        $original = ini_get('max_execution_time');
        ini_set('max_execution_time', '30');

        try {
            $this->get('/enrollments/export')->assertOk()->streamedContent();

            $this->assertSame('0', ini_get('max_execution_time'));
        } finally {
            ini_set('max_execution_time', (string) $original);
        }
    }
}
