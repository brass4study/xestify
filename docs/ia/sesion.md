# Estado de Sesión - Xestify con IA

> **Instrucciones de uso:**
> Al iniciar una nueva conversación con Copilot, escribe:
> _"Lee docs/ia/sesion.md y retoma el desarrollo de Xestify donde lo dejamos."_

---

## Última actualización

**Fecha:** 2026-05-02  
**EPIC activo:** EPIC 6 — Plugins tipo Extension (⏭ SIGUIENTE)  
**Próxima story:** STORY 6.1 — Frontend - Crear módulo DynamicTabs.js

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
| 2.1 ✅ | Tabla `system_entities` + migración 002_core.sql | `2c88d64` | 3/3 ✅ |
| 2.2 ✅ | Tabla `entity_metadata` (schema versionado) | `0445672` | 4/4 ✅ |
| 2.3 ✅ | Tabla `entity_data` (registros de negocio) | `195db58` | 5/5 ✅ |
| 2.4 ✅ | Tabla `plugins_registry` (plugins instalados) | `17fa5df` | 5/5 ✅ |
| 2.5 ✅ | Tabla `plugin_hook_registry` (hooks registrados) | `3352b4a` | 5/5 ✅ |
| 2.6 ✅ | GenericRepository (CRUD JSONB) | `58a2670` | 7/7 ✅ |
| 2.7 ✅ | Verificar idempotencia 002_core.sql | `906b595` | 3/3 ✅ |

**Archivos creados (EPIC 2 hasta ahora):**
- `backend/database/migrations/002_core.sql` — tablas system_entities + entity_metadata + entity_data + plugins_registry + plugin_hook_registry
- `backend/tests/integration/SystemEntitiesTableTest.php` — 3 tests
- `backend/tests/integration/EntityMetadataTableTest.php` — 4 tests
- `backend/tests/integration/EntityDataTableTest.php` — 5 tests
- `backend/tests/integration/PluginsRegistryTableTest.php` — 5 tests
- `backend/tests/integration/PluginHookRegistryTableTest.php` — 5 tests
- `backend/src/Exceptions/RepositoryException.php`
- `backend/src/Repositories/GenericRepository.php` — find, all, create, update, delete (soft), restore
- `backend/tests/integration/GenericRepositoryTest.php` — 7 tests
- `backend/tests/integration/MigrationIdempotenceTest.php` — 3 tests (idempotencia 002_core.sql)

### ⏭ EPIC 3-5 — Pendiente

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
│       ├── 001_users.sql         ✅ Tabla users
│       └── 002_core.sql         ✅ system_entities + entity_metadata + entity_data + plugins_registry
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
- **Servidor dev:** `php -S localhost:8081 -t frontend/src tools/dev/frontend-router.php`
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
