<?php

use App\Actions\Students\UpsertStudentProfile;
use App\Enums\StudentStatus;
use App\Models\Role;
use App\Models\StudentConsent;
use App\Models\StudentPreference;
use App\Models\StudentProfile;
use App\Models\User;

beforeEach(function () {
    Role::firstOrCreate(['name' => Role::ADMIN], ['name' => Role::ADMIN, 'display_name' => 'Admin']);
    Role::firstOrCreate(['name' => Role::MAESTRO], ['name' => Role::MAESTRO, 'display_name' => 'Maestro']);
});

function studentWithProfile(array $overrides = []): User
{
    $user = User::factory()->create();
    StudentProfile::create(array_merge([
        'user_id' => (string) $user->getKey(),
        'enrollment_number' => 'STU-'.fake()->unique()->numerify('#####'),
        'academic_status' => StudentStatus::Active,
        'current_semester' => 4,
    ], $overrides));

    return $user->fresh();
}

test('module 1.8 reads the real student profile instead of demo values', function () {
    $user = studentWithProfile([
        'enrollment_number' => 'REAL-001',
        'academic_status' => StudentStatus::Suspended,
        'current_semester' => 7,
    ]);

    $response = $this->actingAs($user)->get('/student-services');

    $response->assertInertia(fn ($page) => $page
        ->where('student.enrollment', 'REAL-001')
        ->where('student.semester', 7)
        ->where('student.status', 'suspended')
        ->where('student.status_label', 'Suspendido'));
});

test('module 1.9 persists consent and preferences', function () {
    $user = studentWithProfile();
    $profile = $user->studentProfile;

    $this->actingAs($user)->post('/student-services/consents/marketing', [
        'consent_version' => '2026.1',
    ])->assertCreated();

    $this->actingAs($user)->patch('/student-services/preferences', [
        'email' => false,
        'push' => true,
        'sms' => true,
    ])->assertOk();

    expect(StudentConsent::where('student_profile_id', (string) $profile->getKey())->where('consent_id', 'marketing')->first()->status)
        ->toBe('accepted')
        ->and(StudentPreference::where('student_profile_id', (string) $profile->getKey())->first()->email)->toBeFalse()
        ->and(StudentPreference::where('student_profile_id', (string) $profile->getKey())->first()->sms)->toBeTrue();
});

test('required consent cannot be revoked', function () {
    $user = studentWithProfile();

    $this->actingAs($user)->post('/student-services/consents/terms', [
        'consent_version' => '2026.1',
    ])->assertCreated();

    $this->actingAs($user)->delete('/student-services/consents/terms')
        ->assertStatus(422);
});
