# Cierre de módulos 1.6 y 1.7 — Equipo 1

## A. Estado antes

| Módulo/componente | Estado antes | Bloqueante | Evidencia |
|---|---|---|---|
| IdentityService | PARCIAL | Sí | QR existía, pero purpose/status/auditoría no estaban consolidados |
| QrToken | PARCIAL | Sí | nuevos secretos se persistían en texto plano |
| QrValidation | PARCIAL | Sí | faltaba purpose/correlation_id |
| QR API v1 | PARCIAL | Sí | scope existía; contrato de purpose/status necesitaba cierre |
| Student status | PARCIAL | Sí | `StudentServicesController` usaba datos simulados |
| Device | PARCIAL | Sí | revocación eliminaba el documento |
| UserSession | PARCIAL | Sí | revocación funcionaba, faltaba cierre de all-sessions |
| SecurityEvent | PARCIAL | Sí | no tenía correlation_id y QR usaba evento genérico |
| Reauth | COMPLETO | No | middleware + endpoint existentes |
| OAuth | COMPLETO | No | client_credentials + scopes existentes |
| Frontend QR | PARCIAL | No | flujo real existente; dependía de contrato incompleto |
| Frontend Devices | PARCIAL | No | revocación/trust reales; faltaba all-sessions explícito |

## B. Implementado por el agente

- `AttachCorrelationId` para web/API.
- Contratos `IdentityServiceInterface`, `StudentStatusServiceInterface`, `CredentialServiceInterface`.
- `StudentStatusService` como resolución central del estado académico.
- `CredentialService` para el estado de la credencial QR.
- Persistencia hash de nuevos códigos QR y códigos cortos.
- Compatibilidad de lectura para tokens históricos.
- Validación real de `purpose`.
- Respuesta de identidad enriquecida con estudiante, credencial y contexto.
- Códigos estables de error QR.
- `correlation_id` en validaciones y eventos de seguridad.
- Eventos específicos `qr_expired`, `qr_reused`, `qr_revoked`.
- Revocación de dispositivo conservando el registro con `revoked_at`.
- Ruta y flujo de cierre de todas las demás sesiones.
- Índices adicionales para consultas y auditoría.
- Pruebas nuevas para hash, purpose, estado de estudiante, correlation, revocación de dispositivo y all-sessions.
- Contrato documental de integración.

## C. Ya existía

- Laravel + Vue 3 + Inertia.
- Fortify/2FA.
- `IdentityService` y modelos QR.
- QR dinámico/fijo, HMAC, expiración y consumo atómico.
- OAuth client_credentials y scopes.
- `Device`, `UserSession`, `SecurityEvent`.
- middleware de sesión, tracking de dispositivo y reauth.
- UI de QR y dispositivos.
- `EventOutbox`/`DomainEvent`.
- pruebas de QR, autorización, contextos, OAuth y autenticación.

## D. Dependencia externa / limitación de verificación

El entorno de ejecución de esta auditoría no tenía Composer instalado ni `vendor/` disponible. MongoDB tampoco estaba disponible en el runtime de verificación. Por ello no fue posible ejecutar la suite Laravel/Pest contra una base Mongo real.

Además, `npm install --ignore-scripts` agotó el tiempo del entorno y `vite` no quedó disponible; por tanto el build frontend no pudo ejecutarse aquí.

Esto se clasifica como `BLOCKED_EXTERNAL_DEPENDENCY` para la **ejecución de pruebas**, no para la implementación del código. El repositorio contiene las pruebas y cambios necesarios; el proyecto debe ejecutar `composer install`, configurar MongoDB y `npm install` en el entorno de integración.

## E. Validaciones ejecutadas en este entorno

- PHP syntax lint sobre `app`, `bootstrap`, `routes`, `database` y `tests`: correcto.
- Revisión estática de rutas, middleware, contratos y persistencia: realizada.
- Build Vite: bloqueado por dependencia de npm no instalada.
- Suite Pest/Laravel: bloqueada por ausencia de Composer/vendor/MongoDB.

## F. Cambios de base de datos

Nueva migración:

`database/migrations/2026_09_21_001000_close_identity_qr_security_contract.php`

Añade índices para:

- `qr_tokens.code_hash`
- `qr_tokens.short_code_hash`
- combinaciones de estado/expiración de QR
- `qr_validations` por usuario/fecha/contexto/correlation
- `devices` por usuario/actividad/revocación
- `sessions` por usuario/dispositivo/revocación
- `security_events` por dispositivo/sesión/correlation

## G. Definition of Done

| Requisito | Implementado | Probado en runtime | Evidencia |
|---|---:|---:|---|
| QR dinámico | Sí | Bloqueado por entorno | `IdentityService`, `QrController` |
| QR expiración | Sí | Bloqueado por entorno | tests existentes |
| QR revocación | Sí | Bloqueado por entorno | `IdentityService` |
| QR single-use | Sí | Bloqueado por entorno | consumo atómico + tests existentes |
| QR purpose | Sí | Bloqueado por entorno | `IdentityClosureTest` |
| identidad | Sí | Bloqueado por entorno | `IdentityService` |
| student status | Sí | Bloqueado por entorno | `StudentStatusService` |
| credential status | Sí | Bloqueado por entorno | `CredentialService` |
| auditoría | Sí | Bloqueado por entorno | `QrValidation`, `SecurityEvent` |
| correlation ID | Sí | Sintaxis validada | middleware + tests |
| OAuth | Ya existía | Bloqueado por entorno | `ValidateServiceToken`, tests existentes |
| scopes | Ya existía | Bloqueado por entorno | API/tests existentes |
| rate limiting | Ya existía | Bloqueado por entorno | rutas existentes |
| dispositivos | Sí | Bloqueado por entorno | `IdentityService`, controller/UI |
| sesiones | Sí | Bloqueado por entorno | `UserSession`, middleware |
| revocación | Sí | Bloqueado por entorno | controller/service |
| trusted device | Ya existía | Bloqueado por entorno | reauth + `setDeviceTrust` |
| reauth | Ya existía | Bloqueado por entorno | Fortify/custom reauth |
| límite sesiones | Ya existía | Bloqueado por entorno | `enforceConcurrentSessionLimit` |
| security events | Sí | Bloqueado por entorno | eventos específicos |
| API | Sí | Bloqueado por entorno | `/api/v1/identity/qr-validate` |
| frontend | Sí | Build bloqueado | Vue QR/Devices |
| integración | Sí | Bloqueado por entorno | contrato documental |
| pruebas | Sí, ampliadas | Suite bloqueada | `IdentityClosureTest` + existentes |
| documentación | Sí | Sí | `docs/integration/TEAM-1-IDENTITY-CONTRACT.md` |
