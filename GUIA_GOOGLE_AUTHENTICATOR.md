# Guía de instalación y uso — Google Authenticator en Campus Virtual

## 1. Qué se implementa

Campus Virtual utiliza **TOTP (Time-based One-Time Password)** mediante Laravel Fortify. Google Authenticator es solamente una app cliente compatible con TOTP: no se requiere una API, cuenta de servicio ni SDK de Google.

El flujo queda:

```text
correo + contraseña
        ↓
credenciales correctas
        ↓
¿usuario tiene 2FA confirmado?
   ├─ no → sesión normal
   └─ sí → /two-factor-challenge
                    ↓
             código TOTP 6 dígitos
                    ↓
                 dashboard
```

El proyecto ya tenía Fortify 2FA habilitado; este paquete añade la interfaz funcional para administrarlo y una pantalla completa de desafío.

## 2. Aplicar el paquete al repositorio existente

Si ya tienes el repositorio clonado, descomprime este ZIP y ejecuta desde PowerShell:

```powershell
cd campus-virtual-google-authenticator-package
Set-ExecutionPolicy -Scope Process Bypass
.\aplicar-a-repositorio.ps1 -ProjectPath "C:\ruta\a\campus-virtual"
```

El script crea copias `.bak-gauth` de los archivos que sustituye y copia los nuevos componentes.

Después entra al proyecto:

```powershell
cd C:\ruta\a\campus-virtual
composer install
npm install
php artisan optimize:clear
php artisan migrate
npm run build
php artisan test
```

## 3. Crear un clon limpio y aplicar la implementación

Si quieres partir de cero:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\clonar-y-aplicar.ps1 -Destination "C:\Users\TU_USUARIO\Downloads\campus-virtual-gauth"
```

El script realiza:

```text
git clone ...
git switch main
git switch -c feature/google-authenticator
aplica overlay
composer install
npm install
php artisan optimize:clear
php artisan migrate
npm run build
```

> MongoDB debe estar disponible antes de `migrate`/tests según la configuración del proyecto.

## 4. Requisitos

Según el repositorio actual:

- PHP 8.3 o superior.
- Composer.
- Node.js y npm.
- Git.
- MongoDB 7 (el README propone Podman para desarrollo local).
- Extensión PHP `mongodb`.

Verifica:

```powershell
php -v
composer --version
node -v
npm -v
php -m | Select-String mongodb
```

## 5. Configurar `.env`

Si el proyecto es nuevo:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Configura MongoDB según tu instalación.

**Importante:** después de que existan usuarios con 2FA, no cambies `APP_KEY` sin una estrategia de rotación. Fortify cifra el secreto TOTP y los recovery codes con el cifrado de Laravel; un cambio de clave puede impedir descifrarlos.

## 6. Verificar Fortify

Ejecuta:

```powershell
php artisan route:list | Select-String -Pattern "two-factor"
```

Debes encontrar rutas equivalentes a:

```text
GET|POST  two-factor-challenge
POST      user/two-factor-authentication
DELETE    user/two-factor-authentication
GET       user/two-factor-qr-code
GET       user/two-factor-secret-key
POST      user/confirmed-two-factor-authentication
GET|POST  user/two-factor-recovery-codes
```

También puedes ejecutar:

```powershell
php artisan test --filter=GoogleAuthenticatorTwoFactorTest
```

## 7. Activar Google Authenticator como usuario

1. Inicia Campus Virtual:

```powershell
php artisan serve --host=127.0.0.1 --port=8002
npm run dev
```

2. Inicia sesión.
3. Abre **Perfil**.
4. Busca **Autenticación de dos factores (Google Authenticator)**.
5. Pulsa **Activar 2FA**.
6. Confirma tu contraseña.
7. El sistema mostrará un QR y una clave manual.
8. En el teléfono instala/abre **Google Authenticator**.
9. En Google Authenticator pulsa **+** → **Escanear código QR**.
10. Escanea el QR mostrado por Campus Virtual.
11. Google Authenticator mostrará una cuenta de Campus Virtual y un código de 6 dígitos.
12. Introduce ese código en Campus Virtual antes de que cambie.
13. Pulsa **Confirmar y activar**.
14. Guarda los **códigos de recuperación** fuera del repositorio.

### iPhone

No escanees este QR con la app Cámara esperando abrir una página web. El QR TOTP usa `otpauth://` y debe leerse desde Google Authenticator (o una app autenticadora compatible).

## 8. Probar el inicio de sesión con 2FA

1. Cierra sesión.
2. Inicia sesión con correo y contraseña.
3. Debes llegar a **Verificación en dos pasos**.
4. Abre Google Authenticator.
5. Introduce el código actual de 6 dígitos.
6. Al validarse, ingresarás a la sesión autenticada.

Si no tienes el teléfono, pulsa **Usar código de recuperación** e introduce uno de los códigos guardados.

## 9. Regenerar códigos de recuperación

En Perfil → 2FA:

1. Pulsa **Regenerar códigos**.
2. Confirma tu contraseña.
3. Guarda los nuevos códigos.
4. Los códigos anteriores dejan de ser válidos.

## 10. Desactivar 2FA

En Perfil → 2FA:

1. Pulsa **Desactivar 2FA**.
2. Confirma tu contraseña.
3. Fortify elimina el secreto, recovery codes y fecha de confirmación de la cuenta.

## 11. Problemas frecuentes

### El código de Google Authenticator siempre aparece como inválido

Comprueba primero la hora:

- teléfono con fecha/hora automáticas;
- servidor/PC con hora correcta;
- zona horaria correcta.

TOTP depende del tiempo. Una diferencia significativa de reloj invalida códigos.

### Se genera el QR pero el login nunca pide segundo factor

Comprueba que el login pase por la lógica Fortify/2FA del repositorio y que la cuenta tenga `two_factor_confirmed_at` informado. El repositorio actual ya incluye rutas y controlador de desafío 2FA; si tu rama es antigua, actualiza desde `main`/`develop` antes de aplicar este overlay.

### 419 Page Expired

Revisa:

```powershell
php artisan optimize:clear
```

Asegúrate de acceder siempre por el mismo host configurado en `APP_URL` y de que el navegador acepte cookies de sesión.

### 403/423 al activar o desactivar 2FA

La operación requiere confirmación de contraseña. El componente incluido solicita la contraseña y llama primero a `/confirm-password`.

### Perdí mi teléfono y también mis códigos

Un administrador deberá aplicar el procedimiento institucional de recuperación de cuenta. No implementes un “bypass” general de 2FA: cualquier recuperación administrativa debe estar autorizada y auditada.

## 12. Producción

Antes de producción:

- usa HTTPS;
- protege `APP_KEY` y variables sensibles;
- no registres secretos TOTP ni códigos de recuperación;
- habilita rate limiting en login y challenge 2FA;
- sincroniza hora del servidor mediante NTP;
- documenta el proceso de recuperación de cuenta;
- para roles privilegiados, considera hacer 2FA obligatorio mediante una Policy/middleware del módulo 1.3.

## 13. Diferencia entre Google Authenticator y OAuth de los equipos

No mezcles ambos flujos:

```text
Persona                         Módulo/servicio
───────                         ───────────────
Fortify                         OAuth 2.0 client_credentials
correo + contraseña             client_id + client_secret
Google Authenticator (TOTP)     JWT + scopes
sesión web                      API interna
```

Google Authenticator protege usuarios humanos. Los Equipos 2–7 deben seguir consumiendo el servicio 1.10 mediante OAuth/scopes, no mediante códigos TOTP humanos.
