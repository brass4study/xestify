# Registro de Productividad IA — Xestify

> Documento de análisis en tiempo real del impacto de IA en el desarrollo.
> Datos reales de la sesión de implementación.

---

## EPIC 0 — Preparación Técnica

### STORY 0.1: Setup repositorio + estructura
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~87% ⚡
- **Qué hizo IA:**
  - Generó `.gitignore` completo (PHP, Node, OS, IDE)
  - Creó estructura de 15+ carpetas con un comando
  - Generó `README.md` con instrucciones completas
  - Creó `.env.example` con variables tipadas
- **Iteraciones:** 1 (sin revisión manual necesaria)
- **Decisión manual:** Renombrar `documentacion/` → `docs/` para consistencia de naming

---

### STORY 0.2: Container DI casero
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~94% ⚡
- **Qué hizo IA:**
  - Diseñó la API (`register`, `singleton`, `get`, `has`)
  - Implementó el patrón de closure para singleton lazy-init
  - Generó 8 tests con edge cases (sobreescritura, factory count)
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **Decisión manual:** ninguna — implementación directa

---

### STORY 0.3: Router HTTP
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~93% ⚡
- **Qué hizo IA:**
  - Diseñó el sistema de named capture groups para `:param`
  - Implementó resolución de controller via Container
  - Generó 10 tests cubriendo métodos, params, trailing slash
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **Decisión manual:** ninguna — implementación directa

---

### STORY 0.4: Request / Response helpers
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~25 min
- **Aceleración:** ~90% ⚡
- **Qué hizo IA:**
  - Diseñó API de Request (query/body/header/param/bearerToken)
  - Generó Response con envelope estándar y shortcuts
  - Detectó y corrigió problema `PHP_SAPI` en tests CLI
  - Generó 20 tests (11 Request + 9 Response)
- **Iteraciones:** 2 (1 fix por headers en CLI)
- **Decisión manual:** Fix `PHP_SAPI !== 'cli'` para omitir headers en tests

---

## Resumen EPIC 0

| Story | Sin IA | Con IA (real) | Aceleración real |
|-------|--------|---------------|------------------|
| 0.1 Setup | 2h | 15 min | 87% |
| 0.2 Container | 6h | 20 min | 94% |
| 0.3 Router | 5h | 20 min | 93% |
| 0.4 Request/Response | 4h | 25 min | 90% |
| 0.5 Entorno local | 1.5h | 5 min | 94% |
| 0.6 Frontend skeleton | 1.5h | 5 min | 94% |
| **TOTAL EPIC 0** | **20h** | **~1.5h** | **~92%** |

> **Nota académica:** La aceleración real (~92%) supera ampliamente el factor 1.4-1.6x previsto.
> Las stories de setup/infraestructura son donde IA aceleró más (boilerplate puro).
> Las stories de diseño/decisiones (Container API, envelope format) requirieron más juicio humano.

---

## EPIC 1 — Autenticación

### STORY 1.1: Tabla `users` + migración SQL + seeder
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Generó migración SQL idempotente con UUID, JSONB, timestamps, índice
  - Diseñó `Database` como PDO singleton con `ERRMODE_EXCEPTION`
  - Generó `UserSeeder::seedIfEmpty()` con `COUNT(*)` + prepared statement
  - Creó `DatabaseException` como excepción de dominio (evitar `RuntimeException` genérica)
  - Detectó que el seeder debía vivir en `src/` para ser cubierto por el autoloader existente
- **Iteraciones:** 1
- **Decisión manual:** Localización del seeder bajo `Xestify\Database\Seeders` (namespace coherente con estructura `src/`)

---

### STORY 1.2: JwtService (encode/decode HS256)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~25 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Implementó Base64URL encode/decode puro PHP sin dependencias
  - Usó `hash_hmac` + `hash_equals` para firma y comparación timing-safe
  - Generó `AuthException` como excepción de dominio
  - Generó 8 tests: roundtrip, claims iat/exp, firma incorrecta, token adulterado, malformados, expirado
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **Decisión manual:** Solo `access_token` (sin refresh token para MVP)

---

### STORY 1.3: AuthController (POST /api/auth/login)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Implementó validación 422 por email/password vacíos
  - SQL con prepared statement para buscar usuario por email
  - `password_verify()` con 401 si no coincide
  - JSON response con `access_token` en envelope estándar
  - Registro automático en Container + ruta en `routes.php`
- **Iteraciones:** 2
- **Decisión manual:** Responder `{ ok: true, data: { access_token } }` (sin `expires_in` en MVP)
- **Fix post-implementación:**
  - `login()` refactorizado a `login(array $params, ?Request $request = null)` para permitir inyección en tests
  - BOM UTF-8 eliminado de `index.php` (causaba fatal error `strict_types`)
  - `pdo_pgsql` habilitada en `php.ini` (estaba comentada)
  - Creado `AuthControllerTest.php` — 8 tests de integración (happy path, 401, 422, enumeración de usuarios)

---

### STORY 1.4: AuthMiddleware + `Request::setUser/user`
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Añadió `private ?array $user = null` + `setUser()` + `user()` a `Request`
  - Implementó `AuthMiddleware::handle(Request, callable)` con try/catch `AuthException`
  - Generó 6 tests: token válido, payload propagado, sin token, expirado, firma incorrecta, malformado
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **Decisión manual:** ninguna — implementación directa

---

## Resumen EPIC 1

| Story | Sin IA | Con IA (real) | Aceleración real |
|-------|--------|---------------|------------------|
| 1.1 Database + seeder | 2h | ~20 min | 83% |
| 1.2 JwtService | 5h | ~25 min | 92% |
| 1.3 AuthController | 3h | ~15 min | 92% |
| 1.4 AuthMiddleware | 3h | ~15 min | 92% |
| **TOTAL EPIC 1** | **13h** | **~1.25h** | **~90%** |

> **Tests EPIC 1:** 22 nuevos (14 unit + 8 integración). Total acumulado: **60 tests**.

---

## Observaciones metodológicas

### Lo que IA aceleró más
- Boilerplate (estructura, configs, .gitignore)
- Tests unitarios: IA generó suites completas con edge cases
- Corrección de bugs predecibles (PHP_SAPI, headers CLI)

### Lo que requirió decisión humana
- Naming y estructura de carpetas (`docs/` vs `documentacion/`)
- Scope del proyecto (quitar Docker del MVP)
- Diseño de la API pública de cada clase

### Patrón observado
> IA es muy efectiva en "implementar una especificación clara".  
> El humano sigue siendo el responsable de "definir qué construir y por qué".
