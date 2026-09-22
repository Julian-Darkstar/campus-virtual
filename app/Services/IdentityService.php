<?php

namespace App\Services;

use App\Models\Device;
use App\Models\QrToken;
use App\Models\QrValidation;
use App\Models\SecurityEvent;
use App\Models\ServiceClient;
use App\Models\User;
use App\Models\UserSession;
use App\Contracts\IdentityServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Servicio interno de identidad (base del modulo 1.10), usado por los
 * modulos 1.6 - Identidad QR y 1.7 - Dispositivos y sesiones.
 *
 * Los demas equipos NO deben reimplementar esta logica: deben resolver
 * identidad/QR/dispositivos a traves de este servicio o del contrato
 * expuesto en routes/api.php (/api/v1/identity/*) cuando se construya
 * el modulo 1.10.
 */
class IdentityService implements IdentityServiceInterface
{
    public function __construct(
        private readonly StudentStatusService $studentStatus,
        private readonly CredentialService $credentials,
    ) {}

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

        $code = QrToken::generateCode();
        $shortCode = $this->generateUniqueShortCode();
        $purpose ??= 'identity';
        $token = QrToken::create([
            'user_id' => (string) $user->_id,
            'code_hash' => QrToken::hashPresentedCode($code),
            'short_code_hash' => QrToken::hashShortCode($shortCode),
            'type' => 'dynamic',
            'purpose' => $purpose,
            'expires_at' => now()->addSeconds($ttl),
        ]);
        $token->setAttribute('code', $code);
        $token->setAttribute('short_code', $shortCode);

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
            // Tokens creados por versiones anteriores pueden conservar
            // solamente code_hash (el secreto no se puede reconstruir
            // desde un hash). Si encontramos uno de esos tokens, lo
            // renovamos en el mismo documento con un secreto cifrado
            // nuevo. Esto evita que una cuenta ya existente provoque
            // signCode(null) al abrir /identidad/qr.
            $existingCode = $existing->code;

            if (is_string($existingCode) && $existingCode !== '') {
                return $existing;
            }

            $code = QrToken::generateCode();
            $existing->code_hash = QrToken::hashPresentedCode($code);
            $existing->code_encrypted = encrypt($code);
            $existing->expires_at = now()->addDay();
            $existing->consumed_at = null;
            $existing->revoked_at = null;
            $existing->save();
            $existing->setAttribute('code', $code);

            return $existing;
        }

        $code = QrToken::generateCode();
        $token = QrToken::create([
            'user_id' => (string) $user->_id,
            'code_hash' => QrToken::hashPresentedCode($code),
            'code_encrypted' => encrypt($code),
            'type' => 'identification',
            'purpose' => 'identity',
            'expires_at' => now()->addDay(),
        ]);
        $token->setAttribute('code', $code);

        return $token;
    }

    /**
     * Firma HMAC de un codigo QR: protege el contenido del QR frente a
     * manipulacion/adivinanza de formato. La firma no reemplaza la
     * consulta a base de datos (que sigue siendo la fuente de verdad),
     * pero permite rechazar payloads corruptos o inventados antes de
     * siquiera consultar la coleccion qr_tokens.
     */
    public function signCode(string $code): string
    {
        return substr(hash_hmac('sha256', $code, config('app.key')), 0, 10);
    }

    /**
     * Payload completo que se dibuja en el QR: prefijo + codigo + firma.
     */
    public function buildQrPayload(QrToken $token): string
    {
        $prefix = $token->type === 'identification' ? 'CAMPUSDIGITAL-ID:' : 'CAMPUSDIGITAL:';

        return $prefix.$token->code.'.'.$this->signCode($token->code);
    }

    /**
     * Genera un codigo corto numerico (6 digitos) que el estudiante
     * puede dictar/teclear manualmente si no se puede escanear el QR.
     * No es criptograficamente fuerte (es de un solo uso y expira con
     * el token dinamico), pero evita colisiones con otros codigos
     * activos en este momento.
     */
    private function generateUniqueShortCode(): string
    {
        do {
            $shortCode = (string) random_int(100000, 999999);
            $exists = QrToken::where(function ($query) use ($shortCode) {
                    $query->where('short_code_hash', QrToken::hashShortCode($shortCode))
                        ->orWhere('short_code', $shortCode);
                })
                ->where('type', 'dynamic')
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists();
        } while ($exists);

        return $shortCode;
    }

    /**
     * Interpreta lo que escaneo/tecleo el validador: puede ser el
     * codigo "pelado", el codigo corto de respaldo, o el payload
     * completo del QR (prefijo + codigo + firma). Devuelve el codigo
     * a buscar y si la firma (cuando venia incluida) es valida.
     */
    private function parseScannedInput(string $input): array
    {
        $raw = trim($input);

        foreach (['CAMPUSDIGITAL-ID:', 'CAMPUSDIGITAL:'] as $prefix) {
            if (str_starts_with($raw, $prefix)) {
                $raw = substr($raw, strlen($prefix));
                break;
            }
        }

        if (str_contains($raw, '.')) {
            [$code, $signature] = array_pad(explode('.', $raw, 2), 2, '');

            return [
                'code' => $code,
                'is_short_code' => false,
                'signature_valid' => hash_equals($this->signCode($code), $signature),
            ];
        }

        // Codigo corto de respaldo: siempre numerico y de 6 digitos, no
        // lleva firma porque se piensa para captura manual.
        if (preg_match('/^\d{6}$/', $raw)) {
            return ['code' => $raw, 'is_short_code' => true, 'signature_valid' => null];
        }

        return ['code' => $raw, 'is_short_code' => false, 'signature_valid' => null];
    }

    /**
     * Contrato de validacion consumido por otros dominios (2, 5, 6):
     * dado un codigo/QR escaneado, determina si es valido y, de
     * serlo, resuelve la identidad del estudiante. Siempre deja
     * rastro en qr_validations.
     *
     * El consumo del token dinamico es atomico (update condicionado)
     * para que dos validaciones casi simultaneas del mismo QR no
     * puedan resolver ambas como "valid".
     *
     * @param  string  $level  "basic" (por defecto, nombre enmascarado)
     *                         o "full" (nombre completo) segun cuanto
     *                         necesite exponer el consumidor.
     */
    public function validateQrCode(
        string $input,
        ?User $validatedBy,
        ?string $context,
        ?string $ip,
        ?string $validatorLabel = null,
        string $level = 'basic',
        ?string $contextId = null,
        ?string $purpose = null,
        ?string $correlationId = null,
    ): array {
        $parsed = $this->parseScannedInput($input);

        if ($parsed['signature_valid'] === false) {
            $this->logValidation(null, null, $validatedBy, $validatorLabel, 'invalid_signature', $context, $ip, $contextId, $purpose, $correlationId);

            return ['ok'=>false,'result'=>'invalid_signature','error_code'=>'QR_INVALID_SIGNATURE','identity'=>null,'credential'=>null,'student'=>null,'authorization_context'=>['purpose'=>$purpose ?: 'identity','context'=>$context],'request_id'=>$correlationId];
        }

        $token = QrToken::where('code_hash', QrToken::hashPresentedCode($parsed['code']))->first();
        $token ??= QrToken::where('code', $parsed['code'])->first();
        if (! $token && $parsed['is_short_code']) {
            $token = QrToken::where(function ($query) use ($parsed) {
                    $query->where('short_code_hash', QrToken::hashShortCode($parsed['code']))
                        ->orWhere('short_code', $parsed['code']);
                })->where('type', 'dynamic')->first();
        }

        $result = match (true) {
            $token === null => 'not_found',
            $token->isRevoked() => 'revoked',
            $token->isConsumed() => 'consumed',
            $token->isExpired() => 'expired',
            default => 'valid',
        };

        $expectedPurpose = $purpose ?: 'identity';
        $tokenPurpose = $token?->purpose ?: 'identity';
        if ($result === 'valid' && $tokenPurpose !== $expectedPurpose) $result = 'invalid_purpose';

        // Consumo atomico: solo el primer request que llega a marcar
        // consumed_at "gana"; si otro ya lo hizo entre el match() de
        // arriba y este update, aqui se detecta y se corrige el
        // resultado a "consumed".
        if ($result === 'valid' && $token->type === 'dynamic') {
            $updated = QrToken::where('_id', $token->_id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['consumed_at' => now()]);

            if ($updated === 0) {
                $result = 'consumed';
            }
        }

        $this->logValidation($token, $token?->user_id, $validatedBy, $validatorLabel, $result, $context, $ip, $contextId, $expectedPurpose, $correlationId);

        if ($result === 'valid') {
            $student = $this->studentStatus->resolve($token->user);
            $credential = $this->credentials->resolveQr($token);
            if (!$this->studentStatus->isOperationAllowed($student)) {
                $blocked = $student['status'] === 'suspended' ? 'student_suspended' : 'student_inactive';
                return ['ok'=>false,'result'=>$blocked,'error_code'=>strtoupper($blocked),'identity'=>null,'credential'=>$credential,'student'=>$student,'authorization_context'=>['purpose'=>$expectedPurpose,'context'=>$context],'request_id'=>$correlationId];
            }
            return ['ok'=>true,'result'=>'valid','error_code'=>null,'identity'=>array_merge($token->user->displayIdentity($level),['student_id'=>$student['student_id'],'status'=>$student['status']]),'student'=>$student,'credential'=>$credential,'authorization_context'=>['purpose'=>$expectedPurpose,'context'=>$context],'request_id'=>$correlationId];
        }

        return ['ok'=>false,'result'=>$result,'error_code'=>$this->errorCodeForResult($result),'identity'=>null,'credential'=>$token?$this->credentials->resolveQr($token):null,'student'=>$token?->user?$this->studentStatus->resolve($token->user):null,'authorization_context'=>['purpose'=>$expectedPurpose,'context'=>$context],'request_id'=>$correlationId];
    }

    /**
     * Etiqueta legible de qué SERVICIO externo (no un User humano)
     * validó, a partir del client_id ya verificado por
     * ValidateServiceToken (nunca de texto libre enviado en el body).
     * Se usa en validateQr() (contrato /api/v1/identity/qr-validate).
     */
    public function describeServiceValidator(?string $oauthClientId): ?string
    {
        if (! $oauthClientId) {
            return null;
        }

        $name = ServiceClient::where('client_id', $oauthClientId)->value('name');

        return $name ? "Servicio: {$name}" : "Servicio: {$oauthClientId}";
    }

    private function logValidation(
        ?QrToken $token,
        ?string $userId,
        ?User $validatedBy,
        ?string $validatorLabel,
        string $result,
        ?string $context,
        ?string $ip,
        ?string $contextId = null,
        ?string $purpose = null,
        ?string $correlationId = null,
    ): void {
        QrValidation::create([
            'qr_token_id' => $token ? (string) $token->_id : null,
            'user_id' => $userId,
            'validated_by_user_id' => $validatedBy ? (string) $validatedBy->_id : null,
            'validator_label' => $validatorLabel,
            'result' => $result,
            'context' => $context,
            'context_id' => $contextId,
            'purpose' => $purpose,
            'correlation_id' => $correlationId,
            'ip_address' => $ip,
        ]);

        $eventType = match ($result) {
            'valid' => 'qr_validated',
            'expired' => 'qr_expired',
            'consumed' => 'qr_reused',
            'revoked' => 'qr_revoked',
            default => 'qr_validation_failed',
        };

        SecurityEvent::log([
            'user_id' => $userId,
            'type' => $eventType,
            'severity' => $result === 'valid' ? 'info' : 'warning',
            'ip_address' => $ip,
            'correlation_id' => $correlationId,
            'metadata' => ['result'=>$result,'context'=>$context,'purpose'=>$purpose,'validator_label'=>$validatorLabel],
        ]);
    }

    private function errorCodeForResult(string $result): string
    {
        return match ($result) {
            'invalid_signature'=>'QR_INVALID_SIGNATURE','not_found'=>'QR_NOT_FOUND','revoked'=>'QR_REVOKED','expired'=>'QR_EXPIRED','consumed'=>'QR_ALREADY_USED','invalid_purpose'=>'QR_INVALID_PURPOSE','student_suspended'=>'STUDENT_SUSPENDED','student_inactive'=>'STUDENT_INACTIVE',default=>strtoupper($result),
        };
    }

    /**
     * ---------------------------------------------------------------
     * Modulo 1.7 - Dispositivos y sesiones confiables
     * ---------------------------------------------------------------
     */

    /**
     * Huella logica del dispositivo. Se apoya principalmente en una
     * cookie opaca de larga duracion (cd_device_id) para que el mismo
     * telefono/navegador no aparezca como "dispositivo nuevo" cada vez
     * que cambia de IP (redes moviles). Si por algun motivo no hay
     * cookie disponible, cae de vuelta a user agent + IP.
     */
    public function fingerprint(Request $request, ?string $deviceCookie = null): string
    {
        $deviceCookie ??= $request->cookie('cd_device_id');

        $seed = $deviceCookie
            ? 'device:'.$deviceCookie
            : 'ua-ip:'.$request->userAgent().'|'.$request->ip();

        return hash('sha256', $seed);
    }

    /**
     * Resuelve/crea el Device y la UserSession de este request
     * autenticado, ligandola al navegador mediante un token opaco
     * (cd_session_id) guardado en la sesion nativa de Laravel.
     */
    public function trackDeviceSession(Request $request, ?string $deviceCookie = null): UserSession
    {
        $user = $request->user();
        $fingerprint = $this->fingerprint($request, $deviceCookie);

        $device = Device::where('user_id', (string) $user->_id)
            ->where('fingerprint', $fingerprint)
            ->first();

        $isNewDevice = $device === null || $device->revoked_at !== null;

        if (! $device || $device->revoked_at !== null) {
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
                'correlation_id' => $request->attributes->get('correlation_id'),
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
                    'correlation_id' => $request->attributes->get('correlation_id'),
                    'type' => 'new_device',
                    'severity' => 'warning',
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'metadata' => ['device_name' => $device->device_name],
                ]);
            }

            $this->enforceConcurrentSessionLimit($user, $session);
        } else {
            $session->last_activity_at = now();
            $session->save();
        }

        return $session;
    }

    /**
     * Limita cuantas sesiones activas puede tener un mismo estudiante
     * a la vez. Si se excede el limite, revoca las sesiones activas
     * mas antiguas (la recien creada nunca se revoca a si misma).
     */
    private function enforceConcurrentSessionLimit(User $user, UserSession $justCreated): void
    {
        $max = (int) env('MAX_ACTIVE_SESSIONS_PER_USER', 5);

        if ($max <= 0) {
            return;
        }

        $active = UserSession::where('user_id', (string) $user->_id)
            ->whereNull('revoked_at')
            ->orderBy('last_activity_at')
            ->get();

        if ($active->count() <= $max) {
            return;
        }

        $excess = $active->count() - $max;

        foreach ($active as $session) {
            if ($excess <= 0) {
                break;
            }

            if ((string) $session->_id === (string) $justCreated->_id) {
                continue;
            }

            $this->revokeSession($user, $session, 'session_limit_exceeded');
            $excess--;
        }
    }

    public function revokeSession(User $user, UserSession $session, string $reason = 'manual', ?string $correlationId = null): void
    {
        abort_unless($session->user_id === (string) $user->_id, 403);

        $session->update(['revoked_at' => now(), 'revoked_reason' => $reason]);

        SecurityEvent::log([
            'user_id' => (string) $user->_id,
            'device_id' => $session->device_id,
            'session_id' => (string) $session->_id,
            'correlation_id' => $correlationId ?? request()->attributes->get('correlation_id'),
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

    /**
     * Elimina un dispositivo del listado del estudiante, revocando
     * primero cualquier sesion activa que dependa de el.
     */
    public function forgetDevice(User $user, Device $device): void
    {
        abort_unless($device->user_id === (string) $user->_id, 403);

        UserSession::where('device_id', (string) $device->_id)
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (UserSession $session) => $this->revokeSession($user, $session, 'device_removed'));

        SecurityEvent::log([
            'user_id' => (string) $user->_id,
            'device_id' => (string) $device->_id,
            'correlation_id' => request()->attributes->get('correlation_id'),
            'type' => 'device_removed',
            'severity' => 'info',
        ]);

        $device->update(['revoked_at'=>now(),'is_trusted'=>false]);
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
