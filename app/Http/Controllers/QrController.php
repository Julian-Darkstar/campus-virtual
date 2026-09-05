<?php

namespace App\Http\Controllers;

use App\Models\QrToken;
use App\Models\QrValidation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QrController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Security/QrIdentity', [
            'recentValidations' => QrValidation::where('user_id', (string) $request->user()->_id)
                ->latest()->limit(10)->get(),
            'ttlSeconds' => (int) env('QR_IDENTITY_TTL_SECONDS', 60),
        ]);
    }

    public function generate(Request $request)
    {
        $ttl = (int) env('QR_IDENTITY_TTL_SECONDS', 60);
        QrToken::where('user_id', (string) $request->user()->_id)
            ->whereNull('consumed_at')->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token = QrToken::create([
            'user_id' => (string) $request->user()->_id,
            'code' => QrToken::generateCode(),
            'type' => 'dynamic',
            'purpose' => $request->input('purpose', 'identidad'),
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return response()->json([
            'code' => $token->code,
            'payload' => 'CAMPUS-VIRTUAL:'.$token->code,
            'expires_at' => $token->expires_at->toIso8601String(),
            'seconds_remaining' => $ttl,
        ]);
    }

    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'context' => ['nullable', 'string', 'max:120'],
        ]);
        $code = str_replace('CAMPUS-VIRTUAL:', '', trim($data['code']));
        $token = QrToken::where('code', $code)->first();
        $result = match (true) {
            $token === null => 'not_found',
            ! $token->isValid() && $token->consumed_at !== null => 'consumed',
            ! $token->isValid() && $token->revoked_at !== null => 'revoked',
            ! $token->isValid() => 'expired',
            default => 'valid',
        };

        QrValidation::create([
            'qr_token_id' => $token?->_id,
            'user_id' => $token?->user_id,
            'validated_by_user_id' => (string) $request->user()->_id,
            'result' => $result,
            'context' => $data['context'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        if ($result === 'valid' && $token->type === 'dynamic') {
            $token->update(['consumed_at' => now()]);
        }

        return response()->json([
            'ok' => $result === 'valid',
            'result' => $result,
            'identity' => $result === 'valid' && $token->user ? [
                'name' => $token->user->name,
                'email' => $token->user->email,
            ] : null,
        ], $result === 'valid' ? 200 : 422);
    }
}