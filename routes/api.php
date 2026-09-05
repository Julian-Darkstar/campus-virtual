<?php

use App\Http\Controllers\StudentServicesController;
use App\Http\Controllers\QrController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/identity/qr/generate', [QrController::class, 'generate']);
        Route::post('/identity/qr-validate', [QrController::class, 'validateCode']);
    });
    Route::get('/students/{studentId}/status', [StudentServicesController::class, 'status']);
    Route::get('/students/{studentId}/status/history', [StudentServicesController::class, 'statusHistory']);
    Route::get('/students/{studentId}/consents', [StudentServicesController::class, 'consents']);
    Route::post('/students/{studentId}/consents', [StudentServicesController::class, 'acceptConsent']);
    Route::delete('/students/{studentId}/consents/{consentId}', [StudentServicesController::class, 'revokeConsent']);
    Route::get('/students/{studentId}/preferences', [StudentServicesController::class, 'preferences']);
    Route::patch('/students/{studentId}/preferences', [StudentServicesController::class, 'updatePreferences']);
});