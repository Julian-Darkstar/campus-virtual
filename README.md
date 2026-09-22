# Campus Virtual

Plataforma digital universitaria orientada al estudiante. El proyecto integra identidad digital, credenciales NFC/QR, perfiles académicos, autenticación segura y servicios compartidos para los demás módulos del ecosistema Campus Digital.

Este repositorio contiene la base de trabajo del **Equipo 1: Identidad, Acceso, NFC/QR y Perfil del Estudiante**.

## Alcance del Equipo 1

El equipo es responsable de construir la identidad común que consumen los demás módulos. Ningún dominio externo debe duplicar la lógica de autenticación ni la lectura de credenciales.

El alcance inicial contempla:

- Gestión de cuentas y perfil del estudiante.
- Autenticación, recuperación de contraseña y confirmación de cuenta.
- Autenticación de dos factores (2FA) desde la primera versión.
- Roles y permisos contextuales por negocio, asociación, Consejo o servicio.
- Registro y ciclo de vida de tarjetas NFC mediante UID simulado.
- Identidad QR y códigos temporales para validaciones.
- Dispositivos, sesiones confiables y alertas de acceso.
- Gestión y vinculación de dispositivos del usuario.
- Generación, escaneo y validación de códigos QR dinámicos para autenticación y asistencia.
- Validación de la condición estudiantil.
- Consentimientos y preferencias de comunicación.
- Servicios internos de identidad y credenciales para los demás equipos.

## Tecnologías

- **Backend:** Laravel 13 y PHP 8.3 o superior.
- **Frontend:** Vue 3, Inertia.js y Vite.
- **Autenticación:** Laravel Fortify y Breeze.
- **Estilos:** Tailwind CSS.
- **Pruebas:** Pest.
- **Persistencia:** MongoDB 7 para desarrollo local mediante Podman.
- **QR en frontend:** `qrcode` para renderizado. No hay escaneo con cámara (ver nota en "Módulos QR y dispositivos"); la validación de códigos es manual/por sistema externo.

La aplicación usa el paquete `mongodb/laravel-mongodb` y el modelo de usuario compatible con MongoDB. Laravel Fortify continúa siendo responsable de la autenticación; los módulos 1.8 y 1.9 solo consumen la identidad autenticada.

## Requisitos

- PHP 8.3 o superior.
- Composer.
- Node.js y npm.
- Git.
- Podman 5 o superior.
- Extensión PHP `mongodb`.

## Instalación

Clona el repositorio y entra en la carpeta del proyecto:

```bash
git clone https://github.com/Julian-Darkstar/campus-virtual.git
cd campus-virtual
```

Instala las dependencias de backend y frontend:

```bash
composer install
npm install
```

La dependencia del módulo QR se incluye en `package.json`: `qrcode` genera los códigos en el navegador. El backend
reutiliza `mongodb/laravel-mongodb`; no es necesario añadir otro paquete de Composer para generar imágenes porque
el QR se renderiza en Vue. No se usa lectura por cámara (se quitó `@zxing/library`): la validación de un código
QR la hace el sistema externo que lo escanea (lector de biblioteca, caja, torniquete, etc.) llamando al contrato
`/api/v1/identity/qr-validate`, no el navegador del estudiante.

Crea el archivo de entorno y genera la clave de la aplicación:

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Si el proyecto ya estaba instalado, actualiza ambas dependencias antes de migrar:

```bash
composer install
npm install
php artisan migrate
```

Configura MongoDB en el archivo `.env`:

```env
DB_CONNECTION=mongodb
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=campus_virtual
DB_USERNAME=
DB_PASSWORD=
```

## MongoDB local con Podman

Descarga y ejecuta MongoDB 7 con un volumen persistente:

```bash
podman pull docker.io/library/mongo:7
podman run -d --name campus-mongo \
  -p 27017:27017 \
  -v mongo_data:/data/db \
  docker.io/library/mongo:7
podman update --restart=unless-stopped campus-mongo
```

Si el contenedor ya existe, solo inícialo:

```bash
podman start campus-mongo
```

Comprueba MongoDB y Laravel:

```bash
podman exec campus-mongo mongosh --quiet --eval "db.runCommand({ ping: 1 })"
php artisan config:clear
php artisan tinker --execute="DB::connection('mongodb')->command(['ping' => 1]); echo 'MONGO_OK';"
```

La instalación local de desarrollo no habilita autenticación en MongoDB. Para DataGrip utiliza `localhost`, puerto `27017`, autenticación `No authentication` y la base `campus_virtual`. En MongoDB, las tablas se representan como colecciones; la aplicación crea `users` y `sessions` cuando existen documentos.

## Google Authenticator y autenticación 2FA

La autenticación de dos factores utiliza TOTP estándar mediante Laravel Fortify y es compatible con Google Authenticator. No requiere una API, cuenta de servicio ni SDK de Google.

Desde **Perfil**, el usuario puede:

- Activar 2FA confirmando su contraseña.
- Escanear el código QR `otpauth://` desde Google Authenticator o introducir la clave manualmente.
- Confirmar el código TOTP de seis dígitos.
- Consultar o regenerar códigos de recuperación.
- Desactivar 2FA con confirmación de contraseña.

Cuando 2FA está confirmado, el inicio de sesión solicita el código temporal en `/two-factor-challenge` y también permite utilizar un código de recuperación. El secreto TOTP y los códigos de recuperación no se exponen como props de Inertia ni se registran en logs.

Para verificar esta funcionalidad:

```bash
php artisan route:list | Select-String two-factor
php artisan test --filter=GoogleAuthenticatorTwoFactorTest
```

El QR de Google Authenticator es distinto de los QR de identidad del módulo 1.6: debe escanearse desde la aplicación autenticadora, no desde la cámara normal del teléfono.

La instalación verificada utiliza PHP 8.4, Composer 2.10, Node.js 22, npm 10 y la extensión PHP `mongodb`. Las dependencias se instalan con `composer install` y `npm install`; después de crear `.env` y generar la clave, el frontend se valida con `npm run build`. Las migraciones y pruebas requieren que Podman y el contenedor `campus-mongo` estén activos.

## Ramas de desarrollo

Este proyecto utiliza un flujo de Git con dos ramas principales:

### `main`

- **Rama estable** que contiene versiones listas para producción.
- Cambios solo a través de pull requests revisados.
- Cada commit en `main` representa una versión funcional y documentada.

### `develop`

- **Rama de integración continua** donde se agrupan las features en construcción.
- Contiene trabajo en progreso, datos simulados y features pendientes de finalizar.
- Punto de referencia para ver el estado actual del desarrollo.
- Cambios se agrupan en commits temáticos antes de proponer PR a `main`.

### Flujo de trabajo

```
main (stable) ← ← ← ← develop (active development)
                    ↑
                  feature branches
```

1. Crea una rama de feature desde `develop`: `git checkout -b feature/nombre-feature`
2. Realiza cambios y commits
3. Cuando esté lista, integra a `develop` mediante PR
4. Cuando una versión esté completa, crea PR de `develop` a `main`

## Desarrollo local

Para iniciar la aplicación en el puerto `8002`:

```bash
php artisan serve --host=127.0.0.1 --port=8002
```

La aplicación estará disponible en <http://127.0.0.1:8002>.

En otra terminal, ejecuta Vite para recompilar los recursos durante el desarrollo:

```bash
npm run dev
```

## Módulos QR y dispositivos

Con una sesión web autenticada están disponibles:

- `GET /identidad/qr`: pantalla con el QR fijo de identificación y el QR dinámico (rota cada `QR_IDENTITY_TTL_SECONDS`).
- `POST /identidad/qr/generar`: genera/rota el token QR dinámico del usuario autenticado.
- `GET /identidad/qr/historial`: historial paginado de validaciones del usuario.
- `POST /identidad/qr/simular-validacion`: simulador en pantalla del contrato de validación. **Solo visible/usable
  para roles validadores** (`Role::VALIDATOR_ROLES`: bibliotecario, agente_soporte, cajero_negocio,
  agente_recarga_retiro, admin) — un estudiante normal solo genera/consulta su propio QR, no valida el de otros.
  Exige un `context_id` vigente (ver "Contextos de validación" abajo); limitado a `throttle:30,1`.
- `GET /identidad/qr/contextos`, `POST /identidad/qr/contextos`, `POST /identidad/qr/contextos/{id}/cancelar`:
  gestión de contextos de validación (ver abajo). Solo para roles validadores.
- `GET /seguridad/dispositivos`: vincula, consulta y desvincula dispositivos del usuario.

Para integración servidor-a-servidor (otros equipos/dominios), no para el navegador del estudiante:

- `POST /api/v1/identity/qr-validate`: valida un código QR o corto, registra el resultado en auditoría y consume
  los tokens dinámicos. Protegido con el middleware `oauth.service:identity.qr.validate` + `throttle:60,1` (OAuth
  2.0 `client_credentials` propio del módulo 1.10 — **no** usa Laravel Sanctum, aunque el paquete esté en
  `composer.json` como dependencia estándar de Breeze). Pedir `level=full` en el body además requiere que el
  token de servicio tenga el scope `identity.qr.validate.full`.

No existe generación de QR vía API para otros servicios: el QR siempre lo genera el propio estudiante desde su
sesión web; otros dominios únicamente lo *validan*.

Las migraciones crean las colecciones MongoDB `devices`, `qr_tokens`, `qr_validations` y `qr_validation_contexts`,
con índices para usuario, código corto, expiración y autor. El escaneo es manual/por sistema externo (lector de
biblioteca, caja, etc.); esta app web no abre la cámara del navegador para leer códigos.

### Contextos de validación

El `context` de una validación (ej. "Evento de Bienvenida") dejó de ser una lista fija de texto sin dueño ni
vigencia. Ahora es un recurso real (`QrValidationContext`) que crea el propio validador — típicamente quien
organiza el evento/turno — con nombre y **fecha/hora de cierre obligatoria**. Reglas:

- Cualquier rol validador puede **crear** su contexto y **usar** un contexto activo creado por un colega (dos
  bibliotecarios del mismo turno comparten "Turno tarde biblioteca").
- Solo quien lo creó, o un `admin`, puede **cancelarlo** antes de tiempo (error de dedo al crearlo, evento
  cancelado, etc.) — ver `QrValidationContextPolicy`.
- Un contexto vencido (`ends_at` ya pasó) o cancelado deja de aparecer en el listado y `simular-validacion` lo
  rechaza explícitamente (`422`, no un error genérico) si de todos modos se manda su id.
- Esto es exclusivo de la ruta web (validadores humanos); el contrato de servicio-a-servicio
  (`/api/v1/identity/qr-validate`) sigue aceptando `context` como texto libre, ya que ahí quien valida es un
  servicio, no una persona que "organiza" nada.

### Quién puede validar el QR de otra persona

La identidad de "quién validó" (`validator_label` en `qr_validations`) ya no es texto libre que manda quien llama
al endpoint: en la ruta web se calcula del lado del servidor a partir del usuario autenticado y su rol real
(`User::validatorLabel()`); en la API se calcula a partir del `client_id` del servicio ya verificado por el token
OAuth (`IdentityService::describeServiceValidator()`). El campo `note`/`nota` que sigue existiendo es solo un
detalle físico opcional (ej. "Terminal 3"), nunca una identidad.

`DemoAccountsSeeder` crea un cliente OAuth de prueba (`client_id=svc_demo_biblioteca`,
`client_secret=demo-secret-biblioteca`) con los scopes `identity.qr.validate` y `identity.qr.validate.full` para
poder probar el contrato real sin correr `php artisan oauth:client` a mano, además de un contexto de validación
de ejemplo ya vigente para `martha.jimenez@servicios.com` (bibliotecaria demo).

Para generar los recursos frontend de producción:

```bash
npm run build
```

## Pruebas

La suite de pruebas se ejecuta con:

```bash
php artisan test
```

También puede utilizarse el script de Composer:

```bash
composer test
```

Las pruebas usan la base MongoDB `campus_virtual_testing` y requieren el contenedor `campus-mongo` activo. El harness limpia esa base antes de cada prueba.

## OAuth 2.0 entre servicios

Los microservicios consumen la API interna mediante el grant estándar `client_credentials`. Esto es independiente del login web de Fortify.

Genera un cliente una sola vez y guarda el secreto fuera del repositorio:

```bash
php artisan oauth:client equipo-servicios --scope=students:read
```

Solicita un token:

```bash
curl -X POST http://127.0.0.1:8002/api/oauth/token \
  -d grant_type=client_credentials \
  -d client_id=svc_xxx \
  -d client_secret=xxx \
  -d scope=students:read
```

Usa el token como `Authorization: Bearer <access_token>` para las rutas `/api/v1`. Los tokens son JWT firmados, tienen issuer/audience, expiración y scopes. En producción define `OAUTH2_SIGNING_KEY` independiente de `APP_KEY` y rota los clientes periódicamente.

Los cambios de dominio implementan un contrato de eventos versionado (`*.v1`) y se guardan en la colección MongoDB `event_outbox` para que un publicador externo pueda entregarlos a otros servicios sin acoplarlos a las colecciones internas.

El publicador incluido se ejecuta con `php artisan events:publish`. Configura `EVENTS_SINK_URL` y, si el receptor lo requiere, `EVENTS_SINK_TOKEN`. Los eventos publicados reciben `published_at`; los fallidos conservan `attempts` y `last_error` para reintentos. En producción se recomienda ejecutarlo mediante scheduler o worker.

## Cambios Recientes

### Fusión de ramas (integración de mejoras_1.6_1.7 + cerrar-nfc-qr + google-authenticator)

Se consolidaron en `main` las tres ramas que venían divergiendo desde la entrega del módulo 1:

- **De `mejoras_1.6_1.7`:** `NfcCardPolicy` y `RolePolicy` (autorización real por rol), comando `security-events:prune` (retención de bitácora), índice en `qr_tokens.short_code`, endurecimiento de rutas (`role.context:admin` en alta/edición de NFC y en `roles.assign`), limpieza de rutas duplicadas/legacy, tests `NfcCardAuthorizationTest` y `RoleAssignmentTest`.
- **De `cerrar-nfc-qr`:** reemplazo de tarjeta NFC (`NfcCardController::replace`, ruta `nfc-cards.replace`, campos `replacement_of_card_id`/`replaced_by_card_id`), contrato real `POST /api/v1/identity/qr-validate` (`QrController::validateQr`).
- **De `google-authenticator`:** panel 2FA completo en Perfil (`TwoFactorAuthenticationForm.vue`), pantalla de desafío 2FA, corrección del conector por defecto en `config/database.php` (era `sqlite`, ahora `mongodb`), tests `GoogleAuthenticatorTwoFactorTest`.

Conflictos resueltos manualmente durante la fusión:
- `NfcCardController.php` y `QrController.php`: se combinaron ambas funcionalidades (autorización de `mejoras` + reemplazo/validación de `cerrar-nfc-qr`); se agregó `$this->authorize('updateStatus', ...)` al método `replace()`, que no lo traía porque esa rama se escribió antes de que existiera `NfcCardPolicy`.
- `routes/web.php`: se tomó como base la versión endurecida de `mejoras` (sin rutas legacy duplicadas) y se le sumó la ruta de reemplazo de `cerrar-nfc-qr`, protegida con el mismo middleware `role.context:admin`.
- `app/Models/User.php`: el auto-merge de git había dejado **dos** definiciones del método `displayIdentity()` (una de cada rama), lo cual habría sido un error fatal de PHP por redeclaración. Se eliminó la versión simple de `cerrar-nfc-qr` y se conservó la versión de `mejoras` (con enmascarado de nombre para QR).

Pendiente tras esta fusión (no resuelto aquí, ver sección "Estado del proyecto"): módulos 1.8 y 1.9 siguen sin persistencia real.

### Versión 0.2.0-dev (7 de septiembre de 2026)

#### Entrega actual del módulo 1
- **Módulo 1.1:** gestión académica en MongoDB con perfiles, catálogos, historial, CRUD e importación CSV.
- **Módulo 1.2:** autenticación de dos factores integrada con Fortify.
- **Módulo 1.3:** RBAC contextual con roles y scopes.
- **Módulos 1.4 y 1.5:** registro y ciclo de vida de credenciales NFC.
- **Módulos 1.6 y 1.7:** identidad QR, dispositivos, sesiones confiables y reautenticación.
- **Módulos 1.8 y 1.9:** interfaz y contrato API disponibles; la persistencia de condición, consentimientos y preferencias continúa pendiente.
- **Integración entre servicios:** OAuth 2.0 `client_credentials`, JWT, scopes y middleware Bearer.
- **Eventos de dominio:** eventos versionados, outbox MongoDB idempotente y comando `events:publish` con reintentos.
- **Calidad:** 29 pruebas correctas, 71 aserciones y build frontend exitoso (antes de esta fusión; falta re-ejecutar suite completa).

#### Documentación
- La documentación formal del módulo 1 se encuentra en `/home/darkstar/IS/documentacion/terminada/modulo-1`.
- Incluye SRS IEEE 830, plan de desarrollo, arquitectura/API, plan de calidad, seguimiento y cierre.

### Versión 0.1.0-dev (28 de agosto de 2026)

#### Características Implementadas
- **Módulo 1.8**: Validación de condición estudiantil con estado, matrícula, programa y campus.
- **Módulo 1.9**: Gestión de consentimientos y preferencias de comunicación (email, push, SMS).
- **API REST v1**: Endpoints documentados bajo `/api/v1` con datos simulados:
  - Estado académico: GET `/students/{studentId}/status`
  - Historial: GET `/students/{studentId}/status/history`
  - Consentimientos: GET/POST/DELETE `/students/{studentId}/consents`
  - Preferencias: GET/PATCH `/students/{studentId}/preferences`
- **Identidad Visual**: Logo SVG, paleta de colores institucionales, rediseño de interfaz pública y autenticada.
- **Tipografía**: Fuente Manrope como identidad visual del proyecto.

#### Interfaz de Usuario
- Panel protegido en `/student-services` con módulos 1.8 y 1.9.
- Dashboard actualizado con acceso directo a nuevos módulos.
- Diseño responsivo con colores institucionales: azul marino (#00338D), gris pizarra (#64748B), verde validación (#10B981).

## Estado del proyecto

- [x] Estructura inicial Laravel.
- [x] Vue 3 + Inertia.js + Vite.
- [x] Autenticación base con Fortify y Breeze.
- [x] Módulo 1.1: perfiles académicos, catálogos, historial, CRUD e importación CSV adaptados a MongoDB.
- [x] Módulo 1.2: 2FA con Fortify.
- [x] Módulo 1.3: RBAC contextual.
- [x] Módulos 1.4 y 1.5: registro y ciclo de vida de credenciales NFC.
- [x] Módulos 1.6 y 1.7: identidad QR, dispositivos y sesiones confiables.
- [x] Migraciones iniciales de usuarios y 2FA.
- [x] Módulo 1.8: Validación de condición estudiantil (API + UI).
- [x] Módulo 1.9: Consentimientos y preferencias de comunicación (API + UI).
- [x] Identidad visual: Logo, colores institucionales, rediseño de pantallas.
- [x] Endpoints API REST v1 documentados y funcionales.
- [x] Autorización real por policy en NFC (`NfcCardPolicy`) y roles (`RolePolicy`).
- [x] Catálogo de 18 roles alineado a la Matriz Operativa de Roles, Permisos y Restricciones.
- [x] Autorización real por policy en validación de QR (`QrValidationPolicy`) + scope OAuth dedicado en la API.
- [x] Contextos de validación con vigencia y cancelación (`QrValidationContext` / `QrValidationContextPolicy`).
- [x] Rate limiting en `/api/v1/identity/qr-validate` (antes solo dependía del scope OAuth, sin límite de tasa propio).
- [x] Pruebas mecánicas de QR: expiración, consumo, revocación, firma inválida, código corto (antes 0 pruebas del núcleo técnico).
- [x] Reemplazo de tarjeta NFC (ciclo de vida de credenciales).
- [x] 2FA con Google Authenticator (TOTP) end-to-end.
- [x] Retención automática de bitácora de seguridad (`security-events:prune`).
- [ ] Pruebas automatizadas completas contra MongoDB para todos los módulos.
- [ ] Integración de autenticación inter-servicios OAuth 2.0.
- [ ] Servicios y contratos de integración con los demás equipos.
- [ ] Publicación de eventos para cambios de estado/consentimientos.
- [ ] Persistencia real de los módulos 1.8 y 1.9 (hoy devuelven datos simulados).

### Notas de integración

- El módulo 1.1 se portó desde la rama SQL Server a documentos MongoDB (`campuses`, `academic_programs`, `student_profiles` y `academic_status_history`).
- La interfaz administrativa está disponible en `/students`; requiere un usuario con rol `admin` o `student_manager`.
- Los catálogos e índices de 1.1 se inicializan con `php artisan db:seed --class=StudentCatalogSeeder`.
- La importación CSV valida todas las filas antes de escribir. El contenedor local MongoDB usa el replica set `rs0`, habilitando transacciones multi-documento para atomicidad estricta.
- Los módulos 1.8 y 1.9 aún usan datos simulados y deben conectarse a `StudentProfile` y sus eventos cuando se cierre el contrato de dominio.

## Repositorio

<https://github.com/Julian-Darkstar/campus-virtual>

## Licencia

La licencia del proyecto se definirá por el equipo antes de la primera versión pública estable.
