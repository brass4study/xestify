# Historial de Prompts y Decisiones — Xestify con IA

> Documentación de los prompts exactos usados, resultados e iteraciones.
> Utilizado para reproducibilidad y análisis de efectividad de prompts.

---

## Cambio reciente — SonarQube + roadmap EPIC 9

**Prompt:**
```
Analiza los hallazgos de SonarQube/VS Code del workspace, corrige los problemas detectados en frontend/backend y en la skill local de revisión, y actualiza la documentación del roadmap para reflejar el estado real de la Fase 9.
```
**Resultado:** Correcciones aplicadas en componentes UI, páginas y scripts de análisis, con el roadmap de EPIC 9 alineado a la implementación real.
**Iteraciones:** 2
**Lección:** La ruta más fiable para obtener hallazgos consistentes es la exportación desde la skill local; los artefactos temporales deben excluirse del commit para mantener el árbol limpio.

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

### STORY 3.7 — Frontend State.js (estado global)
**Prompt:**
```
STORY 3.7 — Frontend - Crear State.js (estado global):
- Objeto AppState con setUser(), getUser(), setCurrentEntity(), etc.
- Métodos setter/getter simples
- Sin listeners y sin Proxy (Vanilla puro)
- Añadir tests unitarios standalone en HTML runner
```
**Resultado:** `State.js` implementado como objeto global simple + `StateTest.html` con 11 pruebas (11/11 pasando en navegador local).
**Iteraciones:** 1
**Lección:** Un `AppState` explícito con setters/getters y `reset()` simplifica pruebas y evita acoplar componentes frontend en etapas tempranas.

---

### STORY 3.8 — Frontend DynamicForm.js
**Prompt:**
```
STORY 3.8 — Frontend - Crear DynamicForm.js:
- Clase que recibe schema y container
- render() genera inputs por tipo
- validate() valida en cliente
- getData() devuelve object con valores
- Soporta string, number, email, date, select, boolean
- Añadir tests: render diferentes tipos + validación básica
```
**Resultado:** `DynamicForm.js` implementado con renderizado por schema, lectura de datos tipados y validación básica en cliente; `DynamicFormTest.html` con 6 pruebas (6/6 pasando en navegador local). Además, se ajustaron hallazgos SonarQube en `Api.js` y `ApiTest.html` sin regresiones (11/11).
**Iteraciones:** 2
**Lección:** Añadir una opción placeholder vacía en `select` mejora el manejo de `required` en formularios dinámicos y evita falsos positivos al validar.

---

### STORY 3.9 — Frontend DynamicTable.js
**Prompt:**
```
STORY 3.9 — Frontend - Crear DynamicTable.js:
- Clase para renderizar tabla de registros
- Recibe records y schema
- Renderiza columnas dinámicamente
- Manejo básico de paginación
- Añadir tests unitarios standalone
```
**Resultado:** `DynamicTable.js` implementado con render de columnas dinámicas, render de filas por página y controles `Prev/Next`; `DynamicTableTest.html` con 6 pruebas (6/6 pasando en navegador local).
**Iteraciones:** 1
**Lección:** Mantener la paginación como estado interno (`currentPage` + `pageSize`) simplifica integración posterior con vistas `EntityList` y evita recalcular offsets en cada componente consumidor.

---

### STORY 3.10 — Frontend EntityList
**Prompt:**
```
STORY 3.10 — Frontend - Crear página EntityList:
- Clase EntityList en frontend/src/js/pages/
- init(): carga GET /entities y renderiza botones de selección
- loadEntity(slug): carga GET /entities/:slug/records y usa DynamicTable
- Botón "Crear nuevo registro" con callback onCreateNew
- Integración con AppState (entities, currentEntity, records)
- Tests en HTML runner con mockFetch
```
**Resultado:** `EntityList.js` implementado con render completo; `EntityListTest.html` con 7 pruebas (7/7 pasando en navegador local).
**Iteraciones:** 2 (corrección duck-typing en constructor + orden de claves en mockFetch)
**Lección:** Al hacer mock de fetch con prefijos URL como `/entities`, hay que ordenar las claves por longitud descendente para que `/entities/client/records` coincida antes que `/entities`.

---

### STORY 3.11 — Frontend EntityEdit
**Prompt:**
```
STORY 3.11 — Frontend - Crear página EntityEdit:
- Clase EntityEdit en frontend/src/js/pages/
- constructor(container, slug, schema, options)
- Renderizar DynamicForm desde schema
- Pre-rellenar con initialData cuando se edita
- submit(): POST (crear) o PUT (editar según recordId)
- Mostrar errores por campo (ApiError.details) y banner global
- Callbacks onSaved y onCancel configurables
- Tests en HTML runner con mockFetch
```
**Resultado:** `EntityEdit.js` implementado completo; `EntityEditTest.html` con 12 pruebas (12/12 pasando al primer intento).
**Iteraciones:** 1
**Lección:** Pre-rellenar formulario mapeando `initialData` a `field.default` reutiliza la lógica interna de DynamicForm sin necesidad de añadir método `setValue` al componente.

---

## EPIC 4 — Sistema de Plugins y Hooks Backend

### STORY 4.1 — PluginLoader
**Prompt:**
```
STORY 4.1 — Crear PluginLoader:
- Explora backend/plugins/ y lee manifest.json de cada plugin
- Valida compatibilidad (core_version del plugin <= CORE_VERSION actual)
- Registra plugin en plugins_registry si nuevo; actualiza version si ya existe
- Carga Hooks.php del plugin con require_once
- Tests de integración standalone con fixtures temporales (sys_get_temp_dir)
```
**Resultado:** `PluginLoader.php` + `PluginException.php` implementados; `PluginLoaderTest.php` con 8 pruebas (8/8 pasando).
**Iteraciones:** 1
**Lección:** Usar `sys_get_temp_dir()` con nombre aleatorio (`bin2hex(random_bytes(4))`) para fixtures de filesystem en tests de integración garantiza aislamiento sin interferir con otros tests.

---

### STORY 4.2 — HookDispatcher
**Prompt:**
```
STORY 4.2 — Crear HookDispatcher:
- register($hook, $callback, $priority=10)
- execute($hook, $context): ejecuta callbacks en orden prioridad ascendente
- beforeXxx: si callback lanza, propagar excepción (bloquear operación)
- afterXxx: si callback lanza, log warning y continuar
- Tests unitarios standalone
```
**Resultado:** `HookDispatcher.php` + `HookException.php` implementados; `HookDispatcherTest.php` con 11 pruebas (11/11 al primer intento).
**Iteraciones:** 1
**Lección:** Los wildcards `*` en docblocks PHP (e.g. `before*`) son interpretados como operadores por el linter de VS Code — usar `beforeXxx` / `afterXxx` como alternativa segura.

---

### STORY 4.3 — Hooks beforeSave/afterSave en EntityService
**Prompt:**
```
STORY 4.3 — Integrar HookDispatcher en EntityService:
- Inyectar HookDispatcher (nullable) en constructor
- createRecord/updateRecord: beforeSave antes de persistir, afterSave después
- beforeSave puede mutar context['data'] antes de llegue al repo
- Tests unitarios con stubs (sin BD)
```
**Resultado:** `EntityService` modificado; `EntityServiceHooksTest.php` con 10 pruebas (10/10 al primer intento).
**Iteraciones:** 1
**Lección:** Usar `?HookDispatcher $hooks = null` como parámetro opcional mantiene compatibilidad retroactiva con tests de integración existentes sin modificarlos.

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

---

### STORY 4.4 Crear plugin entity_client
**Prompt:**
```
 STORY 4.4 ï¿½ Crear plugin de entidad base entity_client:
 - Estructura: manifest.json (slug, name, version, type, core_version)
 - schema.json con campos nombre (required), email (required), telï¿½fono (optional), activo (boolean, default true)
 - Hooks.php: hook beforeSave que valida email ï¿½nico en entity_data
 - Installer.php: registra entidad en system_entities + siembra schema en entity_metadata (idempotente)
 - Tests unitarios con stubs PDO
```
**Resultado:** 13/13 tests unitarios pasando al primer intento
**Iteraciones:** 1

---

### STORY 4.5 — Ciclo de vida de plugin

**Prompt:**
```
STORY 4.5 — Implementar ciclo de vida de plugins (onInstall, onActivate, onDeactivate):
 - PluginLifecycleInterface: contrato con los tres métodos void
 - PluginLoader: registerPlugin() retorna bool (nuevo=true), load() llama onInstall si es nuevo,
   añadir activate() y deactivate() que actualizan status + llaman al hook correspondiente
 - entity_client/Lifecycle.php: onInstall llama Installer::install()
 - Tests de integración (8 tests) con BD real y fixtures temporales en sys_get_temp_dir()
```
**Resultado:** 8/8 tests de integración pasando
**Iteraciones:** 2 (fix path helpers.php + `Database::connection()`)

---

### STORY 4.6 — Metadatos de plugin (dependencias)

**Prompt:**
```
STORY 4.6 — Validar dependencias entre plugins en manifest.json:
 - Campo opcional `requires` en manifest.json: array de {slug, version}
 - PluginLoader::validateDependencies(): comprueba plugins_registry antes de cargar
 - Bloquear instalación con PluginException si dep faltante o versión insuficiente
 - Tests de integración: 6 casos (sin requires, dep ausente, dep presente, versión baja, entry inválida, sin version)
```
**Resultado:** 6/6 tests de integración pasando al primer intento
**Iteraciones:** 1

---

### STORY 4.7 — Schema final de entidad (identities/fields/custom_fields/relations)

**Prompt:**
```
STORY 4.7 — Implementar contrato final de schema:
 - Separar identidad técnica en `identities` (id autogenerado)
 - Definir campos obligatorios de dominio en `fields`
 - Mantener sugerencias opcionales para frontend en `custom_fields`
 - Definir relaciones en `relations` como opcionales con `required:false`
 - No duplicar FK como custom_field obligatoria: inferir por `target_entity` + `target_field`
 - Caso de negocio: pedido con cliente opcional (pedido anónimo válido)
 - Actualizar tests y documentación técnica/backlog
 - Aplicar normativa de naming: entidad/plural y plugin sin prefijo entity_ (clients)
```
**Resultado:** 14/14 tests unitarios de plugin + 22/22 tests integración de plugins en verde; contrato y naming final (`clients`) alineados en código y documentación
**Iteraciones:** 4 (aclaración progresiva de semántica de relaciones opcionales + rename final de plugin)

---

## EPIC 5 — Frontend Dinámico Base

### STORY 5.1 — Frontend - Crear página Login

**Prompt:**
```
Implementa STORY 5.1 completa en frontend:
- Crear página Login (frontend/src/js/pages/Login.js) con formulario email/password
- Consumir POST /api/v1/auth/login usando Api.js
- Guardar access_token y mostrar error si credenciales inválidas
- Integrar flujo en main.js: si no hay token mostrar Login, si hay token mostrar dashboard
- Añadir botón de logout
- Crear LoginTest.html con pruebas de render, validación, éxito y error
- Mantener arquitectura vanilla JS actual y AppState existente
```
**Resultado:** Login funcional integrado en el entrypoint, token persistido en `localStorage`, logout operativo, test `LoginTest.html` en verde (5/5) y helper `tools/dev/frontend-router.php` para prueba local same-origin sin tocar `API_BASE`.
**Iteraciones:** 3 (ajuste anti-warning Sonar en `main.js`, fallback MIME por ausencia de `mime_content_type`, limpieza de conflicto de servidor local en 8081)

### STORY 5.2 — Frontend - Crear navbar/sidebar de navegación

**Prompt:**
```
Implementa STORY 5.2 completa:
- Crear módulo Navbar (frontend/src/js/modules/Navbar.js) con brand, links a entities y plugins, email del usuario, botón logout
- Usar callbacks onLogout y onNavigate para comunicación con main.js
- Actualizar main.js para que renderDashboard use Navbar + función navigateTo que renderice EntityList o placeholder de plugins
- Añadir email del usuario al response del AuthController y propagarlo hasta AppState
- Estilos completos en main.css
- NavbarTest.html con tests de constructor, render, links, email, logout, navigate y active state
```
**Resultado:** Navbar operativa con routing básico entre Entidades y Plugins, email del usuario visible, sesión `docs/ia` actualizada y commit listo.
**Iteraciones:** 1

### STORY 5.3 — Frontend - Integración E2E EntityList + EntityEdit

**Prompt:**
```
Implementa STORY 5.3 completa:
- Conectar EntityList → EntityEdit en main.js: cuando onCreateNew dispara, mostrar EntityEdit en el mismo content area
- Cuando EntityEdit.onSaved: volver a EntityList y recargar los registros de la entidad guardada
- Cuando EntityEdit.onCancel: volver a EntityList sin recargar registros específicos
- Crear E2ETest.html con tests E2E usando mock fetch que cubran el flujo completo
```
**Resultado:** Flujo completamente integrado en `main.js` con `showEntityList`/`showEntityEdit`. `E2ETest.html` con 9 tests que cubren cada paso del flujo.
**Iteraciones:** 1

### STORY 5.3b — Fix GET /api/v1/entities + EntitySeeder + UTF-8

**Prompt:**
```
La página web solo muestra un panel vacío. GET /api/v1/entities devuelve 404.
Añade el endpoint listEntities, registra la ruta, crea un EntitySeeder con entidades demo
y llámalo desde app.php. Además corrige el encoding UTF-8 en la respuesta JSON.
```
**Resultado:** Endpoint activo, EntitySeeder crea Clientes/Productos al arrancar, respuesta JSON con `charset=utf-8` y PDO con `client_encoding=UTF8`.
**Iteraciones:** 3 (path bootstrap, BASE_PATH, UTF-8 fix)

### STORY 5.3c — Fix Router params + tabla registros

**Prompt:**
```
En la web, acabo de dar de alta 2 clientes, y veo una especie de tabla muy pequeña,
pero no me muestra los datos. Soluciona el tamaño de la tabla y la visualizacion de
los registros.
```
**Resultado:** Se corrigió el router para soportar `{slug}` y evitar 404 en records; se normalizaron filas `content` JSONB en `EntityList` para mostrar datos reales; y se mejoró el CSS de tabla para tamaño/legibilidad.
**Iteraciones:** 2

### STORY 5.4 — Frontend - Crear Modal/Dialog reutilizable

**Prompt:**
```
Continuemos con el siguiente story.
Implementa STORY 5.4: crear Modal/Dialog reutilizable con clase Modal,
métodos show(), close(), setContent() y estilos básicos.
```
**Resultado:** Se creó `Modal.js` con API reutilizable, comportamiento de cierre (botón, backdrop y Escape), estilos base en `main.css` y `ModalTest.html` con 5 pruebas.
**Iteraciones:** 1

### STORY 5.5 — Frontend - Mejoras responsive + refinamiento navbar/tabla

**Prompt:**
```
Continuemos con la siguiente story y ajustemos UX del frontend:
- Navbar sin sección "Entidades" y con enlaces por entidad
- Usuario + salir a la derecha
- Correcciones visuales de tabla, botones (crear/editar), iconos Font Awesome y paginación
- Estados hover/disabled consistentes y layout igual entre Chrome y navegador integrado
- Crear {singular} usando propiedad de entidad en lugar de heurística
```
**Resultado:** Navbar dinámico por entidad, bloque derecho consistente, selector de entidades eliminado del contenido, botón crear con icono y `label_singular`, acciones/paginación iconificadas con estilos unificados, backend actualizado para exponer `label_singular` y seeder versionado.
**Iteraciones:** 6

---

## Sesión Planning — Backlog y Roadmap (2026-05-02)

### Planning A1/A2 — Desglose en EPIC/STORY

**Prompt:**
```
Ok, desglosa A1 y A2 en EPIC/STORY
```
**Resultado:** EPIC A1 con 4 stories (tabla audit_logs, AuditService, hooks en acciones críticas, endpoint+vista admin) y EPIC A2 con 4 stories (modelo roles/permissions, AuthorizationService, enforcement en endpoints, UI condicional). Cada story con Points, Priority, Type, Criteria, IA Usage y Dependencias.
**Iteraciones:** 1
**Lección:** Dar contexto previo de backlog al agente produce stories alineadas con el estilo existente sin fricción.

---

### Planning EPIC 6-10 — Desglose completo

**Prompt:**
```
El EPIC 6 no son "extensiones complejas" son plugins del tipo extension, tal y como habíamos documentado.
Y veo que has añadido EPIC 6, 7 y 8 como OUT OF SCOPE deben estar IN SCOPE y antes de los adicionales
```
**Resultado:** EPIC 6-10 movidos a IN SCOPE, EPIC 6 renombrado a "Plugins tipo extension", 5 EPICs documentados con 4 stories cada uno antes de A1/A2. STORY 7.5 añadida por petición explícita para configuración de plugins.
**Iteraciones:** 3 (ajuste scope 9-10, STORY 7.5, renombrado)
**Lección:** Las correcciones conceptuales de nomenclatura hay que hacerlas desde el primer momento; "extensiones complejas" vs "plugins tipo extension" cambia el entendimiento del sistema.

---

### Planning — Actualizar roadmap

**Prompt:**
```
Actualiza el roadmap
```
**Resultado:** `docs/roadmap.md` reescrito con: decisiones técnicas resueltas en tabla, estado real de 10 fases + A1/A2, tabla de corte MVP, hitos actualizados A-G, métricas de seguimiento y DoD por fase. Eliminado contenido obsoleto (decisiones pendientes, comparativa frameworks).
**Iteraciones:** 1
**Lección:** Un roadmap desactualizado es más confuso que no tenerlo. Reescribir desde cero con estado real es más rápido que parchear.

---

### Planning — Revisión y actualización de toda la documentación

**Prompt:**
```
Revisa toda la documentación, analízala y actualízala allí donde sea necesaria según todas las consideraciones y pasos que ya hemos realizado
```
**Resultado:** Actualizados en una sola pasada: `sesion.md` (EPIC 5 completado, estructura de archivos real, convenciones actualizadas), `MASTER-brief.md` (scope corregido EPIC 0-10 in scope, timeline con estado real, demo actualizada), `productividad.md` (entradas de sesión planning), `prompts.md` (esta entrada).
**Iteraciones:** 1
**Lección:** Tener un agente que mantiene consistencia entre múltiples archivos de documentación simultáneamente es donde la IA aporta más valor en fases de planning.

---

## EPIC 6 — Plugins tipo Extension

### STORY 6.1 — Frontend - Crear módulo DynamicTabs.js

**Prompt:**
```
arranca el story 6.1
```
**Resultado:** `DynamicTabs.js` con API completa (`registerTab`, `render`, `setActiveTab`, `getActiveTab`, `destroy`), hash persistence, deduplicación. `DynamicTabsTest.html` con 6 tests en estilo del proyecto. Estilos de tabs en `main.css`. Fix en `frontend-router.php` para servir `/tests/` y `/src/` (bloqueante de módulos JS).
**Iteraciones:** 3 (MIME type error, estilo tests incorrecto, router incompleto)
**Lección:** El router de desarrollo no cubría las rutas de tests — es una infraestructura que hay que verificar al añadir nuevas carpetas servidas. El estilo de los tests debe compararse visualmente antes de dar por bueno.

### STORY 6.2 — Backend - Hook `registerTabs` y `registerActions` en HookDispatcher

**Prompt:**
```
Sigamos
```
**Resultado:** Método `applyFilter()` añadido a `HookDispatcher`. Semántica filter: callbacks reciben y retornan array acumulado (`$items`), fallos son tolerantes (log + continuar). `HookFilterTest.php` con 7 tests unitarios. Endpoint `GET /api/v1/entities/{slug}/tabs` añadido a `EntityController`, ruta en `routes.php`, `HookDispatcher` registrado como singleton en `config/app.php`. `HookFilterApiTest.php` con 6 tests de integración verificando que el plugin registra tab y aparece en la respuesta de la API. Regresión: 11 tests previos siguen pasando.
**Iteraciones:** 2 (primera sin endpoint API, segunda tras corrección del criterio "aparece en respuesta de API")
**Lección:** `applyFilter` es mejor nombre que `filter` para evitar confusión con built-ins de PHP. El criterio "plugin registra tab y aparece en respuesta de API" implica un test de integración con endpoint real, no solo unitario — leer los criterios con más detalle antes de implementar.

### STORY 6.4 — Plugin `comments` (tipo extension)

**Prompt:**
```
Sigamos con STORY 6.3
```
**Resultado:** Plugin `comments` completo: `manifest.json` (type=extension, target_entity=*), `schema.json` (campos body/author_id), `Hooks.php` (registra `registerTabs`), `Lifecycle.php` (onInstall inserta en `plugin_hook_registry`, sin tabla propia). `CommentsController.php` usa tabla genérica `plugin_extension_data` con content JSONB. Migración `003_plugin_extension_data.sql` como tabla compartida por todos los plugins extension. 9 tests de integración pasando.
**Iteraciones:** 3 (primera con tabla `plugin_comments` propia — incorrecto; segunda corrigiendo a tabla genérica y añadiendo schema.json; tercera corrigiendo duplicación de código en CommentsController)
**Lección:** Los plugins de tipo `extension` NO crean tablas propias — usan `plugin_extension_data` igual que los de tipo `entity` usan `entity_data`. Verificar siempre que el patrón genérico se mantiene consistente antes de implementar.

### STORY 6.3 — Release B: Eliminar system_entities (plugins como única fuente de verdad)

**Prompt:**
```
Si analizamos la tabla 'system_entities' pasa lo mismo que con todo lo que acabamos de hacer, son tablas con los mismos registros, ¿por que estan separadas?
[...discusión arquitectónica...]
Si
```
**Resultado:** Eliminación completa de `system_entities`. Migración `010_drop_system_entities.sql` (DROP TABLE IF EXISTS). `SystemEntity.php` redirigido a consultar `plugins WHERE plugin_type='entity'`. `SystemEntitiesTableTest.php` reescrito para verificar que la tabla ya NO existe + 2 tests sobre el catalog en plugins. `MigrationIdempotenceTest.php` actualizado: system_entities eliminado de lista esperada, test de datos redirigido a plugins, migración 010 añadida. `SystemEntityTest.php` fixtures redirigidos a plugins (INSERT ON CONFLICT, DELETE). Migración aplicada a xestify_dev. Suite completa: 11 suites, 0 fallos.
**Iteraciones:** 2 (un test fallaba por filas de test sin `name` en plugins — corregido filtrando a `status='active'`)
**Lección:** Al filtrar filas de catálogo en tests, siempre filtrar por el estado esperado en producción (`status='active'`) para evitar que filas de test sucias interfieran.

### Fix 6.5-pre — PluginLoader wiring: `registerActiveHooks()` en boot

**Prompt:**
```
Ok, ejecuta esas correcciones
```
*(Tras análisis que detectó que HookDispatcher siempre estaba vacío al arrancar porque PluginLoader nunca se instanciaba en app.php)*

**Resultado:** `PluginLoader::registerActiveHooks(HookDispatcher $dispatcher)` añadido — consulta `plugins WHERE status='active'`, llama `loadHooks()` + `instantiateHooks()` por cada slug activo. `instantiateHooks()` usa `ReflectionClass` para detectar si el constructor necesita `PDO` o no. `app.php` registra `PluginLoader` como singleton y llama `registerActiveHooks()` al boot. `PluginBootTest.php` con 3 tests verificando boot real. Tab "Comentarios" confirmada en `GET /api/v1/entities/client/tabs` desde servidor en vivo.
**Iteraciones:** 1
**Lección:** El wiring de boot debe incluir NO solo registrar singletons en el container, sino también ejecutar las operaciones de inicialización (como registrar hooks). Un singleton registrado pero nunca instanciado ni invocado no tiene efecto. Usar Reflection para instanciar plugins con dependencias variables es más robusto que un switch/mapa hardcodeado.

### Fix general — arquitectura plana de plugins y desacoplamiento frontend/backend

**Prompt:**
```
No, haz un repaso de toda la documentacion para actualizar todo aquello que hayamos cambiado
```
*(y posteriores iteraciones para cerrar commit/push como fix general, no asociado a story específica)*

**Resultado:** Refactor transversal completado: plugins migrados a `/plugins/{slug}` en estructura plana, rutas y loader adaptados, `PluginExtensionController` genérico sustituyendo `CommentsController`, `EntityEdit` desacoplado vía `PluginPanelRegistry` e import dinámico de `plugin.js`, UI comments encapsulada en plugin con corrección de botones en edición, `frontend-router.php` sirviendo `/plugins/*`, tests clave actualizados y documentación mayor revisada/alineada.
**Iteraciones:** 5
**Lección:** Cuando una corrección cruza arquitectura, runtime y documentación, conviene tratarla como fix general de coherencia del sistema y no como scope de una única story.

---

### Fix SonarQube — 44 hallazgos de calidad

**Prompt:**
```
Revisa los findings de sonarqube, tenemos 44 hallazgos
```

**Resultado:** 44 hallazgos resueltos en 11 archivos: constantes para literales duplicados, complejidad reducida extrayendo helpers, tipos de excepción corregidos (`TypeError` vs `Error`, `\AssertionError` vs `\RuntimeException`), condiciones negadas invertidas, imports absolutos → relativos, `String#replace(/g)` → `replaceAll()`, `RegExp#exec()` en lugar de `String#match()`, escapes innecesarios eliminados, y regla `S1848` desactivada vía `.vscode/settings.json` para falsos positivos en tests HTML con side-effects de render.
**Iteraciones:** 2
**Lección:** En tests HTML con vanilla JS, `new Component(container)` sin asignación es idioma legítimo cuando el constructor renderiza en el DOM. SonarLint S1848 es un falso positivo en este contexto; desactivar la regla localmente es la solución correcta.



---

### STORY 6.5 - Frontend - Página PluginManager

**Prompt:**
```text
Implementa la Story 6.5: Frontend - Página PluginManager.
Necesito una página que liste los plugins instalados y permita activar/desactivar cada uno.
También necesito el backend: endpoints GET /api/v1/plugins y PUT /api/v1/plugins/{slug}/status.
```

**Resultado:** PluginManagerController.php + rutas + PluginManager.js + PluginManagerTest.html (8/8). Además se corrigieron regresiones en NavbarTest, LoginTest, EntityListTest y E2ETest, y se actualizó el slug de fixtures de `client` a `clients` (slug canónico). El test E2E integrado se completó simulando el flujo real list/create/reload.
**Iteraciones:** 6
**Lección:** Al cambiar el contrato de un componente (Navbar con canManagePlugins), hay que revisar todos los tests que lo usan. El E2E integrado con Promise requiere simular exactamente los eventos que el código real espera (click en botón Guardar, no submit del form).


---

## EPIC 7 - Actualizaciones de Plugins y Rollback

### STORY 7.1 - Detección de actualizaciones disponibles en PluginLoader

**Prompt:**
```text
Estoy implementando la story 7.1 a través del plugin GitHub Copilot, ha realizado cambios pero no me fío de la validez de esos cambios. Comprueba que lo ha implementado correctamente, que el código nuevo introducido es válido, que su formato y estructura es acorde a la solución. Revisa los tests para que funcionen igual que los demás test de la solución y que pasan correctamente
```

**Prompt de corrección:**
```text
Ok, procede con las correcciones, también me he fijado en que los TestSuites nuevos están con los textos en castellano, no siguen los patrones existentes en los demás testsuite de toda la solución, adáptalos
```

**Prompt de cierre:**
```text
Cerremos la story 7.1
```

**Resultado:** STORY 7.1 cerrada. PluginLoader::getOutdated() detecta actualizaciones comparando la versión instalada contra el manifest en disco, el endpoint GET /api/v1/plugins/updates expone la lista y los tests cubren versión mayor, igual y menor. Se corrigió el diseño para no actualizar plugins.version automáticamente durante load().
**Iteraciones:** 3
**Lección:** Para detectar updates, la versión instalada debe ser un estado persistido independiente de la versión disponible en disco; el boot no debe consumir una actualización antes de que el endpoint pueda reportarla.

---

## Sesion tecnica transversal - Apache+PHP single-origin, setup explicito y rendimiento local

**Prompt inicial:**
```text
PLEASE IMPLEMENT THIS PLAN:
## Plan: Unificar Xestify en un solo origen con Apache+PHP
[...]
La documentación debe pasar a presentar Apache+PHP como forma oficial de servir la aplicación, tanto en local como en producción.
```

**Prompt de ajuste de frontend:**
```text
Adapta el frontend para usar un base path configurable
```

**Prompt de optimizacion de boot:**
```text
Ok, haz los cambios del punto 1 que has sugerido, despues valoraremos el punto 2
```

**Prompt de sync explicito:**
```text
Implementalo ya
```

**Prompt de entorno y rendimiento:**
```text
Ok, no toquemos nada. Sigamos valorando los puntos 3 y 4 que habias sugerido:
3.dejar solo registerActiveHooks() con lectura minima de DB
4.revisar que Apache tenga OPCache activo
```

**Prompt de documentacion:**
```text
Aunque en este caso no estamos cerrando una story, actualiza productivity, por que ha sido un cambio lo suficientemente gordo como para valorarlo, actualiza tambien promps.md y sesion.md
```

**Resultado:** La aplicacion queda servida y documentada para Apache+PHP single-origin, el frontend soporta `base path` dinamico, el backend funciona correctamente bajo alias `/xestify`, el setup pesado sale del boot a scripts manuales y la sincronizacion de plugins pasa a ser explicita. La migracion puntual de datos `client -> clients` se retira del producto porque no corresponde a un escenario legacy real soportado. La medición de rendimiento identificó `Xdebug` como principal lastre local; tras pasar `xdebug.start_with_request` a `trigger`, `login` bajó de ~1103 ms a ~389 ms y `entities` de ~530 ms a ~91 ms.

**Iteraciones:** 9

**Lección:** En un proyecto PHP local servido por Apache, el mayor salto de rendimiento puede no estar en el código de dominio sino en el entorno efectivo de runtime. También conviene separar con claridad runtime, setup y sincronización para evitar trabajo invisible en cada request.

---

### STORY 7.2 - Proceso de actualizacion con migracion de schema

**Prompt de analisis:**
```text
Ok, vamos a implementar la story 7.2, leela y analiza los cambios necesarios.
Asegurate que esta bien planteada la logica, y revisa si podriamos optimizarla.
Hazme preguntas para asegurarnos que vamos a implementarlo como queremos.
```

**Prompt de implementacion:**
```text
PLEASE IMPLEMENT THIS PLAN:
# STORY 7.2 — Sync y actualización de plugins con schema aditivo y rollback automático
[...]
- `sync` no debe “consumir” actualizaciones; solo descubrir y registrar correctamente.
- `update` preserva el `status` previo; si el plugin estaba activo, la nueva versión queda activa.
- Esta story deja preparada la base para `7.4`, pero **no** implementa todavía rollback manual ni `onRollback()`.
- El alcance del schema diff en `7.2` queda limitado a **evolución aditiva segura**.
```

**Resultado:** STORY 7.2 completada. La aplicacion ya dispone de sincronizacion explicita de plugins desde disco (`POST /api/v1/plugins/sync`) sin consumir la version/schema runtime de plugins instalados, y de actualizacion transaccional (`POST /api/v1/plugins/{slug}/update`) con diff de schema solo aditivo, snapshot previo en `plugin_update_history`, `onUpdate(array $context)` opcional y rollback automatico si el lifecycle falla. `tools/setup/sync-plugins.php` adopta la misma semantica y el backlog/documentacion quedan alineados con `plugins.schema_json` como fuente viva del schema.

**Iteraciones:** 4

**Leccion:** En un sistema con plugins versionados, conviene separar nitidamente tres responsabilidades: descubrir desde disco, operar el runtime persistido y actualizar de forma explicita. Si `sync` consume actualizaciones o mezcla schema disponible con schema vivo, el sistema pierde trazabilidad y complica tanto el rollback como la evolucion segura del modelo.

---

### STORY 7.3 - Frontend - Pagina de configuracion de plugin activado

**Prompt de cierre y documentacion:**
```text
Ya he implementado la story 7.3 y su refuerzo prepara el commit y todos los cambios necesarios en la documentacion. Acuerdate de no realizar el commit directamente, consultame antes
```

**Resultado:** STORY 7.3 preparada para commit. La aplicacion incorpora la pagina `PluginConfig` accesible desde `PluginManager` para plugins activos, endpoints `GET/PUT /api/v1/plugins/{slug}/config`, guardado versionado de `plugins.schema_json`, proteccion de campos base y gestion de campos sugeridos/adicionales. El refuerzo amplia el mismo flujo a plugins `extension`, con configuracion de campos, persistencia de `target_entity` (`*` o slug de entidad activa), filtrado de la extension por entidad destino y normalizacion del contenido contra el schema persistido.

**Iteraciones:** 3

**Leccion:** La configuracion de plugins debe tratar `entity` y `extension` como casos compatibles pero no identicos: comparten tabla de configuracion y versionado de schema, pero las extensiones necesitan conservar su relacion de aplicacion (`target_entity`) y normalizar sus datos antes de escribir en `plugin_extension_data`.

---

### STORY 7.4 - Rollback manual de plugin a version anterior

**Prompt de implementacion:**
```text
Implementemos la siguiente Story en el roadmap, la 7.4
```

**Resultado:** STORY 7.4 implementada. Se añadió rollback manual por API (`POST /api/v1/plugins/{slug}/rollback`) con restauración transaccional desde `plugin_update_history` filtrando por `slug + target_version`, ejecución opcional de `onRollback(array $context)` y respuesta de conflicto (`409`) cuando no existe snapshot aplicable. También se incorporó cobertura de integración específica (`PluginRollbackServiceTest`) y ampliación de tests de lifecycle/API.

**Iteraciones:** 3

**Leccion:** Guardar snapshots por versión objetivo permite rollback seguro y determinista en cadenas de updates (`v1->v2->v3`), evitando restauraciones ambiguas cuando hay múltiples snapshots históricos del mismo plugin.

---

### STORY 7.5 - Frontend - UI de actualizacion y rollback en PluginManager

**Prompt de implementacion:**
```text
Ok, continuemos con la story 7.5
```

**Resultado:** STORY 7.5 implementada. `PluginManager` ahora muestra botón `Synchronize` (admin) con llamada a `POST /api/v1/plugins/sync`, badge de actualización por plugin usando `GET /api/v1/plugins/updates`, botón `Update` con `POST /api/v1/plugins/{slug}/update`, y botón `Rollback` con `POST /api/v1/plugins/{slug}/rollback` únicamente cuando `can_rollback` es verdadero en `GET /api/v1/plugins`. Tanto update como rollback exigen confirmación modal previa y muestran feedback en pantalla tras completar la acción.

**Iteraciones:** 3

**Leccion:** Para UX operativa de plugins, no basta con exponer endpoints: conviene publicar también señales de estado derivadas (`can_rollback`) para que el frontend ofrezca acciones válidas y evite flujos fallidos por diseño.

---

## EPIC 8 — Gestión de usuarios

### STORY 8.1 — Backend - Migración de perfil y UserRepository
**Prompt:**
```
Implementa la story 8.1 para Xestify:
- Añadir columnas de perfil y borrado lógico a users (`name`, `avatar`, `deleted_at`).
- Crear/actualizar UserRepository con find, all, update, delete y updatePassword.
- Añadir tests de integración para cubrir el perfil y el borrado lógico.
- Asegurar que el login rechaza usuarios con deleted_at.
```
**Resultado:** Migración y repositorio funcionales, login protegido y tests de integración pasando.
**Iteraciones:** 2
**Lección:** El sitio de decisión para el borrado debe ser el login y el repositorio, no solo la capa de datos.

### STORY 8.2 — Backend - UserController y rutas REST
**Prompt:**
```
Implementa la story 8.2 para Xestify:
- Añadir un UserController con endpoints protegidos para /api/v1/users/me, /api/v1/users y /api/v1/users/{id}.
- Permitir ver y actualizar el perfil propio, listar/mostrar/editar usuarios desde admin y hacer borrado lógico con bloqueo de self-delete.
- Añadir tests de integración y revisar los hallazgos de calidad del código.
```
**Resultado:** Controller funcional con rutas protegidas, lógica de permisos y tests de integración pasando.
**Iteraciones:** 2
**Lección:** El flujo de perfil propio debe exigir password actual en cambios de email y el borrado debe ser lógico con guardas explícitas.

### STORY 8.3 — Frontend - UserMenu dropdown en Navbar
**Prompt:**
```
Implementa la story 8.3 para Xestify:
- Añadir un dropdown de usuario en el navbar para mostrar acciones como Mi perfil, Gestión de usuarios y Cerrar sesión.
- Hacer que el menú se abra al hover y se mantenga estable al pasar del botón al desplegable.
- Conectar las acciones al shell principal para que muestren vistas reales en lugar de placeholders.
- Añadir una prueba HTML de regresión para cubrir el comportamiento del menú.
```
**Resultado:** Dropdown funcional, navegación real desde el menú y test aislado de hover/acciones pasando.
**Iteraciones:** 3
**Lección:** El hover del menú necesita un buffer visual y un contenedor dedicado para evitar que el cursor lo cierre al cruzar el gap entre botón y desplegable.

### STORY 8.4 — Frontend - Página Mi Perfil (`#/profile`)
**Prompt:**
```
Implementa la story 8.4 para Xestify:
- Crear la página de perfil accesible desde `#/profile` con formulario para nombre, email y password.
- Conectar la vista con el endpoint `/api/v1/users/me` para leer y actualizar el perfil propio.
- Añadir validación inline para email y password, preservando valores en errores y mostrando feedback visual.
- Sincronizar el estado global del usuario con el navbar para que los cambios se reflejen en tiempo real.
```
**Resultado:** Página de perfil funcional, validaciones inline y navbar actualizado al guardar cambios; tests de integración y de UI pasaron.
**Iteraciones:** 4
**Lección:** El estado global debe ser la fuente de verdad para la UI y el navbar, no un valor duplicado en cada vista.
**Estado final:** Documentado en sesión, productividad y prompts para cerrar la historia con trazabilidad completa.

### STORY 8.5 — Frontend - Página gestión de usuarios (`#/usuarios`)
**Prompt:**
```
Ok, ahora continuemos con la story 8.5
```
**Resultado:** Story 8.5 implementada con gestión real de usuarios. Se sustituyó la vista demo por una tabla conectada a `/api/v1/users` con acciones por fila: editar (modal con nombre/email/roles), reset password (modal que llama a `PUT /api/v1/users/{id}/password` y muestra contraseña temporal una sola vez con opción copiar), y borrado con confirmación (deshabilitado para el usuario autenticado). Además, se añadió navegación hash `#/usuarios` y `#/usuarios/:id` para acceso directo a ficha de usuario y se amplió backend para soportar actualización de roles y reset admin de contraseña.
**Iteraciones:** 4
**Lección:** Para cerrar una story frontend operativa, conviene validar de extremo a extremo: contrato backend (roles/reset), navegación hash y pruebas UI de modales para evitar regressiones de interacción.
**Estado final:** Validación visual en navegador con `frontend/tests/UserManagementTest.html` (4 passed, 0 failed) y trazabilidad actualizada en documentación de productividad.

### STORY 9.1 — Fundamentos de diseño
**Prompt:**
```
Empecemos con la implementacion del EPIC 9. Quiero una base visual enterprise inspirada en Ant Design, con Tailwind como sistema de estilos y componentes frontend unificados.
```
**Resultado:** STORY 9.1 quedó implementada como base visual común del frontend. Se unificó la construcción de tablas en `DynamicTable`, se alinearon las tabs con el patrón `line` de Ant Design, se endurecieron formularios, acciones y estados visuales, y se sustituyó el Play CDN de Tailwind por una hoja local generada (`tailwind.generated.css`) servida como asset estático. También se documentaron los fundamentos visuales y la decisión técnica asociada.
**Iteraciones:** 11
**Lección:** En una story de fundamentos UI, la mayor parte del trabajo no está en “crear un componente”, sino en cerrar consistencia transversal: tablas, tabs, formularios, estados disabled, iconografía y pipeline real de estilos deben converger en un único punto de verdad.
**Estado final:** Story preparada para commit con trazabilidad actualizada en `sesion.md`, `productividad.md` y `prompts.md`; el siguiente paso del backlog pasa a STORY 9.2.

### STORY 9.2 — Fundamentos de navegacion y anatomia de paginas
**Prompt:**
```
Empecemos la implementacion del epic 9.2.
La convencion de rutas esta mal, deberia ser en ingles.
```
**Resultado:** Se creó `frontend/src/js/modules/Routes.js` como contrato central de navegación hash, se conectó a `main.js` y `UserManager.js`, se documentaron arquitectura de información, plantillas de página y convenciones de copy para i18n futuro, y luego se cerró la limpieza estricta eliminando aliases legacy para dejar el mapa solo en inglés.
**Iteraciones:** 2
**Lección:** Antes de construir un router formal, conviene fijar una única traducción entre estado interno y hash visible; así 9.4 y 9.6 pueden refactorizar sin perseguir rutas hardcodeadas por la UI.
**Estado final:** Story 9.2 documentada y verificada con checks de sintaxis sobre el módulo de rutas y sus consumidores directos; backlog, roadmap y sesión quedaron alineados.

### STORY 9.3 — Libreria de componentes UI base
**Prompt:**
```
No, realiza un analisis profundo para asegurar que se cumplen todos los puntos de la story 9.3.
Los iconos en los botones de las columnas de acciones deben ser mas grandes: 18px.
Prepara el commit de la story 9.3, asegurate de cubrir toda la documentacion y reflejar las decisiones tomadas sobre el front.
```
**Resultado:** Se cerró la story 9.3 con la librería de componentes unificada, el modal sobre la base común, `DynamicForm` y `DynamicTable` alineados con la base UI, Tailwind recompilado correctamente desde la raiz del repo y los iconos de acciones de tabla fijados a 18px.
**Iteraciones:** 4
**Lección:** En UI base, la verificación visual debe incluir el build real de Tailwind y no solo tests de componentes; un content glob incorrecto puede dejar la app sin utilities aunque el DOM parezca correcto.
**Estado final:** Listo para commit con trazabilidad completa en sesión, productividad, prompts y documentacion de frontend.

### STORY 9.4 — Arquitectura frontend y modularizacion
**Prompt:**
```
Acabo de pasar al modo Agente, ejecuta ese plan.
```
**Prompt de auditoría final:**
```
Ok, paremos una comprobacion final y detallada de la story 9.4 para asegurarnos que todo ha quedado implementado
```
**Resultado:** Se cerró la STORY 9.4 dejando el frontend bajo MVC estricto real: `frontend/src/js` queda reducido a `controllers`, `models` y `views`; el entrypoint queda en `app.js` en la raíz del módulo JS; el routing deja de vivir en `models` y se consolida en `RouteController`, `RouteMapController` y `PluginRouteController`; los tests HTML clave se adaptan y se validan en el navegador integrado de VS Code. También se corrigió el runtime del plugin `comments`, se fijó el flujo canónico de validación frontend en pestañas del navegador integrado y se eliminó la deuda de `404` en `E2ETest`.
**Iteraciones:** 8
**Lección:** En una refactorización arquitectónica de frontend sin bundler, el riesgo principal no es el código de negocio sino la coherencia entre rutas relativas, mocks de tests HTML standalone y documentación activa; si no se validan juntos, la arquitectura parece correcta pero la ejecución real se degrada.
**Estado final:** STORY 9.4 implementada y verificada en navegador integrado con 17/17 runners HTML y 146/146 assertions, sin errores de consola; siguiente foco del backlog: STORY 9.5.

### STORY 9.5 — Shell SPA y plantillas de navegacion
**Prompt:**
```
Retomemos la story 9.5, comprueba su estado y valida los puntos que faltan por implementar
```
**Prompt de actualización documental:**
```
Actualiza el resto de documentacion para reflejar el estado actual
```
**Resultado:** Se auditó la implementación local contra los seis criterios de la story, se confirmó la integración del shell y las plantillas en las páginas principales, se migró Login desde una estructura DOM manual a la plantilla standalone `login` de `PageLayout`, se eliminó el duplicado sin uso `ShellLayoutView.js` y se añadió cobertura arquitectónica del contrato de Login.
**Iteraciones:** 4
**Lección:** Los requisitos de navegación básica del shell deben validarse en 9.5 sin adelantar los criterios de refresh, back/forward y mapa hash completo reservados para 9.6; separar ambos alcances evita cerrar una story con deuda ficticia o ampliar el cambio innecesariamente.
**Estado final:** STORY 9.5 implementada y verificada en navegador integrado con 17/17 runners HTML y 166/166 assertions; el entrypoint real de Login monta una única plantilla sin navegación autenticada ni errores de consola. Siguiente foco: STORY 9.6.

### STORY 9.6 — Implementacion del routing SPA
**Prompt:**
```
Implementemos la STORY 9.6, mucho del trabajo necesario ya esta realizado, hay que revisar que realmente este bien implementado y que respeta todo el mapa de rutas. Tienes una descripcion mas detallada en navegacion-anatomia.md.
```
**Resultado:** Se auditó la implementación previa y se completó el mapa bidireccional de rutas. `RouteController` acepta navegación mediante hashes públicos, mantiene entrada directa y refresh, y usa historial sin recarga para soportar back/forward. La ruta de tabs conserva slug, registro y tab hasta `EntityEdit`, incluyendo anatomía de detalle, breadcrumbs y navbar activa.
**Iteraciones:** 5
**Lección:** Un mapa de constantes no equivale a un router completo: cada ruta parametrizada debe ser bidireccional y conservar contexto al entrar directamente, reiniciar y recorrer el historial.
**Estado final:** STORY 9.6 implementada y verificada en navegador integrado con 17/17 runners HTML y 169/169 assertions; diagnósticos y sintaxis en verde. Siguiente foco: STORY 9.7.

**Prompt de unificación de home:**
```
Realmente workbench: '#/workbench', no tiene sentido, con #/home cubririamos su funcionalidad. Eliminalo tanto de codigo como de la documentacion. Veo informacion dispersa sobre si el home esta en #/ o en #/home
```
**Resultado:** Se eliminó `#/workbench`, se descartó `#/` como ruta pública y se unificaron identificador interno, hash y template de inicio en `home` / `#/home`. La raíz vacía solo se normaliza internamente hacia la URL canónica.

**Prompt de simplificación de plugins:**
```
En el mapa de rutas, veo una inconsistencia, /plugins/:slug/config no seria necesario el /config con /plugins/:slug es suficiente.
```
**Resultado:** La ruta SPA de configuración se simplificó a `#/plugins/:slug` en mapa, parser, generador, controlador, tests y documentación. El sufijo `/config` se conserva únicamente en los endpoints REST de configuración.

**Prompt de corrección del runtime:**
```
Algo no has refactorizado bien, cuando accedo a http://127.0.0.1/xestify/#/plugins/comments me dice "Pagina no encontrada."
```
**Resultado:** Se reprodujo el flujo con los módulos servidos por Apache y se detectó que mapa y controlador mantenían validadores independientes. El parser se centralizó en `RouteMapController`, `PluginRouteController` pasó a consumirlo y el test arquitectónico cubre ahora hash, token, reconocimiento y extracción del slug.

**Prompt de consistencia de navegación:**
```
El boton "Volver" o "Volver al listado" es incosistente, en algunas paginas se pone en el shell-main-actions y en otras en el page-header-toolbar
Deberia aparecer siempre en el page-header-toolbar y con con button con la variant secondary
```
**Resultado:** `PluginConfig` movió Volver al toolbar y `UserConfig` cambió Volver al listado a variant secondary. Los tests verifican ubicación, ausencia de duplicados, estilo secondary y conservación del callback; Guardar y demás comandos siguen en `shell-main-actions`.

**Prompt de fallback de inicio:**
```
Por ahora no tenemos una pagina de inicio o home( /# o /#home) hay que añadir un fallback, para que cuando se intente acceder a ellas haga una rediccion al primer #/entity/:slug del menu
```
**Resultado:** `#/home`, `#/` y el hash vacio resuelven a la primera entidad activa del menu y la URL se reemplaza por su ruta `#/entity/:slug`, sin conservar el alias en el historial. Si no hay entidades, los administradores acceden a Plugins y el resto conserva el estado vacio existente.

**Prompt de navegación por tabs:**
```
Cuando cambio entre pestañas en la edicion de una entidad que tiene un plugin de extension(clients) no me cambiar el hash:
Deberia cambiarme a:
#/entity/clients/7fcf9b4d-267c-459a-8024-ed79939f415b/data
#/entity/clients/7fcf9b4d-267c-459a-8024-ed79939f415b/comments
Y sin embargo siempre se queda como:
#/entity/clients/7fcf9b4d-267c-459a-8024-ed79939f415b

Las rutas #/entity/:slug/:id/:tab no estan funcionando o no se invocan desde navegacion
```
**Resultado:** `DynamicTabs` propaga el id seleccionado, `EntityEdit` lo eleva a la aplicacion y `AppController` navega mediante `entityTabPage`. La pestaña base se normaliza a `data`; la extensión usa `comments`. Apache confirmó ambos hashes y back restauró la pestaña Comentarios.

**Prompt de optimización de tabs:**
```
Ahora, cuando cambio de una pestaña a otra hace algo que se siente raro, hace un rerenderizado de la pagina, esto hace que la navegacion no se vea fluida. Optimicemosla para que no rerenderice toda la pagina, solo las partes necesarias, y en caso de ser nevesario que las tengra pre-cargadas
```
**Resultado:** `RouteController` permite actualizar historial sin despachar la página. `AppController` conserva la instancia activa de `EntityEdit` y, para tabs del mismo registro, actualiza solo panel y breadcrumbs. Formulario y paneles se precargan una vez; Apache confirmó identidad DOM estable, conservación de valores sin guardar y back/forward sin rerender.

### STORY 9.7 — Infraestructura transversal de frontend y resiliencia
**Prompt:**
```
Retomemos la implementación de la story 9.7, revisemos qué está implementado, si lo está correctamente, y los puntos/implementaciones que pueden faltar.
```
**Resultado:** Se consolidó la capa transversal de frontend con estado global extendido para shell, navegación y preferencias UI; se añadió `UiResilienceService` para notificaciones, errores amigables y confirmaciones; se preparó una base de i18n y theming persistido para la UI; y se integró el nuevo feedback en las páginas principales sin duplicar handlers por vista.
**Iteraciones:** 4
**Lección:** Las preocupaciones transversales de UX deben resolverse una vez en una capa compartida y después consumirse desde toda la aplicación; si no, cada página vuelve a reinventar loading, error y confirmación.
**Estado final:** STORY 9.7 implementada y validada con smoke test de UI, panel de tema y checks de sintaxis; el siguiente foco del backlog pasa a STORY 9.8.
