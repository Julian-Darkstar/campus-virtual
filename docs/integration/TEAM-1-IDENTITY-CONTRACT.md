# Equipo 1 — Contrato de identidad para Equipos 2–7

## 1. Principio

Equipo 1 es la fuente central de identidad, credenciales QR, estado académico, dispositivos, sesiones y auditoría de seguridad. Los equipos consumidores no deben consultar directamente `users`, `qr_tokens`, `devices`, `sessions` o `security_events` para reconstruir estas reglas.

## 2. QR

### Generación web

`POST /identidad/qr/generar`

- Autenticación: sesión Laravel + CSRF.
- `purpose` es opcional en la UI y por defecto `identity` para mantener compatibilidad.
- El secreto QR se entrega únicamente en la respuesta necesaria para dibujar el QR; los nuevos tokens se persisten como `code_hash` y `short_code_hash`.
- Los tokens dinámicos expiran y son single-use.

### Validación API

`POST /api/v1/identity/qr-validate`

Autenticación: OAuth `client_credentials`.

Scope mínimo: `identity.qr.validate`.

Para `level=full`: además `identity.qr.validate.full`.

Request compatible:

```json
{
  "code": "CAMPUSDIGITAL:...",
  "purpose": "library_checkout",
  "context": "Turno tarde biblioteca",
  "level": "basic"
}
```

`purpose` se valida contra el propósito con el que fue emitido el QR. Si no se envía, se usa `identity` por compatibilidad con clientes v1 existentes.

La respuesta exitosa contiene, según disponibilidad del perfil:

- `identity.user_id` / datos mínimos de identidad;
- `identity.student_id` y `identity.status`;
- `student` con estado centralizado;
- `credential` con tipo, id y estado;
- `authorization_context.purpose` y `context`;
- `request_id` / `X-Correlation-Id`.

## 3. Errores estables

El campo `error_code` permite reaccionar sin depender de mensajes humanos. Entre los códigos implementados están:

- `QR_INVALID_SIGNATURE`
- `QR_NOT_FOUND`
- `QR_REVOKED`
- `QR_EXPIRED`
- `QR_ALREADY_USED`
- `QR_INVALID_PURPOSE`
- `STUDENT_SUSPENDED`
- `STUDENT_INACTIVE`

Los endpoints de autenticación/autorización continúan usando HTTP 401/403/422/428/429 según la situación.

## 4. Estado del estudiante

La resolución se realiza mediante `StudentStatusService` sobre `StudentProfile`. Los estados actuales del catálogo son `active`, `inactive`, `suspended`, `leave` y `graduated`. Un perfil ausente se reporta como `known=false` para conservar compatibilidad con identidades históricas sin perfil.

Un estado no activo no autoriza la operación QR: el consumidor recibe el estado centralizado y un código estable.

## 5. Correlation ID

Equipo 1 acepta `X-Correlation-Id` o `X-Request-Id`. Si ninguno existe, genera un UUID.

La misma identificación se devuelve en:

- `X-Correlation-Id`;
- `X-Request-Id`;
- `request_id` de respuestas de identidad;
- `qr_validations.correlation_id`;
- `security_events.correlation_id`.

## 6. Dispositivos y sesiones

Web:

- `GET /seguridad/dispositivos`
- `POST /seguridad/sesiones/{session}/revocar`
- `POST /seguridad/sesiones/revocar-otras`
- `POST /seguridad/sesiones/revocar-todas`
- `POST /seguridad/dispositivos/{device}/confianza`
- `DELETE /seguridad/dispositivos/{device}`
- `POST /seguridad/reautenticar`

Las acciones sensibles requieren reautenticación reciente. La sesión Laravel es el mecanismo real de autenticación; `UserSession` es el registro de seguridad/revocación remota y `Device` agrupa sesiones.

`revocar-todas` conserva deliberadamente la sesión actual y revoca las demás para evitar dejar al usuario bloqueado antes de recibir la respuesta.

Los dispositivos revocados ya no se eliminan físicamente: conservan `revoked_at` para trazabilidad y sus sesiones activas se revocan.

## 7. Seguridad

No se exponen a consumidores:

- contraseñas;
- `password_hash`;
- secretos 2FA;
- secretos OAuth;
- access tokens;
- secreto QR persistido.

El token QR nuevo se almacena mediante SHA-256 del valor presentado. Los registros antiguos con `code`/`short_code` en texto plano se siguen pudiendo validar durante la transición.

## 8. Integración por equipo

| Equipo | Contrato | Datos entregados |
|---|---|---|
| 2 | IdentityService / API QR | identidad y estado |
| 3 | roles/contexto existentes | roles centrales y contexto |
| 4 | autorización existente | roles/permisos centrales |
| 5 | `/api/v1/identity/qr-validate` | QR, identidad, estudiante, estado de credencial |
| 6 | contrato de identidad/estado | `student_id`, `status`, datos mínimos disponibles |
| 7 | `SecurityEvent` + outbox existente | eventos y trazabilidad |

## 9. Eventos

Los eventos de seguridad existentes se mantienen y las validaciones QR ahora distinguen, cuando corresponde:

- `qr_validated`
- `qr_expired`
- `qr_reused`
- `qr_revoked`
- `qr_validation_failed`

No se creó un bus paralelo: se conserva la infraestructura de `EventOutbox`/`DomainEvent` para los eventos de dominio que ya utilizan ese mecanismo.
