# Equipo 1 — cierre real de las cuatro prioridades base

## Alcance implementado

Este cambio consolida cuatro áreas que estaban parcialmente simuladas o con controles incompletos:

1. rutas/contratos y persistencia base;
2. módulo 1.8 — condición estudiantil real;
3. módulo 1.9 — consentimientos y preferencias reales;
4. RBAC y ámbitos contextuales.

Además se corrigió un bloqueo real del módulo 1.6: el índice único `qr_tokens.code` entraba en conflicto porque los secretos nuevos ya no se guardaban en texto plano.

## 1. Rutas y persistencia

- Las operaciones web de privacidad del estudiante tienen endpoints propios y vuelven a validar propietario/permiso en backend.
- La API de estudiantes ahora exige `students:read` para lecturas y `students:write` para cambios.
- El índice único de `qr_tokens.code` fue eliminado.
- `qr_tokens.code_hash` es ahora la fuente de unicidad.
- Los tokens históricos se migran a hash; los códigos de identificación recuperables se conservan cifrados, nunca en texto plano.
- Se agregó persistencia Mongo para `student_consents` y `student_preferences`.

## 2. Módulo 1.8

La pantalla ya no usa matrícula, carrera, semestre, campus ni estado hardcodeados.

La información se resuelve desde:

- `StudentProfile`;
- `AcademicProgram`;
- `Campus`;
- `AcademicStatusHistory`.

El historial de estado se devuelve desde los registros reales. Los cambios realizados mediante `UpsertStudentProfile` continúan generando historial y `StudentProfileChanged`.

## 3. Módulo 1.9

Los consentimientos se almacenan en `student_consents` con versión, estado, fechas y actor.

Reglas actuales:

- los consentimientos requeridos empiezan como `pending` si todavía no existe una aceptación persistida;
- aceptar registra versión y fecha;
- revocar registra fecha/actor;
- un consentimiento requerido no se puede revocar;
- las preferencias `email`, `push` y `sms` se persisten en `student_preferences`;
- la interfaz vuelve a consultar el backend después de una operación.

Cada cambio de consentimiento continúa emitiendo `student.consent.changed.v1` para integración con otros equipos.

## 4. RBAC

- La asignación de roles continúa restringida a `admin` mediante middleware + policy.
- Los nombres de rol deben pertenecer al catálogo oficial.
- Los roles contextuales ahora requieren el tipo de ámbito correcto y `scope_id`.
- Los roles globales rechazan ámbitos contextuales.
- La navegación oculta la sección de gestión de estudiantes a usuarios que no son `admin` o `maestro`.
- El backend mantiene la autorización aunque alguien intente acceder manualmente a una URL.

## Verificación local disponible

En el entorno de preparación se ejecutó:

- `php -l` sobre PHP de aplicación, rutas, migraciones y pruebas: correcto.
- `node --check` sobre los bloques `<script setup>` de los Vue modificados: correcto.

No se ejecutó la suite Laravel/Pest ni `vite build` porque el ZIP no contiene `vendor/` ni `node_modules/` y este entorno no tiene MongoDB configurado para el proyecto.

## Comandos para validar en Herd

```powershell
composer install
npm install
php artisan optimize:clear
php artisan migrate
php artisan db:seed
php artisan test
npm run build
```

Para una base local de desarrollo completamente limpia, usar `migrate:fresh --seed` solamente si se acepta borrar las colecciones/datos de desarrollo.
