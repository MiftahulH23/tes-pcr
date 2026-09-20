<?php

use App\Http\Controllers\EnrollmentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/enrollments')->name('home');

Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
Route::get('/enrollments/data', [EnrollmentController::class, 'data'])->name('enrollments.data');
Route::get('/enrollments/export', [EnrollmentController::class, 'export'])->name('enrollments.export');
Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->whereNumber('enrollment')->name('enrollments.show');
Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->whereNumber('enrollment')->name('enrollments.update');
Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->whereNumber('enrollment')->name('enrollments.destroy');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
