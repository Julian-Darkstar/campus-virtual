<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentServicesController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\QrController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/student-services', [StudentServicesController::class, 'index'])
        ->name('student-services.index');

    Route::get('/security/qr', [QrController::class, 'index'])->name('security.qr');
    Route::get('/security/devices', [DeviceController::class, 'index'])->name('security.devices');
    Route::post('/security/devices', [DeviceController::class, 'store'])->name('security.devices.store');
    Route::delete('/security/devices/{device}', [DeviceController::class, 'destroy'])->name('security.devices.destroy');
    Route::post('/security/qr/generate', [QrController::class, 'generate'])->name('identity.qr.generate');
    Route::post('/security/qr/validate', [QrController::class, 'validateCode'])->name('identity.qr.validate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
