# Estado de Sesión - Xestify con IA

> **Instrucciones de uso:**
> Al iniciar una nueva conversación con Copilot, escribe:
> _"Lee [docs/10-productivity/sesion.md](sesion.md)y retoma el desarrollo de Xestify donde lo dejamos."_

---

## Última actualización

**Fecha:** 2026-08-05
**EPIC activo:** EPIC 8 - Gestión de usuarios (EN PROGRESO)  
**Próxima story:** STORY 8.5 - Frontend - Página gestión de usuarios (`#/usuarios`)

---

### ✅ Release B completado: consolidación de migraciones y fixes

| Paso | Descripción | Estado |
|------|-------------|--------|
| Migración 010 | `010_drop_system_entities.sql` — DROP TABLE IF EXISTS | ✅ aplicado |
| SystemEntity.php | Redirigido a `plugins WHERE plugin_type='entity'` | ✅ |
| SystemEntitiesTableTest | Reescrito para verificar que la tabla NO existe | ✅ 3/3 |
| MigrationIdempotenceTest | Actualizado a migraciones 001-005 | ✅ 3/3 |
| SystemEntityTest | Fixtures redirigidos a plugins (INSERT + DELETE) | ✅ 7/7 |
| Migraciones consolidadas | Eliminados 002/008/009/010; renombrados a 001-005 | ✅ |
| `003_plugins.sql` | Añadida columna `name` + índice desde el inicio | ✅ |
| `Response.php` | Añadida cabecera `Cache-Control: no-store` | ✅ |
| `EntitySeeder.php` | `ensureSingularLabels` ya no sobreescribe `name` en BD | ✅ |

**Suite completa post-Release B:** EntityControllerTest 9/9, EntityServiceTest 6/6, ClientsPluginTest 14/14, PluginLifecycleTest 8/8, PluginDependenciesTest 6/6, HookFilterApiTest 10/10, CommentsPluginTest 9/9, PluginsRegistryTableTest 6/6, MigrationIdempotenceTest 3/3, SystemEntitiesTableTest 3/3, SystemEntityTest 7/7 ✅

---

## Estado del proyecto

### ✅ EPIC 0 — Preparación Técnica (COMPLETADO)

| Story | Descripción | Commit | Tests |
|-------|-------------|--------|-------|
| 0.1 | Setup repo + estructura de carpetas | `fc8e52c` | — |
| 0.2 | Container DI casero | `3a31033` | 8/8 ✅ |
| 0.3 | Router HTTP | `6190b28` | 10/10 ✅ |
| 0.4 | Request / Response helpers | `fe1d8a4` | 20/20 ✅ |
| 0.5 | Entorno local PHP + PostgreSQL | `fc8e52c` | — |
| 0.6 | Frontend skeleton | `fc8e52c` | — |

### ✅ EPIC 1 — Autenticación (COMPLETADO)

| Story | Descripción | Tests |
|-------|-------------|-------|
| 1.1 | Tabla `users` + migración SQL + seeder | 8/8 ✅ (integración) |
| 1.2 | JwtService (encode/decode HS256) | 8/8 ✅ |
| 1.3 | AuthController (POST /api/auth/login) | 8/8 ✅ (integración) |
| 1.4 | AuthMiddleware + `Request::setUser/user` | 6/6 ✅ |

**Tests EPIC 1:** 30 nuevos (14 unit + 17 integración) → **Total acumulado: 68 tests**

**Archivos creados:**
- `backend/database/migrations/001_users.sql`
- `backend/src/Core/Database.php` — PDO singleton
- `backend/src/Exceptions/DatabaseException.php`
- `backend/src/Exceptions/AuthException.php`
- `backend/src/Services/JwtService.php` — HS256 puro PHP
- `backend/src/Controllers/AuthController.php` — POST /api/auth/login (con Request inyectable)
- `backend/src/Middleware/AuthMiddleware.php`
- `backend/src/Database/Seeders/UserSeeder.php`
- `backend/tests/unit/JwtServiceTest.php`
- `backend/tests/unit/AuthMiddlewareTest.php`
- `backend/tests/integration/DatabaseTest.php` — 9 tests
- `backend/tests/integration/AuthControllerTest.php` — 8 tests

**Archivos modificados:**
- `backend/src/Core/Request.php` — añadido `setUser()` / `user()`
- `backend/src/config/app.php` — registra `Database`, `JwtService`, `AuthController`; llama `UserSeeder`
- `backend/src/config/routes.php` — añadida ruta `POST /api/auth/login`
- `backend/public/index.php` — eliminado BOM UTF-8 que causaba error `strict_types`

**Infraestructura:**
- `C:\php\php.ini` — habilitada extensión `pdo_pgsql` (estaba comentada)

### 🔄 EPIC 2 — Modelo de Datos Core (✅ COMPLETADO)

| Story | Descripción | Commit | Tests |
|-------|-------------|--------|-------|
| 2.1 ✅ | Tabla `system_entities` + migración (consolidada en 003_plugins.sql) | `2c88d64` | 3/3 ✅ |
| 2.2 ✅ | Tabla `entity_metadata` (schema versionado) | `0445672` | 4/4 ✅ |
| 2.3 ✅ | Tabla `entity_data` (registros de negocio) | `195db58` | 5/5 ✅ |
| 2.4 ✅ | Tabla `plugins` (antes `plugins_registry`) | `17fa5df` | 5/5 ✅ |
| 2.5 ✅ | Tabla `plugin_hooks` (antes `plugin_hook_registry`) | `3352b4a` | 5/5 ✅ |
| 2.6 ✅ | GenericRepository (CRUD JSONB) | `58a2670` | 7/7 ✅ |
| 2.7 ✅ | Verificar idempotencia migraciones 001-005 | `906b595` | 3/3 ✅ |

**Archivos creados (EPIC 2 hasta ahora):**
- `backend/database/migrations/001_users.sql` — tabla users
- `backend/database/migrations/002_plugin_entity_data.sql` — tabla plugin_entity_data (antes 004)
- `backend/database/migrations/003_plugins.sql` — tabla plugins con name, schema (antes 005)
- `backend/database/migrations/004_plugin_hooks.sql` — tabla plugin_hooks (antes 006)
- `backend/database/migrations/005_plugin_extension_data.sql` — tabla plugin_extension_data (antes 007)
- `backend/tests/integration/SystemEntitiesTableTest.php` — 3 tests
- `backend/tests/integration/EntityMetadataTableTest.php` — 4 tests
- `backend/tests/integration/EntityDataTableTest.php` — 5 tests
- `backend/tests/integration/PluginsRegistryTableTest.php` — 5 tests
- `backend/tests/integration/PluginHookRegistryTableTest.php` — 5 tests
- `backend/src/Exceptions/RepositoryException.php`
- `backend/src/Repositories/GenericRepository.php` — find, all, create, update, delete (soft), restore
- `backend/tests/integration/GenericRepositoryTest.php` — 7 tests
- `backend/tests/integration/MigrationIdempotenceTest.php` — 3 tests (idempotencia 001-005)

### ✅ EPIC 3 — Motor de Entidades Dinámicas (COMPLETADO)

| Story | Descripción | Commit | Tests |
|-------|-------------|--------|-------|
| 3.1 ✅ | ValidationService (valida contra schema JSONB) | pendiente | 8/8 ✅ |
| 3.2 ✅ | EntityService (orquestación CRUD) | pendiente | 6/6 ✅ |
| 3.3 ✅ | EntityController (endpoints REST) + rutas /api/v1 | pendiente | 9/9 ✅ |
| 3.4 ✅ | Helpers apiSuccess/apiError en Response | 55507f4 | 24/24 ✅ |
| 3.5 ✅ | Modelo SystemEntity (getActive/getBySlug/findOrFail) | b4b39f1 | 7/7 ✅ |
| 3.6 ✅ | Frontend Api.js (cliente HTTP genérico) | 82c8ea6 | 11/11 ✅ |
| 3.7 ✅ | Frontend - Crear State.js (estado global) | f9d77b1 | 11/11 ✅ |
| 3.8 ✅ | Frontend - Crear DynamicForm.js | c6473f0 | 6/6 ✅ |
| 3.9 ✅ | Frontend - Crear DynamicTable.js | 8878f59 | 6/6 ✅ |
| 3.10 ✅ | Frontend - Crear página EntityList | 19b4565 | 7/7 ✅ |
| 3.11 ✅ | Frontend - Crear página EntityEdit | pendiente (este commit) | 12/12 ✅ |
| 3.x ✅ | Correcciones SonarQube/VS Code previas a STORY 3.7 | d410958 | checks editor en verde ✅ |

**Estado actual (2026-05-01):**
- Limpieza de hallazgos completada antes de STORY 3.7.
- Ajustes aplicados en tests y capa backend para eliminar literales duplicados, newline finales, returns redundantes y warning PHP1412.
- `DatabaseTest.php` actualizado para evitar `setAccessible()` (deprecado en PHP 8.5).
- Ajustes de SonarQube en frontend: `Api.js` (catch simplificado) y `ApiTest.html` (`replaceAll`) sin regresión funcional.

### ✅ EPIC 4 — Sistema de Plugins y Hooks Backend (COMPLETADO)

| Story | Descripción | Commit | Tests |
|-------|-------------|--------|-------|
| 4.1 ✅ | PluginLoader (descubre, valida, registra) | 75ad5f4 | 8/8 ✅ |
| 4.2 ✅ | HookDispatcher (registro y ejecución de hooks) | b053e91 | 11/11 ✅ |
| 4.3 ✅ | hooks beforeSave/afterSave en EntityService | c8c9755 | 10/10 ✅ |
| 4.4 ✅ | Plugin clients (manifest, schema, Hooks, Installer) | 989ef37 | 13/13 ✅ |
| 4.5 ✅ | Ciclo de vida de plugin (onInstall, onActivate, onDeactivate) | d1a476e | 8/8 ✅ |
| 4.6 ✅ | Metadatos de plugin (compatibilidad, dependencias entre plugins) | 441be1c | 6/6 ✅ |
| 4.7 ✅ | Extender schema con identidades, campos obligatorios y relaciones opcionales | 7c794a6 | 14/14 ✅ |

### ✅ EPIC 5 — Frontend Dinámico Base (COMPLETADO)

| Story | Descripción | Commit | Tests |
|-------|-------------|--------|-------|
| 5.1 ✅ | Frontend - Crear página Login | `66c7747` | 5/5 ✅ |
| 5.2 ✅ | Frontend - Crear navbar/sidebar de navegación | `a60a3b3` | 9/9 ✅ |
| 5.3 ✅ | Frontend - Integración E2E EntityList + EntityEdit | `3258257` | 11/11 ✅ |
| 5.3b ✅ | Fix: GET /api/v1/entities + EntitySeeder + UTF-8 | `722990c` | — |
| 5.3c ✅ | Fix: Router params `{slug}` + tabla registros (tamaño y datos) | `722990c` | — |
| 5.4 ✅ | Frontend - Crear Modal/Dialog reutilizable | `041ba40` | 5/5 ✅ |
| 5.5 ✅ | Frontend - Mejoras responsive + refinamiento UX navbar/tabla | `84d0b70` | — |

### 🔄 EPIC 6 — Plugins tipo Extension (EN PROGRESO)

| Story | Descripción | Commit | Tests |
|-------|-------------|--------|-------|
| 6.1 ✅ | Frontend - Crear módulo DynamicTabs.js | `f16d2c5` | 6/6 ✅ |
| 6.2 ✅ | Backend - Hook `registerTabs` y `registerActions` en HookDispatcher | `d91aef8` | 7+10/17 ✅ |
| 6.3 ✅ | Release B: `plugins` como única fuente de verdad (eliminar system_entities) | `d5e7dbe` | 11 suites ✅ |
| 6.4 ✅ | Plugin `comments` (tipo extension) | `d61ef09` | 9/9 ✅ |
| 6.5-fix ✅ | Fix: PluginLoader wiring — `registerActiveHooks()` en boot | `e97b3bf` | 3/3 ✅ |
| 6.5-fix-b ✅ | Fix general: arquitectura plana de plugins + UI comments + documentación | `e97b3bf` | 9/9 + 3/3 ✅ |
| sonar-fix ✅ | Fix SonarQube: 44 hallazgos (complejidad, literales, tipos, imports, parámetros) | `01e6041` | 9/9 + 3/3 ✅ |
| 6.5 ✅ | Frontend - Página PluginManager | `7d2d313` | 28/28 backend + 12/12 E2E ✅ |

### ✅ EPIC 7 — Actualizaciones de plugins y rollback (COMPLETADO)

| Story | Descripción | Estado | Verificación |
|-------|-------------|--------|--------------|
| 7.1 | Detección de actualizaciones disponibles en PluginLoader | ✅ Implementada | `php backend/tests/run.php integration-plugins` |
| 7.2 | Sync/update explícito con schema aditivo y snapshots | ✅ Implementada | `php backend/tests/run.php integration-db` |
| 7.3 | Página de configuración de plugins activados | ✅ Implementada | `node --check frontend/src/js/pages/PluginConfig.js` |
| 7.4 | Rollback manual de plugin a versión anterior | ✅ Implementada | `php backend/tests/integration/PluginManagerApiTest.php` |
| 7.5 | UI de sincronización, update y rollback en PluginManager | ✅ Implementada | `node --check frontend/src/js/pages/PluginManager.js` |

**Detalle del cierre de la EPIC 7:**
- `PluginLoader` separa claramente `sync` y `update`, preservando runtime y registrando snapshots previos.
- `PluginManager` expone acciones admin para sincronizar, actualizar y hacer rollback con feedback visual.
- `plugin_update_history` permite restauraciones transaccionales y evita mutaciones implícitas en el boot.

### ✅ EPIC 8 — Gestión de usuarios (EN PROGRESO)

| Story | Descripción | Estado | Verificación |
|-------|-------------|--------|--------------|
| 8.1 | Backend - Migracion de perfil y UserRepository | ✅ Implementada | `php backend/tests/integration/UserRepositoryTest.php` → 5/5 tests |
| 8.2 | Backend - UserController y rutas REST | ✅ Implementada | `php backend/tests/integration/UserControllerTest.php` → 4/4 tests |
| 8.3 | Frontend - UserMenu dropdown en Navbar | ✅ Implementada | `frontend/tests/NavbarTest.html` + validación visual del menú |
| 8.4 | Frontend - Página Mi Perfil (`#/profile`) | ✅ Implementada | `node --check frontend/src/js/pages/UserProfile.js` + `php backend/tests/integration/UserControllerTest.php` |

**Detalle de la story 8.1:**
- `backend/database/migrations/001_users.sql` incorpora `name`, `avatar` y `deleted_at` sobre la tabla `users`.
- `backend/src/repositories/UserRepository.php` implementa `find`, `all`, `update`, `delete` y `updatePassword` con filtrado por borrado lógico y cleanup de usuarios de prueba.
- `backend/tests/integration/UserRepositoryTest.php` añade cobertura de perfil, actualización y borrado lógico.
- `backend/src/controllers/AuthController.php` bloquea el login para usuarios marcados como borrados.
- `backend/tests/integration/AuthControllerTest.php` añade un test de regresión para usuarios eliminados.

**Detalle de la story 8.2:**
- `backend/src/controllers/UserController.php` añade endpoints para ver y actualizar el perfil propio, listar/mostrar/editar usuarios en modo admin y borrar usuarios con protección contra auto-borrado.
- `backend/src/config/routes.php` y `backend/src/config/app.php` registran las rutas protegidas `/api/v1/users/me`, `/api/v1/users` y `/api/v1/users/{id}`.
- `backend/tests/integration/UserControllerTest.php` cubre acceso al perfil, validación de cambio de email con password actual, listado admin y borrado lógico con guardas.

**Detalle de la story 8.3:**
- `frontend/src/js/modules/UserMenu.js` implementa el dropdown del usuario con avatar, nombre, actions de perfil/usuarios/logout y soporte para roles admin.
- `frontend/src/js/modules/Navbar.js` integra el menú de usuario en la barra superior y enlaza las acciones con el shell principal.
- `frontend/tests/NavbarTest.html` cubre render, hover y navegación desde el menu.

**Detalle de la story 8.4:**
- `frontend/src/js/pages/UserProfile.js` implementa la vista de perfil editable con carga desde `/api/v1/users/me`, validación inline, feedback visual y preservación de valores en errores.
- `frontend/src/js/modules/State.js` y `frontend/src/js/modules/Navbar.js` sincronizan el estado global del usuario para que los cambios se reflejen sin recargar el shell.
- `frontend/src/css/main.css` añade estilos para estados de error, fuerza de contraseña y feedback del formulario.
- Verificación aplicada: `node --check frontend/src/js/pages/UserProfile.js` y `php backend/tests/integration/UserControllerTest.php`.

---

## Stack decidido

| Capa | Tecnología | Notas |
|------|-----------|-------|
| Backend | PHP 8.1+ nativo | Sin frameworks |
| Autoload | Manual (`spl_autoload_register`) | Sin Composer |
| Frontend | Vanilla JS ES2020+ | Sin build step |
| Base de datos | PostgreSQL local | Sin Docker en dev |
| Auth | JWT HS256 | `Xestify\Services\JwtService` |
| Schema | Custom minimalista | ~100 líneas PHP |

---

## Estructura de archivos relevantes

```
backend/
├── public/index.php              ← Entry point
├── database/
│   └── migrations/
│       ├── 001_users.sql                  ✅ Tabla users
│       ├── 002_plugin_entity_data.sql     ✅ Tabla plugin_entity_data
│       ├── 003_plugins.sql                ✅ Tabla plugins (name, slug, type, status, schema)
│       ├── 004_plugin_hooks.sql           ✅ Tabla plugin_hooks
│       └── 005_plugin_extension_data.sql  ✅ Tabla plugin_extension_data
├── src/
│   ├── bootstrap.php             ← Autoloader + env loader
│   ├── app.php                   ← Wiring Container + Router + Seeders
│   ├── Core/
│   │   ├── Container.php         ✅ DI container
│   │   ├── Router.php            ✅ HTTP router (soporta {param} y :param)
│   │   ├── Request.php           ✅ + setUser/user (STORY 1.4)
│   │   ├── Response.php          ✅ + apiSuccess/apiError + charset UTF-8
│   │   └── Database.php          ✅ PDO singleton + client_encoding UTF8
│   ├── Controllers/
│   │   ├── HealthController.php  ✅ GET /health
│   │   ├── AuthController.php    ✅ POST /api/auth/login
│   │   └── EntityController.php  ✅ CRUD + GET /api/v1/entities (con label_singular)
│   ├── Database/
│   │   └── Seeders/
│   │       ├── UserSeeder.php    ✅ Seed admin on boot
│   │       └── EntitySeeder.php  ✅ Seed entidades demo (client, product) con label_singular
│   ├── Exceptions/
│   │   ├── AuthException.php          ✅ Dominio: auth errors
│   │   ├── DatabaseException.php      ✅ Dominio: db errors
│   │   ├── RepositoryException.php    ✅ Dominio: repository errors
│   │   ├── EntityServiceException.php ✅ Dominio: entity errors
│   │   └── ValidationException.php    ✅ Dominio: validation errors
│   ├── Repositories/
│   │   └── GenericRepository.php ✅ find, all, create, update (JSONB ||), delete (soft), restore
│   ├── Middleware/
│   │   └── AuthMiddleware.php    ✅ Valida JWT en rutas protegidas
│   ├── Models/
│   │   └── SystemEntity.php      ✅ getActive, getBySlug, findOrFail (+ caché en memoria)
│   ├── Services/
│   │   ├── JwtService.php        ✅ HS256 puro PHP
│   │   ├── ValidationService.php ✅ valida contra schema JSONB (6 tipos + identities/fields/custom_fields/relations)
│   │   └── EntityService.php     ✅ CRUD orquestado + hooks beforeSave/afterSave
│   └── config/
│       ├── app.php               ✅ Registra todos los servicios + Seeders en boot
│       └── routes.php            ✅ /health + /api/auth/login + /api/v1/entities/*
├── plugins/
│   └── clients/                  ✅ Plugin entity tipo 'entity' (manifest, schema, Hooks, Installer)
└── tests/
    ├── unit/
    │   ├── helpers.php                    ← TestSuite + assertion helpers
    │   ├── ContainerTest.php              ✅ 8 tests
    │   ├── RouterTest.php                 ✅ 10 tests
    │   ├── RequestResponseTest.php        ✅ 24 tests
    │   ├── JwtServiceTest.php             ✅ 8 tests
    │   ├── AuthMiddlewareTest.php         ✅ 6 tests
    │   └── ValidationServiceTest.php      ✅ 8 tests
    └── integration/
        ├── DatabaseTest.php               ✅ 8 tests
        ├── AuthControllerTest.php         ✅ 8 tests
        ├── SystemEntitiesTableTest.php    ✅ 3 tests (STORY 2.1)
        ├── EntityMetadataTableTest.php    ✅ 4 tests (STORY 2.2)
        ├── EntityDataTableTest.php        ✅ 5 tests (STORY 2.3)
        ├── PluginsRegistryTableTest.php   ✅ 5 tests (STORY 2.4)
        ├── PluginHookRegistryTableTest.php✅ 5 tests (STORY 2.5)
        ├── GenericRepositoryTest.php      ✅ 7 tests (STORY 2.6)
        ├── MigrationIdempotenceTest.php   ✅ 3 tests (STORY 2.7)
        ├── EntityServiceTest.php          ✅ 6 tests (STORY 3.2)
        ├── EntityControllerTest.php       ✅ 9 tests (STORY 3.3)
        ├── SystemEntityTest.php           ✅ 7 tests (STORY 3.5)
        ├── PluginLoaderTest.php           ✅ 8 tests (STORY 4.1)
        ├── HookDispatcherTest.php         ✅ 11 tests (STORY 4.2)
        ├── HookIntegrationTest.php        ✅ 10 tests (STORY 4.3)
        ├── PluginClientTest.php           ✅ 13 tests (STORY 4.4)
        ├── PluginLifecycleTest.php        ✅ 8 tests (STORY 4.5)
        ├── PluginMetadataTest.php         ✅ 6 tests (STORY 4.6)
        └── SchemaExtensionTest.php        ✅ 14 tests (STORY 4.7)
```

---

## Convenciones establecidas

- **Namespace raíz:** `Xestify\`
- **Autoload:** `Xestify\Core\Container` → `backend/src/Core/Container.php`
- **Tests:** PHP scripts standalone (sin PHPUnit) en `backend/tests/unit/` e `integration/`
- **Ejecutar tests:** `php backend/tests/unit/NombreTest.php`
- **Response envelope éxito:** `{ ok: true, data: {...}, meta?: {...} }`
- **Response envelope error:** `{ ok: false, error: { code, message, details? } }`
- **Rutas dinámicas:** soporta tanto `:param` como `{param}` (Router normaliza ambos)
- **Schema de entidad:** estructura `identities` + `fields` + `custom_fields` + `relations`
- **`label_singular`:** definido explícitamente en schema metadata, nunca inferido por heurística
- **Frontend routing:** rutas tipo `entity:{slug}` para entidades dinámicas, `plugins` para gestor
- **Font Awesome:** cargado vía CDN en `frontend/src/index.html` para iconografía
- **Servidor dev:** `php -S localhost:8081 -t frontend/src tools/dev/frontend-router.php` — sirve `/tests/` y `/src/` además de la app
- **Handler de ruta:** `[Controller::class, 'method']` o `callable`

---

## Decisiones técnicas clave

1. **Sin Docker en desarrollo** — PHP nativo + PostgreSQL local. Docker solo como archivo documental al final.
2. **Sin Composer/autoload PSR-4** — autoload manual propio en `bootstrap.php`
3. **Sin frameworks** — PHP nativo con Container/Router propios
4. **JWT HS256** — `JwtService` próximo en STORY 1.2
5. **Tests standalone** — scripts PHP puros, sin dependencias externas

---

## Comandos útiles

```bash
# Arrancar servidor
php -S localhost:8080 -t backend/public/

# Ejecutar tests unitarios
php backend/tests/unit/ContainerTest.php
php backend/tests/unit/RouterTest.php
php backend/tests/unit/RequestResponseTest.php

# Ver log de commits
git log --oneline
```

---

## Próximos pasos (STORY 1.1)

1. Crear `backend/database/migrations/001_users.sql`
2. Crear `backend/src/Core/Database.php` (conexión PDO singleton)
3. Ejecutar migración: `psql -U postgres -d xestify_dev -f backend/database/migrations/001_users.sql`
4. Crear seeder: `backend/database/seeders/UserSeeder.php`
5. Registrar `Database` como singleton en `backend/src/config/app.php`
6. Tests: migración idempotente + seeder crea admin
# Sesion 2026-05-03 - Cierre tecnico MVP hasta STORY 6.4

Correcciones implementadas tras auditoria:

- `Router -> AuthMiddleware -> Controller` como pipeline real para `/api/v1/entities/*` y `/api/v1/plugins/*`.
- `EntityService` recibe el `HookDispatcher` compartido del contenedor.
- `PluginLoader` exige y persiste `schema.json` en plugins de tipo `entity`.
- `clients` queda como slug canonico; datos legacy `client` migran a `clients`.
- `PluginExtensionController` valida plugin extension activo y registro padre existente.
- Nuevo `AppWiringTest.php` cubre seguridad y wiring real.
- Nuevo runner agrupado: `php backend/tests/run.php unit|integration-db|integration-plugins|all`.

# Sesion 2026-05-04 - STORY 6.5 Frontend - Página PluginManager

Story completada. Archivos creados/modificados:

**Nuevos (backend):**
- `backend/src/controllers/PluginManagerController.php` — GET /api/v1/plugins y PUT /api/v1/plugins/{slug}/status
- `backend/tests/integration/PluginManagerApiTest.php` — 8 tests con stubs TestPdo/TestStatement

**Nuevos (frontend):**
- `frontend/src/js/pages/PluginManager.js` — página de gestión de plugins (lista, activa/desactiva)
- `frontend/tests/PluginManagerTest.html` — 8/8 tests ✅
- `frontend/tests/css/` y `frontend/tests/js/` — assets de soporte para test runner

**Modificados:**
- `backend/src/config/app.php` — registra PluginManagerController
- `backend/src/config/routes.php` — rutas GET y PUT /api/v1/plugins
- `backend/tests/run.php` — añade PluginManagerApiTest al grupo integration-plugins
- `frontend/src/css/main.css` — estilos para PluginManager
- `frontend/src/js/main.js` — integra PluginManager en el flujo de navegación
- `frontend/src/js/modules/Navbar.js` — link Plugins condicional por `canManagePlugins`
- Todos los tests HTML de frontend — correcciones de regresiones: slug `clients` canónico, E2E flow completo

**Tests finales:**
- Backend: 28/28 archivos pasan (runner agrupado)
- Frontend: todos los tests 100% (NavbarTest 10/10, LoginTest 5/5, EntityListTest 7/7, E2ETest 12/12, PluginManagerTest 8/8, y demás)

**Cierre verificado (2026-05-04):**
- Commit de story: `7d2d313`
- Verificacion backend: `php backend/tests/run.php all` → 28/28 archivos pasan
- Verificacion frontend sintaxis: `node --check frontend/src/js/pages/PluginManager.js` y `node --check frontend/src/js/main.js`
- Backlog alineado: el siguiente punto es STORY 7.1

# Sesion 2026-05-06 - STORY 7.1 Detección de actualizaciones disponibles en PluginLoader

Story completada. Archivos creados/modificados:

**Modificados (backend):**
- `backend/src/plugins/PluginLoader.php` — nuevo `getOutdated()` y preservación de `plugins.version` en plugins ya instalados
- `backend/src/controllers/PluginManagerController.php` — nuevo endpoint `GET /api/v1/plugins/updates` reutilizando `PluginLoader`
- `backend/src/config/app.php` — inyección de `PluginLoader` en `PluginManagerController`
- `backend/src/config/routes.php` — ruta `GET /api/v1/plugins/updates`
- `backend/tests/integration/PluginLoaderTest.php` — cobertura para versión mayor, igual y menor
- `backend/tests/integration/PluginManagerApiTest.php` — test del endpoint con fixture real en disco

**Modificados (docs):**
- `docs/03-api/endpoints.md` — documentación del endpoint de updates

**Tests finales:**
- Backend: `php backend/tests/run.php all` → 28/28 archivos pasan
- Plugins: `php backend/tests/run.php integration-plugins` → 8/8 archivos pasan

**Cierre verificado (2026-05-06):**
- Commit de story: pendiente (este commit)
- Verificación crítica: `load()` ya no consume la actualización durante el boot
- Backlog alineado: el siguiente punto es STORY 7.2

# Sesion 2026-05-07 - Runtime Apache+PHP, sync explicito y rendimiento local

Sesion tecnica transversal registrada sin cierre de story formal.

Cambios principales:

- Xestify pasa a documentarse y operarse con Apache+PHP como runtime canonico en un solo origen.
- Se elimina del flujo soportado `tools/dev/frontend-router.php`.
- El frontend resuelve su `base path` de forma dinamica y funciona tanto en raiz de host como bajo alias/subruta (`/xestify`).
- El backend normaliza rutas bajo alias Apache y preserva correctamente `Authorization` en requests protegidas.
- Se sacan del boot normal:
  - `UserSeeder::seedIfEmpty()`
  - `PluginLoader::loadAll()`
- Se introducen operaciones manuales de setup:
  - `tools/setup/seed-admin-user.php`
  - `tools/setup/sync-plugins.php`
- El contrato operativo de plugins queda asi:
  - runtime normal: BD como fuente de verdad
  - sincronizacion disco -> BD: operacion explicita
- Se actualiza backlog para reservar:
  - STORY 7.2: endpoint `POST /api/v1/plugins/sync`
  - STORY 7.5: boton `Sincronizar` en PluginManager
- Se documenta la recomendacion de rendimiento local:
  - `DB_HOST=127.0.0.1`
  - `xdebug.start_with_request = trigger`

Verificacion destacada:

- Backend: `php backend/tests/run.php all` en verde tras los cambios estructurales.
- Frontend: 12 suites HTML en verde.
- Runtime real bajo Apache:
  - login operativo en `/xestify`
  - requests protegidas operativas con bearer token
  - tests frontend servidos por Apache en `/xestify/tests/*`
- Medicion antes/despues de Xdebug:
  - login: ~1103 ms -> ~389 ms
  - entities: ~530 ms -> ~91 ms

Estado resultante:

- La siguiente story formal sigue siendo `STORY 7.2 - Proceso de actualizacion con migracion de schema`.
- No hay commit de cierre asociado a esta sesion tecnica todavia.

# Sesion 2026-05-10 - STORY 7.2 Proceso de actualizacion con migracion de schema

Story completada. Archivos creados/modificados:

**Creados (backend):**
- `backend/database/migrations/006_plugin_update_history.sql` — tabla `plugin_update_history` para snapshots previos a update
- `backend/tests/integration/PluginUpdateHistoryTableTest.php` — verificacion de tabla, columnas e indice

**Modificados (backend):**
- `backend/src/plugins/PluginLoader.php` — separa `syncAll()` y `update()`, preserva runtime en sync, aplica diff de schema solo aditivo y persiste snapshots antes del update
- `backend/src/controllers/PluginManagerController.php` — nuevos endpoints admin `POST /api/v1/plugins/sync` y `POST /api/v1/plugins/{slug}/update`
- `backend/src/config/routes.php` — nuevas rutas de sync y update
- `tools/setup/sync-plugins.php` — usa `syncAll()` y deja de consumir actualizaciones durante la sincronizacion desde disco
- `backend/tests/integration/PluginLoaderTest.php` — cobertura de sync best-effort, update exitoso, diff aditivo, conflictos y rollback atomico
- `backend/tests/integration/PluginManagerApiTest.php` — cobertura API para sync/update, 403, 404 y 409
- `backend/tests/integration/MigrationIdempotenceTest.php` — incluye la migracion `006_plugin_update_history.sql`
- `backend/tests/run.php` — agrega `PluginUpdateHistoryTableTest.php` al grupo `integration-db`

**Modificados (docs):**
- `docs/03-api/endpoints.md` — documentacion de `POST /api/v1/plugins/sync` y `POST /api/v1/plugins/{slug}/update`
- `docs/11-backlog/backlog.md` — STORY 7.2 marcada como incluida y STORY 7.3 como siguiente punto
- `README.md` — corte funcional actualizado a STORY 7.2 y contrato operativo de sync/update
- `docs/README.md` — estado global actualizado a STORY 7.2
- `docs/08-operations/deploy-rpi5.md` — nota operativa del nuevo sync explicito sin consumo de runtime

**Decisiones de diseño cerradas en esta story:**
- `sync` registra plugins nuevos y detecta updates, pero preserva `plugins.version`, `plugins.schema_json` y `plugins.schema_version` de plugins ya instalados.
- `update` solo acepta evolucion de schema aditiva sobre `plugins.schema_json`.
- `onUpdate(array $context)` es opcional y no rompe `PluginLifecycleInterface`.
- Los snapshots previos al update quedan persistidos en `plugin_update_history` para preparar STORY 7.4.

**Tests finales:**
- Backend: `php backend/tests/run.php integration-plugins` → 8/8 archivos pasan
- Backend DB: `php backend/tests/run.php integration-db` → 11/11 archivos pasan
- Backend completo: `php backend/tests/run.php all` → 29/29 archivos pasan

**Cierre verificado (2026-05-10):**
- Commit de story: pendiente (este commit)
- Verificacion critica: `tools/setup/sync-plugins.php` ya no muta schema/version runtime de plugins existentes
- Backlog alineado: el siguiente punto es STORY 7.3

# Sesion 2026-08-04 - STORY 7.3 Frontend - Pagina de configuracion de plugin activado

Story completada junto con su refuerzo funcional para plugins `extension`.

**Creados (backend):**
- `backend/src/plugins/application/ExtensionPluginConfigService.php` - servicio de configuracion de plugins extension, campos activos y `target_entity`
- `backend/src/controllers/ExtensionPluginContentService.php` - normalizacion de contenido extension contra schema persistido y validacion de entidad destino
- `backend/src/controllers/ExtensionPluginDataStore.php` - acceso dedicado a `plugin_extension_data`

**Creados (frontend):**
- `frontend/src/js/pages/PluginConfig.js` - pagina admin `/plugins/{slug}/config` para configurar campos de plugins `entity` y `extension`
- `frontend/tests/PluginConfigTest.html` - 6 tests frontend para render, bloqueo de campos base, reordenacion, guardado y `target_entity`

**Modificados (backend):**
- `backend/src/plugins/application/PluginAdministrationService.php` - `getConfig()` y `saveConfig()` para plugins activos configurables, versionado de schema y proteccion de campos base
- `backend/src/controllers/PluginManagerController.php` - endpoints `GET/PUT /api/v1/plugins/{slug}/config`
- `backend/src/config/app.php` y `backend/src/config/routes.php` - wiring de servicios y rutas de configuracion
- `backend/src/repositories/PluginRepository.php` - persistencia de schema configurado, incremento de `schema_version` y listado de entidades activas
- `backend/src/plugins/application/PluginSyncService.php` y `PluginSchemaReader.php` - soporte de schema en plugins `extension`
- `backend/src/controllers/PluginExtensionController.php` - uso de servicios dedicados, filtrado por `target_entity`, normalizacion por schema y resolucion de `author_name`
- `backend/src/controllers/EntityController.php` y `backend/src/services/EntityService.php` - ajustes para schema vivo y campos configurables

**Modificados (frontend y plugins):**
- `frontend/src/js/main.js` - navegacion hacia `/plugins/{slug}/config`
- `frontend/src/js/pages/PluginManager.js` - boton `Configure` para plugins `entity` y `extension` activos
- `frontend/src/css/main.css` - estilos de la pagina de configuracion y acciones del manager
- `frontend/src/js/modules/DynamicForm.js` y `DynamicTable.js` - soporte de nuevos tipos/campos configurables
- `plugins/comments/schema.json`, `Hooks.php` y `plugin.js` - schema extension configurable, `target_entity`, stamp/autor y UI de comentarios reforzada
- `plugins/clients/Hooks.php` - ajuste de compatibilidad con schema configurado

**Modificados (tests):**
- `backend/tests/integration/PluginManagerApiTest.php` - cobertura de config entity/extension, versionado y `target_entity`
- `backend/tests/integration/PluginSyncServiceTest.php` - schema de extension persistido y backfill desde disco
- `backend/tests/integration/CommentsPluginTest.php` - 17 tests para extension activa, filtro por entidad, autor, stamp y errores
- `backend/tests/unit/PluginSchemaReaderTest.php` y `EntityServiceHooksTest.php` - cobertura de schema extension y hooks
- `frontend/tests/PluginManagerTest.html` - boton configure y callback de navegacion

**Docs actualizadas:**
- `docs/11-backlog/backlog.md` - refuerzo de STORY 7.3 documentado
- `docs/03-api/endpoints.md` - endpoints de configuracion de plugins
- `README.md`, `docs/README.md` y `docs/11-backlog/roadmap.md` - corte funcional actualizado a STORY 7.3

**Tests finales:**
- Backend: `php backend/tests/run.php all` -> 43/43 archivos pasan
- Frontend sintaxis: `node --check frontend/src/js/main.js`, `PluginConfig.js`, `PluginManager.js`, `DynamicForm.js`, `DynamicTable.js` y `plugins/comments/plugin.js`

**Cierre verificado (2026-08-04):**
- Commit de story: pendiente (este commit)
- Verificacion critica: los campos base del plugin no se pueden editar/desactivar desde UI/API y los plugins `extension` pueden restringirse por `target_entity`
- Backlog alineado: el siguiente punto es STORY 7.4

# Sesion 2026-08-05 - STORY 7.4 Rollback manual de plugin a version anterior

Story implementada.

**Creados (backend):**
- `backend/src/plugins/application/PluginRollbackService.php` - rollback transaccional por `slug`, restaura snapshot y ejecuta `onRollback()` opcional
- `backend/tests/integration/PluginRollbackServiceTest.php` - cobertura de rollback exitoso y error cuando no hay snapshot

**Modificados (backend):**
- `backend/src/controllers/PluginManagerController.php` - endpoint `POST /api/v1/plugins/{slug}/rollback`
- `backend/src/config/routes.php` - alta de ruta rollback en plugin manager
- `backend/src/config/app.php` - wiring de `PluginRollbackService` y dependencias
- `backend/src/plugins/application/PluginAdministrationService.php` - exposición de operación `rollback()`
- `backend/src/plugins/runtime/PluginLifecycleInvoker.php` - soporte de convención opcional `onRollback(array $context)`
- `backend/src/repositories/PluginRepository.php` - restauración de estado/version/schema desde snapshot
- `backend/src/repositories/PluginUpdateHistoryRepository.php` - lectura bloqueante del snapshot aplicable por versión objetivo

**Modificados (tests):**
- `backend/tests/helpers/plugins/plugin_services.php` - builder `buildPluginRollbackService()`
- `backend/tests/integration/PluginLifecycleInvokerTest.php` - cobertura de `onRollback()` opcional
- `backend/tests/integration/PluginManagerApiTest.php` - casos API rollback: éxito, 404 y 409
- `backend/tests/run.php` - incluye `PluginRollbackServiceTest.php` en `integration-plugins`

**Docs actualizadas:**
- `docs/03-api/endpoints.md` - endpoint rollback documentado
- `docs/11-backlog/backlog.md` y `docs/11-backlog/roadmap.md` - corte funcional actualizado a STORY 7.4

**Tests finales:**
- Backend plugins: `php backend/tests/run.php integration-plugins` -> 15/15 archivos en verde
- Backend específicos de la story:
  - `PluginRollbackServiceTest.php` -> 2/2 ✅
  - `PluginLifecycleInvokerTest.php` -> 3/3 ✅
  - `PluginManagerApiTest.php` -> 18/18 ✅
- Ajuste adicional post-implementación: `AppWiringTest.php` actualizado para alinear payload de `clients` con schema vigente (`surnames` requerido), dejando el caso de wiring en verde.

**Cierre verificado (2026-08-05):**
- Commit de story: pendiente (este commit)
- Verificacion critica: el rollback restaura versión y schema desde `plugin_update_history`, y devuelve `409` cuando no existe snapshot compatible
- Backlog alineado: el siguiente punto es STORY 7.5

# Sesion 2026-08-05 - STORY 7.5 Frontend - UI de actualizacion y rollback en PluginManager

Story implementada.

**Modificados (frontend):**
- `frontend/src/js/pages/PluginManager.js` - acciones de sincronizacion, update y rollback; lectura de updates; badge de version disponible; confirmacion modal y feedback
- `frontend/src/css/main.css` - estilos de cabecera, badge de update, botones update/rollback, feedback y responsive movil
- `frontend/tests/PluginManagerTest.html` - cobertura de sync, badge/update, rollback condicional y confirmacion modal

**Modificados (backend):**
- `backend/src/repositories/PluginRepository.php` - `GET /plugins` ahora expone `can_rollback` por plugin en base a snapshots compatibles

**Docs actualizadas:**
- `docs/11-backlog/backlog.md` y `docs/11-backlog/roadmap.md` - corte funcional actualizado a STORY 7.5 y cierre de EPIC 7
- `docs/03-api/endpoints.md` - nota de contrato para `can_rollback` en listado de plugins

**Tests finales:**
- Backend plugins: `php backend/tests/run.php integration-plugins` -> 15/15 archivos en verde
- Backend API manager: `php backend/tests/integration/PluginManagerApiTest.php` -> 18/18 ✅
- Frontend sintaxis: `node --check frontend/src/js/pages/PluginManager.js` y `node --check frontend/src/js/main.js`

**Cierre verificado (2026-08-05):**
- Commit de story: pendiente (este commit)
- Verificacion critica: UI admin permite sincronizar, actualizar y hacer rollback con confirmacion modal; rollback solo visible cuando `can_rollback` es verdadero
- Backlog alineado: el siguiente punto es STORY 8.1
