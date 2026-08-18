# Guía de instalación

Guía paso a paso para instalar Xestify sobre Apache + PHP + PostgreSQL en un
único origen (frontend, API y assets de plugins servidos desde la raíz del
repositorio). Válida tanto para un entorno de desarrollo en Windows como
para producción en Linux/Raspberry Pi OS.

Para contexto de producto y arquitectura, consulta [README.md](README.md) y
[docs/01-architecture/overview.md](docs/01-architecture/overview.md). Esta
guía es puramente técnica: cómo dejar la aplicación funcionando.

---

## 1. Requisitos previos

| Componente   | Versión mínima | Notas |
|--------------|-----------------|-------|
| Apache       | 2.4+            | Módulos `mod_rewrite` y `mod_headers` habilitados |
| PHP          | 8.1+            | Extensiones `pdo_pgsql` y `mbstring` habilitadas |
| PostgreSQL   | 13+             | Las migraciones usan `gen_random_uuid()` nativo (disponible sin extensiones desde la 13) |
| git          | cualquiera reciente | Para clonar el repositorio |

Sistemas operativos cubiertos en esta guía: **Linux/Debian/Raspberry Pi OS**
(runtime de producción recomendado) y **Windows** (entorno de desarrollo).

Xestify no usa Composer ni ningún framework PHP, y el frontend no tiene
build step (Vanilla JS servido tal cual, con el CSS de Tailwind ya generado
y versionado en el repo). No hace falta Node/npm para instalar ni ejecutar
la aplicación.

---

## 2. Instalar Apache + PHP

### Linux / Debian / Raspberry Pi OS

```bash
sudo apt update
sudo apt install apache2 php libapache2-mod-php php-pgsql php-mbstring postgresql

sudo a2enmod rewrite headers
sudo systemctl restart apache2
```

### Windows

1. Descarga e instala Apache para Windows (por ejemplo, los binarios de
   [Apache Lounge](https://www.apachelounge.com/download/)) y PHP para
   Windows desde [windows.php.net](https://windows.php.net/download/)
   (build compatible con tu Apache: VC15/VC16, x64, thread-safe si usas
   `mod_php`).
2. Enlaza PHP como módulo (`mod_php`) o como manejador CGI/FastCGI en
   `httpd.conf`, según la distribución de PHP elegida.
3. En `httpd.conf`, asegúrate de que estos módulos están descomentados:
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   LoadModule headers_module modules/mod_headers.so
   ```
4. En `php.ini`, habilita las extensiones necesarias:
   ```ini
   extension=pdo_pgsql
   extension=mbstring
   ```
5. Reinicia el servicio/proceso de Apache.

### Verificar la instalación de PHP

```bash
php -v
php -m
```

`php -m` debe listar `pdo_pgsql` y `mbstring` entre las extensiones activas
(en Windows, `php -m | findstr pgsql`; en Linux, `php -m | grep -E "pgsql|mbstring"`).

---

## 3. Instalar y configurar PostgreSQL

Instala PostgreSQL 13 o superior (`apt install postgresql` en Linux, o el
instalador oficial de [postgresql.org](https://www.postgresql.org/download/windows/)
en Windows).

Crea un rol y una base de datos dedicados para la aplicación (no uses el
superusuario `postgres` en producción):

```sql
CREATE ROLE xestify LOGIN PASSWORD 'una_contraseña_fuerte';
CREATE DATABASE xestify OWNER xestify;
```

En Linux normalmente se ejecuta como:

```bash
sudo -u postgres psql -c "CREATE ROLE xestify LOGIN PASSWORD 'una_contraseña_fuerte';"
sudo -u postgres psql -c "CREATE DATABASE xestify OWNER xestify;"
```

En Windows, usa `psql` con el usuario `postgres` creado por el instalador.

---

## 4. Obtener el código fuente

```bash
git clone <url-del-repositorio> xestify
cd xestify
```

Clónalo directamente en la ruta que vaya a ser el `DocumentRoot` de Apache
(o muévelo allí después).

---

## 5. Configurar Apache

El `DocumentRoot` debe apuntar a la **raíz del repositorio** (no a
`backend/public/`): el enrutado completo — frontend, API y assets de
plugins — vive en el [`.htaccess`](.htaccess) de la raíz, que requiere
`AllowOverride All`.

Ejemplo mínimo de producción (sirviendo desde la raíz del host):

```apache
<VirtualHost *:80>
    ServerName xestify.local
    DocumentRoot "/ruta/a/xestify"

    <Directory "/ruta/a/xestify">
        AllowOverride All
        Require all granted
        Options FollowSymLinks
    </Directory>

    ErrorLog "logs/xestify-error.log"
    CustomLog "logs/xestify-access.log" combined
</VirtualHost>
```

Para variantes de desarrollo (exponiendo `/tests/*` del frontend) o para
servir la aplicación bajo un alias/subruta (por ejemplo
`http://localhost/xestify/`), consulta los ejemplos completos en
[docs/08-operations/apache-vhost-examples.md](docs/08-operations/apache-vhost-examples.md).

Reinicia Apache tras aplicar la configuración.

---

## 6. Configurar la aplicación

Copia la plantilla de entorno:

```bash
cp backend/.env.example backend/.env       # Linux/macOS
copy backend\.env.example backend\.env     # Windows (cmd)
```

Edita `backend/.env` con los valores reales:

```ini
APP_ENV=production
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=xestify
DB_USER=xestify
DB_PASSWORD=una_contraseña_fuerte

JWT_SECRET=<genera_un_valor_aleatorio_de_al_menos_32_caracteres>
JWT_EXPIRY=3600
```

Notas:

- **`JWT_SECRET` es obligatorio**: si queda vacío, la aplicación se niega a
  arrancar (`EnvironmentException` en boot). Genera uno aleatorio, por
  ejemplo con `openssl rand -base64 32` o `php -r "echo bin2hex(random_bytes(32));"`.
- Usa `DB_HOST=127.0.0.1` en vez de `localhost` para evitar la latencia
  extra de resolución de nombre en conexiones locales.
- En desarrollo puedes dejar `APP_ENV=development` y `APP_DEBUG=true`.
- `PSQL_PATH` (comentado en `.env.example`) solo hace falta si vas a
  ejecutar `MigrationIdempotenceTest.php` y `psql` no está en el `PATH`
  (típico en Windows).

---

## 7. Crear el esquema de base de datos

Las migraciones son ficheros SQL planos en
[backend/database/migrations/](backend/database/migrations/), pensados para
aplicarse a mano y en orden. Son idempotentes: se pueden volver a ejecutar
sin error.

### Linux/macOS

```bash
for f in backend/database/migrations/*.sql; do
  psql -h 127.0.0.1 -U xestify -d xestify -f "$f"
done
```

### Windows (PowerShell)

```powershell
Get-ChildItem backend\database\migrations\*.sql | Sort-Object Name | ForEach-Object {
    psql -h 127.0.0.1 -U xestify -d xestify -f $_.FullName
}
```

Si `psql` no está en el `PATH`, usa la ruta completa al ejecutable (por
ejemplo `C:\Program Files\PostgreSQL\18\bin\psql.exe`).

---

## 8. Inicializar la aplicación

Con el esquema ya creado, ejecuta los scripts de arranque en
[tools/setup/](tools/setup/):

```bash
php tools/setup/seed-admin-user.php
php tools/setup/sync-plugins.php
```

- `seed-admin-user.php` crea los usuarios seed iniciales si la tabla
  `users` está vacía (`admin@xestify.local` / `admin123` y
  `usuario@xestify.local` / `usuario123` — **cambia estas contraseñas o
  gestiona los usuarios reales desde la aplicación tras el primer login**).
- `sync-plugins.php` registra en base de datos los plugins presentes en
  `plugins/` (entidades y extensiones de demostración incluidas).

Ninguna de estas operaciones se ejecuta automáticamente en cada request;
hay que lanzarlas explícitamente en la instalación inicial y tras añadir
plugins nuevos.

### Nota para Windows con Apache+PHP

Si usas un binario de PHP dedicado a Apache y su `php.ini` efectivo vive en
una ruta distinta a la de tu PHP de línea de comandos, fuerza ese `ini`
con `-c` al ejecutar los scripts:

```powershell
C:\apache2.4.66\php\php.exe -c C:\apache2.4.66\config\php.ini tools/setup/seed-admin-user.php
C:\apache2.4.66\php\php.exe -c C:\apache2.4.66\config\php.ini tools/setup/sync-plugins.php
```

---

## 9. Verificar la instalación

1. Comprueba el endpoint de salud:
   ```bash
   curl http://xestify.local/health
   ```
   Debe devolver una respuesta `200 OK`.
2. Abre la aplicación en el navegador (`http://xestify.local/` o la
   subruta configurada) e inicia sesión con uno de los usuarios seed.
3. Opcionalmente, ejecuta la suite de tests backend completa contra la
   base de datos real:
   ```bash
   php backend/tests/run.php all
   ```

---

## 10. (Opcional) Poblar datos de demostración

Para tener una instalación con datos de ejemplo listos para evaluar (~2500
registros: clientes, distribuidores, oftalmólogos, marcas, fabricantes,
pedidos, ventas, facturas y fichas clínicas), tras haber creado el usuario
admin ejecuta:

```bash
php tools/setup/seed-business-data.php
```

Es idempotente: se puede volver a ejecutar sin duplicar datos. Detalle
completo en [skills/seed-business-data/SKILL.md](skills/seed-business-data/SKILL.md).

---

## 11. Notas para producción / Raspberry Pi 5

Para hardening, backups, monitoreo y recomendaciones específicas del
despliegue en Raspberry Pi 5, consulta
[docs/08-operations/deploy-rpi5.md](docs/08-operations/deploy-rpi5.md).

Para el procedimiento de actualización de una instalación ya existente,
consulta [docs/08-operations/actualizaciones.md](docs/08-operations/actualizaciones.md).

---

## 12. Solución de problemas comunes

| Síntoma | Causa probable | Solución |
|---|---|---|
| Error de conexión a base de datos al usar la app | `DB_HOST`/`DB_PORT`/credenciales incorrectas en `.env`, o extensión `pdo_pgsql` no habilitada | Revisa `backend/.env` y `php -m \| grep pgsql` |
| La app no arranca, error de `JWT_SECRET` | `JWT_SECRET` vacío en `backend/.env` | Genera y define un valor aleatorio (ver paso 6) |
| 403/404 en rutas de la aplicación (`/api/*`, `/health`, navegación SPA) | Falta `AllowOverride All` o los módulos `mod_rewrite`/`mod_headers` no están cargados | Revisa la `<Directory>` del VirtualHost y los `LoadModule` de Apache |
| Página en blanco o assets (`/css/*`, `/js/*`) no cargan | `DocumentRoot` no apunta a la raíz del repo, o el usuario de Apache no tiene permisos de lectura | Verifica el paso 5 y los permisos de lectura sobre el repositorio clonado |

---

## Documentación relacionada

- [README.md](README.md) — visión general del proyecto
- [docs/08-operations/apache-vhost-examples.md](docs/08-operations/apache-vhost-examples.md) — variantes de VirtualHost (producción, desarrollo, alias/subruta)
- [docs/08-operations/deploy-rpi5.md](docs/08-operations/deploy-rpi5.md) — notas específicas de producción/Raspberry Pi 5
- [docs/08-operations/actualizaciones.md](docs/08-operations/actualizaciones.md) — actualización de una instalación existente
- [skills/seed-business-data/SKILL.md](skills/seed-business-data/SKILL.md) — siembra de datos de demostración
