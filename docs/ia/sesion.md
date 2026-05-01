# Estado de Sesión - Xestify con IA

> **Instrucciones de uso:**
> Al iniciar una nueva conversación con Copilot, escribe:
> _"Lee docs/ia/sesion.md y retoma el desarrollo de Xestify donde lo dejamos."_

---

## Última actualización

**Fecha:** 2026-05-01  
**EPIC activo:** EPIC 1 — Autenticación  
**Próxima story:** STORY 1.1 — Tabla `users` + seeder

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

### 🔄 EPIC 1 — Autenticación (EN PROGRESO)

| Story | Descripción | Estado |
|-------|-------------|--------|
| 1.1 | Tabla `users` + migración SQL + seeder | ⏳ Siguiente |
| 1.2 | JwtService (encode/decode HS256) | ⏳ |
| 1.3 | AuthController (POST /api/auth/login) | ⏳ |
| 1.4 | AuthMiddleware (valida JWT en rutas protegidas) | ⏳ |

### ⏭ EPIC 2-5 — Pendiente

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
├── src/
│   ├── bootstrap.php             ← Autoloader + env loader
│   ├── app.php                   ← Wiring Container + Router
│   ├── Core/
│   │   ├── Container.php         ✅ DI container
│   │   ├── Router.php            ✅ HTTP router
│   │   ├── Request.php           ✅ Request helper
│   │   └── Response.php          ✅ Response helper (envelope JSON)
│   ├── Controllers/
│   │   └── HealthController.php  ✅ GET /health
│   └── config/
│       ├── app.php               ← Service registrations
│       └── routes.php            ← Route definitions
└── tests/unit/
    ├── ContainerTest.php         ✅ 8 tests
    ├── RouterTest.php            ✅ 10 tests
    └── RequestResponseTest.php  ✅ 20 tests
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
