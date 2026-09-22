<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\NfcCardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SecurityDeviceController;
use App\Http\Controllers\StudentServicesController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
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

    // 1.8/1.9: el estudiante gestiona únicamente su propia privacidad
    // desde la sesión web. El backend vuelve a comprobar propietario/rol.
    Route::post('/student-services/consents/{consentId}', function (\Illuminate\Http\Request $request, string $consentId) {
        $profile = $request->user()?->studentProfile;
        abort_unless($profile, 404, 'No existe un perfil estudiantil para esta cuenta.');
        $request->merge(['consent_id' => $consentId]);
        return app(StudentServicesController::class)->acceptConsent($request, (string) $profile->getKey());
    })->name('student-services.consents.accept');
    Route::delete('/student-services/consents/{consentId}', function (\Illuminate\Http\Request $request, string $consentId) {
        $profile = $request->user()?->studentProfile;
        abort_unless($profile, 404, 'No existe un perfil estudiantil para esta cuenta.');
        return app(StudentServicesController::class)->revokeConsent($request, (string) $profile->getKey(), $consentId);
    })->name('student-services.consents.revoke');
    Route::patch('/student-services/preferences', function (\Illuminate\Http\Request $request) {
        $profile = $request->user()?->studentProfile;
        abort_unless($profile, 404, 'No existe un perfil estudiantil para esta cuenta.');
        return app(StudentServicesController::class)->updatePreferences($request, (string) $profile->getKey());
    })->name('student-services.preferences.update');

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::patch('/students/{student}', [StudentController::class, 'update'])->name('students.update');

    // --------------------------------------------------------------
    // Perfil
    // --------------------------------------------------------------
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    // --------------------------------------------------------------
    // Modulo 1.6 - Identidad QR
    // --------------------------------------------------------------
    Route::prefix('identidad/qr')->name('identity.qr.')->group(function () {
        Route::get('/', [QrController::class, 'index'])
            ->name('index');

        Route::get('/historial', [QrController::class, 'history'])
            ->name('history');

        // Limitadas: generar/validar son las operaciones sensibles a
        // fuerza bruta de codigos de este modulo.
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/generar', [QrController::class, 'generate'])
                ->name('generate');

            Route::post('/simular-validacion', [QrController::class, 'simulateValidation'])
                ->name('simulate');
        });

        // Contextos de validación (quién organiza qué y hasta cuándo):
        // cualquier validador puede listarlos/crearlos; solo su autor
        // o un admin puede cancelarlos (ver QrValidationContextPolicy).
        Route::prefix('contextos')->name('contexts.')->group(function () {
            Route::get('/', [QrController::class, 'contexts'])->name('index');
            Route::post('/', [QrController::class, 'storeContext'])->name('store');
            Route::post('/{context}/cancelar', [QrController::class, 'cancelContext'])->name('cancel');
        });
    });

    // --------------------------------------------------------------
    // Modulo 1.7 - Dispositivos y sesiones confiables
    // --------------------------------------------------------------
    Route::prefix('seguridad')->name('security.')->group(function () {

        Route::get('/dispositivos', [SecurityDeviceController::class, 'index'])
            ->name('devices.index');

        Route::get('/eventos', [SecurityDeviceController::class, 'events'])
            ->name('events');

        // Latido para deteccion casi-inmediata de sesion revocada
        // desde otra pestaña/dispositivo (ver AuthenticatedLayout.vue).
        Route::get('/latido', [SecurityDeviceController::class, 'heartbeat'])
            ->name('heartbeat');

        Route::post('/reautenticar', [AuthController::class, 'reauthenticate'])
            ->middleware('throttle:10,1')
            ->name('reauth');

        Route::middleware('reauth')->group(function () {

            Route::post('/sesiones/{session}/revocar', [SecurityDeviceController::class, 'revoke'])
                ->name('sessions.revoke');

            Route::post('/sesiones/revocar-otras', [SecurityDeviceController::class, 'revokeOthers'])
                ->name('sessions.revoke-others');
            Route::post('/sesiones/revocar-todas', [SecurityDeviceController::class, 'revokeAll'])
                ->name('sessions.revoke-all');

            Route::post('/dispositivos/{device}/confianza', [SecurityDeviceController::class, 'trust'])
                ->name('devices.trust');

            Route::delete('/dispositivos/{device}', [SecurityDeviceController::class, 'destroy'])
                ->name('devices.destroy');
        });
    });

    // --------------------------------------------------------------
    // Modulo 1.4 - Registro de tarjetas NFC
    // --------------------------------------------------------------
    // Index/history quedan abiertas a cualquier usuario autenticado;
    // el propio controlador filtra para que un usuario sin rol admin
    // solo vea/consulte sus propias tarjetas (ver NfcCardController y
    // NfcCardPolicy).
    Route::get('/nfc-cards', [NfcCardController::class, 'index'])
        ->name('nfc-cards.index');

    Route::get('/nfc-cards/{nfcCard}/history', [NfcCardController::class, 'history'])
        ->name('nfc-cards.history');

    // Registrar y administrar credenciales NFC es una operación
    // sensible de identidad: se restringe explícitamente a admin,
    // tanto por middleware (defensa en profundidad a nivel de ruta)
    // como por policy dentro del controlador (NfcCardPolicy).
    Route::middleware('role.context:admin')->group(function () {
        Route::get('/nfc-cards/create', [NfcCardController::class, 'create'])
            ->name('nfc-cards.create');

        Route::post('/nfc-cards', [NfcCardController::class, 'store'])
            ->name('nfc-cards.store');

        // ------------------------------------------------------------
        // Modulo 1.5 - Ciclo de vida de credenciales NFC
        // ------------------------------------------------------------
        Route::patch('/nfc-cards/{nfcCard}/status', [NfcCardController::class, 'updateStatus'])
            ->name('nfc-cards.update-status');

        // Reemplazo de tarjeta (rama cerrar-nfc-qr, integrada aquí):
        // misma sensibilidad que updateStatus, mismo nivel de protección.
        Route::post('/nfc-cards/{nfcCard}/replace', [NfcCardController::class, 'replace'])
            ->name('nfc-cards.replace');
    });

    // --------------------------------------------------------------
    // Módulos 1.2 y 1.3 - Roles y permisos contextuales
    // --------------------------------------------------------------
    // /roles (GET) queda visible para cualquier autenticado: cada quien
    // consulta sus propios roles y su estado de 2FA.
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

    // /roles/assign es la ruta que corregimos: SOLO un administrador
    // puede llegar aquí. El middleware de ruta es la primera barrera;
    // RolePolicy::assign (vía $this->authorize en el controlador) es
    // la segunda, para que la regla no dependa únicamente de no
    // olvidar el middleware en una ruta futura.
    Route::post('/roles/assign', [RoleController::class, 'assign'])
        ->middleware('role.context:admin')
        ->name('roles.assign');
});

require __DIR__.'/auth.php';
