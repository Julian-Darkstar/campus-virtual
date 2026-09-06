# Campus Digital — Módulo 1.1

Implementación de **Gestión de cuentas y perfil de estudiantes** para Campus Digital. El proyecto usa Laravel 12, Vue 3 con Composition API y TypeScript, Inertia 2, Vite y SQL Server. La interfaz aplica la paleta y el logo entregados por el equipo.

## Estado del módulo

El módulo está preparado para ejecutarse en ambiente local con SQL Server. La base se inicializa mediante migraciones y seeders, y la suite automatizada usa SQLite en memoria. La autenticación definitiva y el flujo de activación de cuentas pertenecen al módulo 1.2.

## Qué incluye

- Directorio administrativo de estudiantes con búsqueda, filtros y paginación.
- Alta y edición de cuenta, matrícula, campus, carrera, semestre, grupo y estado académico.
- Datos de contacto, idioma y canal preferido.
- Carga y reemplazo de fotografía con validación de tipo y tamaño.
- Historial inmutable de cambios de condición académica.
- Importación CSV transaccional: si una fila falla, no se aplica ninguna.
- Catálogos de campus y carreras con datos de demostración.
- Policies y validación de backend; las reglas no dependen de Vue.
- Evento `StudentProfileChanged` para integrar auditoría y analítica sin acceso directo a tablas.
- Pruebas de creación, autorización, integridad campus/carrera e importación.
- Pantalla puente de acceso local. La autenticación definitiva pertenece al módulo 1.2.

## Stack técnico

- Backend: PHP 8.2+, Laravel 12, Eloquent, Form Requests, Policies y eventos de dominio.
- Frontend: Vue 3, TypeScript, Inertia 2, Vite y Lucide Vue.
- Persistencia: SQL Server para ejecución funcional; SQLite en memoria para pruebas.
- Archivos: almacenamiento local de Laravel para fotografías.

## Requisitos

- PHP 8.2 o superior con `pdo_sqlsrv`, `sqlsrv`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `gd` e `intl`.
- Composer 2.
- Node.js 20.19+ o 22.12+ y npm.
- SQL Server 2019 o superior; SQL Server 2022 recomendado.
- Microsoft ODBC Driver 18 for SQL Server.

## Instalación

1. Descomprime el proyecto y entra a su carpeta.

   ```bash
   cd CampusDigital-Equipo1-Modulo1.1
   ```

2. Instala dependencias y crea el archivo de entorno.

   ```bash
   composer install
   copy .env.example .env
   php artisan key:generate
   npm install
   ```

   En macOS/Linux cambia `copy` por `cp`.

3. Crea una base vacía llamada `campus_digital` y configura `.env`. Usa el usuario y la contraseña reales de SQL Server:

   ```dotenv
   DB_CONNECTION=sqlsrv
   DB_HOST=127.0.0.1
   DB_PORT=1433
   DB_DATABASE=campus_digital
   DB_USERNAME=usuario_sqlserver
   DB_PASSWORD=tu_clave_segura
   DB_ENCRYPT=yes
   DB_TRUST_SERVER_CERTIFICATE=true
   ```

4. Inicializa el esquema, los catálogos y los datos demo:

   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```

   Si la base contiene tablas de otra instalación y no necesitas conservar sus datos, usa una base vacía o, únicamente en desarrollo, `php artisan migrate:fresh --seed`. Este último comando elimina todas las tablas de la base configurada.

5. Inicia backend y frontend:

   ```bash
   composer run dev
   ```

6. Abre `http://localhost:8000`. También funciona `http://127.0.0.1:8000`. En ambiente local, el botón **Entrar a la demostración** inicia sesión con el administrador sembrado. Este puente deja de existir si `APP_ENV` no es `local` o `APP_DEMO_MODE=false`.

### Ejecución separada

Si `composer run dev` no inicia correctamente los procesos en PowerShell, ejecuta cada servicio en una terminal independiente:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
npm run dev -- --host 127.0.0.1
```

## Compilación y pruebas

```bash
npm run type-check
npm run build
composer test
```

Resultado verificado del módulo: **5 pruebas y 15 aserciones correctas**. Las pruebas usan SQLite en memoria para ser rápidas; la ejecución funcional también debe validarse contra SQL Server antes de integrar el módulo a la rama principal.

Para consultar el estado de la base SQL Server:

```bash
php artisan migrate:status
php artisan db:show --counts
```

En SQL Server las claves foráneas del módulo usan `NO ACTION` para representar restricciones de borrado y evitar las sintaxis `RESTRICT` y rutas de cascada múltiple que SQL Server no admite.

## Importación CSV

La interfaz ofrece una plantilla descargable. También está en `docs/student_import_template.csv`. Encabezados obligatorios:

```text
matricula,nombre,correo_institucional,campus,carrera,semestre,grupo,estatus,correo_personal,telefono,canal_preferido
```

Valores admitidos:

- `estatus`: `active`, `inactive`, `suspended`, `leave`, `graduated`.
- `canal_preferido`: `institutional_email`, `personal_email`, `phone`.
- `campus` y `carrera`: código o nombre exacto del catálogo.

Una matrícula existente se actualiza; una nueva se registra. La cuenta se crea sin contraseña y marcada como pendiente de activación para que el módulo 1.2 gestione credenciales y verificación.

La importación es transaccional: si alguna fila no pasa validación, no se persiste ninguna fila del archivo.

## Límites deliberados

- No se implementa login, recuperación, 2FA ni sesiones confiables: corresponden a 1.2 y 1.7.
- Fortify queda declarado pero excluido del auto-descubrimiento para no publicar rutas parciales; 1.2 debe retirar `laravel/fortify` de `dont-discover` y registrar su proveedor.
- No se implementa RBAC contextual completo: corresponde a 1.3. El booleano `is_platform_admin` es una compuerta mínima y temporal para el módulo.
- No se almacenan consentimientos legales: corresponden a 1.9.
- No se usa MongoDB. Identidad, matrícula, catálogos y estados requieren integridad relacional y permanecen en SQL Server.
- No se duplica información de otros equipos. Las integraciones se publican mediante eventos y contratos.

Consulta [docs/MODULO-1.1.md](docs/MODULO-1.1.md) para reglas, modelo de datos, colaboraciones y criterios de aceptación.

## Solución de problemas

- **Login failed for user**: revisa `DB_USERNAME`, `DB_PASSWORD`, el puerto `1433` y que SQL Server permita autenticación SQL.
- **The selected ... is invalid** durante la importación: confirma que el CSV use exactamente los encabezados y valores de [docs/student_import_template.csv](docs/student_import_template.csv).
- **A facade root has not been set** en pruebas: el proyecto ya incluye el bootstrap del kernel Laravel en [tests/TestCase.php](tests/TestCase.php).
- **GD o intl no cargada**: habilita `extension=gd` y `extension=intl` en el `php.ini` que indique `php --ini`, y verifica con `php -m`.

## Referencias técnicas

- [Laravel 12](https://laravel.com/framework/docs/12.x)
- [Starter kit Vue de Laravel 12](https://laravel.com/framework/docs/12.x/starter-kits#vue)
- [Inertia.js](https://inertiajs.com/)
- [Vue 3](https://vuejs.org/guide/introduction.html)
- [Microsoft Drivers for PHP for SQL Server](https://learn.microsoft.com/sql/connect/php/download-drivers-php-sql-server)
