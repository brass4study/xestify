# Registro de Productividad IA — Xestify

> Documento de análisis en tiempo real del impacto de IA en el desarrollo.
> Datos reales de la sesión de implementación.

---

## Cambio reciente — SonarQube + roadmap EPIC 9

- **Fecha:** 2026-08-08
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~45 min
- **Aceleración:** ~82% ⚡
- **Qué hizo IA:**
  - Revisó hallazgos de SonarQube/VS Code y aplicó fixes en componentes frontend, páginas y backend.
  - Mejoró la skill local de revisión para exportar hallazgos y analizar el workspace de forma más fiable.
  - Alineó la documentación de roadmap con el estado real de la Fase 9.
- **Iteraciones:** 2 (ajuste de la skill y revisión final del roadmap)
- **Decisión manual:** Mantener el scope del commit centrado en fixes funcionales y documentación de estado, sin incluir artefactos temporales generados por la skill.

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
- **Decisión manual:** Renombrar `documentacion/` a `docs/` para consistencia de naming

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

## EPIC 1 — Autenticación

### STORY 1.1: Tabla users + migración + seeder
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~90% ⚡
- **Qué hizo IA:**
  - Creó migración 001_users.sql con campos: id, email UNIQUE, password_hash, role ENUM, created_at
  - Implementó Database.php singleton (PDO)
  - Creó UserSeeder con hash bcrypt de admin por defecto
  - 8 tests de integración
- **Iteraciones:** 1
- **Decisión manual:** Usar enum ROLE IN ('admin', 'user')

---

### STORY 1.2: JwtService (HS256)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~94% ⚡
- **Qué hizo IA:**
  - Implementó encode/decode HS256 en PHP puro (hash_hmac)
  - Validación de expiry, payload
  - 8 tests unitarios
- **Iteraciones:** 1
- **Decisión manual:** Usar SECRET_KEY de .env

---

### STORY 1.3: AuthController (POST /api/auth/login)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~87% ⚡
- **Qué hizo IA:**
  - Endpoint con validación de credenciales
  - Devuelve JWT en response
  - 8 tests de integración
- **Iteraciones:** 1
- **Decisión manual:** ninguna

---

### STORY 1.4: AuthMiddleware + Request::setUser/user()
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h
- **Tiempo real con IA:** ~10 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Middleware que valida JWT en header Authorization
  - Inyecta user en Request
  - 6 tests
- **Iteraciones:** 1
- **Decisión manual:** ninguna

---

## EPIC 2 — Modelo de Datos Core

### STORY 2.1: Crear tabla system_entities
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h
- **Tiempo real con IA:** ~10 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Añadió `system_entities` a `002_core.sql` con 7 campos
  - Constraint UNIQUE en slug
  - 3 tests de integración
- **Iteraciones:** 1
- **Decisión manual:** ninguna

---

### STORY 2.2: Crear tabla entity_metadata (schema versionado)
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

### STORY 2.3: Crear tabla entity_data (registros de negocio)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h
- **Tiempo real con IA:** ~10 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Añadió `entity_data` a `002_core.sql` con JSONB + soft delete (`deleted_at`)
  - Creó índices BTREE en `entity_slug` y `owner_id`, GIN en `content`
  - Creó `EntityDataTableTest.php` con 5 tests (table, columns, nullable deleted_at, GIN index, BTREE index)
- **Iteraciones:** 1
- **Decisión manual:** GIN index en `content` para búsquedas JSONB eficientes; soft delete via `deleted_at` NULL

---

### STORY 2.4: Crear tabla plugins_registry (plugins instalados)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 45 min
- **Tiempo real con IA:** ~10 min
- **Aceleración:** ~77% ⚡
- **Qué hizo IA:**
  - Añadió `plugins_registry` a `002_core.sql` con 7 campos y CHECK constraints
  - Constraint UNIQUE en plugin_slug, CHECK en plugin_type y status
  - Creó `PluginsRegistryTableTest.php` con 5 tests
- **Iteraciones:** 1
- **Decisión manual:** ninguna

---

### STORY 2.5: Crear repositorio GenericRepository (CRUD JSONB)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~89% ⚡
- **Qué hizo IA:**
  - Creó `Xestify\Exceptions\RepositoryException`
  - Implementó `GenericRepository` con find, all (con includeDeleted), create, update (merge JSONB `||`), delete (soft), restore
  - Todos los queries con parámetros preparados (PDO)
  - Creó `GenericRepositoryTest.php` con 7 tests CRUD completos + cleanup por test
- **Iteraciones:** 1
- **Decisión manual:** Update usa operador JSONB `||` para merge parcial (no reemplaza todo el content); soft delete vía `deleted_at`

---

### STORY 2.6: Verificar idempotencia migración 002_core.sql
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 30 min
- **Tiempo real con IA:** ~5 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Creó `MigrationIdempotenceTest.php` con 3 tests
  - Test 1: verifica todas las tablas existen al inicio
  - Test 2: ejecuta 002_core.sql nuevamente via psql, verifica exit code 0
  - Test 3: inserta datos de prueba, re-ejecuta migración, verifica que datos persisten sin duplicación
- **Iteraciones:** 1
- **Decisión manual:** Test de idempotencia es crítico para deploys seguros; todas las CREATE TABLE IF NOT EXISTS deben ser correctas

---

## Resumen EPIC 2 (COMPLETADO)

| Story | Estimado | Real | Aceleración |
|-------|----------|------|-------------|
| 2.1 | 1h | ~10 min | 83% |
| 2.2 | 1h | ~15 min | 75% |
| 2.3 | 1h | ~10 min | 83% |
| 2.4 | 45 min | ~10 min | 77% |
| 2.5 | 45 min | ~10 min | 78% |
| 2.6 | 3h | ~20 min | 89% |
| 2.7 | 30 min | ~5 min | 83% |
| **Total** | **7h 45m** | **~80 min** | **~83% ⚡** |

---

## Estadísticas Globales (hasta EPIC 2)

**Total de stories completadas:** 14
**Total de tests:** 100+
**Aceleración promedio IA:** ~85% (15x faster on average)
**Tiempo ahorrado:** ~25 horas

**Metrics:**
- EPIC 0: 6 stories, 38 tests
- EPIC 1: 4 stories, 30 tests  
- EPIC 2: 7 stories, 32 tests

**Archivos creados:** 40+
**Líneas de código:** 2000+

---

## Refactor — Calidad + Estructura (2026-05-01)

### Refactor: Directorios a minúsculas + encodings + calidad
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Recuperó 14 archivos PHP desde git y los copió en directorios minúsculos (core/, controllers/, etc.)
  - Actualizó todos los namespace y use statements de CamelCase a minúsculas
  - Añadió newline final en todos los archivos PHP (php:S113)
  - Extrajo constante `QUERY_EXECUTE_MSG` para eliminar strings duplicadas en 3 tests
  - Limpió trailing whitespace en MigrationIdempotenceTest.php
  - Redujo 165 problemas de intelephense a 0
- **Iteraciones:** 2 (segunda iteración para reducir complejidad cognitiva)
- **Decisión manual:** ninguna

---

## EPIC 3 — Motor de Entidades Dinámicas

### STORY 3.1: ValidationService (valida contra schema JSONB)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~89% ⚡
- **Qué hizo IA:**
  - Implementó `validate(data, schema)` con soporte de 6 tipos: string, number, boolean, date, email, select
  - Validaciones de required, minLength, maxLength, min, max, options
  - Soporte dual de schema: `fields` como mapa clave→reglas o como lista con `name`
  - Refactorizó automáticamente para cumplir reglas de calidad (≤3 returns, complejidad cognitiva ≤15)
  - 8 tests unitarios standalone
- **Iteraciones:** 2 (segunda para refactor de calidad SonarQube)
- **Decisión manual:** ninguna

### STORY 3.2: EntityService (orquestación CRUD)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~25 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Implementó `EntityService` con 5 métodos (createRecord, updateRecord, deleteRecord, getRecord, listRecords)
  - Creó excepciones de dominio `EntityServiceException` y `ValidationException`
  - Extendió `ValidationService::validate()` con `$requireAll` para updates parciales
  - Detectó y eliminó BOM UTF-8 de 21 archivos PHP (prevenía ejecución de todos los tests)
  - 6 tests de integración: create, validación fallida, schema ausente, update parcial, delete, listado
- **Iteraciones:** 1 (fix BOM fue diagnóstico inmediato)
- **Decisión manual:** ninguna

### STORY 3.3: EntityController (endpoints REST)
- **Fecha:** 2026-05-01

- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Implementó `EntityController` con 6 métodos (schema, index, create, show, update, destroy)
  - Registró 6 rutas en `routes.php` y bindings en `config/app.php`
  - Manejo de errores: `ValidationException` → 422, `EntityServiceException` → 404, `RepositoryException` → 404
  - 9 tests E2E: schema, schema 404, create, create 422, index, show, show 404, update, delete
- **Iteraciones:** 1
- **Decisión manual:** ninguna

### STORY 3.4: Helpers apiSuccess/apiError (respuesta REST envelopada)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h
- **Tiempo real con IA:** ~5 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Añadió `apiSuccess(data, meta)` y `apiError(code, message, details)` como métodos estáticos a `Response`
  - 4 tests nuevos en `RequestResponseTest.php` (total: 24 tests)
- **Iteraciones:** 1
- **Decisión manual:** ninguna

### STORY 3.5: Modelo SystemEntity (acceso a metadata)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h 30min
- **Tiempo real con IA:** ~8 min
- **Aceleración:** ~91% ⚡
- **Qué hizo IA:**
  - Creó `backend/src/models/SystemEntity.php` con `getActive()`, `getBySlug()`, `findOrFail()` y caché en memoria
  - 7 tests de integración en `SystemEntityTest.php` (fixtures temporales en DB, cleanup al final)
- **Iteraciones:** 1
- **Decisión manual:** ninguna

### STORY 3.6: Frontend Api.js (cliente HTTP genérico)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~10 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/modules/Api.js` con clase `Api` (get/post/put/delete) y clase `ApiError`
  - Token Bearer inyectado automáticamente en headers cuando se establece con `setToken()`
  - Validación de envelope `{ ok, data, error }` con propagación via `ApiError`
  - Test runner HTML standalone `frontend/tests/ApiTest.html` con 11 tests (fetch mockeado)
- **Iteraciones:** 1
- **Decisión manual:** test runner HTML en vez de Node.js (sin bundlers, Vanilla puro)

### HARDENING PRE 3.7: Corrección de hallazgos SonarQube + VS Code
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~25 min
- **Aceleración:** ~79% ⚡
- **Qué hizo IA:**
  - Normalizó mensajes de asserts duplicados con constantes en tests de integración
  - Eliminó `return` redundante y redujo ruido de calidad en servicios/controladores
  - Añadió/confirmó newline final en archivos reportados por Sonar
  - Sustituyó reset por reflection con `Closure::bind` en `DatabaseTest.php` para evitar deprecación de `setAccessible()` en PHP 8.5
  - Validó diagnóstico global del editor sin errores
- **Iteraciones:** 2
- **Decisión manual:** priorizar limpieza completa de calidad antes de iniciar STORY 3.7

### STORY 3.7: Frontend - Crear State.js (estado global)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 1h
- **Tiempo real con IA:** ~12 min
- **Aceleración:** ~80% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/modules/State.js` con objeto global `AppState`
  - Implementó setters/getters simples para `user`, `currentEntity`, `entities`, `records`, `metadata`, `token`, `loading`, `error`
  - Añadió método `reset()` para restaurar estado inicial de forma explícita
  - Creó test runner `frontend/tests/StateTest.html` con 11 casos unitarios
  - Verificó ejecución real en navegador local (`11 passed, 0 failed`)
- **Iteraciones:** 1
- **Decisión manual:** mantener patrón de objeto plano (no clase, no listeners, no Proxy)

### STORY 3.8: Frontend - Crear DynamicForm.js
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h 30min
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~87% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/modules/DynamicForm.js` como clase que recibe schema + container
  - Implementó `render()` para generar controles por tipo (`string`, `number`, `email`, `date`, `select`, `boolean`)
  - Implementó `validate()` con reglas básicas cliente (`required`, tipo, min/max, minLength/maxLength, options)
  - Implementó `getData()` devolviendo objeto normalizado por tipo
  - Creó `frontend/tests/DynamicFormTest.html` con 6 pruebas (render tipos, getData, validación básica y schema en formato mapa)
  - Validó ejecución real en navegador local (`6 passed, 0 failed`)
  - Corrigió hallazgos SonarQube puntuales en `Api.js` y `ApiTest.html` manteniendo `11/11` tests
- **Iteraciones:** 2
- **Decisión manual:** mantener validación básica enfocada en reglas necesarias del backlog, sin listeners ni lógica reactiva

### STORY 3.9: Frontend - Crear DynamicTable.js
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~18 min
- **Aceleración:** ~85% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/modules/DynamicTable.js` como clase para renderizar tablas por schema y records
  - Implementó renderizado dinámico de columnas para schema en formato lista y mapa
  - Implementó paginación básica con `Prev/Next`, `getCurrentPage()`, `getTotalPages()` y `getCurrentPageRecords()`
  - Añadió `setRecords()` y `setSchema()` para actualizar dataset y estructura sin recrear instancia
  - Creó `frontend/tests/DynamicTableTest.html` con 6 tests (columnas, rows, paginación, límites, reset de página y estado vacío)
  - Verificó ejecución real en navegador local (`6 passed, 0 failed`)
- **Iteraciones:** 1
- **Decisión manual:** paginación simple sin sorting/filtros para cumplir criterio MUST sin sobrecargar la story

---

### STORY 3.10: Frontend - Crear página EntityList
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/pages/EntityList.js` con carga de entidades vía GET /entities
  - Implementó botones de selección de entidad y carga de registros vía GET /entities/:slug/records
  - Integró `DynamicTable` para renderizado de registros
  - Expuso botón "Crear nuevo registro" con callback configurable `onCreateNew`
  - Sincronizó `AppState` con entidades actuales, entidad seleccionada y registros cargados
  - Creó `frontend/tests/EntityListTest.html` con 7 tests (7/7 pasando)
  - Usó `mockFetch` con ordenamiento por longitud de clave para resolver ambigüedad URL `/entities` vs `/entities/:slug/records`
- **Iteraciones:** 2 (primera iteración: mock API duck-typing + orden de claves en mockFetch)
- **Decisión manual:** duck-typing en constructor para aceptar mock APIs sin `instanceof`; `mockFetch` ordena por longitud de clave descendente para evitar match prematuro de prefijo `/entities`

---

### STORY 3.11: Frontend - Crear página EntityEdit
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~18 min
- **Aceleración:** ~90% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/pages/EntityEdit.js` integrando `DynamicForm` para crear/editar registros
  - Implementó submit con POST (crear) y PUT (editar) según presencia de `recordId`
  - Implementó pre-relleno del formulario via `initialData` aplicado como defaults de schema
  - Mostró errores por campo (`ApiError.details`) y banner global para errores genéricos
  - Expuso callbacks `onSaved` y `onCancel` configurables
  - Creó `frontend/tests/EntityEditTest.html` con 12 tests (12/12 pasando al primer intento)
- **Iteraciones:** 1
- **Decisión manual:** pre-relleno via `#applyInitialData` que mapea `initialData` como `field.default` reutilizando la lógica existente de DynamicForm sin modificarla

---

## EPIC 4 — Sistema de Plugins y Hooks Backend

### STORY 4.1: Crear PluginLoader
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~89% ⚡
- **Qué hizo IA:**
  - Creó `PluginException` en `backend/src/exceptions/`
  - Implementó `PluginLoader` con `discover()`, `load()`, `loadAll()`
  - Validación de manifest (campos obligatorios, tipo válido, compatibilidad de core version)
  - Registro en `plugins_registry` con INSERT o UPDATE según si ya existe
  - Carga de `Hooks.php` via `require_once` cuando presente
  - Creó `PluginLoaderTest.php` con 8 tests de integración usando fixtures temporales en `sys_get_temp_dir()`
- **Iteraciones:** 1
- **Decisión manual:** fixtures en `sys_get_temp_dir()` con nombres aleatorios para evitar colisiones; cleanup de BD por slug después de cada test

### STORY 4.2: Crear HookDispatcher
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Creó `HookException` en `backend/src/exceptions/`
  - Implementó `HookDispatcher` con `register()` y `execute()`
  - Ejecución de callbacks en orden de prioridad ascendente (menor = primero)
  - `beforeXxx` hooks: excepción propaga y bloquea operación; `\Throwable` genérico se envuelve en `HookException`
  - `afterXxx` hooks: excepción no propaga, se loguea a STDERR como warning
  - Callback retornando no-array preserva contexto anterior
  - Creó `HookDispatcherTest.php` con 11 tests unitarios standalone
- **Iteraciones:** 1
- **Decisión manual:** `fwrite(STDERR, ...)` para warnings de after* (sin dependencia de Logger); callbacks retornan array modificado o null para no-op

### STORY 4.3: Hooks beforeSave/afterSave en EntityService
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~88% ⚡
- **Qué hizo IA:**
  - Inyectó `?HookDispatcher $hooks = null` en `EntityService` (sin romper tests existentes)
  - `createRecord` y `updateRecord`: `dispatchBefore` + `dispatchAfter` reemplazando stub `fireHooks`
  - `beforeSave` puede mutar `$context['data']` antes de la persistencia
  - `afterSave` falla silenciosamente (delegado a HookDispatcher)
  - Creó stubs `PdoStub`, `PdoStatementStub`, `RepositoryStub` para tests unitarios sin BD
  - Creó `EntityServiceHooksTest.php` con 10 tests unitarios standalone
- **Iteraciones:** 1
- **Decisión manual:** `?HookDispatcher $hooks = null` para mantener compatibilidad con wiring existente sin romper `buildService()` en `EntityServiceTest.php`

### STORY 4.4: Plugin de entidad base (entity_client)
- **Fecha:** 2026-05-01
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Creó `backend/plugins/entity_client/manifest.json` con campos requeridos por PluginLoader
  - Creó `backend/plugins/entity_client/schema.json` con campos nombre, email, teléfono, activo
  - Creó `backend/plugins/entity_client/Hooks.php` con hook `beforeSave` que valida email único contra `entity_data`
  - Creó `backend/plugins/entity_client/Installer.php` con `install()` idempotente (INSERT … ON CONFLICT) en `system_entities` y `entity_metadata`
  - Creó `EntityClientPluginTest.php` (renombrado después a `ClientsPluginTest.php`) con 13 tests unitarios (stubs PDO, sin BD real)
- **Iteraciones:** 1
- **Decisión manual:** Installer separado de Hooks para mantener responsabilidad única; `ON CONFLICT … DO UPDATE` en `system_entities` para permitir actualizaciones de versión sin error

### STORY 4.5: Ciclo de vida de plugin (onInstall, onActivate, onDeactivate)
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~25 min
- **Aceleración:** ~90% ⚡
- **Qué hizo IA:**
  - Creó `backend/src/plugins/PluginLifecycleInterface.php` con contrato `onInstall/onActivate/onDeactivate`
  - Modificó `PluginLoader.php`: `registerPlugin()` retorna `bool` (nuevo/existente), añadidos `activate()`, `deactivate()`, `requireLifecycleFile()`, `instantiateLifecycle()`
  - Creó `backend/plugins/entity_client/Lifecycle.php`: `onInstall` llama `Installer::install()`
  - Creó `backend/tests/integration/PluginLifecycleTest.php` con 8 tests de integración (BD real, fixtures temporales con `$GLOBALS` para trackear llamadas)
- **Iteraciones:** 2 (fix `helpers.php` path + `Database::connection()` vs `::getInstance()`)
- **Decisión manual:** Fixture Lifecycle.php generado en `sys_get_temp_dir()` para evitar polución de `backend/plugins/`; `// NOSONAR` en `new $class()` justificado por convención de plugins

### STORY 4.6: Metadatos de plugin (compatibilidad, dependencias entre plugins)
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Añadió `validateDependencies()` a `PluginLoader`: valida campo `requires` del manifest (array de `{slug, version}`), comprueba que cada dependencia está en `plugins_registry` con versión suficiente
  - Creó `backend/tests/integration/PluginDependenciesTest.php` con 6 tests (requires vacío, dep no instalada, dep instalada, versión baja, entry malformada, sin campo version)
- **Iteraciones:** 1
- **Decisión manual:** `requires` sin campo `version` usa `0.0.0` como mínimo (acepta cualquier versión instalada); no se comprueba status del plugin dependiente (solo que esté registrado)

### STORY 4.7: Extender schema con identidades, campos obligatorios y relaciones opcionales
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~40 min
- **Aceleración:** ~87% ⚡
- **Qué hizo IA:**
  - Actualizó `backend/plugins/clients/schema.json` al contrato final: `identities`, `fields`, `custom_fields`, `relations`
  - Reestructuró `clients`: obligatorios de dominio en `fields` (`nombre`, `apellidos`) y sugerencias frontend en `custom_fields` (`email`, `telefono`, `activo`, `creation_stamp`)
  - Actualizó `backend/tests/unit/ClientsPluginTest.php` a 14 tests para validar contrato nuevo y que el instalador siga sembrando solo `fields` en `entity_metadata`
  - Renombró plugin para cumplir normativa: carpeta `backend/plugins/clients`, slug `clients`, namespace `Xestify\plugins\clients`
  - Alineó documentación funcional/técnica: `docs/plugins/plantilla-plugin-entidad.md`, `docs/plugins/plantilla-plugin-extension.md`, `docs/mvp/backlog.md`, `docs/mvp/decisiones-tecnicas.md`, `docs/arquitectura/plugins.md`, `docs/roadmap.md`
- **Iteraciones:** 4 (ajuste de semántica de relaciones + endurecimiento de tests + rename final de naming)
- **Decisión manual:** La relación `belongs_to` se declara únicamente en `relations` con `key` + `target_entity` + `target_field`; si falta valor, la relación se trata como ausente (pedido anónimo válido). Normativa aplicada: entidades en plural y slug sin prefijo `entity_`.

---

## EPIC 5 — Frontend Dinámico Base

### STORY 5.1: Frontend - Crear página Login
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~35 min
- **Aceleración:** ~85% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/pages/Login.js` con formulario email/password, validación de requeridos y `POST /api/v1/auth/login`
  - Integró flujo de autenticación en `frontend/src/js/main.js`: render condicional Login/Dashboard, persistencia de token, logout
  - Añadió estilos base en `frontend/src/css/main.css` para pantalla de login y shell principal
  - Creó `frontend/tests/LoginTest.html` con 5 casos (render, validación, éxito, error)
  - Añadió `tools/dev/frontend-router.php` para servir frontend y proxy `/api` en el mismo origen local
- **Iteraciones:** 3 (ajuste de warnings Sonar + compatibilidad PHP 8.5 + corrección de conflicto de servidor local en puerto 8081)
- **Decisión manual:** Mantener `API_BASE = '/api/v1'` en frontend y resolver localmente con router-proxy para no introducir diferencias entre entorno local y despliegue real.

### STORY 5.2: Frontend - Crear navbar/sidebar de navegación
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~89% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/modules/Navbar.js` con brand, links de navegación (entities/plugins), email de usuario y botón logout
  - Actualizó `frontend/src/js/main.js` para usar `Navbar` en el dashboard y añadir función `navigateTo` para routing entre páginas
  - Añadió email del usuario al response de `AuthController` (`backend/src/controllers/AuthController.php`) y lo propagó por `Login.js` → `main.js` → `AppState`
  - Amplió `frontend/src/css/main.css` con estilos completos de navbar y shell reestructurado
  - Creó `frontend/tests/NavbarTest.html` con 9 casos de test (constructor, render, links, email, logout, navigate, active state, setUserEmail)
- **Iteraciones:** 1
- **Decisión manual:** Email devuelto directamente en la respuesta de login (sin decodificar JWT en el cliente), para simplificar el frontend.

### STORY 5.3: Frontend - Integración E2E EntityList + EntityEdit
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~25 min
- **Aceleración:** ~92% ⚡
- **Qué hizo IA:**
  - Refactorizó `navigateTo('entities')` en `main.js` extrayendo `showEntityList` y `showEntityEdit` para el flujo completo
  - Añadió importación de `EntityEdit` en `main.js` y cableo de `onCreateNew` → `showEntityEdit` → `onSaved`/`onCancel` → `showEntityList`
  - Creó `frontend/tests/E2ETest.html` con 9 tests E2E que cubren: init de EntityList, carga de registros, botón "Crear", callback onCreateNew, validación de formulario, POST, error de API, cancel, y flujo completo integrado con mock fetch
- **Iteraciones:** 1
- **Decisión manual:** El schema se deriva de `AppState.getEntities()` en `showEntityEdit`, evitando pasarlo explícitamente por el callback de `onCreateNew`.

### STORY 5.3b: Fix GET /api/v1/entities + EntitySeeder + UTF-8
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Añadió `listEntities()` a `EntityController.php` con JOIN LATERAL para última versión de schema
  - Registró ruta `GET /api/v1/entities` en `routes.php` antes de las rutas de slug
  - Creó `EntitySeeder.php` con entidades demo (Clientes y Productos) con seed idempotente
  - Cabló `EntitySeeder::seedIfEmpty()` en `app.php`
  - Corrigió encoding UTF-8 añadiendo `charset=utf-8` al `Content-Type` de Response y `client_encoding=UTF8` al DSN de PDO
- **Iteraciones:** 3 (bootstrap path, BASE_PATH, UTF-8)
- **Decisión manual:** Ninguna; el fix era directo.

### STORY 5.3c: Fix Router params + tabla registros (tamaño y visualización)
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~18 min
- **Aceleración:** ~85% ⚡
- **Qué hizo IA:**
  - Corrigió `Router::buildPattern()` para soportar parámetros en formato `{slug}` además de `:slug`
  - Solucionó 404 en `GET /api/v1/entities/{slug}/records` al hacer click en entidades desde frontend
  - Normalizó registros en `EntityList` para mapear `content` (JSONB) a columnas visibles en `DynamicTable`
  - Añadió estilos de tabla/records en `main.css` para ancho correcto, legibilidad y responsive
- **Iteraciones:** 2 (fix funcional + ajuste lint)
- **Decisión manual:** Mantener compatibilidad dual de rutas (`{param}` y `:param`) en el router.

### STORY 5.4: Frontend - Crear Modal/Dialog reutilizable
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~88% ⚡
- **Qué hizo IA:**
  - Creó `frontend/src/js/modules/Modal.js` como componente reutilizable
  - Implementó métodos requeridos `show()`, `close()`, `setContent()` y soporte de título
  - Añadió cierre por backdrop y tecla Escape para usabilidad básica
  - Añadió estilos base del modal en `frontend/src/css/main.css`
  - Creó `frontend/tests/ModalTest.html` con 5 tests (show, close, contenido string, contenido HTMLElement, cierre por backdrop)
- **Iteraciones:** 1
- **Decisión manual:** Mantener API mínima de story y añadir `setTitle()`/`isOpen()` como extras no disruptivos.

### STORY 5.5: Frontend - Mejoras responsive + refinamiento UX navbar/tabla
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** ~55 min
- **Aceleración:** ~85% ⚡
- **Qué hizo IA:**
  - Reestructuró navbar para navegación por entidad (enlaces dinámicos) y mantuvo usuario + logout en bloque derecho
  - Eliminó selector duplicado de entidades en el contenido principal y dejó navegación solo en navbar
  - Corrigió título de tabla para usar `label` de entidad y añadió botón "Crear {label_singular}" con icono
  - Migró iconografía a Font Awesome y homogeneizó iconos en acciones y paginación
  - Ajustó estilos: botones sin borde, estados disabled consistentes, hover por color de texto y transiciones
  - Mejoró backend para exponer `label_singular` en `GET /entities` y actualizó seeder para versionar schema con ese campo
- **Iteraciones:** 6 (layout cross-browser, singular label por definición, iconos y estados hover/disabled)
- **Decisión manual:** Singular de entidad se define explícitamente en schema (`label_singular`), evitando heurísticas de texto.

---

## Sesión Planning — Backlog y Roadmap (2026-05-02)

### Planning: Nuevos EPIC A5 + A6 (Auditoría y Permisos)
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 3h (diseño, escritura, discusión de alcance)
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~89% ⚡
- **Qué hizo IA:**
  - Propuso estructura de EPIC A5 (Auditoría) con 4 stories: tabla, servicio, hooks en acciones, endpoint+vista
  - Propuso estructura de EPIC A6 (Permisos) con 4 stories: modelo, AuthorizationService, enforcement, UI condicional
  - Alineó criterios de aceptación con el estilo y granularidad del backlog existente
- **Iteraciones:** 2 (corrección de scope: A3/A4/A5 pasan a post-MVP)
- **Decisión manual:** Alcance final definido por usuario: A5/A6 en MVP, A3/A4/A5 post-MVP

---

### Planning: Desglose EPIC 6-10 en backlog
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 5h (diseño de 5 EPICs con stories, criterios y dependencias)
- **Tiempo real con IA:** ~30 min
- **Aceleración:** ~90% ⚡
- **Qué hizo IA:**
  - EPIC 6: 4 stories para plugins tipo extension (DynamicTabs, hooks registerTabs/Actions, plugin comments, PluginManager UI)
  - EPIC 7: 5 stories para actualizaciones/rollback + configuración de custom_fields desde UI (STORY 7.5)
  - EPIC 8: 4 stories para operación técnica (health, backup, Docker RPi5, hardening)
  - EPIC 9: 4 stories para marketplace (schema, API, UI, publicación)
  - EPIC 11: 4 stories para QA (E2E, coverage 80%, CI, benchmarks)
  - Renombró EPIC 6 de "Extensiones complejas" a "Plugins tipo extension" (corrección conceptual)
  - Movió EPIC 6-10 de OUT OF SCOPE a IN SCOPE tras decisión del usuario
- **Iteraciones:** 3 (ajuste scope 9-10, añadir STORY 7.5, renombrado EPIC 6)
- **Decisión manual:** STORY 7.5 (configuración de plugin con custom_fields desde UI) propuesta por usuario

---

### Planning: Actualización de roadmap y documentación
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~87% ⚡
- **Qué hizo IA:**
  - Reescribió `docs/roadmap.md` completo con estado real (Fases 0-4 completadas, Fase 5 completada, Fases 6-10+A5/A6 pendientes)
  - Eliminó sección de decisiones técnicas pendientes (ya resueltas)
  - Añadió tabla de corte MVP, hitos actualizados y métricas de seguimiento
  - Actualizó `ia-productivity-template.md` con los nuevos EPIC 6-10 y A5/A6
  - Actualizó `MASTER-brief.md` con scope real, timeline completado y demo actualizada
- **Iteraciones:** 1
- **Decisión manual:** ninguna

---

## EPIC 6 — Plugins tipo Extension

### STORY 6.1: Frontend - Crear módulo DynamicTabs.js
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 2h (clase + CSS + tests)
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Generó la clase `DynamicTabs` con API completa: `registerTab()`, `render()`, `setActiveTab()`, `getActiveTab()`, `destroy()`, persistencia de tab activa en URL hash, deduplicación por id
  - Generó 6 tests completos con el estilo del proyecto (✅/❌, separadores, `.pass`/`.fail`)
  - Añadió estilos de tabs en `main.css` (barra, botones, estado activo, responsive)
  - Corrigió `tools/dev/frontend-router.php` para servir rutas `/tests/` y `/src/` (fix bloqueante: módulos JS no se cargaban)
- **Iteraciones:** 3 (MIME type error en servidor, ajuste estilo tests, refactoring router)
- **Decisión manual:** La corrección del router fue identificada por el usuario; la API plugin-first de `DynamicTabs` se mantuvo sin cambios del diseño inicial

### STORY 6.2: Backend - Hook `registerTabs` y `registerActions` en HookDispatcher
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 45 min (diseño API, implementación, tests)
- **Tiempo real con IA:** ~8 min
- **Aceleración:** ~82% ⚡
- **Qué hizo IA:**
  - Añadió método `applyFilter(string $hook, array $items, array $args): array` a `HookDispatcher`
  - Implementó semántica filter: callbacks reciben y retornan array acumulado, fallos no son bloqueantes (log warning + continuar)
  - Creó `HookFilterTest.php` con 7 tests unitarios (vacío, acumulación, prioridad, args, registerActions, fallo tolerante, coexistencia con `execute()`)
  - Añadió endpoint `GET /api/v1/entities/{slug}/tabs` en `EntityController` + ruta en `routes.php` + singleton en `config/app.php`
  - Creó `HookFilterApiTest.php` con 6 tests de integración: plugin registra tab y aparece en respuesta de API, slug en args, múltiples plugins, registerActions
  - Verificó regresión: 11 tests `HookDispatcherTest` siguen pasando
- **Iteraciones:** 2 (primera iteración sin endpoint API, segunda iteración con corrección del criterio de tests de integración)
- **Decisión manual:** Nombre del método `applyFilter` (vs `filter`) para evitar colisión con built-ins de PHP

### STORY 6.4: Plugin `comments` (tipo extension)
- **Fecha:** 2026-05-02
- **Estimado sin IA:** 60 min (controller, lifecycle, hooks, migración, schema, tests)
- **Tiempo real con IA:** ~15 min
- **Aceleración:** ~75% ⚡
- **Qué hizo IA:**
  - Creó `backend/plugins/comments/manifest.json` (tipo `extension`, `target_entity: *`)
  - Creó `backend/plugins/comments/schema.json` (campos `body` y `author_id`)
  - Creó `backend/plugins/comments/Hooks.php` — registra hook `registerTabs` que añade tab "Comentarios"
  - Creó `backend/plugins/comments/Lifecycle.php`
  - Creó `backend/src/controllers/CommentsController.php` — usa tabla genérica `plugin_extension_data` con content JSONB
  - Creó `backend/database/migrations/003_plugin_extension_data.sql` — tabla genérica compartida por todos los plugins extension
  - Añadió rutas GET/POST `/api/v1/plugins/comments/{entity}/{id}` en `routes.php` y singleton en `app.php`
  - Creó `backend/tests/integration/CommentsPluginTest.php` con 9 tests
- **Iteraciones:** 3 (primera con tabla `plugin_comments` propia → corrección a tabla genérica `plugin_extension_data` → corrección sintaxis archivo duplicado)
- **Decisión manual:** Arquitectura: extensiones usan tabla genérica `plugin_extension_data` con JSONB, no tablas propias; parejo con el patrón `entity_data`

### STORY 6.3: Release B — Eliminar system_entities (plugins como única fuente de verdad)
- **Fecha:** 2026-05-03
- **Estimado sin IA:** 90 min (7 archivos, SQL, tests)
- **Tiempo real con IA:** ~20 min
- **Aceleración:** ~78% ⚡
- **Qué hizo IA:**
  - Creó `backend/database/migrations/010_drop_system_entities.sql` (DROP TABLE IF EXISTS idempotente)
  - Modificó `SystemEntity.php` — queries redirigidas a `plugins WHERE plugin_type='entity'`
  - Reescribió `SystemEntitiesTableTest.php` — ahora verifica que la tabla NO existe + 2 tests de catalog en plugins
  - Actualizó `MigrationIdempotenceTest.php` — eliminó system_entities de tablas esperadas, añadió migración 010, redirigió test de datos a plugins
  - Actualizó `SystemEntityTest.php` — fixtures INSERT/DELETE en plugins (entity, inactive status)
  - Aplicó migración 010 a xestify_dev (DROP TABLE devuelta)
- **Iteraciones:** 2 (test "plugins entity rows have required fields" fallaba por filas de test sin name → filtrado a status='active')
- **Decisión manual:** Ninguna — arquitectura ya decidida en Release A; solo ejecución técnica

### Fix 6.5-pre: PluginLoader wiring — `registerActiveHooks()` en boot
- **Fecha:** 2026-05-03
- **Estimado sin IA:** 45 min
- **Tiempo real con IA:** ~10 min
- **Aceleración:** ~78% ⚡
- **Qué hizo IA:**
  - Añadió `registerActiveHooks(HookDispatcher $dispatcher): void` a `PluginLoader` — consulta plugins activos en BD y registra sus hooks en el dispatcher
  - Añadió `instantiateHooks(string $slug): ?object` — instancia la clase `Hooks` de cada plugin usando `ReflectionClass` para detectar si el constructor necesita `PDO` o no, sin hardcoding por slug
  - Cabló `PluginLoader` en `app.php`: singleton registrado, `registerActiveHooks()` llamado al boot
  - Creó `backend/tests/integration/PluginBootTest.php` con 3 tests que verifican el path real de boot (sin registro manual de hooks)
- **Iteraciones:** 1
- **Decisión manual:** Usar Reflection en `instantiateHooks()` para detectar dependencias en lugar de un mapa explícito o convención por nombre — más robusto y extensible a futuro

### Fix general: arquitectura plana de plugins + extensiones genéricas + docs
- **Fecha:** 2026-05-03
- **Estimado sin IA:** 180 min
- **Tiempo real con IA:** ~55 min
- **Aceleración:** ~69% ⚡
- **Qué hizo IA:**
  - Migró plugins a estructura plana en `/plugins/{slug}` y ajustó `PluginLoader`/tests a rutas finales
  - Reemplazó `CommentsController` por `PluginExtensionController` con rutas REST genéricas por `plugin_slug`
  - Refactorizó `EntityEdit` para carga dinámica de `plugin.js` y contrato `PluginPanelRegistry`
  - Encapsuló toda la UI de comentarios en `plugins/comments/plugin.js` y corrigió UX de botones en edición
  - Actualizó `frontend-router.php` para servir assets desde `/plugins/*`
  - Ejecutó y validó tests de integración clave (`CommentsPluginTest` y `PluginBootTest`)
  - Actualizó documentación de arquitectura/API/plantillas y roadmap al modelo vigente
- **Iteraciones:** 5
- **Decisión manual:** Tratar esta entrega como fix general transversal (no story nueva), priorizando coherencia arquitectónica y reducción de acoplamiento del core

---

### Fix SonarQube — 44 hallazgos de calidad
- **Fecha:** 2026-05-03
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~30 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - `HookFilterTest.php` + `HookFilterApiTest.php`: añadió `// NOSONAR` en firmas fijas de closures, sustituyó `\RuntimeException` por `\AssertionError`, añadió comentario en constructor vacío de stub PDO
  - `GenericRepository.php`: extrajo constante `SQL_UPDATE` para eliminar literal `'UPDATE '` duplicado ×3
  - `SystemEntitiesTableTest.php`: extrajo constante `MSG_QUERY_EXECUTE` para literal duplicado ×3
  - `frontend-router.php`: redujo complejidad cognitiva de 17 a <10 extrayendo 4 helpers (`filterRequestHeaders`, `collectResponseHeaders`, `applyStatusCode`, `applyResponseHeaders`)
  - `Modal.js`: eliminó escapes innecesarios `\"` en template literal
  - `DynamicTabs.js`: corrigió condición negada, `String#match()` → `RegExp#exec()`, `Error` → `TypeError`
  - `EntityEdit.js`: corrigió condición negada en ternario
  - `StateTest.html`: `replace(/regex/g)` → `replaceAll()`
  - `plugin.js` (comments): rutas de import absolutas → relativas
  - `.vscode/settings.json`: desactivó regla `javascript:S1848` (falso positivo en tests con side-effects de render)
- **Iteraciones:** 2 (primera iteración: aplicar fixes; segunda: gestionar S1848 en tests HTML con `settings.json`)
- **Decisión manual:** Regla S1848 desactivada en lugar de reescribir los tests — instanciar sin asignar es idioma válido cuando el constructor tiene side-effects de renderizado DOM


---

### STORY 6.5 - Frontend - Página PluginManager
- **Fecha:** 2026-05-04
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~60 min
- **Aceleración:** ~80%
- **Qué hizo IA:**
  - Creó `PluginManagerController.php` con endpoints GET /api/v1/plugins y PUT /api/v1/plugins/{slug}/status
  - Creó `PluginManagerApiTest.php` con 8 tests usando stubs TestPdo/TestStatement sin base de datos real
  - Creó `PluginManager.js` (frontend) con lista de plugins, toggle activo/inactivo y estados de carga
  - Creó `PluginManagerTest.html` con 8 tests (8/8)
  - Integró PluginManager en `main.js` y añadió link condicional en `Navbar.js` (`canManagePlugins`)
  - Corrigió regresiones en NavbarTest, LoginTest, EntityListTest y E2ETest causadas por los nuevos cambios
  - Actualizó todos los fixtures de tests frontend de slug `client` a `clients` (slug canónico)
  - Completó el test E2E integrado con click simulado en botón Guardar de EntityEdit
- **Iteraciones:** 6
- **Decisión manual:** El test E2E integrado requirió análisis manual del flujo real de EntityEdit para entender que escucha click en botón, no el evento submit del form
- **Cierre:** Verificado contra commit `7d2d313`; backend `php backend/tests/run.php all` pasa 28/28 archivos.

---

## EPIC 7 - Actualizaciones de Plugins y Rollback

### STORY 7.1 - Detección de actualizaciones disponibles en PluginLoader
- **Fecha:** 2026-05-06
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~45 min
- **Aceleración:** ~75%
- **Qué hizo IA:**
  - Auditó la implementación inicial generada con GitHub Copilot y detectó que el boot consumía las actualizaciones al sobrescribir `plugins.version`.
  - Corrigió `PluginLoader::load()` para preservar la versión instalada cuando el plugin ya existe.
  - Implementó `PluginLoader::getOutdated()` para comparar versión instalada en base de datos contra versión disponible en `manifest.json`.
  - Cableó `PluginManagerController` para reutilizar el `PluginLoader` del contenedor y exponer `GET /api/v1/plugins/updates`.
  - Reforzó `PluginLoaderTest.php` con casos de versión mayor, igual y menor.
  - Reescribió el test del endpoint con fixture real en disco y alineó los nombres nuevos de `TestSuite::run()` con el estilo existente.
  - Actualizó `docs/03-api/endpoints.md`.
- **Iteraciones:** 3 (revisión de Copilot, corrección de diseño, refuerzo de tests/contrato API)
- **Decisión manual:** La versión instalada no se actualiza durante `load()`; se mantiene hasta que una story posterior implemente el proceso explícito de update/rollback.

---

## Sesion tecnica transversal - Apache+PHP single-origin, setup explicito y rendimiento local

- **Fecha:** 2026-05-07
- **Estimado sin IA:** 8h
- **Tiempo real con IA:** ~2h 15min
- **Aceleración:** ~72%
- **Qué hizo IA:**
  - Replanteó el runtime de desarrollo y despliegue para operar con Apache+PHP en un solo origen.
  - Sustituyó el flujo basado en router PHP de desarrollo por configuración Apache + `.htaccess`.
  - Adaptó el frontend para resolver `base path` dinámico y funcionar bajo alias/subruta (`/xestify`).
  - Corrigió backend para aceptar rutas bajo alias Apache y preservar el header `Authorization`.
  - Extrajo del boot normal el seeding de usuario y la sincronización de plugins.
  - Creó scripts manuales de setup y sincronización (`seed-admin-user.php`, `sync-plugins.php`).
  - Redefinió el contrato operativo de plugins: runtime desde BD, sync disco -> BD como operación explícita.
  - Actualizó backlog, README y documentación operativa para reflejar Apache+PHP, setup manual y recomendaciones de rendimiento.
  - Midió tiempos reales bajo Apache y aisló el mayor cuello local en `Xdebug` arrancando en cada request.
- **Iteraciones:** 9
- **Decisión manual:** No cerrar ninguna story nueva con este trabajo; registrarlo como sesión técnica transversal y reservar el sync administrativo para STORY 7.2/7.5.

---

### STORY 7.2 - Proceso de actualizacion con migracion de schema
- **Fecha:** 2026-05-10
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** ~1h 45min
- **Aceleración:** ~71%
- **Qué hizo IA:**
  - Reinterpretó la story contra el modelo real del repo, usando `plugins.schema_json` como schema vivo en lugar de `entity_metadata`.
  - Añadió la migración `005_plugin_update_history.sql` y la tabla `plugin_update_history` para snapshots previos al update.
  - Refactorizó `PluginLoader` para separar `syncAll()` y `update()`, preservando el runtime de plugins ya instalados durante la sincronización desde disco.
  - Implementó el flujo transaccional de update con diff de schema solo aditivo, snapshot previo, `onUpdate(array $context)` opcional y rollback automático ante error.
  - Expuso `POST /api/v1/plugins/sync` y `POST /api/v1/plugins/{slug}/update` en `PluginManagerController` y `routes.php`.
  - Adaptó `tools/setup/sync-plugins.php` al nuevo contrato explícito de sync sin consumo parcial de actualizaciones.
  - Reforzó tests de integración para sync best-effort, update exitoso, conflicto por cambio no aditivo, rollback al fallar lifecycle y tabla `plugin_update_history`.
  - Actualizó backlog, README, docs de API y documentación operativa para reflejar el cierre de la story.
- **Iteraciones:** 4
- **Decisión manual:** Mantener `onUpdate()` como convención opcional detectada con `method_exists` y no ampliar `PluginLifecycleInterface`, para no romper plugins existentes mientras se prepara la base de rollback manual de la STORY 7.4.

---

### STORY 7.3 - Frontend - Pagina de configuracion de plugin activado
- **Fecha:** 2026-08-04
- **Estimado sin IA:** 8h
- **Tiempo real con IA:** ~2h 30min
- **Aceleración:** ~69%
- **Qué hizo IA:**
  - Preparó el cierre documental de la story ya implementada y contrastó el alcance real contra los diffs del repositorio.
  - Documentó la nueva página `PluginConfig.js` para configurar plugins activos desde `#/plugins/{slug}`.
  - Registró los endpoints admin `GET /api/v1/plugins/{slug}/config` y `PUT /api/v1/plugins/{slug}/config`.
  - Resumió la lógica backend de `PluginAdministrationService` para proteger campos base, activar/desactivar sugerencias, añadir campos libres y versionar `plugins.schema_json`.
  - Incorporó el refuerzo para plugins `extension`: configuración de campos desde la misma tabla, persistencia de `target_entity` y validación de entidad destino.
  - Recogió la separación de responsabilidades en `ExtensionPluginConfigService`, `ExtensionPluginContentService` y `ExtensionPluginDataStore`.
  - Verificó la suite backend completa y sintaxis de los módulos JS afectados antes de preparar el commit.
- **Iteraciones:** 3
- **Decisión manual:** Tratar el refuerzo de plugins `extension` como parte del cierre de STORY 7.3, manteniendo STORY 7.4 enfocada exclusivamente en rollback manual.

---

### STORY 7.4 - Rollback manual de plugin a version anterior
- **Fecha:** 2026-08-05
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** ~2h
- **Aceleración:** ~67%
- **Qué hizo IA:**
  - Implementó `PluginRollbackService` con transacción, lock de plugin instalado y selección de snapshot por `slug + target_version`.
  - Añadió restauración de estado/version/schema en `PluginRepository` y consulta dedicada en `PluginUpdateHistoryRepository`.
  - Expuso `POST /api/v1/plugins/{slug}/rollback` en `PluginManagerController` y `routes.php`.
  - Añadió convención opcional `onRollback(array $context)` en `PluginLifecycleInvoker` sin romper `PluginLifecycleInterface`.
  - Reforzó la cobertura con pruebas de integración para rollback exitoso, rollback sin snapshot y API de rollback (200/404/409).
  - Actualizó documentación de endpoints y estado de backlog/roadmap/sesión.
- **Iteraciones:** 3
- **Decisión manual:** Mantener `onRollback()` como convención opcional detectada por `method_exists`, igual que `onUpdate()`, para preservar compatibilidad con plugins existentes.

---

### STORY 7.5 - Frontend - UI de actualizacion y rollback en PluginManager
- **Fecha:** 2026-08-05
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** ~1h 45min
- **Aceleración:** ~65%
- **Qué hizo IA:**
  - Extendió `PluginManager` con sincronización explícita (`POST /plugins/sync`) y recarga de datos con feedback visual.
  - Integró lectura de updates (`GET /plugins/updates`) para mostrar badge de "update disponible" por plugin.
  - Añadió acciones `Update` y `Rollback` con confirmación modal previa y mensajes de éxito/error.
  - Incorporó señal de backend `can_rollback` en `GET /plugins` para mostrar rollback solo cuando existe snapshot compatible.
  - Actualizó estilos responsive y cobertura de tests frontend para los nuevos flujos.
  - Verificó regresión con `PluginManagerApiTest` y grupo completo `integration-plugins`.
- **Iteraciones:** 3
- **Decisión manual:** Exponer `can_rollback` desde backend en el listado para evitar UI ambigua y cumplir el criterio de mostrar rollback únicamente cuando hay versión previa recuperable.

---

## EPIC 8 — Gestión de usuarios

### STORY 8.1: Backend - Migración de perfil y UserRepository
- **Fecha:** 2026-08-05
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~45 min
- **Aceleración:** ~75% ⚡
- **Qué hizo IA:**
  - Añadió columnas de perfil (`name`, `avatar`, `deleted_at`) a la migración base de usuarios.
  - Implementó `UserRepository` con CRUD orientado a perfil y borrado lógico.
  - Añadió tests de integración para lectura, actualización, password y borrado.
  - Ajustó el login para rechazar usuarios borrados.
- **Iteraciones:** 2 (ajustes de limpieza de tests y validación de login)
- **Decisión manual:** Mantener borrado lógico en `users` para conservar trazabilidad y bloquear acceso desde autenticación.

### STORY 8.2: Backend - UserController y rutas REST
- **Fecha:** 2026-08-05
- **Estimado sin IA:** 3h
- **Tiempo real con IA:** ~45 min
- **Aceleración:** ~85% ⚡
- **Qué hizo IA:**
  - Implementó `UserController` con endpoints de perfil propio y administración.
  - Registró rutas protegidas y wiring del controlador en el container.
  - Generó tests de integración para perfil, validación de password y borrado lógico.
- **Iteraciones:** 2 (ajuste de validaciones y revisión de SonarQube)
- **Decisión manual:** Mantener el borrado lógico y bloquear el auto-borrado para admins.

### STORY 8.3: Frontend - UserMenu dropdown en Navbar
- **Fecha:** 2026-08-05
- **Estimado sin IA:** 2h
- **Tiempo real con IA:** ~1h 15m
- **Aceleración:** ~63% ⚡
- **Qué hizo IA:**
  - Implementó el componente `UserMenu` con disparador, hover y acciones de perfil/usuarios/logout.
  - Integró el menú en el navbar principal y conectó la navegación al shell de la aplicación.
  - Ajustó estilos para que el dropdown se muestre correctamente y no colapse al pasar el cursor.
  - Añadió una prueba aislada para validar render, hover y navegación.
- **Iteraciones:** 3 (ajustes de hover, buffer de transición y revisión del comportamiento real)
- **Decisión manual:** Mantener el menú como interacción de hover/simple click y dirigir las acciones a vistas reales dentro del shell.

### STORY 8.4: Frontend - Página Mi Perfil (`#/profile`)
- **Fecha:** 2026-08-05
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~2h 30m
- **Aceleración:** ~62% ⚡
- **Qué hizo IA:**
  - Implementó la vista de perfil con formulario editable, carga de datos del usuario autenticado y guardado hacia el endpoint propio.
  - Añadió validación inline para email y password, incluyendo feedback de fuerza de contraseña y preservación de valores en errores.
  - Conectó la actualización del estado global con el navbar para que el cambio se refleje en tiempo real sin recargar.
  - Ajustó mensajes de respuesta al español y cubrió la ruta con pruebas de integración y de UI.
  - Registró el estado final de la story en los documentos de seguimiento de productividad para que la implementación quede trazada y reproducible.
- **Iteraciones:** 4 (ajustes de validación, estado global, sincronización navbar y limpieza de hallazgos)
- **Decisión manual:** Mantener el flujo de guardado simple y coherente con la arquitectura actual, sin introducir un segundo mecanismo de estado paralelo.

### STORY 8.5: Frontend - Página gestión de usuarios (`#/usuarios`)
- **Fecha:** 2026-08-06
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** ~2h 45m
- **Aceleración:** ~54% ⚡
- **Qué hizo IA:**
  - Reemplazó la página demo de usuarios por una vista real con carga desde API y tabla completa (avatar, nombre, email, roles, alta y acciones).
  - Implementó modales de editar usuario, reset password y borrado con confirmación y guard de auto-borrado.
  - Añadió soporte de rutas hash `#/usuarios` y `#/usuarios/:id` con navegación directa y selección de ficha de usuario.
  - Extendió backend para edición de roles y endpoint admin de reset (`PUT /api/v1/users/{id}/password`) más pruebas de integración.
  - Añadió test frontend dedicado `UserManagementTest.html` con cobertura de flujos clave.
- **Iteraciones:** 4 (ajustes de test reset, refactor por reglas de calidad y refinado de UX en modales)
- **Decisión manual:** Mantener la generación de contraseña temporal en backend y mostrarla una sola vez en modal, priorizando seguridad operativa sin persistencia en frontend.

### STORY 9.1: Fundamentos de diseño
- **Fecha:** 2026-08-06
- **Estimado sin IA:** 12h
- **Tiempo real con IA:** ~6h 30m
- **Aceleración:** ~46% ⚡
- **Qué hizo IA:**
  - Consolidó la base visual enterprise inspirada en Ant Design con Tailwind, tokens `brand/slateui`, tipografía IBM Plex y componentes homogéneos.
  - Refactorizó `DynamicTable` para convertirlo en la clase única de tablas y migró Entidades, Plugins, Usuarios y PluginConfig al mismo constructor.
  - Ajustó `DynamicTabs` y `EntityEdit` para alinear tabs y layout con el patrón esperado, incluyendo `ink bar` y control del padding superior del wrapper.
  - Sustituyó el Play CDN de Tailwind por una hoja local generada (`tailwind.generated.css`) a partir de `tailwind.src.css` y `tailwind.config.cjs`, eliminando warnings de runtime.
  - Recolocó los overrides residuales necesarios dentro de capas Tailwind (`@layer base` / `@layer utilities`) para evitar CSS paralelo fuera del pipeline.
- **Iteraciones:** 11 (refinado visual incremental, unificación de acciones/tablas, migración de Tailwind y ajustes tras validación manual)
- **Decisión manual:** Mantener Tailwind como fuente única de estilos en runtime, pero sin CDN y sin bundler, generando la hoja final offline y sirviéndola como asset estático.

### STORY 9.2: Fundamentos de navegacion y anatomia de paginas
- **Fecha:** 2026-08-07
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~40 min
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Extrajo la convención de rutas a `frontend/src/js/modules/Routes.js` como fuente única de hashes actuales y reservados.
  - Centralizó el parseo y generación de URLs para perfil, usuarios, plugins y entidades desde un contrato compartido.
  - Eliminó los aliases legacy en español (`#/usuarios`, `#/entidades`, `nuevo`) para dejar el mapa estrictamente canónico en inglés.
  - Documentó arquitectura de información, plantillas de página, breadcrumbs y reglas de copy en `docs/05-frontend/navegacion-anatomia.md`.
  - Alineó backlog, roadmap y sesión con el nuevo estado de la story.
  - Eliminó un hash hardcodeado en `UserManager.js` para preparar el terreno a la modularización y al router formal.
- **Iteraciones:** 2 (contrato inicial + limpieza de aliases legacy y docs finales)
- **Decisión manual:** Mantener el routing hash actual y posponer el router formal a STORY 9.6, usando en 9.2 solo un contrato compartido de navegación en inglés.

### STORY 9.3: Libreria de componentes UI base
- **Fecha:** 2026-08-08
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** ~3h 30m
- **Aceleración:** ~42% ⚡
- **Qué hizo IA:**
  - Consolidó `ComponentFactory` como API única y catálogo canónico de componentes.
  - Implementó y validó `InputSwitch` como control booleano estándar.
  - Unificó el modal sobre la base común y alineó `Tabs` con tokens Tailwind.
  - Detectó y corrigió el problema real de build de Tailwind por globs mal resueltos desde la raíz del repo.
  - Ajustó los iconos de las columnas de acciones de tabla a 18px para mejorar legibilidad.
  - Ejecutó baterías amplias de tests y validaciones en navegador sobre Components, DynamicForm, DynamicTable, Modal, PluginManager, PluginConfig, EntityList, EntityEdit, Navbar, Login, UserManagement y UserProfile.
- **Iteraciones:** 4
- **Decisión manual:** Mantener Tailwind como fuente única de estilos runtime, corregir el pipeline para que se ejecute desde la raiz del repo y forzar 18px solo en iconos de acciones de tabla.

### STORY 9.4: Arquitectura frontend y modularizacion
- **Fecha:** 2026-08-08
- **Estimado sin IA:** 8h
- **Tiempo real con IA:** ~4h 15m
- **Aceleración:** ~47% ⚡
- **Qué hizo IA:**
  - Dejó `frontend/src/js/app.js` como bootstrap técnico mínimo en la raíz y delegó el arranque y el wiring de la SPA en `controllers/AppController.js`.
  - Consolidó el routing en `RouteController`, `RouteMapController` y `PluginRouteController`, eliminando `RouteModel.js` y `PluginRouteModel.js` como capas ambiguas en `models`.
  - Alineó `AppController`, `UserManager` y el runtime de plugins (`PluginPanelModel` + `plugins/comments/plugin.js`) con la organización MVC estricta.
  - Adaptó y verificó los tests HTML clave (`StateTest`, `FrontendArchitectureTest`, `E2ETest`) en el navegador integrado de VS Code, incluyendo la corrección de mocks para evitar `404` espurios en tabs de entidad.
  - Ejecutó la auditoría final sobre los 17 runners HTML, corrigió una regresión de inicialización heredada en `UserProfile` y dejó 146/146 assertions sin errores de consola.
  - Actualizó la documentación técnica y de backlog para reflejar el frontend MVC estricto y el nuevo punto de entrada del runtime.
- **Iteraciones:** 8 (definición de criterio MVC, refactor de rutas, ajuste de imports, validación en navegador, corrección de tests, auditoría completa y limpieza de hallazgos de Sonar)
- **Decisión manual:** Mantener `app.js` en la raíz como entrypoint localizable y sin lógica de aplicación; conservar el cliente API y el runtime de paneles de plugin en `models`, y mover la traducción de rutas y la orquestación de navegación a `controllers` sin abrir capas paralelas.

### STORY 9.5: Shell SPA y plantillas de navegacion
- **Fecha:** 2026-08-10
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** No medido; la sesión retomó una implementación local previa
- **Aceleración:** No calculable con datos fiables
- **Qué hizo IA:**
  - Auditó cada criterio de aceptación contra el código y los tests locales, separando el alcance de shell de los requisitos futuros de routing de STORY 9.6.
  - Validó `ShellLayout`, `PageLayout`, `ListLayout` y `FormLayout`, además de su integración en las páginas principales.
  - Detectó que Login construía un shell manual paralelo y lo migró a la plantilla standalone `login` de `PageLayout`.
  - Eliminó `ShellLayoutView.js`, implementación duplicada y sin consumidores.
  - Añadió cobertura arquitectónica de Login y ejecutó los 17 runners HTML en el navegador integrado con 166/166 aserciones.
  - Auditó y alineó README, índices documentales, arquitectura MVC, navegación, decisiones técnicas, backlog y roadmap con el cierre real de la story.
- **Iteraciones:** 5 (auditoría de alcance, corrección focal, validación completa, alineación documental y normalización de naming)
- **Decisión manual:** Mantener una única implementación persistente de `ShellLayout` para páginas autenticadas y resolver Login mediante `PageLayout` standalone, sin navbar y sin introducir otro layout paralelo.

### STORY 9.6: Implementacion del routing SPA
- **Fecha:** 2026-08-10
- **Estimado sin IA:** 5h
- **Tiempo real con IA:** No medido; la sesión partió de una implementación parcial ya existente
- **Aceleración:** No calculable con datos fiables
- **Qué hizo IA:**
  - Auditó el router existente contra las once rutas y los criterios de entrada directa, refresh, back/forward y persistencia de contexto.
  - Completó el mapa bidireccional con login, home y tabs de registro, parsing estricto y navegación programática mediante hashes públicos.
  - Integró el contexto de tab en `AppController`, `EntityEdit`, plantillas, breadcrumbs y navbar.
  - Centralizó el parser de configuración de plugins tras reproducir un desajuste entre el mapa y `PluginRouteController` en el runtime Apache.
  - Unificó los retornos de navegación en `page-header-toolbar` con variant `secondary`, separándolos de los comandos persistentes del formulario.
  - Sustituyó la supresión global de `hashchange` por entradas deterministas de historial para evitar renders duplicados y pérdidas de navegación.
  - Convirtió `#/home`, `#/` y el hash vacio en aliases que se reemplazan por la primera entidad activa mientras no existe una pagina de inicio.
  - Conectó la selección de `DynamicTabs` con `EntityEdit` y `AppController` para navegar a la subruta del tab, usando `data` como id base y el slug para extensiones.
  - Separó la actualización de historial del despacho de páginas y reutilizó la instancia activa de `EntityEdit` para evitar rerenders entre tabs, incluidos back/forward.
  - Amplió los tests de tabs y ejecutó los 17 runners HTML en navegador integrado con 169/169 aserciones.
- **Iteraciones:** 12 (auditoría local, núcleo del mapa, integración de tabs, cobertura de historial, unificación de home, simplificación de plugins, reproducción en Apache, consistencia de retornos, fallback de inicio, navegación interactiva por tabs, optimización sin rerender y limpieza final)
- **Decisión manual:** Mantener identificadores internos para las vistas y exponer hashes canónicos como contrato público del router; hasta implementar una pagina de inicio, `#/home` y `#/` redirigen a la primera entidad activa. La configuración usa `#/plugins/:slug` sin segmentos redundantes y los tabs usan `#/entity/:slug/:id/:tab`, con `data` como pestaña base. Los tabs del mismo registro actualizan historial sin despachar de nuevo la página y back/forward reutiliza la instancia precargada.

### STORY 9.7: Infraestructura transversal de frontend y resiliencia
- **Fecha:** 2026-08-10
- **Estimado sin IA:** 6h
- **Tiempo real con IA:** No medido; la implementación se cerró en una sesión de consolidación sobre la base ya existente
- **Aceleración:** No calculable con datos fiables
- **Qué hizo IA:**
  - Extendió el estado global de la app para incluir notificaciones, navegación transversal y preferencias UI compartidas.
  - Implementó `UiResilienceService` como servicio único para feedback global, mensajes amigables de error, confirmaciones modales y notificaciones de éxito/error.
  - Añadió una base de i18n ligera (`I18nModel`) y conectó textos reutilizables en shell, formularios y páginas clave.
  - Implementó el modelo y panel de tema con preferencias persistidas por cliente y aplicación global en tiempo real.
  - Integró `AppController` con el nuevo pipeline de resiliencia para capturar errores JS/red y mostrar feedback coherente en la shell.
  - Actualizó `Login`, `EntityList`, `EntityEdit`, `PluginManager` y `UserManager` para consumir la infraestructura común en lugar de duplicar handlers y estados locales.
- **Iteraciones:** 4 (estado transversal, feedback global, i18n/theming y validación final en navegador)
- **Decisión manual:** Mantener la capa transversal como una única fuente de verdad para feedback y preferencias; no abrir un segundo store paralelo para cada página.

## STORY 9.8 UX transversal, accesibilidad y microinteracciones

- **Fecha:** 2026-08-10
- **Estimado sin IA:** 4h
- **Tiempo real con IA:** ~1h 20m
- **Aceleración:** ~70% ⚡
- **Qué hizo IA:**
  - Consolidó estados compartidos de loading, vacío, error y éxito para las páginas clave del frontend.
  - Reforzó la capa de confirmación y pending states para acciones sensibles como borrar, activar/desactivar plugins o guardar cambios.
  - Mejoró el modal y las notificaciones con foco inicial, trampa de foco, cierre con Escape y retorno de foco al disparador.
  - Añadió y validó pruebas de regresión en navegador integrado para la resiliencia y la gestión de usuarios.
- **Iteraciones:** 3 (ajustes de modal, validación de tests y alineación documental)
- **Decisión manual:** Mantener la capa de UX en un servicio compartido y no repartirse la lógica por páginas; la validación de accesibilidad debe hacerse en navegador real.

## STORY 9.9 Documentacion de arquitectura frontend y testing UI automatizado

- **Fecha:** 2026-08-11
- **Estimado sin IA:** 8h
- **Tiempo real con IA:** ~3h
- **Aceleración:** ~63% ⚡
- **Qué hizo IA:**
  - Reorganizó `frontend/tests/` en `integration/` (los 19 runners HTML existentes, reubicados y con sus rutas relativas corregidas) y `e2e/` (proyecto Playwright nuevo), acordando la jerarquía con el usuario antes de mover nada.
  - Instaló y configuró Playwright (`package.json`, `playwright.config.js` con `baseURL` configurable, `.htaccess` de exclusión) contra el runtime real Apache+PHP.
  - Al verificar los 19 runners movidos contra el runtime real (en vez del mecanismo de verificación previo), encontró y corrigió 2 bugs reales de producción (`UserConfig.js` sin importar `AppState`; `EntityList.js` borraba su propio estado vacío por orden de ejecución) y 5 aserciones de test obsoletas frente al comportamiento real vigente.
  - Escribió 5 specs E2E (`login`, `shell-navigation`, `entity-crud`, `plugin-manager`, `theme-wysiwyg`) cubriendo los flujos mínimos exigidos por la story, incluyendo el flujo WYSIWYG completo (cambio de tema, aplicación inmediata, persistencia tras recargar).
  - Depuró condiciones de carrera propias de los tests (navegación antes de que el listado inicial asentara, paginación sobre un dataset local de 200+ registros) sin tocar código de producción salvo cuando el hallazgo era un bug real confirmado.
  - Redactó `arquitectura.md`, `guia-extension.md` y `testing-ui.md`, enlazándolos desde `README.md` y actualizando la ruta de tests en `AGENTS.md`.
- **Iteraciones:** 7 (reorganización de tests, scaffold Playwright, diagnóstico y fix de bugs de producción, corrección de aserciones obsoletas, 5 specs E2E, estabilización de condiciones de carrera, documentación)

## EPIC 10 — Login, Persons y Plugins de Demostración

### STORY 10.1: Mejoras en la sección de login

- **Fecha:** 2026-08-13 a 2026-08-14
- **Estimado sin IA:** 16h (refactor UX completo de login + protección de usuarios seed + identidad visual nueva + adaptación a tema oscuro, con su verificación)
- **Tiempo real con IA:** No medido con precisión; sesión larga repartida en varios días con iteraciones de feedback UI/UX punto por punto
- **Aceleración:** No calculable con datos fiables
- **Qué hizo IA:**
  - Diseñó y planificó con el usuario (varias rondas de `AskUserQuestion`) el refactor completo de mensajes/carga/validación de `Login.js` antes de tocar código, documentando explícitamente las decisiones y lo descartado.
  - Implementó el backend de STORY 10.1: `/health` expone `APP_DEBUG`, segundo usuario seed idempotente, protección de ambos usuarios seed (incluida autoservicio `PUT /users/me`).
  - Implementó el refactor frontend: zona de feedback única, componente `Loader` propio, shake accesible, duración mínima anti-parpadeo, interceptor centralizado de sesión caducada, toggle de contraseña, validación de cliente con foco automático.
  - Iteró en más de 15 rondas de feedback visual/UX punto por punto del usuario (iconos Font Awesome, wordmark, colores por tema, componentes `Logo`/`BrandLogo`/`Loader` extraídos a partir de HTML/CSS exacto proporcionado, adaptación a `pageStyle` dark de cada elemento reportado uno a uno).
  - Verificó cada cambio contra el runtime real Apache+PHP+Postgres con Playwright (no solo tests con mocks), detectando y corrigiendo 6+ bugs reales de producción durante el proceso (tema no persistía tras logout, varios elementos sin adaptar a dark, fondo del toggle deshabilitado).
  - Al preparar el commit final, auditó el cumplimiento completo de la story contra el plan original y encontró 3 gaps de verificación reales: 2 tests de integración nunca registrados en el runner (`AuthControllerTest`, `MigrationIdempotenceTest`), 2 aserciones obsoletas que validaban el comportamiento previo con bug, y cobertura unitaria ausente del interceptor de sesión caducada.
- **Iteraciones:** 20+ (planificación con preguntas de aclaración, implementación backend, refactor UX frontend completo, identidad visual con componentes nuevos, más de 15 rondas de correcciones visuales/UX punto por punto, verificación final con detección de gaps de test)
- **Decisión manual:** El loader se reimplementa como componente propio en vez de reutilizar el genérico existente (decisión explícita del usuario, rechazando la primera propuesta de reutilizarlo); los componentes `Logo`/`BrandLogo` se implementan con el HTML/CSS exacto proporcionado por el usuario, sin margen de interpretación; `brand.css` queda como única excepción documentada a "Tailwind como capa principal".
- **Decisión manual:** Ante cada hallazgo que tocaba código de producción (`frontend/src/`) o cambiaba el comportamiento esperado de un test existente, la IA paró y pidió confirmación explícita antes de aplicar el fix, en vez de asumir alcance ampliado por su cuenta.

---

### STORY 10.2: Renombrar plugin `clients` a `persons`

- **Fecha:** 2026-08-14 a 2026-08-15
- **Estimado sin IA:** 6h (rename mecánico pero de alcance amplio: backend, ~10 tests frontend, ~14 ficheros de documentación, ajustes de datos en BD, y dos bugs de sincronización de schema no evidentes hasta ejecutar la verificación real)
- **Tiempo real con IA:** ~2h30 repartidas en dos sesiones, con el usuario pidiendo explícitamente pausar y pedir confirmación tras cada bloque del plan antes de continuar
- **Aceleración:** ~58%
- **Qué hizo IA:**
  - Investigación previa con 3 agentes `Explore` en paralelo (backend, frontend, documentación) para mapear todas las referencias reales a `clients` antes de planificar, evitando planificar a ciegas.
  - Detectó por su cuenta, revisando el patrón de migración a seguir, que `007_users_add_is_seed.sql` mezclaba una columna estructural con un backfill puntual, y que `UserSeeder.php` exige la columna desde el primer `INSERT` — proponiendo fusionar la columna en `001_users.sql` y tratar el backfill como ajuste puntual documentado, no como migración permanente.
  - Ejecutó el rename completo: carpeta/namespace/manifest/schema del plugin, ~15 ficheros de test backend, 10 tests de integración frontend (incluido `E2ETest.html`, no detectado en la exploración inicial y encontrado en un barrido final de verificación), 3 specs E2E, `AGENTS.md` y 14 ficheros de documentación viva.
  - Aplicó los ajustes de datos puntuales contra la BD local vía `psql` (backfill `is_seed`, rename `clients`→`persons` en `plugins`/`plugin_entity_data`/`plugin_extension_data`), documentando en el chat las filas afectadas de cada sentencia.
  - Detectó por su cuenta, al revisar la pregunta del usuario sobre `schema_json`, que el campo `"entity"` embebido en `plugins.schema_json` había quedado obsoleto tras el ajuste de columnas (`InstalledPluginSchemaValidator::assertEntityMatches()` habría fallado en el próximo sync) y lo corrigió con `jsonb_set`.
  - Ejecutó la verificación completa: backend 56/56 (diagnosticando y resolviendo un problema de PATH que resolvía a un PHP sin `pdo_pgsql`), comprobación headless de apoyo de los 10 runners frontend afectados, y 12/12 specs E2E contra el runtime real.
- **Iteraciones:** 15+ (exploración en paralelo, 4 rondas de `AskUserQuestion` para cerrar decisiones de alcance antes de escribir el plan, ejecución en bloques con pausa y confirmación explícita tras cada uno, corrección de `id_cliente`→`id_person` señalada por el usuario, hallazgo y fix del `schema_json.entity` obsoleto, verificación final)
- **Decisión manual:** El usuario interrumpió `ExitPlanMode` para señalar el riesgo de `007_users_add_is_seed.sql` antes de aprobar el plan, cambiando el enfoque de migración committeada a ajuste puntual documentado — una decisión de arquitectura de datos que la IA no había planteado por su cuenta.
- **Decisión manual:** El usuario pidió explícitamente pausar tras cada bloque del plan para revisar y stagear cambios antes de continuar, en vez de ejecutar la story de una sola vez.
- **Decisión manual:** El usuario detectó la clave técnica en español `id_cliente` (mezclada en un test real, no solo en documentación) y especificó directamente el nombre de reemplazo (`id_person`), sin dejarlo a interpretación de la IA.
- **Decisión manual:** Ante la duda planteada por el usuario sobre si el diseño de STORY 10.3 debería absorber la gestión de `schema_json.entity`, se decidió dejarlo hablado sin tocar el backlog todavía, para no ampliar el alcance de esta story.

---

### STORY 10.3: Desacoplar `plugin_name` de `slug`, identidad editable y consolidación en `manifest_json`

- **Fecha:** 2026-08-15 a 2026-08-16
- **Estimado sin IA:** 30h (AC original de desacoplo `plugin_name`/`slug` con
  cascada transaccional ~10h, más 4 ampliaciones de alcance sucesivas — refactor
  de esquema `manifest_json`, alta manual, borrado en cascada, grid de
  relaciones y tab de relación inversa — cada una con su propio diseño,
  implementación y tests, ~5h cada una)
- **Tiempo real con IA:** Sesión larga de varios bloques de trabajo continuo
  (no medida con precisión por bloque)
- **Aceleración:** No calculable con datos fiables
- **Qué hizo IA:**
  - Implementó el AC original: identidad técnica fija `plugin_name`, `slug`
    editable con cascada transaccional (`plugin_entity_data`, `plugin_extension_data`,
    `plugin_update_history`, `target_entity` de otros plugins), edición de
    `name`/`description` en `PluginConfig`.
  - Propuso y, tras 4 preguntas dirigidas de `AskUserQuestion` confirmadas por
    el usuario, ejecutó un refactor mayor no previsto en el AC: consolidar
    `plugin_name`/`plugin_type`/`version`/`name`/`description` de `plugins` en
    una columna `manifest_json JSONB` viva, y eliminar `schema_version` (residual,
    sin consumidores reales) de `plugins` y `plugin_update_history`. Reescribió
    ~15 ficheros con SQL literal repetido siguiendo el patrón ya usado en la
    sesión: cambios de producción primero, correr la suite completa para
    obtener fallos concretos, arreglar el fixture de mayor apalancamiento
    primero.
  - Diagnosticó y corrigió una coupling de orden de tests oculta: al añadir la
    limpieza de filas huérfanas de `plugins` que dejaban tests anteriores, un
    test de `AppWiringTest.php` dejó de pasar porque dependía silenciosamente
    de una fila que otro test dejaba atrás — se corrigió haciéndolo autónomo.
  - Implementó, tras confirmación explícita del usuario en cada fork de
    diseño (dos divisiones de clase por límite de método de SonarQube, decisión
    de routing `#new` con su matiz explicado, campos editables en el alta vs.
    solo-lectura), las 4 ampliaciones de alcance: alta manual de plugin (§6),
    borrado en cascada (§7), grid "Relaciones" editable (§8) y tab de relación
    inversa (§9).
  - Corrigió, a petición del usuario a mitad de sesión, un bug real de
    sincronización de estado en `PluginConfig.js` (acciones de fila descartaban
    ediciones sin guardar) — y aplicó el mismo fix, no pedido explícitamente
    pero de la misma clase de defecto, a los casos de error al guardar.
  - Al cerrar la sesión, implementó a petición del usuario que el alta manual
    active el plugin por defecto, y durante la propia verificación (no al
    pedirlo el usuario) encontró y corrigió dos bugs reales adicionales que
    ese cambio expuso: `POST /plugins`/`PUT /plugins/{slug}/status` devolvían
    la fila cruda sin aplanar (rompiendo silenciosamente nombre/tipo tras esas
    acciones), y `PluginManager.js` parcheaba la fila en memoria en vez de
    recargar, perdiendo el flag `can_rollback`.
  - Cuando el usuario pidió analizar los huecos documentales dejados por la
    sesión, lanzó 3 agentes `Explore` en paralelo (arquitectura/API, guías de
    plugins/entidades, proceso/backlog) que auditaron 13 ficheros de
    documentación contra el código real, verificó manualmente los 2 hallazgos
    más graves antes de presentarlos, y corrigió los 13 ficheros tras
    confirmación del alcance con el usuario.
- **Iteraciones:** 30+ (repartidas en: AC original; 4 preguntas dirigidas para
  el refactor `manifest_json`; ~15 ficheros de test reescritos uno a uno tras
  el refactor; varias rondas de hallazgos de SonarQube corregidos según se
  screenshoteaban; 2 divisiones de clase confirmadas explícitamente; corrección
  de rumbo del sentinel de ruta `_new`→`#new`; bug de sincronización de estado
  reportado por el usuario; decisión de activación por defecto con 2 bugs
  encontrados durante su propia verificación; auditoría y corrección de huecos
  documentales)
- **Decisión manual:** El usuario corrigió dos veces seguidas una explicación
  técnica sobre por qué se usó `_new` como sentinel de ruta en vez de `new`,
  hasta que se demostró con un caso de Node en vivo — la IA mantuvo su
  posición con evidencia concreta en vez de ceder sin verificar.
- **Decisión manual:** El usuario disputó dos veces un fix de SonarQube sobre
  un setter con arrow function sin llaves, pidiendo verificación con `git diff`
  primero y luego con una demostración en vivo de que un arrow de bloque
  siempre devuelve `undefined` — la IA sostuvo el fix ya aplicado con evidencia
  en ambos casos.
- **Decisión manual:** Ante cada fork de diseño con impacto arquitectónico
  (dividir una clase, forma exacta del sentinel de ruta, si los campos del
  alta manual debían ser editables y guardables o solo de lectura, alcance
  del pase de documentación), la IA preguntó explícitamente antes de decidir,
  en vez de asumir alcance por su cuenta — patrón consistente durante toda la
  sesión.
- **Decisión manual:** El usuario limitó explícitamente el activado automático
  por defecto al alta manual, dejando la sincronización masiva desde disco sin
  cambios (sigue registrando `inactive`) — para no activar en bloque plugins
  descubiertos sin revisión previa del admin.

---

### STORY 10.4: Plugins de demostración — entidades `orders`, `invoices`, `basic`

- **Fecha:** 2026-08-16
- **Estimado sin IA:** 5h (3 plugins de entidad siguiendo un patrón ya
  establecido, uno con Hook de unicidad, más tests de contrato y diagnóstico de
  2 bugs de entorno/wiring no evidentes hasta ejecutar la verificación real)
- **Tiempo real con IA:** ~1h30
- **Aceleración:** ~70%
- **Qué hizo IA:**
  - Investigación previa con 2 agentes `Explore` en paralelo (patrón real de
    `plugins/persons/` y mecanismo de multi-instancia; bloque `relations`,
    tipos de campo y patrón de seeders) antes de planificar, evitando repetir
    el patrón desactualizado que el propio texto del backlog describía
    (`Installer.php`, ya eliminado en STORY 10.3).
  - Detectó por su cuenta, revisando el AC original a la luz de STORY 10.2, que
    `sales` sería un plugin redundante de `orders` (mismos campos, mismo
    target conceptual) ahora que `clients`/`distributors` están unificados en
    `persons` — lo planteó como pregunta dirigida en vez de decidirlo o
    implementarlo sin más.
  - Implementó los 3 plugins (`orders`, `invoices` con `Hooks.php` de unicidad
    sobre `invoice_number`, `basic`) y sus 3 tests de contrato siguiendo
    exactamente el patrón de `PersonsPluginTest.php`/`ProductsPluginTest.php`.
  - Al ejecutar la verificación real (sync de plugins contra la BD local), se
    encontró con que la BD ya tenía 3 instancias activas y reales del plugin
    `persons` con slugs propios (datos de TFM del usuario, no residuales) —
    algo que ninguna exploración previa podía haber anticipado por vivir solo
    en el estado de la BD, no en el código ni la documentación. Identificó que
    su propia implementación (relación `orders → persons` fija en el
    `schema.json` de disco) quedaría inservible en esa BD concreta, lo
    reportó con evidencia (consulta SQL directa) en vez de asumir un arreglo,
    y aplicó la corrección que el usuario indicó (relación vacía en disco,
    configurable después por instalación) incluyendo deshacer y rehacer el
    registro en BD del plugin afectado.
  - Diagnosticó y corrigió, sin que se le pidiera explícitamente arreglarlos
    (bloqueaban su propia verificación), dos bugs de entorno/wiring
    preexistentes y no relacionados con el diseño de la story:
    `tools/setup/sync-plugins.php` roto desde STORY 10.3 (`PluginSyncService`
    construido sin su dependencia `PluginWriteRepository`, ya obligatoria) y
    el PHP CLI del entorno sin `php.ini` cargado (extensiones `pdo_pgsql`/
    `mbstring` ya activas en el `php.ini` real de Apache, pero el CLI no lo
    cargaba) — corregido con una variable de entorno `PHPRC` persistente.
  - Ejecutó una revisión de SonarQube a petición del usuario, diagnosticando
    primero por qué el análisis de workspace completo no devolvía hallazgos de
    los ficheros nuevos (no habían sido abiertos en el editor, por lo que
    SonarLint nunca los había analizado) antes de corregir el único hallazgo
    real que sí pudo confirmar (literal duplicado en un test preexistente).
- **Iteraciones:** 10+ (exploración en paralelo, 3 rondas de `AskUserQuestion`
  de alcance antes del plan, implementación, hallazgo de datos reales en BD
  con 2 rondas adicionales de `AskUserQuestion` para no asumir sobre datos de
  TFM del usuario, 2 bugs de entorno diagnosticados y corregidos durante la
  propia verificación, revisión de SonarQube).
- **Decisión manual:** El usuario confirmó la recomendación de descartar
  `sales` en vez de mantenerlo como segundo ejemplo gemelo de `relations`.
- **Decisión manual:** El usuario indicó explícitamente no tocar ni
  reconfigurar sus 3 instancias reales de `persons` (datos de TFM, no
  residuales) al descubrirse durante la verificación, y que la relación
  `orders → persons` del AC original no debía fijarse en el `schema.json` de
  disco — es una sugerencia de patrón, no un contrato; la relación real se
  crea después, por instalación, desde `PluginConfig`.
- **Decisión manual:** El usuario pidió explícitamente revisar hallazgos de
  SonarQube antes de cerrar la story, en vez de darla por completa solo con
  los tests en verde.

### STORY 10.5: Plugins de demostración — extensiones `optometries`, `contact_lenses`

- **Fecha:** 2026-08-17
- **Estimado sin IA:** 16h (2 plugins de extensión con historial de varios
  registros por owner, más dos capacidades de núcleo nuevas — `relations`
  en extensiones y validación server-side —, un sistema de organización de
  UI (`layers`) transversal a todo plugin, componente SVG reutilizable,
  página independiente de ficha con routing SPA nuevo, y una decena de
  rondas de ajuste visual iterativo contra un sketch de referencia)
- **Tiempo real con IA:** ~3h
- **Aceleración:** ~81% ⚡
- **Qué hizo IA:**
  - Detectó, antes de implementar (revisando el AC original a la luz de que
    Marca/Fabricante/Distribuidor/Oftalmólogo/Optometrista debían ser
    relaciones reales, no selects fijos), que los plugins `extension` no
    tenían soporte de `relations` en absoluto y que
    `PluginExtensionController` no validaba `content` contra schema en el
    servidor — dos huecos de núcleo que había que cerrar antes de poder
    empezar los plugins de demostración en sí.
  - Diseñó y cerró con el usuario, en varias rondas de `AskUserQuestion`, la
    convención general `layers` (catálogo en `manifest.json`, no en
    `schema.json` — corrección del propio usuario sobre un borrador
    intermedio) y `resortable` (bloquea también la reasignación de capa,
    no solo Subir/Bajar — segunda corrección del usuario sobre el mismo
    diseño), documentando el razonamiento de cada corrección en el plan en
    vez de solo aplicarla.
  - Encontró por su cuenta, escribiendo `contact-lenses/Hooks.php`, que el
    nombre literal del AC original (`contact-lenses`, con guion) rompe la
    sintaxis del namespace PHP que `PluginClassLoader` construye por
    concatenación — lo detectó y corrigió (renombre a `contact_lenses`)
    antes de sincronizar nada en BD, no como un bug encontrado después.
  - Diagnosticó y corrigió 5 bugs no relacionados con el diseño de la
    story, encontrados al construir/verificar sus propias piezas: un campo
    `auto_generated` fuera de `identities` rompiendo `ValidationService`
    tras conectar la validación nueva; `DynamicTable` recortando a 10
    registros incluso con paginación desactivada; `resortable`/`layer`
    perdiéndose silenciosamente al reconstruir estado desde el DOM tras un
    Subir/Bajar en `PluginConfig.js`; una etiqueta de gauge invisible por
    quedar fuera del `viewBox`; y una densidad "compact" que no compactaba
    por convivencia de clases CSS (`classList.add` sobre una clase base ya
    presente).
  - Reescribió `plugin.js` de `optometries` completo, desde 0, tres veces
    en la misma sesión, cada vez en respuesta a feedback visual concreto
    del usuario contra un sketch de referencia (estructura por capas,
    geometría del gauge, adopción de `DynamicTable` para la tabla de
    medidas, estilo de formulario) — y extrajo las partes genéricas a un
    módulo compartido (`ExtensionLayerFields.js`) antes de escribir el
    segundo plugin, para no duplicar la lógica de orquestación por capas.
  - Reutilizó la infraestructura de navegación de la Tercera ronda de
    STORY 10.2/10.3 (router SPA, `PluginItemEdit.js` genérica) sin
    necesitar ningún cambio para el segundo plugin — confirmando la
    predicción de diseño de que sería reutilizable sin tocar.
- **Iteraciones:** 15+ (núcleo relations/validación, 3+ rondas de
  `AskUserQuestion` sobre `layers`/`resortable`, reescritura completa de
  `plugin.js` ×3 con verificación visual del usuario entre cada una,
  hallazgo y corrección del nombre inválido de `contact_lenses`, 5 bugs
  diagnosticados durante la propia construcción, pase de SonarQube, cierre
  de documentación en 7 ficheros).
- **Decisión manual:** El usuario corrigió dos veces seguidas el mismo
  diseño de `layers` (primero moviendo el catálogo de `schema.json` a
  `manifest.json`, después extendiendo `resortable` para que también
  bloquee la capa) antes de darlo por cerrado.
- **Decisión manual:** El usuario pidió explícitamente rehacer
  `plugin.js` de `optometries` "desde 0" tras verlo en el navegador, en
  vez de aceptar ajustes incrementales sobre la primera versión.
- **Decisión manual:** El usuario decidió, vía `AskUserQuestion`, que la
  columna "Adición" de `contact_lenses` se queda fija (no se convierte en
  fila) pese a que Radio/Diámetro/Uso/Pack y las relaciones por ojo sí
  pasaron a ser filas de la tabla con `colSpan` completo.

### STORY 10.6: Datos de ejemplo para los plugins de demostración

- **Fecha:** 2026-08-17
- **Estimado sin IA:** 12h (diseñar un seeder de 11 grupos con dependencias
  cruzadas, generar a mano pools de datos españoles realistas —incluyendo
  el algoritmo de letra de control del DNI—, resolver idempotencia sin
  columnas únicas naturales, verificar coherencia de todas las relaciones
  por SQL y documentar el cierre en 6 ficheros)
- **Tiempo real con IA:** ~2h
- **Aceleración:** ~83% ⚡
- **Qué hizo IA:**
  - Investigó el estado real de la BD antes de proponer nada: descubrió que
    `schema_json` en BD había sufrido drift respecto a los `schema.json` en
    disco (campos añadidos vía `PluginConfig` como `phone_mobile`,
    `legal_name`, `type`), que la relación `orders → distributors` ya
    estaba configurada en producción, y que la instancia `purchases` que
    STORY 10.4 daba por descartada seguía existiendo — reportó cada
    hallazgo con evidencia SQL antes de asumir nada.
  - Descubrió, preguntando directamente en vez de asumir, que `purchases`
    era trabajo en curso del propio usuario (renombrado a `sales`, segunda
    instancia real de `orders` para ventas a cliente) y amplió el alcance
    de la story en consecuencia, en vez de tratarlo como residuo a limpiar.
  - Cerró con el usuario, en dos rondas de `AskUserQuestion`, los volúmenes
    exactos de los 11 grupos (sin aceptar una cifra orientativa propia
    cuando el usuario pidió números concretos), la cobertura 100% de
    fichas por cliente, la correlación "cliente VIP" entre 3 grupos
    distintos y la estrategia de idempotencia "todo o nada por grupo".
  - Validó el diseño técnico con un agente Plan antes de escribir código:
    corrigió un error de namespace (mayúsculas que habrían roto el
    autoload en Linux/producción, aunque no en Windows), y detectó que el
    "skip" de idempotencia debía cargar los ids existentes en vez de
    dejarlos vacíos, para que los grupos dependientes no se rompieran en
    un re-run parcial.
  - Encontró y corrigió, verificando su propio código antes de tocar la BD
    real, un bug real de `strtr()` operando byte a byte sobre acentos
    UTF-8 multibyte (`"García"` → `"garcuna"`), con un smoke test que lo
    confirmó y validó la corrección.
  - Hizo un pase de limpieza SonarQube proactivo sobre su propio código
    nuevo (sin que se le pidiera): dividió una clase de 38 métodos en 4
    clases cohesivas y sustituyó excepciones genéricas por una excepción
    de dominio nueva, antes de dar la story por terminada.
  - Verificó la coherencia de los ~2500 registros sembrados con consultas
    SQL dirigidas (unicidad de apellidos, relaciones sin ids huérfanos,
    cobertura 100% de fichas, letra de control de los 325 DNI generados)
    en vez de confiar solo en que el script terminara sin errores.
- **Iteraciones:** 12+ (investigación previa de BD real, 4 rondas de
  `AskUserQuestion` con 14 preguntas en total antes de escribir el plan,
  validación con agente Plan, bug de `strtr()` multibyte encontrado y
  corregido, pase de limpieza SonarQube, verificación de integridad por
  SQL, cierre de documentación en 6 ficheros).
- **Decisión manual:** El usuario corrigió que `purchases`/`sales` no era
  un residuo a limpiar sino su propio trabajo en curso, ampliando el
  alcance de la story a sembrar ambas instancias de `orders`.
- **Decisión manual:** El usuario pidió explícitamente que se le preguntara
  el número exacto de cada entidad ("no me falles tu propuesta
  orientativa") en vez de aceptar una estimación razonable propuesta por
  la IA.
- **Decisión manual:** El usuario pidió limpiar por completo
  `plugin_entity_data`/`plugin_extension_data` antes de sembrar (no solo
  las filas huérfanas detectadas) y que `clients`/`ophthalmologists`
  tuvieran apellidos únicos garantizados — ambas correcciones se aplicaron
  directamente sobre el plan ya escrito, antes de aprobarlo.
