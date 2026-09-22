<?php

use App\Models\QrToken;
use App\Models\QrValidationContext;
use App\Models\Role;
use App\Models\User;
use App\Services\IdentityService;

beforeEach(function () {
    foreach (Role::VALID_ROLES as $roleName) {
        Role::firstOrCreate(['name' => $roleName], ['name' => $roleName, 'display_name' => $roleName]);
    }

    $this->validator = User::factory()->create();
    $this->validator->assignRole(Role::ADMIN);

    $this->context = QrValidationContext::create([
        'name' => 'Contexto de prueba',
        'created_by' => (string) $this->validator->_id,
        'ends_at' => now()->addHour(),
    ]);
});

function validateAs($testCase, $validator, string $code, array $extra = [])
{
    return $testCase->actingAs($validator)->postJson('/identidad/qr/simular-validacion', array_merge([
        'code' => $code,
        'context_id' => (string) $testCase->context->_id,
    ], $extra));
}

test('an expired dynamic token is rejected as expired, not valid', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $token = QrToken::create([
        'user_id' => (string) $owner->_id,
        'code' => QrToken::generateCode(),
        'short_code' => '100001',
        'type' => 'dynamic',
        'expires_at' => now()->subMinute(), // ya vencido
    ]);

    $response = validateAs($this, $this->validator, $token->code);

    $response->assertOk()->assertJsonPath('result', 'expired')->assertJsonPath('ok', false);
});

test('an already-consumed token is rejected as consumed on the second attempt', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $token = app(IdentityService::class)->issueDynamicQrToken($owner);

    $first = validateAs($this, $this->validator, $token->code);
    $first->assertOk()->assertJsonPath('result', 'valid');

    // Mismo codigo, segundo intento: el primero ya lo consumió.
    $second = validateAs($this, $this->validator, $token->code);
    $second->assertOk()->assertJsonPath('result', 'consumed')->assertJsonPath('ok', false);
});

test('issuing a new dynamic token revokes the previous one', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $identity = app(IdentityService::class);
    $oldToken = $identity->issueDynamicQrToken($owner);
    $identity->issueDynamicQrToken($owner); // revoca $oldToken automáticamente

    $response = validateAs($this, $this->validator, $oldToken->code);

    $response->assertOk()->assertJsonPath('result', 'revoked')->assertJsonPath('ok', false);
});

test('a tampered signature is rejected before touching the database, as invalid_signature', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $token = app(IdentityService::class)->issueDynamicQrToken($owner);
    $tamperedPayload = 'CAMPUSDIGITAL:'.$token->code.'.0000000000'; // firma inventada

    $response = validateAs($this, $this->validator, $tamperedPayload);

    $response->assertOk()->assertJsonPath('result', 'invalid_signature')->assertJsonPath('ok', false);

    // El token real sigue intacto: la firma invalida no debe consumirlo.
    expect($token->fresh()->consumed_at)->toBeNull();
});

test('a code that does not exist is rejected as not_found', function () {
    $response = validateAs($this, $this->validator, '000000000000000000000000');

    $response->assertOk()->assertJsonPath('result', 'not_found')->assertJsonPath('ok', false);
});

test('the short backup code resolves the same as the full signed payload', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $token = app(IdentityService::class)->issueDynamicQrToken($owner);

    $response = validateAs($this, $this->validator, $token->short_code);

    $response->assertOk()->assertJsonPath('result', 'valid')->assertJsonPath('ok', true);
});
