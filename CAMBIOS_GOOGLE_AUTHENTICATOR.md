# Cambios realizados — Google Authenticator / TOTP

Este paquete está preparado para la rama `main` del repositorio público `Julian-Darkstar/campus-virtual`, revisada el 16-sep-2026.

El repositorio **ya contiene el núcleo de Fortify 2FA**: el trait `TwoFactorAuthenticatable`, la migración de `two_factor_*` y la feature `Features::twoFactorAuthentication(...)`. Por esa razón, no se agrega una librería de Google ni se modifica la base criptográfica. Google Authenticator consume TOTP estándar.

Se añaden/completan estas piezas:

1. **Panel 2FA dentro de Perfil** (`TwoFactorAuthenticationForm.vue`).
2. Activación con confirmación de contraseña.
3. Obtención del QR TOTP generado por Fortify.
4. Clave manual de respaldo para registrar el autenticador.
5. Confirmación con código TOTP de 6 dígitos.
6. Consulta y regeneración de códigos de recuperación.
7. Desactivación segura de 2FA.
8. Pantalla de desafío 2FA para el inicio de sesión.
9. Prop `twoFactorEnabled` sin exponer secreto ni recovery codes.
10. Pruebas de configuración/rutas de Fortify.

## Seguridad

- El `two_factor_secret` y los códigos de recuperación **no se envían como props Inertia**.
- Las operaciones de activar/desactivar/regenerar requieren confirmar la contraseña.
- No se registra el secreto TOTP en logs.
- No se agrega ninguna credencial al repositorio.
- No se utiliza API externa de Google.

## Compatibilidad

El QR de esta función contiene un URI `otpauth://`. Debe escanearse **desde Google Authenticator**, no desde la cámara normal de iOS. Esto es distinto de los QR de identidad del módulo 1.6.
