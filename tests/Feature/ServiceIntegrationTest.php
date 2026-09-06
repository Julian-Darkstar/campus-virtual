<?php

use App\Events\StudentConsentChanged;
use App\Models\EventOutbox;
use App\Models\ServiceClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

it('issues and accepts an OAuth service token', function () {
    $client = ServiceClient::create([
        'name' => 'Payments service',
        'client_id' => 'svc_payments',
        'secret_hash' => Hash::make('secret'),
        'scopes' => ['students:read'],
        'active' => true,
    ]);

    $tokenResponse = $this->postJson('/api/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->client_id,
        'client_secret' => 'secret',
        'scope' => 'students:read',
    ]);

    $tokenResponse->assertOk()->assertJsonPath('token_type', 'Bearer');
    $token = $tokenResponse->json('access_token');

    $this->getJson('/api/v1/students/student-1/status', [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonPath('meta.api_version', 'v1');
});

it('rejects API requests without a service token', function () {
    $this->getJson('/api/v1/students/student-1/status')->assertUnauthorized();
});

it('stores versioned domain events in the outbox', function () {
    StudentConsentChanged::dispatch('student-1', 'privacy', 'accepted', '2026.1');

    expect(EventOutbox::where('event_name', 'student.consent.changed.v1')->count())->toBe(1);
    expect(EventOutbox::first()->payload['consent_id'])->toBe('privacy');
});

it('publishes pending events and marks them as delivered', function () {
    config(['events.sink_url' => 'https://events.test/v1/events']);
    Http::fake(['https://events.test/*' => Http::response(['accepted' => true], 202)]);
    StudentConsentChanged::dispatch('student-1', 'privacy', 'accepted', '2026.1');

    $this->artisan('events:publish')->assertSuccessful();

    expect(EventOutbox::first()->published_at)->not->toBeNull();
    Http::assertSentCount(1);
});
