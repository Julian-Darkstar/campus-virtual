<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\SecurityDeviceController;
use App\Http\Controllers\StudentServicesController;
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --------------------------------------------------------------
    // Modulo 1.6 - Identidad QR
    // --------------------------------------------------------------
    Route::prefix('identidad/qr')->name('identity.qr.')->group(function () {
        Route::get('/', [QrController::class, 'index'])->name('index');
        Route::post('/generar', [QrController::class, 'generate'])->name('generate');
        Route::get('/historial', [QrController::class, 'history'])->name('history');
        Route::post('/simular-validacion', [QrController::class, 'simulateValidation'])->name('simulate');
    });

    // --------------------------------------------------------------
    // Modulo 1.7 - Dispositivos y sesiones confiables
    // --------------------------------------------------------------
    Route::prefix('seguridad')->name('security.')->group(function () {
        Route::get('/dispositivos', [SecurityDeviceController::class, 'index'])->name('devices.index');

        Route::post('/reautenticar', [AuthController::class, 'reauthenticate'])->name('reauth');

        Route::middleware('reauth')->group(function () {
            Route::post('/sesiones/{session}/revocar', [SecurityDeviceController::class, 'revoke'])->name('sessions.revoke');
            Route::post('/sesiones/revocar-otras', [SecurityDeviceController::class, 'revokeOthers'])->name('sessions.revoke-others');
            Route::post('/dispositivos/{device}/confianza', [SecurityDeviceController::class, 'trust'])->name('devices.trust');
        });
    });
});

require __DIR__.'/auth.php';
