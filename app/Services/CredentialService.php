<?php
namespace App\Services;
use App\Contracts\CredentialServiceInterface;
use App\Models\QrToken;
class CredentialService implements CredentialServiceInterface
{
 public function resolveQr(QrToken $token): array
 {
  $status=match(true){$token->isRevoked()=>'revoked',$token->isConsumed()=>'consumed',$token->isExpired()=>'expired',default=>'active'};
  return ['type'=>'qr','id'=>(string)$token->_id,'status'=>$status];
 }
}
