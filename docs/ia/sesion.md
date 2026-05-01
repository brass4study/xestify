# Estado de Sesión - Xestify con IA

> **Instrucciones de uso:**
> Al iniciar una nueva conversación con Copilot, escribe:
> _"Lee docs/ia/sesion.md y retoma el desarrollo de Xestify donde lo dejamos."_

---

## Última actualización

**Fecha:** 2026-05-01  
**EPIC activo:** EPIC 3 — Motor de Entidades Dinámicas (🔄 EN PROGRESO)  
**Próxima story:** STORY 3.3 — Crear EntityController (endpoints REST)

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

### 🔄 EPIC 3 — Motor de Entidades Dinámicas (EN PROGRESO)

| Story | Descripción | Commit | Tests |
|-------|-------------|--------|-------|
| 3.1 ✅ | ValidationService (valida contra schema JSONB) | pendiente | 8/8 ✅ |
| 3.2 ✅ | EntityService (orquestación CRUD) | pendiente | 6/6 ✅ |

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
│   ├── app.php                   ← Wiring Container + Router
│   ├── Core/
│   │   ├── Container.php         ✅ DI container
│   │   ├── Router.php            ✅ HTTP router
│   │   ├── Request.php           ✅ + setUser/user (STORY 1.4)
│   │   ├── Response.php          ✅ Response helper (envelope JSON)
│   │   └── Database.php          ✅ PDO singleton
│   ├── Controllers/
│   │   ├── HealthController.php  ✅ GET /health
│   │   └── AuthController.php    ✅ POST /api/auth/login
│   ├── Database/
│   │   └── Seeders/
│   │       └── UserSeeder.php    ✅ Seed admin on boot
│   ├── Exceptions/
│   │   ├── AuthException.php     ✅ Dominio: auth errors
│   │   ├── DatabaseException.php ✅ Dominio: db errors
│   │   └── RepositoryException.php ✅ Dominio: repository errors
│   ├── Repositories/
│   │   └── GenericRepository.php ✅ find, all, create, update, delete, restore
│   ├── Middleware/
│   │   └── AuthMiddleware.php    ✅ Valida JWT en rutas protegidas
│   ├── Services/
│   │   └── JwtService.php        ✅ HS256 puro PHP
│   └── config/
│       ├── app.php               ✅ Registra Database, JwtService, AuthController
│       └── routes.php            ✅ /health + /api/auth/login
└── tests/
    ├── unit/
    │   ├── helpers.php            ← TestSuite + assertion helpers
    │   ├── ContainerTest.php      ✅ 8 tests
    │   ├── RouterTest.php         ✅ 10 tests
    │   ├── RequestResponseTest.php✅ 20 tests
    │   ├── JwtServiceTest.php     ✅ 8 tests
    │   └── AuthMiddlewareTest.php ✅ 6 tests
    └── integration/
        ├── DatabaseTest.php                    ✅ 8 tests
        ├── AuthControllerTest.php              ✅ 8 tests
        ├── SystemEntitiesTableTest.php         ✅ 3 tests (STORY 2.1)
        ├── EntityMetadataTableTest.php         ✅ 4 tests (STORY 2.2)
        ├── EntityDataTableTest.php             ✅ 5 tests (STORY 2.3)
        ├── PluginsRegistryTableTest.php        ✅ 5 tests (STORY 2.4)
        ├── PluginHookRegistryTableTest.php     ✅ 5 tests (STORY 2.5)
        ├── GenericRepositoryTest.php           ✅ 7 tests (STORY 2.6)
        └── MigrationIdempotenceTest.php        ✅ 3 tests (STORY 2.7)
```

---

## Convenciones establecidas

- **Namespace raíz:** `Xestify\`
- **Autoload:** `Xestify\Core\Container` → `backend/src/Core/Container.php`
- **Tests:** PHP scripts standalone (sin PHPUnit) en `backend/tests/unit/`
- **Ejecutar tests:** `php backend/tests/unit/NombreTest.php`
- **Ejecutar todos:** _(ver STORY 0.7 cuando se implemente)_
- **Response envelope éxito:** `{ ok: true, data: {...}, meta?: {...} }`
- **Response envelope error:** `{ ok: false, error: { code, message, details? } }`
- **Rutas dinámicas:** `:param` → extraído como named capture group
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
