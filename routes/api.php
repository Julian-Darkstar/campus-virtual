<?php

use App\Http\Controllers\StudentServicesController;
use App\Http\Controllers\OAuthTokenController;
use Illuminate\Support\Facades\Route;

Route::post('/oauth/token', OAuthTokenController::class)->middleware('throttle:60,1');

Route::prefix('v1')->middleware('oauth.service')->group(function () {
    // No scope is defined for this Team 1 contract; access is limited to valid service clients.
    Route::get('/students/{studentId}/status', [StudentServicesController::class, 'status']);
    Route::get('/students/{studentId}/status/history', [StudentServicesController::class, 'statusHistory']);
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/students/{studentId}/consents', [StudentServicesController::class, 'consents']);
    Route::post('/students/{studentId}/consents', [StudentServicesController::class, 'acceptConsent']);
    Route::delete('/students/{studentId}/consents/{consentId}', [StudentServicesController::class, 'revokeConsent']);
    Route::get('/students/{studentId}/preferences', [StudentServicesController::class, 'preferences']);
    Route::patch('/students/{studentId}/preferences', [StudentServicesController::class, 'updatePreferences']);
    Route::patch('/students/{studentId}/status', [StudentServicesController::class, 'changeStatus']);
});
