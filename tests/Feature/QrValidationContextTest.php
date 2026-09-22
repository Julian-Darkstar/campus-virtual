<?php

use App\Models\QrValidationContext;
use App\Models\Role;
use App\Models\User;
use App\Services\IdentityService;

beforeEach(function () {
    foreach (Role::VALID_ROLES as $roleName) {
        Role::firstOrCreate(['name' => $roleName], ['name' => $roleName, 'display_name' => $roleName]);
    }
});

test('a validator can create a validation context with an end date', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(Role::BIBLIOTECARIO, 'service', 'biblioteca-central');

    $response = $this->actingAs($organizer)->postJson('/identidad/qr/contextos', [
        'name' => 'Evento de Bienvenida',
        'ends_at' => now()->addHours(3)->toDateTimeString(),
    ]);

    $response->assertCreated()->assertJsonPath('name', 'Evento de Bienvenida');

    $this->assertDatabaseHas('qr_validation_contexts', [
        'name' => 'Evento de Bienvenida',
        'created_by' => (string) $organizer->_id,
    ]);
});

test('a non-validator cannot create a validation context', function () {
    $student = User::factory()->create();
    $student->assignRole(Role::ESTUDIANTE);

    $this->actingAs($student)->postJson('/identidad/qr/contextos', [
        'name' => 'Intento no autorizado',
        'ends_at' => now()->addHour()->toDateTimeString(),
    ])->assertForbidden();
});

test('creating a context without an end date is rejected', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(Role::ADMIN);

    $this->actingAs($organizer)->postJson('/identidad/qr/contextos', [
        'name' => 'Sin fecha de cierre',
    ])->assertUnprocessable();
});

test('a different validator can use a colleague active context, but not cancel it', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(Role::BIBLIOTECARIO, 'service', 'biblioteca-central');
    $colleague = User::factory()->create();
    $colleague->assignRole(Role::BIBLIOTECARIO, 'service', 'biblioteca-central');

    $context = QrValidationContext::create([
        'name' => 'Turno tarde biblioteca',
        'created_by' => (string) $organizer->_id,
        'ends_at' => now()->addHours(4),
    ]);

    // Puede USARLO: aparece en el listado que ve el colega.
    $this->actingAs($colleague)->getJson('/identidad/qr/contextos')
        ->assertOk()
        ->assertJsonFragment(['id' => (string) $context->_id]);

    // Pero NO puede cancelarlo: no es el autor ni admin.
    $this->actingAs($colleague)->postJson("/identidad/qr/contextos/{$context->_id}/cancelar")
        ->assertForbidden();

    expect($context->fresh()->cancelled_at)->toBeNull();
});

test('the creator can cancel their own context', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(Role::CAJERO_NEGOCIO, 'business', 'negocio-cafeteria-central');

    $context = QrValidationContext::create([
        'name' => 'Turno con error de dedo',
        'created_by' => (string) $organizer->_id,
        'ends_at' => now()->addHours(2),
    ]);

    $this->actingAs($organizer)->postJson("/identidad/qr/contextos/{$context->_id}/cancelar")
        ->assertOk();

    expect($context->fresh()->cancelled_at)->not->toBeNull();
});

test('an admin can cancel any context even if they did not create it', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(Role::AGENTE_RECARGA_RETIRO, 'association', 'asociacion-sistemas');
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN);

    $context = QrValidationContext::create([
        'name' => 'Evento cancelado por la asociación',
        'created_by' => (string) $organizer->_id,
        'ends_at' => now()->addHours(5),
    ]);

    $this->actingAs($admin)->postJson("/identidad/qr/contextos/{$context->_id}/cancelar")
        ->assertOk();

    expect($context->fresh()->cancelled_at)->not->toBeNull();
});

test('validating under a cancelled context is rejected with a clear message', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(Role::ADMIN);
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $context = QrValidationContext::create([
        'name' => 'Contexto cancelado',
        'created_by' => (string) $organizer->_id,
        'ends_at' => now()->addHour(),
        'cancelled_at' => now(),
    ]);

    $token = app(IdentityService::class)->issueDynamicQrToken($owner);

    $response = $this->actingAs($organizer)->postJson('/identidad/qr/simular-validacion', [
        'code' => $token->code,
        'context_id' => (string) $context->_id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('context_id');

    // El intento no debe consumir el token del estudiante: el contexto
    // invalido se rechaza antes de tocar el QR.
    expect($token->fresh()->consumed_at)->toBeNull();
});

test('validating under an expired context is rejected with a clear message', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(Role::ADMIN);
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $context = QrValidationContext::create([
        'name' => 'Contexto vencido',
        'created_by' => (string) $organizer->_id,
        'ends_at' => now()->subMinute(),
    ]);

    $token = app(IdentityService::class)->issueDynamicQrToken($owner);

    $this->actingAs($organizer)->postJson('/identidad/qr/simular-validacion', [
        'code' => $token->code,
        'context_id' => (string) $context->_id,
    ])->assertUnprocessable()->assertJsonValidationErrors('context_id');
});

test('validating without a context_id is rejected', function () {
    $organizer = User::factory()->create();
    $organizer->assignRole(Role::ADMIN);
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $token = app(IdentityService::class)->issueDynamicQrToken($owner);

    $this->actingAs($organizer)->postJson('/identidad/qr/simular-validacion', [
        'code' => $token->code,
    ])->assertUnprocessable()->assertJsonValidationErrors('context_id');
});
