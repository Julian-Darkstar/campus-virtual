# Configuración de SQL Server

## Windows

1. Instala SQL Server 2022 Developer o Express y habilita autenticación SQL.
2. Instala Microsoft ODBC Driver 18.
3. Instala o habilita las extensiones PHP `sqlsrv` y `pdo_sqlsrv` compatibles con tu versión exacta de PHP y su tipo TS/NTS.
4. Confirma que PHP las detecta:

   ```powershell
   php -m | Select-String "sqlsrv"
   ```

5. Crea la base:

   ```sql
   CREATE DATABASE campus_digital;
   ```

6. Configura `.env` y ejecuta `php artisan migrate --seed`.

## Cifrado local

ODBC Driver 18 cifra conexiones por defecto. Para desarrollo con certificado local se admite:

```dotenv
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=true
```

En producción usa un certificado confiable y cambia `DB_TRUST_SERVER_CERTIFICATE=false`.

## MongoDB

No se configura MongoDB en 1.1. Duplicar identidad o condición académica entre SQL Server y MongoDB introduciría dos fuentes de verdad. Un motor documental solo debe añadirse cuando exista un caso de acceso propio, un dueño de datos y una estrategia de consistencia documentada.

