# Módulo 1.1 — Gestión de cuentas y perfil

## Responsabilidad

Crear y mantener la identidad académica base de cada estudiante. Este módulo es dueño de `users`, `student_profiles`, `campuses`, `academic_programs` y `academic_status_history` hasta que los catálogos institucionales se extraigan a un dominio compartido.

## Casos de uso entregados

1. Consultar el directorio con búsqueda por nombre, matrícula o correo.
2. Filtrar por condición académica y campus.
3. Registrar una cuenta de estudiante sin credenciales activas.
4. Editar datos básicos, académicos, contacto y preferencias operativas.
5. Cargar o reemplazar fotografía.
6. Cambiar condición académica con motivo y conservar historial.
7. Importar altas o actualizaciones por CSV de forma atómica.

## Reglas de negocio

| Regla | Implementación |
|---|---|
| Una matrícula identifica a un estudiante | Índice único en `student_profiles.enrollment_number`. |
| Un correo institucional identifica una cuenta | Índice único en `users.email`. |
| La carrera debe pertenecer al campus | Validación cruzada en `StoreStudentRequest`; FK en ambas columnas. |
| Una cuenta importada no obtiene contraseña | `password = null` y `account_activation_pending = true`; 1.2 completa la activación. |
| El estado académico usa catálogo cerrado | Enum PHP: activo, inactivo, suspendido, baja temporal y egresado. |
| Todo cambio de estado exige motivo | La edición rechaza el cambio sin `status_reason`. |
| El historial académico no se sobrescribe | Cada transición inserta un registro en `academic_status_history`. |
| La fotografía es opcional y limitada | JPG, PNG o WebP, máximo 2 MB; se guarda en disco público. |
| El canal preferido debe tener dato utilizable | Si se elige correo personal o teléfono, ese campo pasa a ser obligatorio. |
| La importación no deja datos parciales | Primero valida todas las filas y luego ejecuta una transacción. |
| Solo administración gestiona perfiles | `StudentProfilePolicy` y Gate `import-students`. |

## Estados y consecuencias

| Estado | Uso previsto | Consecuencia para módulos consumidores |
|---|---|---|
| `active` | Alumno inscrito y vigente | Elegible por defecto, sujeto a reglas del servicio. |
| `inactive` | Cuenta sin vigencia académica | No debe recibir beneficios nuevos. |
| `suspended` | Restricción administrativa activa | Servicios sensibles deben bloquear la operación. |
| `leave` | Baja temporal | Conservar historial y limitar beneficios por vigencia. |
| `graduated` | Egresado | Mantener identidad e historial; elegibilidad depende del servicio. |

Este módulo informa el estado; no decide reglas financieras, comerciales, de biblioteca o becas de otros equipos.

## Modelo de datos

```mermaid
erDiagram
    USERS ||--o| STUDENT_PROFILES : posee
    CAMPUSES ||--o{ ACADEMIC_PROGRAMS : ofrece
    CAMPUSES ||--o{ STUDENT_PROFILES : ubica
    ACADEMIC_PROGRAMS ||--o{ STUDENT_PROFILES : inscribe
    STUDENT_PROFILES ||--o{ ACADEMIC_STATUS_HISTORY : registra
    USERS ||--o{ ACADEMIC_STATUS_HISTORY : cambia

    USERS {
      bigint id PK
      string name
      string email UK
      string password nullable
      boolean is_platform_admin
      boolean account_activation_pending
    }
    STUDENT_PROFILES {
      bigint id PK
      bigint user_id UK,FK
      string enrollment_number UK
      bigint campus_id FK
      bigint academic_program_id FK
      tinyint current_semester
      string academic_status
      string photo_path nullable
    }
    ACADEMIC_STATUS_HISTORY {
      bigint id PK
      bigint student_profile_id FK
      string from_status nullable
      string to_status
      string reason nullable
      bigint changed_by FK
      datetime changed_at
    }
```

## Dependencias

| Dependencia | Estado en este proyecto | Condición de integración |
|---|---|---|
| 1.2 Fortify | Pendiente; se incluye pantalla puente local | Reemplazar `/login` y eliminar `DemoSessionController`; conservar `users`. |
| 1.3 Roles y permisos | Compuerta temporal `is_platform_admin` | Migrar Policies/Gates a RBAC contextual sin cambiar controladores. |
| 1.8 Condición estudiantil | Historial base ya disponible | El servicio de elegibilidad deberá leer historial, restricciones y vigencias. |
| 1.9 Consentimientos | No implementado | Mover preferencias legales y de comunicación al dominio correspondiente. |
| 1.10 IdentityService | Evento inicial publicado | Exponer consultas internas sin acceso directo de otros equipos a tablas. |

## Colaboraciones con otros equipos

- **Equipo 2 — Wallet:** recibe `studentId` y condición, pero no puede editar `users` o `student_profiles`.
- **Equipos 3 y 4 — Negocios/inventario:** usan la identidad resuelta por Equipo 1; los roles de negocio llegarán desde 1.3.
- **Equipo 5 — Servicios:** consulta estudiante, estado y, después, credenciales NFC/QR. No replica matrícula.
- **Equipo 6 — Consejo y asociaciones:** usa campus, carrera, semestre y grupo para segmentación; consentimientos se verifican en 1.9.
- **Equipo 7 — Auditoría/analítica:** escucha `StudentProfileChanged` y registra correlación; no modifica el perfil.

Contrato actual del evento:

```php
new StudentProfileChanged(
    studentId: int,
    operation: 'created'|'updated',
    changedFields: string[],
    actorId: ?int,
)
```

No se envían correos, teléfonos ni fotografía en el evento. Los consumidores con permiso deben pedir los datos al futuro `IdentityService`.

## Seguridad

- Validación y autorización se ejecutan en servidor.
- No se asignan contraseñas predecibles a cuentas importadas.
- Los uploads restringen tipo, MIME y tamaño.
- Se utiliza mass assignment con listas explícitas.
- El modo demo requiere simultáneamente ambiente `local` y `APP_DEMO_MODE=true`.
- Las respuestas Inertia no exponen `password`, `remember_token` ni campos administrativos internos.

## Criterios de aceptación

- [x] Matrícula y correo institucional no pueden duplicarse.
- [x] Carrera inválida para un campus es rechazada.
- [x] Alta individual y CSV producen el mismo modelo de datos.
- [x] Cambio de estado deja registro con actor, fecha y motivo.
- [x] Usuario no administrador recibe HTTP 403.
- [x] Vue muestra estados de carga, error, vacío y éxito.
- [x] Interfaz funciona en escritorio, tableta y móvil.
- [x] Paleta y logo coinciden con la guía visual entregada.
- [x] Pruebas automatizadas cubren las reglas críticas.

## Definition of Done pendiente de integración

Antes de fusionar con el producto completo deben ejecutarse las pruebas contra una instancia real de SQL Server, conectar Fortify 1.2, sustituir la compuerta temporal por 1.3, y registrar `StudentProfileChanged` en el contrato formal de auditoría del Equipo 7.

