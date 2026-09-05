<?php

namespace App\Services;

use App\Models\Device;
use App\Models\QrToken;
use App\Models\QrValidation;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Servicio interno de identidad (base del modulo 1.10), usado por los
 * modulos 1.6 - Identidad QR y 1.7 - Dispositivos y sesiones.
 *
 * Los demas equipos NO deben reimplementar esta logica: deben resolver
 * identidad/QR/dispositivos a traves de este servicio o del contrato
 * expuesto en routes/api.php (/api/v1/identity/*).
 */
class IdentityService
{
    /**
     * ---------------------------------------------------------------
     * Modulo 1.6 - Identidad QR
     * ---------------------------------------------------------------
     */

    /**
     * Genera un nuevo token QR dinamico para el usuario, revocando
     * cualquier token dinamico previo aun vigente, para garantizar que
     * solo exista un QR "activo" a la vez por alumno.
     */
    public function issueDynamicQrToken(User $user, ?string $purpose = null): QrToken
    {
        $ttl = (int) env('QR_IDENTITY_TTL_SECONDS', 30);

        QrToken::where('user_id', (string) $user->_id)
            ->where('type', 'dynamic')
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token = QrToken::create([
            'user_id' => (string) $user->_id,
            'code' => QrToken::generateCode(),
            'type' => 'dynamic',
            'purpose' => $purpose,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        SecurityEvent::log([
            'user_id' => (string) $user->_id,
            'type' => 'qr_generated',
            'severity' => 'info',
            'metadata' => ['qr_token_id' => (string) $token->_id, 'ttl_seconds' => $ttl],
        ]);

        return $token;
    }

    /**
     * Genera (o reutiliza si sigue vigente) el QR "fijo" de
     * identificacion del estudiante, con una vigencia mas larga.
     */
    public function issueIdentificationQrToken(User $user): QrToken
    {
        $existing = QrToken::where('user_id', (string) $user->_id)
            ->where('type', 'identification')
            ->whereNull('revoked_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return $existing;
        }

        return QrToken::create([
            'user_id' => (string) $user->_id,
            'code' => QrToken::generateCode(),
            'type' => 'identification',
            'purpose' => 'identidad-estudiantil',
            'expires_at' => now()->addDay(),
        ]);
    }

    /**
     * Contrato de validacion consumido por otros dominios (2, 5, 6):
     * dado un codigo QR, determina si es valido y, de serlo, resuelve
     * la identidad del estudiante. Siempre deja rastro en qr_validations.
     */
    public function validateQrCode(string $code, ?User $validatedBy, ?string $context, ?string $ip): array
    {
        $token = QrToken::where('code', $code)->first();

        $result = match (true) {
            $token === null => 'not_found',
            $token->isRevoked() => 'revoked',
            $token->isConsumed() => 'consumed',
            $token->isExpired() => 'expired',
            default => 'valid',
        };

        QrValidation::create([
            'qr_token_id' => $token ? (string) $token->_id : null,
            'user_id' => $token?->user_id,
            'validated_by_user_id' => $validatedBy ? (string) $validatedBy->_id : null,
            'result' => $result,
            'context' => $context,
            'ip_address' => $ip,
        ]);

        SecurityEvent::log([
            'user_id' => $token?->user_id,
            'type' => $result === 'valid' ? 'qr_validated' : 'qr_validation_failed',
            'severity' => $result === 'valid' ? 'info' : 'warning',
            'ip_address' => $ip,
            'metadata' => ['code' => $code, 'result' => $result, 'context' => $context],
        ]);

        if ($result === 'valid' && $token->type === 'dynamic') {
            $token->update(['consumed_at' => now()]);
        }

        if ($result === 'valid') {
            return [
                'ok' => true,
                'result' => 'valid',
                'identity' => $token->user->displayIdentity(),
            ];
        }

        return ['ok' => false, 'result' => $result, 'identity' => null];
    }

    /**
     * ---------------------------------------------------------------
     * Modulo 1.7 - Dispositivos y sesiones confiables
     * ---------------------------------------------------------------
     */

    public function fingerprint(Request $request): string
    {
        return hash('sha256', $request->userAgent().'|'.$request->ip());
    }

    /**
     * Resuelve/crea el Device y la UserSession de este request
     * autenticado, ligandola al navegador mediante un token opaco
     * (cd_session_id) guardado en la sesion nativa de Laravel.
     */
    public function trackDeviceSession(Request $request): UserSession
    {
        $user = $request->user();
        $fingerprint = $this->fingerprint($request);

        $device = Device::where('user_id', (string) $user->_id)
            ->where('fingerprint', $fingerprint)
            ->first();

        $isNewDevice = $device === null;

        if (! $device) {
            $device = new Device([
                'user_id' => (string) $user->_id,
                'fingerprint' => $fingerprint,
                'device_name' => $this->guessDeviceName($request),
                'device_type' => 'browser',
                'platform' => $this->guessPlatform($request),
                'browser' => $this->guessBrowser($request),
                'is_trusted' => false,
                'first_seen_at' => now(),
            ]);
        }

        $device->last_seen_at = now();
        $device->last_ip_address = $request->ip();
        $device->save();

        $cdSessionId = $request->session()->get('cd_session_id');
        $session = $cdSessionId ? UserSession::find($cdSessionId) : null;

        if (! $session || $session->isRevoked()) {
            $session = UserSession::create([
                'user_id' => (string) $user->_id,
                'device_id' => (string) $device->_id,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);

            $request->session()->put('cd_session_id', (string) $session->_id);

            SecurityEvent::log([
                'user_id' => (string) $user->_id,
                'device_id' => (string) $device->_id,
                'session_id' => (string) $session->_id,
                'type' => 'login_success',
                'severity' => 'info',
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            if ($isNewDevice) {
                SecurityEvent::log([
                    'user_id' => (string) $user->_id,
                    'device_id' => (string) $device->_id,
                    'session_id' => (string) $session->_id,
                    'type' => 'new_device',
                    'severity' => 'warning',
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'metadata' => ['device_name' => $device->device_name],
                ]);
            }
        } else {
            $session->last_activity_at = now();
            $session->save();
        }

        return $session;
    }

    public function revokeSession(User $user, UserSession $session, string $reason = 'manual'): void
    {
        abort_unless($session->user_id === (string) $user->_id, 403);

        $session->update(['revoked_at' => now(), 'revoked_reason' => $reason]);

        SecurityEvent::log([
            'user_id' => (string) $user->_id,
            'device_id' => $session->device_id,
            'session_id' => (string) $session->_id,
            'type' => 'session_revoked',
            'severity' => 'info',
            'ip_address' => $session->ip_address,
            'metadata' => ['reason' => $reason],
        ]);
    }

    public function revokeSessionById(User $user, string $sessionId, string $reason = 'manual'): void
    {
        $session = UserSession::where('_id', $sessionId)
            ->where('user_id', (string) $user->_id)
            ->first();

        if ($session && ! $session->isRevoked()) {
            $this->revokeSession($user, $session, $reason);
        }
    }

    public function setDeviceTrust(User $user, Device $device, bool $trusted): void
    {
        abort_unless($device->user_id === (string) $user->_id, 403);

        $device->update(['is_trusted' => $trusted]);

        SecurityEvent::log([
            'user_id' => (string) $user->_id,
            'device_id' => (string) $device->_id,
            'type' => $trusted ? 'device_trusted' : 'device_untrusted',
            'severity' => 'info',
        ]);
    }

    private function guessDeviceName(Request $request): string
    {
        return trim(($this->guessPlatform($request) ?: 'Dispositivo').' · '.($this->guessBrowser($request) ?: 'Navegador'));
    }

    private function guessPlatform(Request $request): string
    {
        $ua = (string) $request->userAgent();

        return match (true) {
            Str::contains($ua, 'Windows') => 'Windows',
            Str::contains($ua, 'Mac OS') => 'macOS',
            Str::contains($ua, 'Android') => 'Android',
            Str::contains($ua, ['iPhone', 'iPad']) => 'iOS',
            Str::contains($ua, 'Linux') => 'Linux',
            default => 'Desconocido',
        };
    }

    private function guessBrowser(Request $request): string
    {
        $ua = (string) $request->userAgent();

        return match (true) {
            Str::contains($ua, 'Edg/') => 'Edge',
            Str::contains($ua, 'Chrome/') => 'Chrome',
            Str::contains($ua, 'Firefox/') => 'Firefox',
            Str::contains($ua, 'Safari/') && ! Str::contains($ua, 'Chrome') => 'Safari',
            default => 'Navegador',
        };
    }
}
