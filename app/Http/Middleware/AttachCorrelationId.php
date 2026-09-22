<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
class AttachCorrelationId
{
 public function handle(Request $request, Closure $next): Response
 {
  $incoming=trim((string)($request->header('X-Correlation-Id') ?: $request->header('X-Request-Id')));
  $id=preg_match('/^[A-Za-z0-9._:-]{1,128}$/',$incoming)?$incoming:(string)Str::uuid();
  $request->attributes->set('correlation_id',$id);
  $response=$next($request);
  $response->headers->set('X-Correlation-Id',$id);
  $response->headers->set('X-Request-Id',$id);
  return $response;
 }
}
