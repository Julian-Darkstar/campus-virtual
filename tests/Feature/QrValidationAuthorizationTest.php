<?php

use App\Models\QrToken;
use App\Models\QrValidationContext;
use App\Models\Role;
use App\Models\ServiceClient;
use App\Models\User;
use App\Services\IdentityService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    foreach (Role::VALID_ROLES as $roleName) {
        Role::firstOrCreate(['name' => $roleName], ['name' => $roleName, 'display_name' => $roleName]);
    }

    // Contexto compartido para las pruebas que validan por la ruta web
    // (la ruta ahora exige context_id: ver QrValidationContextTest.php
    // para el ciclo de vida de los contextos en si).
    $creator = User::factory()->create();
    $creator->assignRole(Role::ADMIN);
    $this->context = QrValidationContext::create([
        'name' => 'Contexto de prueba (autorizacion)',
        'created_by' => (string) $creator->_id,
        'ends_at' => now()->addHour(),
    ]);
});

function issueDynamicQrFor(User $user): array
{
    $token = app(IdentityService::class)->issueDynamicQrToken($user);

    return ['token' => $token, 'code' => $token->code];
}

test('a regular student cannot validate another student QR', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    ['code' => $code] = issueDynamicQrFor($owner);

    $anotherStudent = User::factory()->create();
    $anotherStudent->assignRole(Role::ESTUDIANTE);

    $response = $this->actingAs($anotherStudent)->postJson('/identidad/qr/simular-validacion', [
        'code' => $code,
        'context_id' => (string) $this->context->_id,
    ]);

    $response->assertForbidden();

    // El token sigue sin consumir: el intento no autorizado no debe
    // tener efecto sobre el QR real del dueño.
    expect(QrToken::where('code_hash', QrToken::hashPresentedCode($code))->first()->consumed_at)->toBeNull();
});

test('a user with a validator role can validate another student QR (basic level)', function () {
    $owner = User::factory()->create(['name' => 'Estudiante De Prueba']);
    $owner->assignRole(Role::ESTUDIANTE);
    ['code' => $code] = issueDynamicQrFor($owner);

    $librarian = User::factory()->create(['name' => 'Martha Jiménez']);
    $librarian->assignRole(Role::BIBLIOTECARIO, 'service', 'biblioteca-central');

    $response = $this->actingAs($librarian)->postJson('/identidad/qr/simular-validacion', [
        'code' => $code,
        'context_id' => (string) $this->context->_id,
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result', 'valid');

    // Nivel basico por defecto: nombre enmascarado, no el real.
    expect($response->json('identity.name'))->not->toBe('Estudiante De Prueba');
});

test('a validator role without FULL_IDENTITY_ROLES cannot request level=full', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    ['code' => $code] = issueDynamicQrFor($owner);

    $support = User::factory()->create();
    $support->assignRole(Role::AGENTE_SOPORTE, 'service', 'soporte-ti');

    $response = $this->actingAs($support)->postJson('/identidad/qr/simular-validacion', [
        'code' => $code,
        'context_id' => (string) $this->context->_id,
        'level' => 'full',
    ]);

    $response->assertForbidden();
});

test('a validator role in FULL_IDENTITY_ROLES can request level=full and gets the real name', function () {
    $owner = User::factory()->create(['name' => 'Estudiante Completo Torres']);
    $owner->assignRole(Role::ESTUDIANTE);
    ['code' => $code] = issueDynamicQrFor($owner);

    $cashier = User::factory()->create();
    $cashier->assignRole(Role::CAJERO_NEGOCIO, 'business', 'negocio-cafeteria-central');

    $response = $this->actingAs($cashier)->postJson('/identidad/qr/simular-validacion', [
        'code' => $code,
        'context_id' => (string) $this->context->_id,
        'level' => 'full',
    ]);

    $response->assertOk()->assertJsonPath('identity.name', 'Estudiante Completo Torres');
});

test('the resolved identity reflects the real roles of the owner, not a hardcoded value', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    $owner->assignRole(Role::PROPIETARIO_NEGOCIO, 'business', 'negocio-cafeteria-central');
    ['code' => $code] = issueDynamicQrFor($owner);

    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN);

    $response = $this->actingAs($admin)->postJson('/identidad/qr/simular-validacion', [
        'code' => $code,
        'context_id' => (string) $this->context->_id,
    ]);

    $response->assertOk();
    expect($response->json('identity.roles'))
        ->toContain(Role::ESTUDIANTE)
        ->toContain(Role::PROPIETARIO_NEGOCIO);
});

test('the real API contract rejects a service token without the qr-validate scope', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    ['code' => $code] = issueDynamicQrFor($owner);

    $client = ServiceClient::create([
        'name' => 'Servicio sin permiso de QR',
        'client_id' => 'svc_sin_scope',
        'secret_hash' => Hash::make('secret'),
        'scopes' => ['students:read'], // sin identity.qr.validate
        'active' => true,
    ]);

    $token = $this->postJson('/api/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->client_id,
        'client_secret' => 'secret',
        'scope' => 'students:read',
    ])->json('access_token');

    $this->postJson('/api/v1/identity/qr-validate', ['code' => $code], [
        'Authorization' => "Bearer {$token}",
    ])->assertForbidden();
});

test('the real API contract accepts a service token with the qr-validate scope', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    ['code' => $code] = issueDynamicQrFor($owner);

    $client = ServiceClient::create([
        'name' => 'Biblioteca Central',
        'client_id' => 'svc_biblioteca',
        'secret_hash' => Hash::make('secret'),
        'scopes' => ['identity.qr.validate'],
        'active' => true,
    ]);

    $token = $this->postJson('/api/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->client_id,
        'client_secret' => 'secret',
        'scope' => 'identity.qr.validate',
    ])->json('access_token');

    $this->postJson('/api/v1/identity/qr-validate', ['code' => $code], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonPath('result', 'valid');
});

test('the real API contract rejects level=full without the extra scope', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    ['code' => $code] = issueDynamicQrFor($owner);

    $client = ServiceClient::create([
        'name' => 'Biblioteca Central (sin full)',
        'client_id' => 'svc_biblioteca_basic',
        'secret_hash' => Hash::make('secret'),
        'scopes' => ['identity.qr.validate'], // sin identity.qr.validate.full
        'active' => true,
    ]);

    $token = $this->postJson('/api/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->client_id,
        'client_secret' => 'secret',
        'scope' => 'identity.qr.validate',
    ])->json('access_token');

    $this->postJson('/api/v1/identity/qr-validate', [
        'code' => $code,
        'level' => 'full',
    ], [
        'Authorization' => "Bearer {$token}",
    ])->assertForbidden();
});
