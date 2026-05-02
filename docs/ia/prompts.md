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

### STORY 4.4 � Crear plugin entity_client

**Prompt:**
`````n STORY 4.4 � Crear plugin de entidad base entity_client:
 - Estructura: manifest.json (slug, name, version, type, core_version)
 - schema.json con campos nombre (required), email (required), tel�fono (optional), activo (boolean, default true)
 - Hooks.php: hook beforeSave que valida email �nico en entity_data
 - Installer.php: registra entidad en system_entities + siembra schema en entity_metadata (idempotente)
 - Tests unitarios con stubs PDO
`````n
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

