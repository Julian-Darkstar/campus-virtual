<?php

namespace App\Services;

use App\Events\StudentConsentChanged;
use App\Models\StudentConsent;
use App\Models\StudentPreference;
use App\Models\StudentProfile;
use App\Models\User;

class StudentPrivacyService
{
    public const CONSENTS = [
        'terms' => [
            'name' => 'Términos de uso',
            'description' => 'Reglas de uso de Campus Virtual.',
            'version' => '2026.1',
            'required' => true,
        ],
        'privacy' => [
            'name' => 'Aviso de privacidad',
            'description' => 'Tratamiento de datos personales y académicos.',
            'version' => '2026.1',
            'required' => true,
        ],
        'marketing' => [
            'name' => 'Comunicaciones institucionales',
            'description' => 'Novedades, eventos y beneficios del campus.',
            'version' => '2026.1',
            'required' => false,
        ],
    ];

    public function consents(StudentProfile $profile): array
    {
        $records = StudentConsent::where('student_profile_id', (string) $profile->getKey())
            ->get()
            ->keyBy('consent_id');

        return collect(self::CONSENTS)->map(function (array $definition, string $id) use ($records) {
            $record = $records->get($id);
            $versionMatches = $record && $record->version === $definition['version'];
            $status = $versionMatches ? (string) $record->status : 'pending';

            return [
                'id' => $id,
                ...$definition,
                'status' => $status,
                'version' => $definition['version'],
                'accepted_at' => $versionMatches ? optional($record->accepted_at)->toISOString() : null,
                'revoked_at' => $versionMatches ? optional($record->revoked_at)->toISOString() : null,
            ];
        })->values()->all();
    }

    public function accept(StudentProfile $profile, string $consentId, string $version, ?User $actor = null): StudentConsent
    {
        $definition = self::CONSENTS[$consentId] ?? null;
        if (! $definition || $definition['version'] !== $version) {
            abort(422, 'El consentimiento solicitado no existe o su versión ya no está vigente.');
        }

        $now = now();
        $record = StudentConsent::updateOrCreate(
            [
                'student_profile_id' => (string) $profile->getKey(),
                'consent_id' => $consentId,
            ],
            [
                'user_id' => (string) $profile->user_id,
                'version' => $version,
                'status' => 'accepted',
                'accepted_at' => $now,
                'revoked_at' => null,
                'accepted_by' => $actor?->getKey() ? (string) $actor->getKey() : null,
                'revoked_by' => null,
                'updated_by' => $actor?->getKey() ? (string) $actor->getKey() : null,
            ]
        );

        StudentConsentChanged::dispatch(
            (string) $profile->getKey(),
            $consentId,
            'accepted',
            $version,
            $actor?->getKey() ? (string) $actor->getKey() : null,
        );

        return $record->fresh();
    }

    public function revoke(StudentProfile $profile, string $consentId, ?User $actor = null): StudentConsent
    {
        $record = StudentConsent::where('student_profile_id', (string) $profile->getKey())
            ->where('consent_id', $consentId)
            ->first();

        if (! $record || $record->status !== 'accepted') {
            abort(422, 'No existe un consentimiento aceptado que pueda revocarse.');
        }

        if ((self::CONSENTS[$consentId]['required'] ?? false) === true) {
            abort(422, 'Este consentimiento es obligatorio y no puede revocarse mientras la cuenta requiera su aceptación.');
        }

        $record->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $actor?->getKey() ? (string) $actor->getKey() : null,
            'updated_by' => $actor?->getKey() ? (string) $actor->getKey() : null,
        ]);

        StudentConsentChanged::dispatch(
            (string) $profile->getKey(),
            $consentId,
            'revoked',
            (string) $record->version,
            $actor?->getKey() ? (string) $actor->getKey() : null,
        );

        return $record->fresh();
    }

    public function preferences(StudentProfile $profile): array
    {
        $record = StudentPreference::where('student_profile_id', (string) $profile->getKey())->first();

        return [
            'email' => $record?->email ?? true,
            'push' => $record?->push ?? true,
            'sms' => $record?->sms ?? false,
        ];
    }

    public function updatePreferences(StudentProfile $profile, array $values, ?User $actor = null): StudentPreference
    {
        $current = $this->preferences($profile);
        $next = array_merge($current, $values);

        return StudentPreference::updateOrCreate(
            ['student_profile_id' => (string) $profile->getKey()],
            [
                'user_id' => (string) $profile->user_id,
                'email' => (bool) $next['email'],
                'push' => (bool) $next['push'],
                'sms' => (bool) $next['sms'],
                'updated_by' => $actor?->getKey() ? (string) $actor->getKey() : null,
            ]
        );
    }
}
