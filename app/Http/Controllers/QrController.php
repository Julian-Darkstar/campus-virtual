<?php

namespace App\Http\Controllers;

use App\Models\QrValidation;
use App\Models\QrValidationContext;
use App\Services\IdentityService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Modulo 1.6 - Identidad QR.
 *
 * Expone:
 *  - Pantalla del QR fijo de identificacion + QR dinamico rotativo,
 *    con su codigo corto de respaldo para captura manual.
 *  - Endpoint para generar/rotar el token dinamico.
 *  - Endpoint de "validacion" que simula el contrato consumido por
 *    otros equipos (2, 5, 6) cuando escanean el QR de un estudiante.
 */
class QrController extends Controller
{
    private const HISTORY_PAGE_SIZE = 15;

    public function __construct(private IdentityService $identity)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $identificationToken = $this->identity->issueIdentificationQrToken($user);

        return Inertia::render('Security/QrIdentity', [
            'ttlSeconds' => (int) env('QR_IDENTITY_TTL_SECONDS', 30),
            'identificationPayload' => $this->identity->buildQrPayload($identificationToken),
            'recentValidations' => $this->mapValidations(
                $this->historyQuery($user)->limit(10)->get()
            ),
            'historyPageSize' => self::HISTORY_PAGE_SIZE,
            // El simulador de validación (sección "Simulador de
            // validación externa") solo tiene sentido para alguien que
            // realmente puede validar QR ajenos; se lo ocultamos al
            // resto en vez de dejar que lo intente y reciba un 403.
            'canValidate' => $user->isQrValidator(),
            'canViewFullIdentity' => $user->canRequestFullQrIdentity(),
            'qrContexts' => $user->isQrValidator() ? $this->mapContexts($this->activeContextsQuery()->get()) : [],
            'currentUserId' => (string) $user->_id,
        ]);
    }

    public function generate(Request $request)
    {
        $token = $this->identity->issueDynamicQrToken(
            $request->user(),
            $request->input('purpose') ?: null
        );

        return response()->json([
            'code' => $token->code,
            'short_code' => $token->short_code,
            'qr_payload' => $this->identity->buildQrPayload($token),
            'expires_at' => $token->expires_at->toIso8601String(),
            'seconds_remaining' => $token->secondsRemaining(),
            'ttl_seconds' => (int) env('QR_IDENTITY_TTL_SECONDS', 30),
        ]);
    }

    /**
     * Historial paginado (scroll "cargar más") de validaciones sobre
     * los QR de este estudiante.
     */
    public function history(Request $request)
    {
        $offset = max(0, (int) $request->query('offset', 0));

        $items = $this->historyQuery($request->user())
            ->skip($offset)
            ->limit(self::HISTORY_PAGE_SIZE)
            ->get();

        return response()->json([
            'items' => $this->mapValidations($items),
            'next_offset' => $offset + self::HISTORY_PAGE_SIZE,
            'has_more' => $items->count() === self::HISTORY_PAGE_SIZE,
        ]);
    }

    /**
     * Simulador del contrato /api/v1/identity/qr-validate: permite
     * demostrar como otro dominio (biblioteca, evento, caja de
     * asociacion) validaria el QR de un estudiante. Acepta el codigo
     * pelado, el codigo corto de respaldo, o el payload completo (con
     * firma) tal como saldria de escanear el QR.
     *
     * Solo quien tiene un rol "validador" (Role::VALIDATOR_ROLES)
     * puede llamar esto: antes cualquier usuario autenticado podía
     * validar el QR de cualquier otro. El nivel "full" además exige
     * Role::FULL_IDENTITY_ROLES (ver QrValidationPolicy).
     *
     * "context" ya NO es texto libre: debe ser un QrValidationContext
     * vigente (creado por algún validador, con fecha de cierre), para
     * que no se pueda validar "a nombre de" un contexto vencido,
     * cancelado, o inventado en el momento.
     */
    public function simulateValidation(Request $request)
    {
        $this->authorize('validate', QrValidation::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:120'],
            'context_id' => ['required', 'string'],
            // Antes se llamaba "validator_label" y el cliente podía
            // escribir CUALQUIER identidad ahí (ej. "Biblioteca
            // Central" sin serlo). Ahora es solo una nota de detalle
            // físico opcional (ej. número de terminal); la identidad
            // de quién valida la calcula el servidor a partir del
            // usuario autenticado (ver User::validatorLabel()).
            'note' => ['nullable', 'string', 'max:150'],
            'level' => ['nullable', 'string', 'in:basic,full'],
            'purpose' => ['nullable', 'string', 'max:100'],
        ]);

        $context = QrValidationContext::find($data['context_id']);

        if (! $context) {
            throw ValidationException::withMessages([
                'context_id' => 'Ese contexto de validación no existe.',
            ]);
        }

        if (! $context->isActive()) {
            throw ValidationException::withMessages([
                'context_id' => $context->cancelled_at
                    ? 'Ese contexto de validación fue cancelado.'
                    : 'Ese contexto de validación ya venció.',
            ]);
        }

        $level = $data['level'] ?? 'basic';

        if ($level === 'full') {
            $this->authorize('viewFull', QrValidation::class);
        }

        $label = $request->user()->validatorLabel();
        if (! empty($data['note'])) {
            $label .= ' — '.$data['note'];
        }

        $result = $this->identity->validateQrCode(
            input: $data['code'],
            validatedBy: $request->user(),
            context: $context->name,
            ip: $request->ip(),
            validatorLabel: $label,
            level: $level,
            contextId: (string) $context->_id,
            purpose: $data['purpose'] ?? 'identity',
            correlationId: $request->attributes->get('correlation_id'),
        );

        return response()->json($result);
    }

    /**
     * Contrato real /api/v1/identity/qr-validate consumido por otros
     * equipos (2, 5, 6) vía routes/api.php. A diferencia de
     * simulateValidation() (ruta web, autenticación de sesión), este
     * endpoint lo llaman SERVICIOS (OAuth 2.0 client_credentials, sin
     * usuario humano de por medio) — por eso la autorización aquí no
     * es por rol de usuario sino por scope del token de servicio:
     * routes/api.php exige el scope "identity.qr.validate" para poder
     * llegar aquí, y este método exige además
     * "identity.qr.validate.full" si se pide level=full.
     */
    public function validateQr(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'context' => ['nullable', 'string', 'max:150'],
            'purpose' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'in:basic,full'],
        ]);

        $level = $data['level'] ?? 'basic';
        $scopes = preg_split(
            '/\s+/',
            trim((string) $request->attributes->get('oauth_scope', '')),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if ($level === 'full' && ! in_array('identity.qr.validate.full', $scopes, true)) {
            return response()->json([
                'message' => 'El scope del servicio no incluye identity.qr.validate.full; no puede solicitar level=full.',
            ], 403);
        }

        return response()->json(
            $this->identity->validateQrCode(
                input: $data['code'],
                validatedBy: null,
                context: $data['context'] ?? 'api',
                ip: $request->ip(),
                validatorLabel: $this->identity->describeServiceValidator(
                    $request->attributes->get('oauth_client_id')
                ),
                level: $level,
                purpose: $data['purpose'] ?? 'identity',
                correlationId: $request->attributes->get('correlation_id'),
            )
        );
    }

    /**
     * Lista los contextos de validación vigentes (no cancelados, no
     * vencidos) para que cualquier validador pueda usarlos — no solo
     * quien los creó: dos bibliotecarios del mismo turno deben poder
     * compartir el contexto "Turno tarde biblioteca".
     */
    public function contexts(Request $request)
    {
        $this->authorize('create', QrValidationContext::class);

        return response()->json([
            'items' => $this->mapContexts($this->activeContextsQuery()->get()),
        ]);
    }

    /**
     * Crea un contexto de validación con fecha/hora de cierre
     * obligatoria. Lo crea el propio validador (ej. quien organiza el
     * evento), no un administrador a nombre de otros.
     */
    public function storeContext(Request $request)
    {
        $this->authorize('create', QrValidationContext::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'ends_at' => ['required', 'date', 'after:now'],
        ]);

        $context = QrValidationContext::create([
            'name' => $data['name'],
            'created_by' => (string) $request->user()->_id,
            'ends_at' => $data['ends_at'],
        ]);

        return response()->json($this->mapContext($context), 201);
    }

    /**
     * Cancela un contexto antes de su fecha de cierre (error de dedo
     * al crearlo, evento cancelado, etc.). Solo quien lo creó o un
     * admin puede hacerlo (ver QrValidationContextPolicy::cancel).
     */
    public function cancelContext(Request $request, QrValidationContext $context)
    {
        $this->authorize('cancel', $context);

        $context->update(['cancelled_at' => now()]);

        return response()->json($this->mapContext($context));
    }

    private function activeContextsQuery()
    {
        return QrValidationContext::whereNull('cancelled_at')
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('created_at');
    }

    private function mapContexts($items): array
    {
        return $items->map(fn (QrValidationContext $c) => $this->mapContext($c))->values()->all();
    }

    private function mapContext(QrValidationContext $c): array
    {
        return [
            'id' => (string) $c->_id,
            'name' => $c->name,
            'ends_at' => optional($c->ends_at)->toIso8601String(),
            'created_by' => (string) $c->created_by,
        ];
    }

    private function historyQuery($user)
    {
        return QrValidation::where('user_id', (string) $user->_id)
            ->orderByDesc('created_at');
    }

    private function mapValidations($items)
    {
        return $items->map(fn (QrValidation $v) => [
            'id' => (string) $v->_id,
            'result' => $v->result,
            'context' => $v->context,
            'validator_label' => $v->validator_label,
            'ip_address' => $v->ip_address,
            'created_at' => optional($v->created_at)->diffForHumans(),
        ]);
    }
}

