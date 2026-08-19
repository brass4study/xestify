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
| PostgreSQL   | 13+             | El esquema usa `gen_random_uuid()` nativo (disponible sin extensiones desde la 13) |
| git          | cualquiera reciente | Para clonar el repositorio (opción 4.1) |
| unzip        | cualquiera reciente | Para descomprimir el ZIP de una release (opción 4.2) |

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

La aplicación necesita un rol y una base de datos dedicados (no uses el
superusuario `postgres` en producción). Tienes dos opciones:

- **Dejar que el instalador los cree** (paso 6): `php tools/setup/install.php --create-db`
  se conecta a la base de datos de mantenimiento (`postgres`) con un usuario
  con privilegios (por defecto `postgres`, contraseña pedida por consola o
  `XESTIFY_MAINT_PASSWORD`) y crea el rol `DB_USER` y la base de datos
  `DB_NAME` si no existen.
- **Crearlos a mano** ahora:

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

Dos formas de obtener el código: clonando el repositorio, o descargando el
paquete de una release ya publicada en GitHub.

### 4.1 Clonar el repositorio

```bash
git clone https://github.com/brass4study/xestify.git xestify
cd xestify
```

Clónalo directamente en la ruta que vaya a ser el `DocumentRoot` de Apache
(o muévelo allí después).

### 4.2 Descargar una release de GitHub

Recomendado para producción: el ZIP de una release contiene solo lo
necesario para instalar y ejecutar la aplicación (sin `docs/`, `skills/`
ni tests — ver `skills/publish-release/SKILL.md`).

Con GitHub CLI (`gh`):

```bash
gh release download vX.Y.Z --repo brass4study/xestify --pattern '*.zip'
unzip xestify-vX.Y.Z.zip
```

Sin `gh`, con `curl` directamente contra la URL del asset del release:

```bash
curl -L -o xestify-vX.Y.Z.zip \
  https://github.com/brass4study/xestify/releases/download/vX.Y.Z/xestify-vX.Y.Z.zip
unzip xestify-vX.Y.Z.zip
```

O a mano desde el navegador: entra en
[github.com/brass4study/xestify/releases](https://github.com/brass4study/xestify/releases),
abre la release que quieras instalar y descarga el asset
`xestify-vX.Y.Z.zip` de su sección "Assets".

Cualquiera de estas formas crea la carpeta `xestify-X.Y.Z/` (el prefijo
del zip no lleva la "v" inicial del tag). Esa carpeta hace el mismo papel
que `xestify/` en la opción 4.1: muévela o clónala en la ruta que vaya a
ser el `DocumentRoot` de Apache antes de continuar con el resto de la
guía.

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

## 6. Instalación asistida (recomendada)

Con Apache, PHP y PostgreSQL instalados y el código en su sitio, el
instalador de línea de comandos hace el resto en un solo paso, de forma
idempotente (se puede volver a ejecutar sin efectos secundarios):

```bash
php tools/setup/install.php
```

Qué hace, en orden:

1. **Requisitos**: PHP ≥ 8.1 con `pdo_pgsql` y `mbstring`.
2. **`backend/.env`**: si no existe, lo crea a partir de `backend/.env.example`
   preguntando por consola host/puerto/base de datos/usuario/contraseña de
   PostgreSQL y `APP_ENV` (`development` por defecto, `production` para una
   instalación real; `APP_DEBUG` se deriva de él). Genera un `JWT_SECRET`
   aleatorio de 64 caracteres hexadecimales y, en Linux/macOS, deja el fichero
   con permisos `0640`. Si `backend/.env` ya existe se usa tal cual.
3. **Rol y base de datos** (solo con `--create-db`): los crea si faltan
   conectando a la base de datos de mantenimiento como superusuario (`--maint-user`,
   por defecto `postgres`; contraseña por consola o `XESTIFY_MAINT_PASSWORD`).
4. **Conexión** con las credenciales de `backend/.env`.
5. **Esquema base**: aplica en orden, a través de PDO (no hace falta `psql`),
   los ficheros de [backend/database/schema/](backend/database/schema/)
   (`users`, `plugins`, `plugin_entity_data`, `plugin_extension_data`,
   `plugin_update_history`, `configuration`). Son idempotentes.
6. **Administrador real**: crea el primer usuario administrador (email, nombre
   y contraseña de al menos 12 caracteres, pedidos por consola). Es el usuario
   con el que se entra en producción; se omite si ya existe uno.
7. **Usuarios de demostración** (`admin@xestify.local` / `admin123` y
   `usuario@xestify.local` / `usuario123`): **solo** si `APP_DEBUG=true` (o con
   `--with-seed-users`). Con `APP_DEBUG=false` esas cuentas no pueden iniciar
   sesión, así que en producción no se crean.
8. **Plugins**: registra en base de datos los plugins presentes en `plugins/`
   (quedan inactivos: se activan o instancian desde PluginManager).
9. **Datos de demostración** (solo con `--seed-business-data`, ver paso 10).

Opciones útiles (`php tools/setup/install.php --help` muestra todas),
agrupadas igual que en la propia ayuda del comando:

**Generales**

- `--non-interactive`: no pregunta nada, usa flags, variables de entorno y
  valores por defecto (para scripts/CI). Falla con código 2 si falta un
  secreto obligatorio.

**Configuración de `backend/.env`** (solo se usan si el fichero aún no existe)

- `--db-host=`: host de PostgreSQL (por defecto `127.0.0.1`).
- `--db-port=`: puerto de PostgreSQL (por defecto `5432`).
- `--db-name=`: nombre de la base de datos (por defecto `xestify_dev`).
- `--db-user=`: usuario de PostgreSQL (por defecto `postgres`).
- `--app-env=`: `development` (por defecto) o `production` — de aquí se
  deriva `APP_DEBUG` (solo `true` en `development`).

**Base de datos**

- `--create-db`: crea el rol `DB_USER` y la base de datos `DB_NAME` si no
  existen, conectando a la base de datos de mantenimiento con
  credenciales de superusuario.
- `--maint-db=`: base de datos de mantenimiento a la que conectar (por
  defecto `postgres`).
- `--maint-user=`: usuario de mantenimiento con privilegios para crear el
  rol/BD (por defecto `postgres`).

**Usuarios**

- `--admin-email=`: email del administrador real (si no se indica, se
  pide por consola).
- `--admin-name=`: nombre del administrador real (si no se indica, se
  pide por consola).
- `--skip-admin`: no crea el administrador real.
- `--with-seed-users`: crea igualmente `admin@xestify.local` /
  `usuario@xestify.local` (contraseñas conocidas) aunque
  `APP_DEBUG=false`.

**Plugins y datos**

- `--skip-plugins`: no sincroniza los plugins de disco a la base de datos.
- `--seed-business-data`: siembra datos de negocio de demostración
  (requiere las instancias de plugin activas; ver paso 10).

**Las contraseñas nunca se pasan como flag** (quedarían en el historial de la
shell y en la lista de procesos): se piden por consola (sin eco en Linux/macOS)
o se leen de las variables de entorno `XESTIFY_DB_PASSWORD`,
`XESTIFY_MAINT_PASSWORD` y `XESTIFY_ADMIN_PASSWORD` (más `XESTIFY_ADMIN_EMAIL`
/ `XESTIFY_ADMIN_NAME` como alternativa a los flags).

Ejemplo de instalación de producción sin interacción:

```bash
XESTIFY_MAINT_PASSWORD='...' XESTIFY_DB_PASSWORD='...' XESTIFY_ADMIN_PASSWORD='...' \
php tools/setup/install.php --non-interactive --create-db \
    --db-host=127.0.0.1 --db-name=xestify --db-user=xestify --app-env=production \
    --admin-email=admin@miempresa.es --admin-name="Administrador"
```

Scripts complementarios en [tools/setup/](tools/setup/):

- `php tools/setup/create-admin-user.php` — crea otro administrador real (o
  recupera el acceso) en una instalación existente. Mientras la aplicación no
  tenga alta de usuarios desde la interfaz (backlog, STORY A1.8), esta es la vía
  para dar de alta usuarios.
- `php tools/setup/check-install.php --url=http://host/` — comprobación de
  seguridad post-instalación (paso 9).
- `php tools/setup/seed-admin-user.php`, `sync-plugins.php`,
  `seed-business-data.php` — los pasos individuales que también ejecuta el
  instalador, por si quieres lanzarlos por separado.

### Nota para Windows con Apache+PHP

Si usas un binario de PHP dedicado a Apache y su `php.ini` efectivo vive en
una ruta distinta a la de tu PHP de línea de comandos, fuerza ese `ini`
con `-c` al ejecutar los scripts:

```powershell
C:\apache2.4.66\php\php.exe -c C:\apache2.4.66\config\php.ini tools/setup/install.php
```

---

## 7. Instalación manual (alternativa)

Equivalente paso a paso al instalador, por si prefieres controlar cada
operación.

### 7.1 Configurar la aplicación

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
- En Linux, protege el fichero: `chmod 0640 backend/.env` y grupo del usuario
  de Apache (`chgrp www-data backend/.env`).

### 7.2 Crear el esquema de base de datos

El esquema base son ficheros SQL planos en
[backend/database/schema/](backend/database/schema/), pensados para aplicarse
en orden. Son idempotentes: se pueden volver a ejecutar sin error.
(`backend/database/migrations/` queda reservada para migraciones incrementales
futuras; hoy está vacía.)

Linux/macOS:

```bash
for f in backend/database/schema/*.sql; do
  psql -h 127.0.0.1 -U xestify -d xestify -f "$f"
done
```

Windows (PowerShell):

```powershell
Get-ChildItem backend\database\schema\*.sql | Sort-Object Name | ForEach-Object {
    psql -h 127.0.0.1 -U xestify -d xestify -f $_.FullName
}
```

Si `psql` no está en el `PATH`, usa la ruta completa al ejecutable (por
ejemplo `C:\Program Files\PostgreSQL\18\bin\psql.exe`).

### 7.3 Inicializar la aplicación

```bash
php tools/setup/create-admin-user.php      # administrador real (obligatorio en producción)
php tools/setup/seed-admin-user.php        # usuarios de demostración (solo desarrollo/demo)
php tools/setup/sync-plugins.php           # registra los plugins de plugins/
```

- `create-admin-user.php` pide email, nombre y contraseña (o los lee de
  `XESTIFY_ADMIN_EMAIL` / `XESTIFY_ADMIN_NAME` / `XESTIFY_ADMIN_PASSWORD`).
- `seed-admin-user.php` crea `admin@xestify.local` / `admin123` y
  `usuario@xestify.local` / `usuario123` (`is_seed=true`). Con `APP_DEBUG=false`
  **no pueden iniciar sesión** (el script avisa); son cuentas para desarrollo,
  demos y tests.
- `sync-plugins.php` registra en base de datos los plugins presentes en
  `plugins/` (entidades y extensiones de demostración incluidas).

Ninguna de estas operaciones se ejecuta automáticamente en cada request;
hay que lanzarlas explícitamente en la instalación inicial y tras añadir
plugins nuevos.

---

## 8. Modelo de seguridad de la instalación

Xestify se sirve con el `DocumentRoot` en la raíz del repositorio, así que
todo el código (incluidos `backend/.env`, los scripts de `tools/` y las
clases PHP de `plugins/`) vive dentro del árbol servible. La protección se
apoya en varias capas independientes:

1. **Los scripts de `tools/` solo funcionan desde línea de comandos.**
   `tools/setup/bootstrap.php` (que todo script de `tools/` requiere en su
   primera sentencia) responde `403` y termina si `PHP_SAPI` no es `cli`, sea
   cual sea el servidor web o su configuración. Un test automático
   (`backend/tests/unit/ToolsCliGuardTest.php`) impide añadir scripts sin ese
   guard.
2. **`.htaccess` raíz**: reescribe únicamente las rutas públicas (`/`, `/api/*`,
   `/health`, `/css/*`, `/js/*`, `/plugins/<x>/plugin.js`, `/plugins/<x>/assets/*`)
   y devuelve `403` (sin distinguir mayúsculas) para `backend/`, `docs/`,
   `frontend/`, `skills/`, `tools/`, `var/`, los `.md` de la raíz y cualquier
   dotfile.
3. **`.htaccess` por directorio** (`Require all denied` en `tools/setup/`,
   `tools/dev/`, `backend/`, `docs/`, `skills/`; `Require all granted` solo en
   `backend/public/`; en `plugins/` se deniegan `*.php` y `*.json`): segunda
   barrera aunque cambien las reglas raíz.
4. **Sin secretos en la línea de comandos** y `backend/.env` con permisos
   restringidos.
5. **Sin cuentas por defecto en producción**: los usuarios seed con contraseña
   conocida solo se crean con `APP_DEBUG=true`, y aun existiendo no pueden
   iniciar sesión con `APP_DEBUG=false`.

Requisitos para que las capas 2 y 3 actúen: Apache con `mod_rewrite`,
`AllowOverride All` sobre el `DocumentRoot` y el `DocumentRoot` apuntando a la
raíz del proyecto (paso 5). Verifícalo siempre con `check-install.php` (paso 9).

Deuda conocida: la solución definitiva es servir desde una subcarpeta pública
(`DocumentRoot` = `public/`) con el resto del código fuera del árbol servible;
está anotada en `docs/09-history/decisiones-tecnicas.md` (DECISION 11).

Herramientas de QA/desarrollo (`tools/dev/`) no se incluyen en el artefacto de
release.

---

## 9. Verificar la instalación

1. Comprueba el endpoint de salud:
   ```bash
   curl http://xestify.local/health
   ```
   Debe devolver una respuesta `200 OK`.
2. Ejecuta la comprobación de exposición web contra la URL real de la
   instalación (acepta subrutas, p. ej. `http://localhost/xestify/`):
   ```bash
   php tools/setup/check-install.php --url=http://xestify.local/
   ```
   Verifica que `/health` y la SPA responden `200` y que ninguna ruta interna
   se sirve (`/tools/setup/install.php`, `/Tools/setup/install.php`,
   `/backend/.env`, `/plugins/comments/Hooks.php`, `/INSTALL.md`, `/.git/HEAD`,
   etc. deben dar `403`/`404`). Sale con código 1 si algo está expuesto.
   Equivalente manual: `curl -I http://xestify.local/backend/.env` → `403`.
3. Abre la aplicación en el navegador (`http://xestify.local/` o la
   subruta configurada) e inicia sesión con el administrador real creado en el
   paso 6 (o con un usuario seed si `APP_DEBUG=true`).
4. Opcionalmente, ejecuta la suite de tests backend completa contra la
   base de datos real:
   ```bash
   php backend/tests/run.php all
   ```

---

## 10. (Opcional) Poblar datos de demostración

Para tener una instalación con datos de ejemplo listos para evaluar (~2500
registros: clientes, distribuidores, oftalmólogos, marcas, fabricantes,
pedidos, ventas, facturas y fichas clínicas), ejecuta:

```bash
php tools/setup/seed-business-data.php          # o: php tools/setup/install.php --seed-business-data
```

Requisitos previos: el usuario `admin@xestify.local` (usuarios seed, paso 6/7)
y las once instancias de plugin que usa el seeder **activas** (`clients`,
`distributors`, `ophthalmologists`, `brands`, `manufacturers`, `orders`,
`sales`, `invoices`, `optometries`, `contact_lenses`, `comments`). En una
instalación recién creada los plugins quedan inactivos y varias de esas
instancias (`clients`/`distributors`/`ophthalmologists` sobre `persons`,
`brands`/`manufacturers` sobre `basic`, `sales` sobre `orders`) se crean desde
PluginManager; hasta entonces el seeder aborta sin insertar nada e indica qué
falta. Es idempotente: se puede volver a ejecutar sin duplicar datos. Detalle
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
| La app no arranca, error de `JWT_SECRET` | `JWT_SECRET` vacío en `backend/.env` | Genera y define un valor aleatorio (ver paso 7.1) o deja que `install.php` lo genere |
| 403/404 en rutas de la aplicación (`/api/*`, `/health`, navegación SPA) | Falta `AllowOverride All` o los módulos `mod_rewrite`/`mod_headers` no están cargados | Revisa la `<Directory>` del VirtualHost y los `LoadModule` de Apache |
| Página en blanco o assets (`/css/*`, `/js/*`) no cargan | `DocumentRoot` no apunta a la raíz del repo, o el usuario de Apache no tiene permisos de lectura | Verifica el paso 5 y los permisos de lectura sobre el repositorio clonado |
| `install.php` no conecta: base de datos o rol inexistente | Aún no se ha creado el rol/BD del paso 3 | Relanza con `--create-db` (credenciales de mantenimiento) o créalos a mano |
| `check-install.php` marca rutas `EXPUESTO` (`/backend/.env`, `/tools/...` responden 200) | `.htaccess` ignorados: falta `AllowOverride All`, `mod_rewrite` no cargado, o el `DocumentRoot` no es la raíz del proyecto | Corrige el VirtualHost (paso 5) y repite la comprobación; los scripts de `tools/` siguen sin ejecutarse por web (guard CLI), pero `.env` y el código quedan legibles |
| No puedo iniciar sesión en producción con `admin@xestify.local` | Con `APP_DEBUG=false` los usuarios seed están bloqueados por diseño | Crea un administrador real: `php tools/setup/create-admin-user.php` |
| `Falta la contrasena ...` con código 2 | Modo `--non-interactive` sin la variable de entorno del secreto | Define `XESTIFY_DB_PASSWORD` / `XESTIFY_MAINT_PASSWORD` / `XESTIFY_ADMIN_PASSWORD` o ejecuta en modo interactivo |

---

## Documentación relacionada

- [README.md](README.md) — visión general del proyecto
- [docs/08-operations/apache-vhost-examples.md](docs/08-operations/apache-vhost-examples.md) — variantes de VirtualHost (producción, desarrollo, alias/subruta)
- [docs/08-operations/deploy-rpi5.md](docs/08-operations/deploy-rpi5.md) — notas específicas de producción/Raspberry Pi 5
- [docs/08-operations/actualizaciones.md](docs/08-operations/actualizaciones.md) — actualización de una instalación existente
- [skills/seed-business-data/SKILL.md](skills/seed-business-data/SKILL.md) — siembra de datos de demostración
