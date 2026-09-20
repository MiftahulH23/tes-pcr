<?php

namespace App\Actions\Enrollments;

use App\Support\EnrollmentFilters;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams every matching row (not just the current page) as CSV without
 * loading the result set into memory: lazyById walks the table in id-ordered
 * chunks (WHERE id > lastId LIMIT n) instead of a single buffered query, so
 * this stays flat in memory whether it exports 200 rows or 5,000,000.
 */
class ExportEnrollmentsCsv
{
    private const CHUNK_SIZE = 2000;

    private const HEADER = ['NIM', 'Nama Mahasiswa', 'Kode MK', 'Nama MK', 'Semester', 'Tahun Ajaran', 'Status'];

    public function handle(array $params): StreamedResponse
    {
        // Any custom sort from the UI is irrelevant to (and incompatible
        // with) id-ordered chunked export, so drop it and keep only filters.
        $query = EnrollmentFilters::fromRequest($params, true)->reorder();

        $filename = 'krs-export-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            // PHP-FPM's default 30s time limit silently cut the 5M-row export off at ~550k rows; a stream this long must lift it.
            set_time_limit(0);

            $handle = fopen('php://output', 'w');
            // PHP 8.4 deprecates fputcsv() without an explicit $escape — pass
            // '' for standard double-quote CSV escaping instead of the old
            // backslash default. Left implicit, every one of 5M+ calls
            // raised a deprecation notice, which (with APP_DEBUG on) was
            // the actual bottleneck: an 8k-row filtered export took 18s
            // here versus ~0.5s once explicit.
            fputcsv($handle, self::HEADER, ',', '"', '');

            $query->lazyById(self::CHUNK_SIZE, 'enrollments.id', 'id')->each(function ($row) use ($handle) {
                fputcsv($handle, [
                    $row->student_nim,
                    $row->student_name,
                    $row->course_code,
                    $row->course_name,
                    $row->semester,
                    $row->academic_year,
                    $row->status,
                ], ',', '"', '');
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
