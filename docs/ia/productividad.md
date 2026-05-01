# Registro de Productividad IA â€” Xestify

> Documento de anÃ¡lisis en tiempo real del impacto de IA en el desarrollo.
> Datos reales de la sesiÃ³n de implementaciÃ³n.

---

## EPIC 0 â€” PreparaciÃ³n TÃ©cnica

### STORY 0.1: Setup repositorio + estructura
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~15 min
- **AceleraciÃ³n:** ~87% âš¡
- **QuÃ© hizo IA:**
  - GenerÃ³ `.gitignore` completo (PHP, Node, OS, IDE)
  - CreÃ³ estructura de 15+ carpetas con un comando
  - GenerÃ³ `README.md` con instrucciones completas
  - CreÃ³ `.env.example` con variables tipadas
- **Iteraciones:** 1 (sin revisiÃ³n manual necesaria)
- **DecisiÃ³n manual:** Renombrar `documentacion/` â†’ `docs/` para consistencia de naming

---

### STORY 0.2: Container DI casero
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** ~20 min
- **AceleraciÃ³n:** ~94% âš¡
- **QuÃ© hizo IA:**
  - DiseÃ±Ã³ la API (`register`, `singleton`, `get`, `has`)
  - ImplementÃ³ el patrÃ³n de closure para singleton lazy-init
  - GenerÃ³ 8 tests con edge cases (sobreescritura, factory count)
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **DecisiÃ³n manual:** ninguna â€” implementaciÃ³n directa

---

### STORY 0.3: Router HTTP
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~20 min
- **AceleraciÃ³n:** ~93% âš¡
- **QuÃ© hizo IA:**
  - DiseÃ±Ã³ el sistema de named capture groups para `:param`
  - ImplementÃ³ resoluciÃ³n de controller via Container
  - GenerÃ³ 10 tests cubriendo mÃ©todos, params, trailing slash
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **DecisiÃ³n manual:** ninguna â€” implementaciÃ³n directa

---

### STORY 0.4: Request / Response helpers
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~25 min
- **AceleraciÃ³n:** ~90% âš¡
- **QuÃ© hizo IA:**
  - DiseÃ±Ã³ API de Request (query/body/header/param/bearerToken)
  - GenerÃ³ Response con envelope estÃ¡ndar y shortcuts
  - DetectÃ³ y corrigiÃ³ problema `PHP_SAPI` en tests CLI
  - GenerÃ³ 20 tests (11 Request + 9 Response)
- **Iteraciones:** 2 (1 fix por headers en CLI)
- **DecisiÃ³n manual:** Fix `PHP_SAPI !== 'cli'` para omitir headers en tests

---

## Resumen EPIC 0

| Story | Sin IA | Con IA (real) | AceleraciÃ³n real |
|-------|--------|---------------|------------------|
| 0.1 Setup | 2h | 15 min | 87% |
| 0.2 Container | 6h | 20 min | 94% |
| 0.3 Router | 5h | 20 min | 93% |
| 0.4 Request/Response | 4h | 25 min | 90% |
| 0.5 Entorno local | 1.5h | 5 min | 94% |
| 0.6 Frontend skeleton | 1.5h | 5 min | 94% |
| **TOTAL EPIC 0** | **20h** | **~1.5h** | **~92%** |

> **Nota acadÃ©mica:** La aceleraciÃ³n real (~92%) supera ampliamente el factor 1.4-1.6x previsto.
> Las stories de setup/infraestructura son donde IA acelerÃ³ mÃ¡s (boilerplate puro).
> Las stories de diseÃ±o/decisiones (Container API, envelope format) requirieron mÃ¡s juicio humano.

---

## EPIC 1 â€” AutenticaciÃ³n

### STORY 1.1: Tabla `users` + migraciÃ³n SQL + seeder
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~20 min
- **AceleraciÃ³n:** ~83% âš¡
- **QuÃ© hizo IA:**
  - GenerÃ³ migraciÃ³n SQL idempotente con UUID, JSONB, timestamps, Ã­ndice
  - DiseÃ±Ã³ `Database` como PDO singleton con `ERRMODE_EXCEPTION`
  - GenerÃ³ `UserSeeder::seedIfEmpty()` con `COUNT(*)` + prepared statement
  - CreÃ³ `DatabaseException` como excepciÃ³n de dominio (evitar `RuntimeException` genÃ©rica)
  - DetectÃ³ que el seeder debÃ­a vivir en `src/` para ser cubierto por el autoloader existente
- **Iteraciones:** 1
- **DecisiÃ³n manual:** LocalizaciÃ³n del seeder bajo `Xestify\Database\Seeders` (namespace coherente con estructura `src/`)

---

### STORY 1.2: JwtService (encode/decode HS256)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~25 min
- **AceleraciÃ³n:** ~92% âš¡
- **QuÃ© hizo IA:**
  - ImplementÃ³ Base64URL encode/decode puro PHP sin dependencias
  - UsÃ³ `hash_hmac` + `hash_equals` para firma y comparaciÃ³n timing-safe
  - GenerÃ³ `AuthException` como excepciÃ³n de dominio
  - GenerÃ³ 8 tests: roundtrip, claims iat/exp, firma incorrecta, token adulterado, malformados, expirado
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **DecisiÃ³n manual:** Solo `access_token` (sin refresh token para MVP)

---

### STORY 1.3: AuthController (POST /api/auth/login)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~15 min
- **AceleraciÃ³n:** ~92% âš¡
- **QuÃ© hizo IA:**
  - ImplementÃ³ validaciÃ³n 422 por email/password vacÃ­os
  - SQL con prepared statement para buscar usuario por email
  - `password_verify()` con 401 si no coincide
  - JSON response con `access_token` en envelope estÃ¡ndar
  - Registro automÃ¡tico en Container + ruta en `routes.php`
- **Iteraciones:** 2
- **DecisiÃ³n manual:** Responder `{ ok: true, data: { access_token } }` (sin `expires_in` en MVP)
- **Fix post-implementaciÃ³n:**
  - `login()` refactorizado a `login(array $params, ?Request $request = null)` para permitir inyecciÃ³n en tests
  - BOM UTF-8 eliminado de `index.php` (causaba fatal error `strict_types`)
  - `pdo_pgsql` habilitada en `php.ini` (estaba comentada)
  - Creado `AuthControllerTest.php` â€” 8 tests de integraciÃ³n (happy path, 401, 422, enumeraciÃ³n de usuarios)

---

### STORY 1.4: AuthMiddleware + `Request::setUser/user`
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~15 min
- **AceleraciÃ³n:** ~92% âš¡
- **QuÃ© hizo IA:**
  - AÃ±adiÃ³ `private ?array $user = null` + `setUser()` + `user()` a `Request`
  - ImplementÃ³ `AuthMiddleware::handle(Request, callable)` con try/catch `AuthException`
  - GenerÃ³ 6 tests: token vÃ¡lido, payload propagado, sin token, expirado, firma incorrecta, malformado
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **DecisiÃ³n manual:** ninguna â€” implementaciÃ³n directa

---

## Resumen EPIC 1

| Story | Sin IA | Con IA (real) | AceleraciÃ³n real |
|-------|--------|---------------|------------------|
| 1.1 Database + seeder | 2h | ~20 min | 83% |
| 1.2 JwtService | 5h | ~25 min | 92% |
| 1.3 AuthController | 3h | ~15 min | 92% |
| 1.4 AuthMiddleware | 3h | ~15 min | 92% |
| **TOTAL EPIC 1** | **13h** | **~1.25h** | **~90%** |

> **Tests EPIC 1:** 22 nuevos (14 unit + 8 integraciÃ³n). Total acumulado: **60 tests**.

---

## Observaciones metodolÃ³gicas

### Lo que IA acelerÃ³ mÃ¡s
- Boilerplate (estructura, configs, .gitignore)
- Tests unitarios: IA generÃ³ suites completas con edge cases
- CorrecciÃ³n de bugs predecibles (PHP_SAPI, headers CLI)

### Lo que requiriÃ³ decisiÃ³n humana
- Naming y estructura de carpetas (`docs/` vs `documentacion/`)
- Scope del proyecto (quitar Docker del MVP)
- DiseÃ±o de la API pÃºblica de cada clase

### PatrÃ³n observado
> IA es muy efectiva en "implementar una especificaciÃ³n clara".  
> El humano sigue siendo el responsable de "definir quÃ© construir y por quÃ©".

---

## EPIC 2 â€” Modelo de Datos Core

### STORY 2.1: Crear tabla `system_entities` (registro de entidades)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h
- **Tiempo real con IA:** ~10 min
- **AceleraciÃ³n:** ~83% âš¡
- **QuÃ© hizo IA:**
  - GenerÃ³ `002_core.sql` con UUID PK, slug UNIQUE, campos nullable y timestamps
  - CreÃ³ `SystemEntitiesTableTest.php` con 3 tests (table exists, columns, unique constraint)
  - ReplicÃ³ el patrÃ³n de DatabaseTest (connectivity probe, skip graceful, helpers)
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **DecisiÃ³n manual:** Sin FK formal a otras tablas â€” slug como clave de uniÃ³n para flexibilidad con plugins dinÃ¡micos

---

### STORY 2.4: Crear tabla `plugins_registry` (plugins instalados)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h
- **Tiempo real con IA:** ~10 min
- **AceleraciÃ³n:** ~83% âš¡
- **QuÃ© hizo IA:**
  - AmpliÃ³ `002_core.sql` con CHECK constraints para `plugin_type` y `status`
  - CreÃ³ `PluginsRegistryTableTest.php` con 5 tests (table, columns, unique, check type, check status)
  - VerificÃ³ idempotencia de la migraciÃ³n (segunda ejecuciÃ³n sin errores)
- **Iteraciones:** 1 (tests pasaron al primer intento)
- **DecisiÃ³n manual:** CHECK constraints en SQL en lugar de validaciÃ³n en PHP â€” el dominio es pequeÃ±o y estable


### STORY 2.2: Crear tabla `entity_metadata` (schema versionado)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~75% ⚡
- **Qué hizo IA:**
  - Añadió `entity_metadata` a `002_core.sql` con JSONB, CHECK constraint `schema_json ? 'fields'`
  - Creó índice compuesto `(entity_slug, schema_version)`
  - Creó `EntityMetadataTableTest.php` con 4 tests (table, columns, index, check constraint con rollback)
- **Iteraciones:** 2 (fix para endurecer test del CHECK constraint)
- **Decisión manual:** Validación de `schema_json` via `CHECK (schema_json ? 'fields')` en SQL — falla rápido en DB antes de llegar a capa de servicio

---

## Resumen EPIC 2 (parcial)

| Story | Sin IA | Con IA (real) | AceleraciÃ³n real |
|-------|--------|---------------|------------------|
| 2.1 system_entities | 1h | ~10 min | 83% |
| 2.4 plugins_registry | 1h | ~10 min | 83% |
| 2.2, 2.3, 2.5, 2.6, 2.7 | â€” | â€” | â€” |
