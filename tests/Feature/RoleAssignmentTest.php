<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    Role::firstOrCreate(['name' => Role::ADMIN], ['name' => Role::ADMIN, 'display_name' => 'Admin']);
    Role::firstOrCreate(['name' => Role::ESTUDIANTE], ['name' => Role::ESTUDIANTE, 'display_name' => 'Estudiante']);
});

test('a regular authenticated user cannot self-assign the admin role', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/roles/assign', [
        'role_name' => Role::ADMIN,
    ]);

    $response->assertForbidden();
    expect($user->fresh()->hasRole(Role::ADMIN))->toBeFalse();
});

test('a guest cannot hit the role assignment endpoint', function () {
    $response = $this->post('/roles/assign', [
        'role_name' => Role::ADMIN,
    ]);

    $response->assertRedirect('/login');
});

test('an admin can assign a valid role to another user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN);

    $target = User::factory()->create();

    $response = $this->actingAs($admin)->post('/roles/assign', [
        'role_name' => Role::ESTUDIANTE,
        'user_id' => (string) $target->getKey(),
    ]);

    $response->assertRedirect();
    expect($target->fresh()->hasRole(Role::ESTUDIANTE))->toBeTrue();
});

test('an arbitrary role name outside the catalog is rejected even for an admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN);

    $response = $this->actingAs($admin)->post('/roles/assign', [
        'role_name' => 'super-root',
    ]);

    $response->assertSessionHasErrors('role_name');
});

test('User::assignRole rejects a role that is not in the catalog regardless of caller', function () {
    $user = User::factory()->create();

    expect(fn () => $user->assignRole('not-a-real-role'))
        ->toThrow(InvalidArgumentException::class);
});

test('a contextual role requires the matching scope and scope id', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN);
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->post('/roles/assign', [
        'role_name' => Role::BIBLIOTECARIO,
    ]);

    $response->assertSessionHasErrors('scope_type');
    expect($target->fresh()->hasRole(Role::BIBLIOTECARIO))->toBeFalse();

    $response = $this->actingAs($admin)->post('/roles/assign', [
        'role_name' => Role::BIBLIOTECARIO,
        'user_id' => (string) $target->getKey(),
        'scope_type' => 'service',
        'scope_id' => 'biblioteca-central',
    ]);

    $response->assertRedirect();
    expect($target->fresh()->hasRole(Role::BIBLIOTECARIO, 'service', 'biblioteca-central'))->toBeTrue();
});
