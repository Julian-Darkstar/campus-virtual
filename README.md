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
- Validación de la condición estudiantil.
- Consentimientos y preferencias de comunicación.
- Servicios internos de identidad y credenciales para los demás equipos.

## Tecnologías

- **Backend:** Laravel 13 y PHP 8.3 o superior.
- **Frontend:** Vue 3, Inertia.js y Vite.
- **Autenticación:** Laravel Fortify y Breeze.
- **Estilos:** Tailwind CSS.
- **Pruebas:** Pest.
- **Persistencia:** pendiente de definición entre PostgreSQL, SQL Server o MongoDB.

La base de datos definitiva aún no está configurada. El proyecto conserva una configuración local provisional para permitir el desarrollo del scaffolding y la autenticación.

## Requisitos

- PHP 8.3 o superior.
- Composer.
- Node.js y npm.
- Git.

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

Crea el archivo de entorno y genera la clave de la aplicación:

```bash
cp .env.example .env
php artisan key:generate
```

Cuando se defina el motor de base de datos, actualiza las variables `DB_*` del archivo `.env` y ejecuta las migraciones correspondientes.

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

## Estado del proyecto

- [x] Estructura inicial Laravel.
- [x] Vue 3 + Inertia.js + Vite.
- [x] Autenticación base con Fortify y Breeze.
- [x] Soporte de 2FA preparado.
- [x] Migraciones iniciales de usuarios y 2FA.
- [ ] Modelo de datos definitivo.
- [ ] Perfil académico completo del estudiante.
- [ ] Gestión de UID NFC.
- [ ] Identidad QR dinámica.
- [ ] Roles y permisos contextuales.
- [ ] Servicios y contratos de integración con los demás equipos.

## Repositorio

<https://github.com/Julian-Darkstar/campus-virtual>

## Licencia

La licencia del proyecto se definirá por el equipo antes de la primera versión pública estable.
