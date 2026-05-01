# Historial de Prompts y Decisiones — Xestify con IA

> Documentación de los prompts exactos usados, resultados e iteraciones.
> Utilizado para reproducibilidad y análisis de efectividad de prompts.

---

## EPIC 0 — Preparación Técnica

### STORY 0.1 — Setup repositorio
**Prompt:**
```
Crea la estructura completa de un proyecto PHP-vanilla + vanilla JS sin frameworks.
Carpetas: backend/{public,src,tests,database}, frontend/{src,tests}, docs, etc.
Genera .gitignore completo, .env.example, README.md con instrucciones locales.
```
**Resultado:** Estructura MVP lista, 12 carpetas + 3 configs, lista para desarrollo inmediato.
**Iteraciones:** 1
**Lección:** Especificar la estructura exacta evita reorganizaciones posteriores.

---

### STORY 0.2 — Container DI
**Prompt:**
```
Crea Xestify\Core\Container con métodos:
- register(string $key, callable|object $factory): void
- singleton(string $key, callable $factory): void
- get(string $key): mixed
- has(string $key): bool

Tests unitarios: sobreescritura, singleton behavior, lazy-init factory.
```
**Resultado:** 8 tests, 100% passing, patrón closure para lazy-init funciona perfecto.
**Iteraciones:** 1
**Lección:** Patrón closure permite diferir instantiación hasta primer acceso.

---

### STORY 0.3 — Router HTTP
**Prompt:**
```
Router con named capture groups: GET /posts/{id} extrae :id automáticamente.
Métodos HTTP: GET, POST, PUT, DELETE.
Resuelve controller desde Container.
Tests: trailing slash, método incorrecto, 404, parámetros.
```
**Resultado:** 10 tests, patrón regex con ?P<name> funciona limpio.
**Iteraciones:** 1
**Lección:** Regex named groups es elegante sin necesidad de parser personalizado.

---

## EPIC 1 — Autenticación

### STORY 1.1 — Tabla users + migración + seeder
**Prompt:**
```
Migración 001_users.sql:
- Tabla users: id (UUID), email (UNIQUE), password_hash (VARCHAR), role (ENUM 'admin'|'user'), created_at, updated_at
- Seeder que crea admin por defecto en boot
- 8 tests de integración: table exists, columns, constraints, seeder runs once
```
**Resultado:** Migración idempotente, Database singleton funciona, UserSeeder ejecuta en app.php sin duplicar.
**Iteraciones:** 1
**Lección:** IF NOT EXISTS + singleton previene reintentos accidentales de seeding.

---

### STORY 1.2 — JwtService (HS256)
**Prompt:**
```
JWT HS256 en PHP puro sin librerías:
- Métodos: encode(payload, secret): string, decode(token, secret): array|null
- Validar expiry, signature
- 8 tests: valid token, expired, tampered signature, missing claims
```
**Resultado:** hash_hmac para signature, json_encode para payload, 8/8 tests pasando.
**Iteraciones:** 1
**Lección:** PHP built-in hash_hmac es suficiente; no necesita `php-jwt`.

---

### STORY 1.3 — AuthController (POST /api/auth/login)
**Prompt:**
```
Endpoint POST /api/auth/login:
- Body: { "email": "...", "password": "..." }
- Lookup en tabla users
- Validar password_hash
- Retorna: { "token": "...", "user": {...} }
- 8 tests: credenciales válidas, password incorrecto, user no existe, validación input
```
**Resultado:** Endpoint funciona, inyecta Database y JwtService via Container.
**Iteraciones:** 1
**Lección:** DI container permite mocking fácil de dependencias en tests.

---

### STORY 1.4 — AuthMiddleware + Request::setUser()
**Prompt:**
```
Middleware que:
1. Extrae JWT de header Authorization: Bearer <token>
2. Decodifica via JwtService
3. Inyecta user en Request->user() para acceso posterior
4. 6 tests: token válido, token expirado, header missing, formato incorrecto
```
**Resultado:** Middleware funciona, Request::user() devuelve null si no autenticado.
**Iteraciones:** 1
**Lección:** Middleware en request pipeline es punto de entrada ideal para autenticación.

---

## EPIC 2 — Modelo de Datos Core

### STORY 2.1 — Tabla system_entities
**Prompt:**
```
STORY 2.1 — Crear tabla system_entities (registro de tipos de entidad):
- id UUID PK, slug VARCHAR(100) UNIQUE, name VARCHAR(255), source_plugin_slug VARCHAR(100) NULL, is_active BOOL DEFAULT true, timestamps
- 3 tests: table exists, 7 columns, slug UNIQUE constraint
```
**Resultado:** SQL + test 3/3 en la primera iteración
**Iteraciones:** 1
**Lección:** Slug como identificador amigable en lugar de ID numérico facilita URLs legibles.

---

### STORY 2.2 — Tabla entity_metadata
**Prompt:**
```
STORY 2.2 — Crear tabla entity_metadata con:
- id UUID PK, entity_slug VARCHAR(100), schema_version INT DEFAULT 1, schema_json JSONB NOT NULL, created_at
- CHECK constraint: schema_json ? 'fields' (objeto con clave fields obligatoria)
- Índice compuesto (entity_slug, schema_version)
- Test de integración: table exists, expected columns, índice, CHECK constraint rechaza schema_json sin fields
```
**Resultado:** SQL + test 4/4 en iteraciones — 1 ajuste en test de constraint para verificar causa exacta del error
**Iteraciones:** 2
**Lección:** Al testear CHECK constraints de PostgreSQL, verificar que el PDOException incluye el nombre de la constraint en su mensaje para distinguir el fallo correcto de otro error inesperado.

---

### STORY 2.3 — Tabla entity_data
**Prompt:**
```
STORY 2.3 — Crear tabla entity_data con:
- id UUID PK, entity_slug VARCHAR(100), owner_id UUID NULL, content JSONB DEFAULT '{}', created_at, updated_at, deleted_at
- Índices: BTREE(entity_slug), BTREE(owner_id), GIN(content)
- Soft delete via deleted_at NULL
- 5 tests: table exists, 7 columns, deleted_at nullable, GIN index, BTREE slug index
```
**Resultado:** SQL + test 5/5 en la primera iteración
**Iteraciones:** 1
**Lección:** GIN index es esencial para queries JSONB (@>, ?, etc.); declarar `owner_id` como NULL permite registros sin propietario explícito.

---

### STORY 2.4 — Tabla plugins_registry
**Prompt:**
```
STORY 2.4 — Crear tabla plugins_registry con:
- id UUID PK, plugin_slug VARCHAR(100) UNIQUE, plugin_type VARCHAR(20), version VARCHAR(20),
  status VARCHAR(20) DEFAULT 'inactive', installed_at, updated_at
- CHECK constraints: plugin_type IN ('entity', 'extension'), status IN ('active', 'inactive', 'error')
- 5 tests: table exists, 7 columns, plugin_slug UNIQUE, plugin_type CHECK, status CHECK
```
**Resultado:** SQL + test 5/5 en la primera iteración
**Iteraciones:** 1
**Lección:** CHECK constraints con valores enumerados previenen valores inválidos a nivel de base de datos.

---

### STORY 2.5 — Tabla plugin_hook_registry
**Prompt:**
```
STORY 2.5 — Crear tabla plugin_hook_registry con:
- id UUID PK, plugin_slug VARCHAR(100), target_entity_slug VARCHAR(100), hook_name VARCHAR(50), priority INT DEFAULT 10, enabled BOOL DEFAULT true
- Índice compuesto (target_entity_slug, hook_name)
- Sin FK a plugins_registry (desacoplamiento intencional)
- 5 tests: table exists, 6 columns, priority default 10, enabled default true, composite index
```
**Resultado:** SQL + test 5/5 en la primera iteración
**Iteraciones:** 1
**Lección:** Omitir FK a plugins_registry es una decisión deliberada — permite registrar hooks de plugins que aún no están instalados, lo que facilita el bootstrap del sistema.

---

### STORY 2.6 — GenericRepository (CRUD JSONB)
**Prompt:**
```
STORY 2.6 — Crear Xestify\Repositories\GenericRepository con:
- Métodos: find(id), all(slug, includeDeleted), create(slug, content, ownerId), update(id, content), delete(id), restore(id)
- Operaciones en entity_data con JSONB
- Update usa merge JSONB (operador ||) no reemplazo
- Soft delete via deleted_at
- Parámetros preparados PDO — nunca interpolación
- RepositoryException separada de DatabaseException
- 7 tests de integración con cleanup por test
```
**Resultado:** RepositoryException + GenericRepository + test 7/7 en la primera iteración
**Iteraciones:** 1
**Lección:** El operador JSONB `||` en PostgreSQL hace merge de objetos (shallow), ideal para update parcial sin sobrescribir campos no enviados.

---

### STORY 2.7 — Verificar idempotencia migración 002_core.sql
**Prompt:**
```
STORY 2.7 — Crear test de idempotencia de migración 002_core.sql:
- Test 1: Verifica todas las tablas existen (system_entities, entity_metadata, entity_data, plugins_registry, plugin_hook_registry)
- Test 2: Ejecuta 002_core.sql por segunda vez, verifica que psql sale con exit code 0
- Test 3: Inserta datos de prueba, re-ejecuta migración, verifica que datos persisten y sin duplicación
- 3 tests, sin simulación — usa psql real + PostgreSQL para garantizar idempotencia
```
**Resultado:** MigrationIdempotenceTest 3/3 tests pasan
**Iteraciones:** 1
**Lección:** La idempotencia de migraciones es crítica — el test verifica que correr la migración múltiples veces es seguro y sin efectos secundarios.

---

---

## Refactor — Calidad + Estructura

### Refactor: directorios a minúsculas + namespaces + calidad
**Prompt:**
```
Hay 165 problemas en intelephense. Los namespaces están en CamelCase (Xestify\Core)
pero los directorios en minúsculas (core/). Corrige todo:
- Actualizar namespace y use statements a Xestify\core, Xestify\controllers, etc.
- Resolver strings duplicadas con constante QUERY_EXECUTE_MSG
- Limpiar trailing whitespace
- Reducir complejidad cognitiva y número de returns en métodos
```
**Resultado:** 165 problemas → 0 problemas
**Iteraciones:** 2 (segunda para refactor de calidad SonarQube tras nuevos errores detectados)
**Lección:** En Windows, git con `core.ignorecase=false` es necesario para detectar renombrados de directorio en case-insensitive FS.

---

## EPIC 3 — Motor de Entidades Dinámicas

### STORY 3.1 — ValidationService (valida contra schema JSONB)
**Prompt:**
```
STORY 3.1 — Crear Xestify\services\ValidationService con:
- Método validate(array $data, array $schema): array (devuelve errores por campo)
- Tipos soportados: string, number, boolean, date (YYYY-MM-DD), email, select
- Validaciones: required, minLength, maxLength, min, max, options
- Schema dual: fields como mapa string=>rules O como lista [{name, type, ...}]
- 8 tests unitarios standalone: payload válido, required, tipo incorrecto, email, longitud, rango, select, lista-style
- Cumplir reglas SonarQube: ≤3 returns, complejidad cognitiva ≤15
```
**Resultado:** ValidationService + 8 tests, 0 errores intelephense, refactor automático de calidad
**Iteraciones:** 2 (segunda para reducir returns y complejidad cognitiva con switch)
**Lección:** Separar cada validación de tipo en método privado propio (`validateStringType`, `validateDateType`, etc.) reduce complejidad cognitiva y facilita añadir nuevos tipos.

---

### STORY 3.2 — EntityService (orquestación CRUD)
**Prompt:**
```
STORY 3.2 — Crear Xestify\services\EntityService con:
- Métodos: createRecord($entitySlug, $data, $ownerId), updateRecord($id, $entitySlug, $data),
  deleteRecord($id), getRecord($id), listRecords($entitySlug, $includeDeleted)
- Obtiene schema vigente de entity_metadata (schema_version DESC LIMIT 1)
- Valida con ValidationService (full para create, $requireAll=false para update)
- Persiste en entity_data via GenericRepository
- Dispara hooks (stub vacío para EPIC 4)
- Excepciones de dominio: EntityServiceException + ValidationException con getErrors()
- 6 tests de integración: create válido, create inválido, create sin schema, update parcial, delete soft, listRecords
```
**Resultado:** EntityService + 2 nuevas excepciones + 6/6 tests; se detectó BOM UTF-8 en 21 archivos que habría roto todos los tests
**Iteraciones:** 1
**Lección:** El BOM UTF-8 (EF BB BF) al inicio de archivos PHP con `declare(strict_types=1)` impide la ejecución cuando el archivo es requerido como script principal. Eliminar con PowerShell: `$bytes[3..($bytes.Length-1)]`.

---

### STORY 3.3 — EntityController (endpoints REST)
**Prompt:**
```
STORY 3.3 — Crear Xestify\controllers\EntityController con:
- GET    /api/entities/{slug}/schema        → schema_json vigente
- GET    /api/entities/{slug}/records       → listado activo con meta.total
- POST   /api/entities/{slug}/records       → crear (ValidationException→422, EntityServiceException→404)
- GET    /api/entities/{slug}/records/{id}  → registro único o 404
- PUT    /api/entities/{slug}/records/{id}  → update parcial (merge JSONB)
- DELETE /api/entities/{slug}/records/{id}  → soft delete
- Registrar rutas en config/routes.php
- Registrar bindings en config/app.php
- 9 tests E2E standalone (sin HTTP server)
```
**Resultado:** EntityController + rutas + app bindings + 9/9 tests en primera iteración
**Iteraciones:** 1
**Lección:** El patrón `ob_start() / ob_get_clean()` para capturar Response::json() en tests E2E es limpio y reutilizable; basta con construir un `Request` con body/params sintéticos.

---

### STORY 3.4 — Helpers estáticos apiSuccess/apiError en Response
**Prompt:**
```
STORY 3.4 — Añadir a Xestify\core\Response:
- public static function apiSuccess(mixed $data = null, array $meta = []): void
- public static function apiError(int $code, string $message, array $details = []): void
Cada uno delega al método de instancia existente (json / error).
Añadir 4 tests en RequestResponseTest.php (total 24 tests):
- apiSuccess() emite envelope ok:true con data y meta
- apiSuccess() omite meta cuando está vacío
- apiError() emite envelope ok:false con code y message
- apiError() incluye details de validación por campo
```
**Resultado:** 2 helpers estáticos + 4 tests → 24/24 en primera iteración
**Iteraciones:** 1
**Lección:** Los métodos estáticos que delegan a `self::make()` mantienen el patrón fluent intacto.

---

### STORY 3.5 — Modelo SystemEntity (acceso a metadata)
**Prompt:**
```
STORY 3.5 — Crear Xestify\models\SystemEntity con:
- getActive(): array            → todas las entidades activas (caché en memoria)
- getBySlug(string): ?array     → entidad por slug (usa caché, fallback a query)
- findOrFail(string): array     → igual pero lanza EntityServiceException si no existe
- Caché slug-keyed en propiedad privada, poblada una sola vez por instancia
- 7 tests de integración con fixtures temporales (insert + cleanup)
```
**Resultado:** SystemEntity + 7/7 tests en primera iteración
**Iteraciones:** 1
**Lección:** El patrón cache-on-first-load con `?array $cache = null` es limpio y evita queries redundantes sin complejidad de TTL.

---

### STORY 3.6 — Frontend Api.js (cliente HTTP genérico)
**Prompt:**
```
STORY 3.6 — Crear frontend/src/js/modules/Api.js con:
- Clase Api con constructor(baseUrl = '/api/v1')
- Métodos: get(path), post(path, body), put(path, body), delete(path)
- setToken(token|null) inyecta Authorization: Bearer en headers
- Valida envelopes { ok, data, error } — lanza ApiError(code, message, details) en ok:false
- Maneja errores de red (fetch rejection) como ApiError con code 0
- Clase ApiError extends Error con propiedades code y details
- Test runner HTML standalone (sin Node.js, sin npm) con fetch mockeado — 11 tests
```
**Resultado:** Api.js + ApiError + ApiTest.html → 11/11 en primera iteración
**Iteraciones:** 1
**Lección:** Para tests frontend vanilla sin bundler, un HTML con `type="module"` y fetch mockeado con `globalThis.fetch = async () => {}` es equivalente al patrón PHP standalone.

---

### HARDENING PRE 3.7 — Limpieza de SonarQube/VS Code
**Prompt:**
```
Antes de STORY 3.7, corrige todos los hallazgos activos de VS Code y SonarQube.
Prioriza:
- newlines finales faltantes
- strings duplicadas en asserts
- returns redundantes
- warning de variable no asignada en config/app.php
- deprecaciones en tests (setAccessible en PHP 8.5)
```
**Resultado:** Workspace sin errores en diagnósticos del editor; `DatabaseTest.php` migrado a `Closure::bind` para reset de singleton sin APIs deprecadas; bloque de calidad cerrado para iniciar STORY 3.7.
**Iteraciones:** 2
**Lección:** Para tests que necesitan tocar estado estático privado, `Closure::bind` evita depender de reflection legacy y mantiene compatibilidad hacia PHP 8.5+.

---

## Lecciones acumuladas

1. **Estructura antes de código** — Invertir 15 min en la estructura correcta evita reorganizaciones posteriores.
2. **DI container desde el inicio** — Facilita testing y desacoplamiento.
3. **Regex named groups** — Suficientemente potente para routing sin parser personalizado.
4. **PostgreSQL first** — CHECK constraints, JSONB, IF NOT EXISTS hacen migaciones seguras.
5. **Soft delete** — Preferible a hard delete para auditoría.
6. **JSONB merge operator ||** — Ideal para updates parciales sin sobrescribir.
7. **Tests de integración críticos** — Especialmente para migraciones y repositories.
8. **Caracteres encoding** — UTF-8 sin BOM en todos los archivos.
9. **php:S113** — Newline obligatoria al final de cada archivo.
10. **Directorios minúsculas** — Convención consistente en toda la estructura.
11. **Separar validaciones por tipo** — Un método por tipo facilita extensión y reduce complejidad.
12. **BOM UTF-8 en PHP** — EF BB BF antes de `<?php` rompe `declare(strict_types=1)` en scripts requeridos. Eliminar con `$bytes[3..]` en PowerShell.
