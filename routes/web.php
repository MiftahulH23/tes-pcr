<?php

use App\Http\Controllers\EnrollmentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
Route::get('/enrollments/data', [EnrollmentController::class, 'data'])->name('enrollments.data');
Route::post('/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
