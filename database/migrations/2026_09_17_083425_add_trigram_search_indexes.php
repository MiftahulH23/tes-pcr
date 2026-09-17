<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A plain B-tree index can't accelerate `LIKE '%term%'` (leading wildcard),
 * which is exactly what live search needs across 5M+ enrollments joined to
 * students/courses. Postgres' pg_trgm extension indexes trigrams of the
 * text so substring search can use a GIN index instead of a full scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX students_nim_trgm_idx ON students USING GIN (nim gin_trgm_ops)');
        DB::statement('CREATE INDEX students_name_trgm_idx ON students USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX courses_code_trgm_idx ON courses USING GIN (code gin_trgm_ops)');
        DB::statement('CREATE INDEX courses_name_trgm_idx ON courses USING GIN (name gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS students_nim_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS students_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS courses_code_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS courses_name_trgm_idx');
    }
};
