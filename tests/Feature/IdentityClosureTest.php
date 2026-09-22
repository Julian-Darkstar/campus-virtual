<?php

use App\Enums\StudentStatus;
use App\Models\Device;
use App\Models\QrToken;
use App\Models\QrValidation;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\UserSession;
use App\Services\IdentityService;

beforeEach(function () {
    foreach (Role::VALID_ROLES as $roleName) {
        Role::firstOrCreate(['name' => $roleName], ['name' => $roleName, 'display_name' => $roleName]);
    }
});

test('new QR secrets are stored as hashes, not plaintext', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $token = app(IdentityService::class)->issueDynamicQrToken($owner, 'library_checkout');

    $stored = QrToken::find($token->_id);

    expect($token->code)->not->toBeEmpty()
        ->and($stored->code)->toBeNull()
        ->and($stored->code_hash)->toBe(QrToken::hashPresentedCode($token->code))
        ->and($stored->purpose)->toBe('library_checkout');
});

test('QR purpose is enforced by the central identity contract', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    $token = app(IdentityService::class)->issueDynamicQrToken($owner, 'library_checkout');

    $result = app(IdentityService::class)->validateQrCode(
        input: $token->code,
        validatedBy: null,
        context: 'library',
        ip: '127.0.0.1',
        purpose: 'payment_confirmation',
        correlationId: 'test-purpose-1',
    );

    expect($result['ok'])->toBeFalse()
        ->and($result['result'])->toBe('invalid_purpose')
        ->and($token->fresh()->consumed_at)->toBeNull();
});

test('a suspended student is resolved centrally and cannot authorize a QR operation', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    StudentProfile::create([
        'user_id' => (string) $owner->_id,
        'enrollment_number' => 'STU-SUSP-1',
        'academic_status' => StudentStatus::Suspended,
    ]);
    $token = app(IdentityService::class)->issueDynamicQrToken($owner);

    $result = app(IdentityService::class)->validateQrCode(
        input: $token->code,
        validatedBy: null,
        context: 'library',
        ip: '127.0.0.1',
        correlationId: 'test-student-status-1',
    );

    expect($result['ok'])->toBeFalse()
        ->and($result['result'])->toBe('student_suspended')
        ->and($result['student']['status'])->toBe('suspended');
});

test('QR validation persists correlation id in both audit records', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);
    $token = app(IdentityService::class)->issueDynamicQrToken($owner);

    app(IdentityService::class)->validateQrCode(
        input: $token->code,
        validatedBy: null,
        context: 'library',
        ip: '127.0.0.1',
        correlationId: 'corr-qr-123',
    );

    expect(QrValidation::first()->correlation_id)->toBe('corr-qr-123')
        ->and(SecurityEvent::where('correlation_id', 'corr-qr-123')->exists())->toBeTrue();
});

test('revoking a device preserves the security record and prevents reuse of the revoked device', function () {
    $user = User::factory()->create();
    $device = Device::create([
        'user_id' => (string) $user->_id,
        'fingerprint' => hash('sha256', 'device-test'),
        'device_name' => 'Chrome test',
        'device_type' => 'browser',
        'platform' => 'Windows',
        'browser' => 'Chrome',
        'is_trusted' => true,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);
    $session = UserSession::create([
        'user_id' => (string) $user->_id,
        'device_id' => (string) $device->_id,
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);

    app(IdentityService::class)->forgetDevice($user, $device);

    expect($device->fresh()->revoked_at)->not->toBeNull()
        ->and($device->fresh()->is_trusted)->toBeFalse()
        ->and($session->fresh()->revoked_at)->not->toBeNull();
});

test('revoke all sessions leaves the current session active', function () {
    $user = User::factory()->create();
    $current = UserSession::create(['user_id' => (string) $user->_id, 'started_at' => now(), 'last_activity_at' => now()]);
    $other = UserSession::create(['user_id' => (string) $user->_id, 'started_at' => now()->subMinute(), 'last_activity_at' => now()->subMinute()]);

    $response = $this->actingAs($user)->withSession(['cd_session_id' => (string) $current->_id, 'reauth_at' => now()])
        ->post('/seguridad/sesiones/revocar-todas');

    $response->assertRedirect();
    expect($current->fresh()->revoked_at)->toBeNull()
        ->and($other->fresh()->revoked_at)->not->toBeNull();
});

test('identification QR can be reloaded without persisting the plaintext secret', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::ESTUDIANTE);

    $token = app(IdentityService::class)->issueIdentificationQrToken($owner);
    $reloaded = $token->fresh();

    expect($reloaded->code)->toBe($token->code)
        ->and($reloaded->code_encrypted)->not->toBeNull()
        ->and($reloaded->getRawOriginal('code'))->toBeNull();
});
