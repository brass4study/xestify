# Despliegue en Raspberry Pi 5

## Objetivo

Desplegar Xestify localmente por negocio con Apache+PHP como runtime principal.

## Requisitos base

- Raspberry Pi 5 (8GB recomendado)
- Raspberry Pi OS 64-bit
- Apache 2.4+ con `mod_rewrite`
- PHP 8.1+ integrado en Apache o mediante modulo compatible
- PostgreSQL
- Disco SSD o SD de calidad industrial

## Servicios sugeridos

- Apache+PHP (frontend, API y assets de plugins en un solo origen)
- db-postgres (persistencia)
- scheduler (cron de updates)

## Variables de entorno minimas

- APP_ENV=production
- DB_HOST=db-postgres
- DB_PORT=5432
- DB_NAME=xestify
- DB_USER=xestify
- DB_PASSWORD=change_me

## Flujo de instalacion

1. Clonar repositorio local
2. Configurar archivo `.env`
3. Configurar Apache con `docs/08-operations/apache-vhost-examples.md`
4. Ejecutar migraciones core
5. Registrar usuario admin inicial con `php tools/setup/seed-admin-user.php`
6. Sincronizar plugins desde disco con `php tools/setup/sync-plugins.php`
7. Verificar salud de API y UI

## Runtime web canonico

- `DocumentRoot`: raiz del repositorio
- Frontend shell: `frontend/src/index.html`
- Estaticos frontend: `/css/*` y `/js/*`
- API y health: `/api/*` y `/health` -> `backend/public/index.php`
- Assets de plugins: `/plugins/{slug}/plugin.js` y `/plugins/{slug}/assets/*`
- Tests HTML: solo en desarrollo, habilitados con `SetEnvIf ... ENABLE_TEST=1`

La regla de enrutado vive en la raiz del repo (`.htaccess`). En desarrollo,
usar el bloque de desarrollo de `docs/08-operations/apache-vhost-examples.md`
para exponer `/tests/*`; en produccion esa ruta no debe exponerse.

Tambien se soporta servir la aplicacion bajo un alias o subruta de Apache. Por
ejemplo, con `Alias /xestify "C:/Proyectos/Xestify"` la app resuelve su base
path de forma dinamica y mantiene operativos `/xestify/`, `/xestify/api/*`,
`/xestify/plugins/*` y, en desarrollo, `/xestify/tests/*`.

## Operaciones manuales de setup

- Admin inicial: `php tools/setup/seed-admin-user.php`
- Sincronizacion de plugins disco -> BD: `php tools/setup/sync-plugins.php`

Estas operaciones ya no se ejecutan en cada request. Deben lanzarse de forma
explicita durante instalacion, mantenimiento o migraciones controladas.

La carga de plugins en runtime ya no escanea el directorio `plugins/` en cada
request. El boot registra solo los hooks de plugins activos en BD; la
sincronizacion de nuevos plugins o cambios de manifest se hace con
`sync-plugins.php` o, mas adelante, con el flujo administrativo dedicado.

### Nota para Windows con Apache+PHP

Si se usa el binario PHP de Apache en Windows y el `php.ini` efectivo vive en
una ruta separada, ejecutar los scripts con `-c` para forzar ese ini. Ejemplo:

```powershell
C:\apache2.4.66\php\php.exe -c C:\apache2.4.66\config\php.ini tools/setup/seed-admin-user.php
C:\apache2.4.66\php\php.exe -c C:\apache2.4.66\config\php.ini tools/setup/sync-plugins.php
```

### Nota de rendimiento para desarrollo local

En Apache+PHP local, dos ajustes ayudan de forma clara:

- usar `DB_HOST=127.0.0.1` para evitar latencia extra de resolucion sobre `localhost`
- dejar Xdebug en modo trigger:

```ini
xdebug.mode = debug
xdebug.start_with_request = trigger
```

Con esta configuracion, Xdebug sigue disponible para depuracion explicita pero
no penaliza cada request normal.

## Backups

- Backup diario de PostgreSQL
- Retencion minima de 7 dias
- Export opcional a almacenamiento externo

## Monitoreo minimo

- Logs de app y Apache
- Estado de los servicios
- Espacio de disco y uso de memoria

## Hardening recomendado

- Cambiar credenciales por defecto
- Bloquear puertos no usados
- Limitar acceso SSH
- Actualizaciones de seguridad del OS
