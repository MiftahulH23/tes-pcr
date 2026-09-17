<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->char('academic_year', 9);
            $table->enum('semester', ['GANJIL', 'GENAP']);
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'])->default('DRAFT');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'course_id', 'academic_year', 'semester'], 'enrollments_unique_krs');
            $table->index('status');
            $table->index('semester');
            $table->index('academic_year');
            $table->index(['academic_year', 'semester', 'student_id'], 'enrollments_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
