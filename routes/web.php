<?php

use App\Http\Controllers\DemoSessionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => auth()->check() ? to_route('students.index') : to_route('login'));

Route::get('/login', fn () => Inertia::render('Auth/LoginPlaceholder'))->middleware('guest')->name('login');
Route::post('/demo/session', [DemoSessionController::class, 'store'])->middleware('guest')->name('demo.session.store');
Route::delete('/demo/session', [DemoSessionController::class, 'destroy'])->middleware('auth')->name('demo.session.destroy');

Route::middleware('auth')->group(function (): void {
    Route::resource('students', StudentController::class)->except(['show', 'destroy']);
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import');
});
