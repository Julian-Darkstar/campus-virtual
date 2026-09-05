<?php

namespace App\Http\Controllers;

use App\Models\QrValidation;
use App\Services\IdentityService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Modulo 1.6 - Identidad QR.
 *
 * Expone:
 *  - Pantalla del QR fijo de identificacion + QR dinamico rotativo.
 *  - Endpoint para generar/rotar el token dinamico.
 *  - Endpoint de "validacion" que simula el contrato consumido por
 *    otros equipos (2, 5, 6) cuando escanean el QR de un estudiante.
 */
class QrController extends Controller
{
    public function __construct(private IdentityService $identity)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $identificationToken = $this->identity->issueIdentificationQrToken($user);

        $recentValidations = QrValidation::where('user_id', (string) $user->_id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (QrValidation $v) => [
                'id' => (string) $v->_id,
                'result' => $v->result,
                'context' => $v->context,
                'ip_address' => $v->ip_address,
                'created_at' => optional($v->created_at)->diffForHumans(),
            ]);

        return Inertia::render('Security/QrIdentity', [
            'ttlSeconds' => (int) env('QR_IDENTITY_TTL_SECONDS', 30),
            'identificationPayload' => 'CAMPUSDIGITAL-ID:'.$identificationToken->code,
            'recentValidations' => $recentValidations,
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
            'qr_payload' => 'CAMPUSDIGITAL:'.$token->code,
            'expires_at' => $token->expires_at->toIso8601String(),
            'seconds_remaining' => $token->secondsRemaining(),
            'ttl_seconds' => (int) env('QR_IDENTITY_TTL_SECONDS', 30),
        ]);
    }

    public function history(Request $request)
    {
        $items = QrValidation::where('user_id', (string) $request->user()->_id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn (QrValidation $v) => [
                'id' => (string) $v->_id,
                'result' => $v->result,
                'context' => $v->context,
                'ip_address' => $v->ip_address,
                'created_at' => optional($v->created_at)->diffForHumans(),
            ]);

        return response()->json(['items' => $items]);
    }

    /**
     * Simulador del contrato /api/v1/identity/qr-validate: permite
     * demostrar, dentro de este mismo mockup, como otro dominio
     * (biblioteca, evento, caja de asociacion) validaria el QR de un
     * estudiante.
     */
    public function simulateValidation(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'context' => ['nullable', 'string', 'max:150'],
        ]);

        $result = $this->identity->validateQrCode(
            $data['code'],
            $request->user(),
            $data['context'] ?? 'simulador-interno',
            $request->ip()
        );

        return response()->json($result);
    }
}
