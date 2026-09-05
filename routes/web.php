<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\RoleController;
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

Route::middleware(['auth', 'verified', 'session.active', 'device.track'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/student-services', [StudentServicesController::class, 'index'])
        ->name('student-services.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Módulos 1.2 y 1.3
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles/assign', [RoleController::class, 'assign'])->name('roles.assign');

    // Módulos 1.6 y 1.7 (Equipo)
    Route::get('/security/devices', [SecurityDeviceController::class, 'index'])->name('security.devices.index');
    Route::delete('/security/devices/{device}', [SecurityDeviceController::class, 'destroy'])->name('security.devices.destroy');
    Route::post('/security/devices/logout-others', [SecurityDeviceController::class, 'logoutOthers'])->name('security.devices.logout-others');

    Route::get('/identity/qr', [QrController::class, 'showIdentityQr'])->name('identity.qr');
    Route::get('/identity/qr/view', [QrController::class, 'showIdentityQr'])->name('identity.qr.index');
    Route::post('/identity/qr/refresh', [QrController::class, 'refreshQr'])->name('identity.qr.refresh');
    Route::post('/identity/qr/validate', [QrController::class, 'validateQr'])->name('identity.qr.validate');
});

require __DIR__.'/auth.php';