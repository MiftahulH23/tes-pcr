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
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::HEADER);

            $query->lazyById(self::CHUNK_SIZE, 'enrollments.id', 'id')->each(function ($row) use ($handle) {
                fputcsv($handle, [
                    $row->student_nim,
                    $row->student_name,
                    $row->course_code,
                    $row->course_name,
                    $row->semester,
                    $row->academic_year,
                    $row->status,
                ]);
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
