<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('academic:seed-enrollments
    {--count=5000000 : Number of enrollment rows to reach}
    {--students=200000 : Number of student rows to generate}
    {--courses=400 : Number of course rows to generate}
    {--chunk=5000 : Rows per bulk insert batch}
    {--fresh : Truncate students, courses and enrollments before seeding}')]
#[Description('Bulk-seed students, courses and enrollments for load testing (default: 5,000,000 enrollments)')]
class SeedEnrollments extends Command
{
    private const SUBJECT_PREFIXES = ['IF', 'SI', 'TI', 'MI', 'EL', 'SP', 'AK', 'MN', 'HK', 'PS'];

    private const FIRST_NAMES = [
        'Ahmad', 'Budi', 'Citra', 'Dewi', 'Eka', 'Fajar', 'Gita', 'Hadi', 'Indra', 'Joko',
        'Kartika', 'Lestari', 'Made', 'Nur', 'Oki', 'Putri', 'Rizki', 'Sari', 'Taufik', 'Umar',
        'Vina', 'Wahyu', 'Yanto', 'Zaskia',
    ];

    private const LAST_NAMES = [
        'Saputra', 'Wijaya', 'Kusuma', 'Pratama', 'Hidayat', 'Santoso', 'Halim', 'Permana',
        'Wibowo', 'Setiawan', 'Nugroho', 'Firmansyah', 'Rahayu', 'Puspita', 'Gunawan',
    ];

    public function handle(): int
    {
        $targetEnrollments = (int) $this->option('count');
        $studentCount = (int) $this->option('students');
        $courseCount = (int) $this->option('courses');
        $chunkSize = (int) $this->option('chunk');

        if ($this->option('fresh')) {
            $this->info('Truncating students, courses, enrollments...');
            DB::statement('TRUNCATE TABLE enrollments, courses, students RESTART IDENTITY CASCADE');
        }

        $start = microtime(true);

        $this->seedStudents($studentCount, $chunkSize);
        $this->seedCourses($courseCount, $chunkSize);
        $this->seedEnrollments($targetEnrollments, $studentCount, $courseCount, $chunkSize);

        $elapsed = round(microtime(true) - $start, 2);
        $finalCount = DB::table('enrollments')->count();

        $this->newLine();
        $this->info("Done in {$elapsed}s. enrollments COUNT(*) = {$finalCount}");

        return self::SUCCESS;
    }

    private function seedStudents(int $count, int $chunkSize): void
    {
        $existing = DB::table('students')->count();

        if ($existing >= $count) {
            $this->info("Students already at {$existing}, skipping.");

            return;
        }

        $this->info("Seeding students ({$existing} -> {$count})...");
        $bar = $this->output->createProgressBar($count - $existing);

        $now = now();

        for ($start = $existing; $start < $count; $start += $chunkSize) {
            $batch = [];
            $end = min($start + $chunkSize, $count);

            for ($i = $start; $i < $end; $i++) {
                $first = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)];
                $last = self::LAST_NAMES[intdiv($i, count(self::FIRST_NAMES)) % count(self::LAST_NAMES)];

                $batch[] = [
                    'nim' => (string) (10_000_000 + $i),
                    'name' => "{$first} {$last}",
                    'email' => "student{$i}@example.test",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('students')->insert($batch);
            $bar->advance(count($batch));
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedCourses(int $count, int $chunkSize): void
    {
        $existing = DB::table('courses')->count();

        if ($existing >= $count) {
            $this->info("Courses already at {$existing}, skipping.");

            return;
        }

        $this->info("Seeding courses ({$existing} -> {$count})...");
        $bar = $this->output->createProgressBar($count - $existing);

        $now = now();
        $prefixes = self::SUBJECT_PREFIXES;
        $prefixCount = count($prefixes);

        for ($start = $existing; $start < $count; $start += $chunkSize) {
            $batch = [];
            $end = min($start + $chunkSize, $count);

            for ($i = $start; $i < $end; $i++) {
                $prefix = $prefixes[$i % $prefixCount];
                $number = 100 + intdiv($i, $prefixCount);

                $batch[] = [
                    'code' => "{$prefix}{$number}",
                    'name' => "Course {$prefix} {$number}",
                    'credits' => ($i % 6) + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('courses')->insert($batch);
            $bar->advance(count($batch));
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedEnrollments(int $target, int $studentCount, int $courseCount, int $chunkSize): void
    {
        $existing = DB::table('enrollments')->count();

        if ($existing >= $target) {
            $this->info("Enrollments already at {$existing}, target reached.");

            return;
        }

        $this->info("Seeding enrollments ({$existing} -> {$target})...");
        $bar = $this->output->createProgressBar($target - $existing);

        $academicYears = [];
        for ($y = 2020; $y <= 2026; $y++) {
            $academicYears[] = "{$y}/" . ($y + 1);
        }
        $yearCount = count($academicYears);
        $semesters = Enrollment::SEMESTERS;
        $semesterCount = count($semesters);
        $statuses = Enrollment::STATUSES;
        $statusCount = count($statuses);
        $now = now();

        $inserted = $existing;
        $attemptsWithNoProgress = 0;

        while ($inserted < $target) {
            $batch = [];

            for ($i = 0; $i < $chunkSize; $i++) {
                $batch[] = [
                    'student_id' => random_int(1, $studentCount),
                    'course_id' => random_int(1, $courseCount),
                    'academic_year' => $academicYears[random_int(0, $yearCount - 1)],
                    'semester' => $semesters[random_int(0, $semesterCount - 1)],
                    'status' => $statuses[random_int(0, $statusCount - 1)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $affected = DB::table('enrollments')->insertOrIgnore($batch);
            $inserted += $affected;
            $bar->advance($affected);

            if ($affected === 0) {
                $attemptsWithNoProgress++;

                if ($attemptsWithNoProgress > 20) {
                    $this->newLine();
                    $this->warn('No progress after 20 consecutive batches (combination space exhausted). Stopping early.');

                    break;
                }
            } else {
                $attemptsWithNoProgress = 0;
            }
        }

        $bar->finish();
        $this->newLine();
    }
}
