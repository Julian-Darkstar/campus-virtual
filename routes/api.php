<?php

use App\Http\Controllers\StudentServicesController;
use App\Http\Controllers\OAuthTokenController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QrController;

Route::post('/oauth/token', OAuthTokenController::class)->middleware('throttle:60,1');

Route::prefix('v1')->middleware('oauth.service:students:read')->group(function () {
    Route::get('/students/{studentId}/status', [StudentServicesController::class, 'status']);
    Route::get('/students/{studentId}/status/history', [StudentServicesController::class, 'statusHistory']);
    Route::get('/students/{studentId}/consents', [StudentServicesController::class, 'consents']);
    Route::get('/students/{studentId}/preferences', [StudentServicesController::class, 'preferences']);
});

Route::prefix('v1')->middleware('oauth.service:students:write')->group(function () {
    Route::post('/students/{studentId}/consents', [StudentServicesController::class, 'acceptConsent']);
    Route::delete('/students/{studentId}/consents/{consentId}', [StudentServicesController::class, 'revokeConsent']);
    Route::patch('/students/{studentId}/preferences', [StudentServicesController::class, 'updatePreferences']);
});

// Modulo 1.6: fuera del grupo de arriba (que solo exige un token de
// servicio activo, sin importar el scope) porque esta ruta necesita
// el scope especifico "identity.qr.validate". Antes cualquier
// servicio con cualquier token valido podia llegar aqui. Ver
// ValidateServiceToken y QrController::validateQr — el scope
// adicional "identity.qr.validate.full" se revisa dentro del
// controlador porque el middleware solo soporta un scope por ruta.
//
// throttle:60,1 -> el scope solo controla QUE puede llamar, no
// CUANTAS veces; sin esto, un token valido (legitimo o filtrado)
// podria intentar fuerza bruta sobre codigos cortos de 6 digitos.
Route::prefix('v1')->middleware(['oauth.service:identity.qr.validate', 'throttle:60,1'])->group(function () {
    Route::post('/identity/qr-validate', [QrController::class, 'validateQr']);
});
