<?php
namespace App\Contracts;
use App\Models\QrToken;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
interface IdentityServiceInterface
{
 public function issueDynamicQrToken(User $user, ?string $purpose=null): QrToken;
 public function issueIdentificationQrToken(User $user): QrToken;
 public function buildQrPayload(QrToken $token): string;
 public function validateQrCode(string $input, ?User $validatedBy, ?string $context, ?string $ip, ?string $validatorLabel=null, string $level='basic', ?string $contextId=null, ?string $purpose=null, ?string $correlationId=null): array;
 public function trackDeviceSession(Request $request, ?string $deviceCookie=null): UserSession;
 public function revokeSession(User $user, UserSession $session, string $reason='manual', ?string $correlationId=null): void;
}
