<?php
namespace App\Contracts;
use App\Models\QrToken;
interface CredentialServiceInterface { public function resolveQr(QrToken $token): array; }
