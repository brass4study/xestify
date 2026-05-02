# Backlog Ejecutable - MVP Xestify (MASTER - 1 mes)

## Objetivo

Backlog reducido para completar Xestify MVP en **4-5 semanas** como proyecto de Master en Desarrollo con IA.

**Escala de estimación:** Puntos Fibonacci (1, 2, 3, 5, 8, 13)  
**Columnas clave:**
- **Puntos:** Complejidad
- **Sin IA (horas):** Tiempo sin asistencia
- **Con IA (horas):** Tiempo con CodeVibe/Copilot
- **Priority:** MUST (crítico), SHOULD (importante)

**Factor de aceleración esperado:** 1.4-1.6x (60% más rápido con IA)

---

## Scope Académico

### ✅ IN SCOPE (MVP)
- EPIC 0: Setup técnico
- EPIC 1: Autenticación
- EPIC 2: Modelo de datos
- EPIC 3: CRUD dinámico
- EPIC 4: Plugins backend (básico)
- EPIC 5: Frontend base
- EPIC 6: Plugins tipo extension
- EPIC 7: Actualizaciones de plugins y rollback
- EPIC 8: Operación técnica y observabilidad
- EPIC 9: Marketplace de plugins
- EPIC 10: QA y calidad
- Adición MVP A1: Auditoría funcional (cambios en configuración, usuarios y plugins)
- Adición MVP A2: Matriz de permisos fina (más granular que admin/no-admin)

### ❌ OUT OF SCOPE (para futuro/thesis)
- Adición post-MVP A3: Hardening de sesiones (expiración, revocación, refresh)
- Adición post-MVP A4: Panel de health técnico (DB, hooks, plugins activos)
- Adición post-MVP A5: Exportación/importación de configuración entre entornos

### 📌 Decisiones de Alcance (2026-05-02)
- **IN SCOPE MVP:** EPIC 0-10 + A1 (Auditoría funcional) + A2 (Matriz de permisos fina)
- **POSTERIOR A MVP:** A3 (Hardening de sesiones) + A4 (Panel health técnico) + A5 (Export/import configuración)

---

## EPIC 0: Preparación Técnica (Fase 0 - Semana 1)

Objetivo: Entorno dev reproducible, baseline arquitectura, pipeline de calidad.

### STORY 0.1: Setup repositorio Git y estructura inicial
- **Points:** 2
- **Sin IA:** 2 horas
- **Con IA:** 0.5 horas
- **Aceleración:** **75%** ⚡
- **Priority:** MUST
- **Type:** Task
- **Criteria:**
  - ✅ `.gitignore` generado (PHP, Node, Docker, OS)
  - ✅ Estructura `backend/`, `frontend/`, `docker/`, `docs/`
  - ✅ README.md con instrucciones
  - ✅ Repositorio inicializado
- **IA Usage:** Generar .gitignore, estructura carpetas, README base
- **Dependencias:** Ninguna
- **Blockers:** Ninguno

### STORY 0.2: Crear Container de inyección de dependencias minimo
- **Points:** 5
- **Sin IA:** 6 horas
- **Con IA:** 3 horas
- **Aceleración:** **50%** ⚡
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Clase `Xestify\Core\Container` con métodos `register()`, `singleton()`, `get()`
  - ✅ Se puede inyectar factory callable
  - ✅ Tests unitarios: registrar y recuperar servicios
  - ✅ Zero dependencias externas
- **IA Usage:** Estructurar clase base, generar tests unitarios, documentación
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

### STORY 0.3: Crear Router HTTP básico
- **Points:** 3
- **Sin IA:** 5 horas
- **Con IA:** 2 horas
- **Aceleración:** **60%** ⚡
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Clase `Xestify\Core\Router` con `get()`, `post()`, `put()`, `delete()`
  - ✅ Mapear rutas a controladores
  - ✅ Extraer parámetros de URL con named capture groups
  - ✅ 10 tests unitarios
- **IA Usage:** Generar regex de rutas dinámicas, lógica de dispatch, suite de tests
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

### STORY 0.4: Crear Request/Response helpers
- **Points:** 2
- **Sin IA:** 4 horas
- **Con IA:** 1.5 horas
- **Aceleración:** **62%** ⚡
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Clase `Xestify\Core\Request` (headers, body, query params, bearerToken)
  - ✅ Clase `Xestify\Core\Response` con envelope JSON estándar
  - ✅ Shortcuts: notFound(), unauthorized(), unprocessable(), serverError()
  - ✅ 20 tests unitarios (11 Request + 9 Response)
- **IA Usage:** Generar estructura de clases, envelope format, suite de tests, fix PHP_SAPI
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

### STORY 0.5: Setup entorno local (PHP nativo + PostgreSQL)
- **Points:** 2
- **Sin IA:** 1.5 horas
- **Con IA:** 0.5 horas
- **Aceleración:** **67%** ⚡
- **Priority:** MUST
- **Type:** Infrastructure
- **Criteria:**
  - ✅ `backend/.env` configurado con credenciales de PostgreSQL local
  - ✅ `backend/config/database.php` conecta con PDO sin errores
  - ✅ `php -S localhost:8080 -t public/` sirve la app
  - ✅ Healthcheck endpoint GET `/health` responde 200
- **IA Usage:** Generar config PDO, .env.example, healthcheck endpoint
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno (PHP 8.1+ y PostgreSQL ya instalados localmente)

> ⚠️ **Decisión documentada (2026-05-01):** Docker queda fuera del scope MVP académico.
> PHP nativo + PostgreSQL local es suficiente para desarrollo y demo.
> `docker-compose.yml` se añadirá como archivo documental en Semana 4 (COULD).

### STORY 0.5b: Añadir docker-compose.yml documental
- **Points:** 2
- **Priority:** COULD
- **Type:** Infrastructure
- **Criteria:**
  - ✅ `docker-compose.yml` en raíz con servicios: app-php, db-postgres, nginx
  - ✅ Funciona como referencia para deployment futuro en RPi5
  - ✅ No requerido para desarrollo ni demo académica
- **Dependencias:** STORY 0.1
- **Blockers:** Solo si sobra tiempo en Semana 4

### STORY 0.6: Setup frontend skeleton (HTML + CSS + JS)
- **Points:** 2
- **Sin IA:** 1.5 horas
- **Con IA:** 0.5 horas
- **Aceleración:** **67%** ⚡
- **Priority:** MUST
- **Type:** UI
- **Criteria:**
  - ✅ `frontend/src/index.html` con estructura base
  - ✅ `frontend/src/js/main.js` carga sin errores
  - ✅ CSS reset mínimo
  - ✅ Página carga en navegador
- **IA Usage:** Generar HTML base, CSS reset, estructura JS entry point
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

### STORY 0.7: Configurar linting y tests CI/CD local
- **Points:** 3
- **Priority:** SHOULD
- **Type:** DevOps
- **Criteria:**
  - ✅ PHP codesniffer configurado
  - ✅ Jest/PHPUnit setup básico
  - ✅ Makefile o script `make test` local
  - ✅ CI/CD (GitHub Actions o similar) lee tests
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

---

## EPIC 1: Core Autenticación y Seguridad (Fase 1 - Semanas 2-3)

Objetivo: Acceso seguro por JWT, roles base, permisos.

### STORY 1.1: Crear tabla `users` y seeder
- **Points:** 2
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Migración SQL: id, email, password_hash, roles (JSON), created_at
  - ✅ Migración idempotente
  - ✅ Seeder crea usuario admin default
  - ✅ Tests de migración
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

### STORY 1.2: Implementar JWT signing y validation
- **Points:** 5
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Clase `Xestify\Services\JwtService` con `encode()`, `decode()`
  - ✅ Usar RS256 o HS256 (decidir)
  - ✅ Manejo de expiración
  - ✅ Validar firma
  - ✅ Tests unitarios
- **Dependencias:** STORY 0.2, STORY 0.1
- **Blockers:** Decisión algoritmo JWT (RS256 vs HS256)

### STORY 1.3: Crear AuthController (login endpoint)
- **Points:** 3
- **Priority:** MUST
- **Type:** API
- **Criteria:**
  - ✅ POST `/api/auth/login` con email, password
  - ✅ Validar credenciales contra tabla users
  - ✅ Responder con access_token + refresh_token
  - ✅ Rechazar credenciales incorrectas (401)
  - ✅ Tests de login exitoso y fallido
- **Dependencias:** STORY 1.1, STORY 1.2, STORY 0.3, STORY 0.4
- **Blockers:** Ninguno

### STORY 1.4: Crear AuthMiddleware para verificar JWT
- **Points:** 3
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Middleware extrae header `Authorization: Bearer <token>`
  - ✅ Valida firma y expiración
  - ✅ Adjunta `$request->user()` si válido
  - ✅ Devuelve 401 si no válido
  - ✅ Tests de token válido, expirado, inválido
- **Dependencias:** STORY 1.2, STORY 0.3
- **Blockers:** Ninguno

### STORY 1.5: Crear tabla de roles y permisos base
- **Points:** 3
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Tabla `roles` (id, name: admin/operador/lectura)
  - ✅ Tabla `permissions` (id, slug: read/create/update/delete, resource: entities/plugins/system)
  - ✅ Tabla `role_permissions` (role_id, permission_id)
  - ✅ Seeder con combinaciones base
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

### STORY 1.6: Implementar AuthorizationService (check permisos)
- **Points:** 3
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Clase con método `can($user, $permission, $resource)`
  - ✅ Resolver permisos desde tabla
  - ✅ Cache de permisos en sesión (opcional)
  - ✅ Tests de permisos (allow/deny)
- **Dependencies:** STORY 1.5
- **Blockers:** Ninguno

### STORY 1.7: Crear tabla de auditoría minima
- **Points:** 2
- **Priority:** SHOULD
- **Type:** Database
- **Criteria:**
  - ✅ Tabla `audit_logs` (id, user_id, action, resource, timestamp)
  - ✅ Registrar logins y cambios críticos
  - ✅ Query para ver logs por usuario
- **Dependencias:** STORY 1.1
- **Blockers:** Ninguno

---

## EPIC 2: Modelo de Datos Core (Fase 2 - Semanas 3-4)

Objetivo: Tablas PostgreSQL estables con JSONB.

### STORY 2.1: Crear tabla `system_entities` (registro de entidades)
- **Points:** 2
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Campos: id (UUID), slug, name, source_plugin_slug, is_active, created_at, updated_at
  - ✅ Índice en slug (unique)
  - ✅ Migración idempotente
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

### STORY 2.2: Crear tabla `entity_metadata` (schema versionado)
- **Points:** 2
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Campos: id (UUID), entity_slug, schema_version, schema_json (JSONB), created_at
  - ✅ Índice en (entity_slug, schema_version)
  - ✅ Validar schema_json estructura mínima en INSERT
- **Dependencias:** STORY 2.1
- **Blockers:** Ninguno

### STORY 2.3: Crear tabla `entity_data` (registros de negocio)
- **Points:** 2
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Campos: id (UUID), entity_slug, owner_id (UUID null), content (JSONB), created_at, updated_at, deleted_at
  - ✅ Índices: (entity_slug), (owner_id), GIN(content)
  - ✅ Soft delete por deleted_at
- **Dependencias:** STORY 2.1
- **Blockers:** Ninguno

### STORY 2.4: Crear tabla `plugins_registry` (plugins instalados)
- **Points:** 2
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Campos: id (UUID), plugin_slug (unique), plugin_type, version, status, installed_at, updated_at
  - ✅ plugin_type = 'entity' | 'extension'
  - ✅ status = 'active' | 'inactive' | 'error'
- **Dependencias:** STORY 0.1
- **Blockers:** Ninguno

### STORY 2.5: Crear tabla `plugin_hook_registry` (hooks registrados)
- **Points:** 2
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Campos: id (UUID), plugin_slug, target_entity_slug, hook_name, priority, enabled
  - ✅ hook_name = 'beforeSave' | 'afterSave' | 'registerTabs' etc.
  - ✅ Índice en (target_entity_slug, hook_name)
- **Dependencias:** STORY 2.4
- **Blockers:** Ninguno

### STORY 2.6: Crear repositorio GenericRepository (CRUD JSONB)
- **Points:** 5
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Métodos: `find()`, `all()`, `create()`, `update()`, `delete()`, `restore()`
  - ✅ Operaciones en entity_data con JSONB
  - ✅ Parámetros preparados (anti SQL injection)
  - ✅ Tests CRUD básicos
- **Dependencies:** STORY 2.3
- **Blockers:** Ninguno

### STORY 2.7: Crear migraciones para tablas core
- **Points:** 3
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Un archivo `.sql` con todas las tablas
  - ✅ Migraciones en `backend/database/migrations/001_init.sql`
  - ✅ Se ejecutan una sola vez
  - ✅ Idempotentes
- **Dependencias:** STORY 2.1, 2.2, 2.3, 2.4, 2.5
- **Blockers:** Ninguno

---

## EPIC 3: Motor de Entidades Dinámicas (Fase 3 - Semanas 5-7)

Objetivo: CRUD genérico con validación por schema.

### STORY 3.1: Crear ValidationService (valida contra schema)
- **Points:** 5
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Método `validate($data, $schema)`
  - ✅ Valida tipos: string, number, boolean, date, email, select
  - ✅ Valida requeridos, longitud, rango
  - ✅ Devuelve array de errores por campo
  - ✅ Tests: campo requerido faltante, tipo incorrecto, email inválido
- **Dependencias:** STORY 2.2
- **Blockers:** Ninguno

### STORY 3.2: Crear EntityService (orquestación CRUD)
- **Points:** 8
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Métodos: `createRecord()`, `updateRecord()`, `deleteRecord()`, `getRecord()`, `listRecords()`
  - ✅ Obtiene schema vigenteSi
  - ✅ Valida con ValidationService
  - ✅ Persiste en entity_data
  - ✅ Dispara hooks (implementado vacío por ahora)
  - ✅ Tests: create/update/delete básicos
- **Dependencias:** STORY 3.1, STORY 2.6
- **Blockers:** Ninguno

### STORY 3.3: Crear EntityController (endpoints REST)
- **Points:** 5
- **Priority:** MUST
- **Type:** API
- **Criteria:**
  - ✅ GET `/api/v1/entities/{slug}/schema` → schema_json
  - ✅ GET `/api/v1/entities/{slug}/records` → listado paginado
  - ✅ POST `/api/v1/entities/{slug}/records` → crear registro
  - ✅ GET `/api/v1/entities/{slug}/records/{id}` → registro por id
  - ✅ PUT `/api/v1/entities/{slug}/records/{id}` → actualizar
  - ✅ DELETE `/api/v1/entities/{slug}/records/{id}` → soft delete
  - ✅ Tests E2E de cada endpoint
- **Dependencias:** STORY 3.2, STORY 1.4
- **Blockers:** Ninguno

### STORY 3.4: Crear respuesta REST envelopada (estándar)
- **Points:** 2
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Todas las respuestas siguen: `{ ok: bool, data: {...}, meta: {...}, error: {...} }`
  - ✅ Helper `apiSuccess($data, $meta)` y `apiError($code, $message, $details)`
  - ✅ Errores de validación incluyen detalles por campo
- **Dependencias:** STORY 0.4
- **Blockers:** Ninguno

### STORY 3.5: Crear modelo SystemEntity (acceso a metadata)
- **Points:** 3
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Clase con métodos `getActive()`, `getBySlug()`, `findOrFail()`
  - ✅ Consultas a system_entities y entity_metadata
  - ✅ Cache de entidades activas en memoria
- **Dependencias:** STORY 2.1, STORY 2.2
- **Blockers:** Ninguno

### STORY 3.6: Frontend - Crear Api.js (cliente HTTP genérico)
- **Points:** 3
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Clase `Api` con métodos `get()`, `post()`, `put()`, `delete()`
  - ✅ Maneja header `Authorization: Bearer <token>`
  - ✅ Valida respuestas envelopadas
  - ✅ Manejo básico de errores
  - ✅ Tests unitarios
- **Dependencias:** STORY 0.6
- **Blockers:** Ninguno

### STORY 3.7: Frontend - Crear State.js (estado global)
- **Points:** 2
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Objeto AppState con setUser(), getUser(), setCurrentEntity(), etc.
  - ✅ Métodos setter/getter simples
  - ✅ Sem listeners, sem Proxy (Vanilla puro)
- **Dependencias:** STORY 0.6
- **Blockers:** Ninguno

### STORY 3.8: Frontend - Crear DynamicForm.js
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Clase que recibe schema y container
  - ✅ Método `render()` genera inputs por tipo
  - ✅ Método `validate()` valida en cliente
  - ✅ Método `getData()` devuelve object con valores
  - ✅ Soporta string, number, email, date, select, boolean
  - ✅ Tests: render diferentes tipos, validación básica
- **Dependencias:** STORY 0.6
- **Blockers:** Ninguno

### STORY 3.9: Frontend - Crear DynamicTable.js
- **Points:** 3
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Clase para renderizar tabla de registros
  - ✅ Recibe records y schema
  - ✅ Renderiza columnas dinámicamente
  - ✅ Manejo básico de paginación
- **Dependencias:** STORY 0.6
- **Blockers:** Ninguno

### STORY 3.10: Frontend - Crear página EntityList
- **Points:** 3
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Vista que lista todas las entidades disponibles
  - ✅ Click en entidad → carga registros
  - ✅ Botón "Crear nuevo registro"
  - ✅ Integración con Api.js
- **Dependencies:** STORY 3.6, STORY 3.9
- **Blockers:** Ninguno

### STORY 3.11: Frontend - Crear página EntityEdit
- **Points:** 4
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Formulario para crear/editar registro
  - ✅ Integracion con DynamicForm
  - ✅ Validación con Api + UX
  - ✅ Guardar → POST/PUT a backend
- **Dependencies:** STORY 3.8, STORY 3.6
- **Blockers:** Ninguno

---

## EPIC 4: Sistema de Plugins y Hooks Backend (Fase 4 - Semanas 8-10)

Objetivo: Extensibilidad sin modificar Core.

### STORY 4.1: Crear PluginLoader (descubre y carga plugins)
- **Points:** 5
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Explora `backend/plugins/` y lee manifest.json
  - ✅ Valida compatibilidad (core version)
  - ✅ Registra en BD si nueva
  - ✅ Carga Hooks.php del plugin
  - ✅ Tests: cargar plugin válido, rechazar incompatible
- **Dependencias:** STORY 2.4, STORY 0.2
- **Blockers:** Ninguno

### STORY 4.2: Crear HookDispatcher (ejecutor de hooks)
- **Points:** 5
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Métodos: `register($hook, $callback)`, `execute($hook, $context)`
  - ✅ Ejecuta callbacks por prioridad
  - ✅ Si hook before* falla, bloquea operación
  - ✅ Si hook after* falla, log warning
  - ✅ Tests: múltiples hooks, orden, excepciones
- **Dependencias:** STORY 2.5, STORY 0.2
- **Blockers:** Ninguno

### STORY 4.3: Implementar hooks beforeSave/afterSave en EntityService
- **Points:** 3
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ Disparar `beforeSave` antes de insertar
  - ✅ Disparar `afterSave` después
  - ✅ beforeSave puede rechazar con excepción
  - ✅ Tests: hook bloquea, hook modifica contexto
- **Dependencias:** STORY 4.2, STORY 3.2
- **Blockers:** Ninguno

### STORY 4.4: Crear plugin de entidad base (clients)
- **Points:** 5
- **Priority:** MUST
- **Type:** Plugin
- **Criteria:**
  - ✅ Estructura: manifest.json, schema.json, Hooks.php
  - ✅ Schema define campos: nombre, email, teléfono, activo
  - ✅ Hook de validación personalizada (ej. email único)
  - ✅ Installer registra entidad en system_entities
- **Dependencias:** STORY 4.1
- **Blockers:** Plantilla de plugin finalizada

### STORY 4.5: Implementar ciclo de vida de plugin (onInstall, onActivate)
- **Points:** 3
- **Priority:** MUST
- **Type:** Feature
- **Criteria:**
  - ✅ PluginLoader ejecuta onInstall() del plugin
  - ✅ onActivate() cuando se activa
  - ✅ onDeactivate() cuando se desactiva
  - ✅ Tests: ciclo completo
- **Dependencias:** STORY 4.1
- **Blockers:** Ninguno

### STORY 4.6: Crear metadatos de plugin (compatibilidad, dependencias)
- **Points:** 2
- **Priority:** SHOULD
- **Type:** Feature
- **Criteria:**
  - ✅ manifest.json valida compatibilidad core version
  - ✅ Validar dependencias (plugin A requiere plugin B)
  - ✅ Bloquear instalación si no cumple
- **Dependencias:** STORY 4.1
- **Blockers:** Ninguno

### STORY 4.7: Extender schema con identidades, campos obligatorios y relaciones opcionales
- **Points:** 5
- **Priority:** MUST
- **Type:** Feature
- **Descripción:**
  Definir el contrato definitivo de `schema.json` para entidades dinámicas con cuatro bloques:

  **`identities`**: campos técnicos de identidad del sistema (autogenerados, no editables).

  **`fields`**: campos funcionales del dominio definidos por el plugin.
  Aquí se declaran los obligatorios del negocio (`required: true`).

  **`custom_fields`**: catálogo de sugerencias opcionales para frontend en la configuración.
  El admin puede seleccionar estas sugerencias o crear campos manuales adicionales.

  **`relations`**: metadatos de relaciones entre entidades.
  Cada relación puede ser opcional (`required: false`) y su tipo/propiedades se infieren
  desde la entidad destino mediante `target_entity` + `target_field`.

  El `schema.json` del plugin define la plantilla/contrato inicial. El schema usado en runtime
  sigue siendo el schema vivo en `entity_metadata`, resultado de la configuración del admin.

  Caso esperado: un pedido puede tener relación opcional con cliente (`belongs_to`) y permitir
  registros anónimos (sin cliente asociado).

- **Criteria:**
  - ✅ `schema.json` de plugin usa estructura: `identities`, `fields`, `custom_fields`, `relations`
  - ✅ `identities.id` declarado como identidad de sistema (autogenerado, no editable)
  - ✅ `fields` contiene los obligatorios del dominio (`required: true`) definidos por el plugin
  - ✅ `custom_fields` contiene sugerencias opcionales para configuración en frontend
  - ✅ `relations` permite relaciones opcionales con `required: false`
  - ✅ Cada relación define al menos: `key`, `type` (belongs_to | has_many | has_one), `target_entity`, `target_field`, `required`, `label`
  - ✅ La relación no requiere declarar una `custom_field` extra para su FK; se infiere por `target_field`
  - ✅ Caso de pedido anónimo soportado: relación a cliente opcional sin romper validación
  - ✅ `ValidationService` valida siempre contra el schema vivo (el que el admin ha configurado)
  - ✅ Si una relación opcional no se informa en runtime, el registro sigue siendo válido
  - ✅ `entity_metadata.schema_json` CHECK constraint sigue validando solo `fields` (retrocompatible)
  - ✅ Actualizar schema de `clients` según contrato nuevo (`identities` + `fields` + `custom_fields` + `relations`)
  - ✅ Tests: instalador usa plantilla base; ValidationService valida schema vivo; relación opcional no rompe
- **Dependencias:** STORY 4.4, STORY 3.1
- **Blockers:** Decisiones 5 y 6 aprobadas (ver docs/mvp/decisiones-tecnicas.md)

---

## EPIC 5: Frontend Dinámico Base (Fase 5 - Semanas 9-12)

Objetivo: UI funcional para entidades dinámicas.

### STORY 5.1: Frontend - Crear página Login
- **Points:** 3
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Formulario email + password
  - ✅ POST `/api/auth/login`
  - ✅ Almacenar JWT en localStorage
  - ✅ Redirigir a dashboard si exitoso
  - ✅ Mostrar error si credenciales inválidas
- **Dependencias:** STORY 3.6, STORY 0.6
- **Blockers:** Ninguno

### STORY 5.2: Frontend - Crear navbar/sidebar de navegación
- **Points:** 2
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Mostrar usuario logueado
  - ✅ Link a EntityList
  - ✅ Link a PluginManager
  - ✅ Botón Logout
- **Dependencias:** STORY 0.6, STORY 3.7
- **Blockers:** Ninguno

### STORY 5.3: Frontend - Integración E2E EntityList + EntityEdit
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Flujo: Listar entidades → Seleccionar → Ver registros → Crear registro
  - ✅ Formulario dinámico se rellena y valida
  - ✅ POST a backend funciona
  - ✅ Listado se actualiza
  - ✅ Tests E2E con mock API
- **Dependencias:** STORY 3.10, STORY 3.11
- **Blockers:** Ninguno

### STORY 5.4: Frontend - Crear Modal/Dialog reutilizable
- **Points:** 2
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Clase Modal para confirmaciones, errores
  - ✅ Métodos show(), close(), setContent()
  - ✅ Estilos básicos
- **Dependencias:** STORY 0.6
- **Blockers:** Ninguno

### STORY 5.5: Frontend - Mejorar estilos CSS para mobile/desktop
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Responsive design básico
  - ✅ Tablas legibles en móvil
  - ✅ Formularios usables
- **Dependencias:** STORY 0.6
- **Blockers:** Ninguno

---

## EPIC 6: Plugins tipo Extension (Fase 6)

Objetivo: Soporte completo para plugins de tipo `extension` que inyectan pestañas, vistas y comportamientos adicionales en entidades existentes, sin modificar su código base.

### STORY 6.1: Frontend - Crear módulo DynamicTabs.js
- **Points:** 3
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Clase `DynamicTabs` que renderiza tabs a partir de definición de array
  - ✅ Tabs pueden ser registradas desde plugins vía API JS
  - ✅ Tab activa persiste en URL hash o estado local
  - ✅ Tests: render básico, cambio de tab, tab activa por defecto
- **IA Usage:** Boilerplate clase + tests + CSS tab styles
- **Dependencias:** STORY 5.2, STORY 0.6
- **Blockers:** Ninguno

### STORY 6.2: Backend - Hook `registerTabs` y `registerActions` en HookDispatcher
- **Points:** 3
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ HookDispatcher soporta hooks de tipo `filter` (retornan valor acumulado)
  - ✅ Hook `registerTabs` permite que plugins añadan tabs a una entidad
  - ✅ Hook `registerActions` permite que plugins añadan acciones (botones) a filas de tabla
  - ✅ Tests: plugin registra tab y aparece en respuesta de API
- **IA Usage:** Extensión HookDispatcher + test de filtros acumulados
- **Dependencias:** STORY 4.2, STORY 4.3
- **Blockers:** Ninguno

### STORY 6.3: Plugin de ejemplo tipo extension (`comments`)
- **Points:** 5
- **Priority:** MUST
- **Type:** Fullstack
- **Criteria:**
  - ✅ Plugin `comments` con `manifest.json`, tipo `extension`, `target_entity: *`
  - ✅ Registra hook `registerTabs` → añade tab "Comentarios" a cualquier entidad
  - ✅ Tab muestra listado de comentarios del registro activo (GET `/api/v1/plugins/comments/{entity}/{id}`)
  - ✅ Formulario para añadir comentario (POST)
  - ✅ Tests de instalación, hook y endpoints
- **IA Usage:** Scaffolding plugin completo + endpoints + frontend tab content
- **Dependencias:** STORY 6.1, STORY 6.2, STORY 4.4
- **Blockers:** Ninguno

### STORY 6.4: Frontend - Página PluginManager (listar, activar, desactivar)
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Página `PluginManager` lista plugins instalados con estado (activo/inactivo/error)
  - ✅ Botones activar/desactivar llaman a API y actualizan estado
  - ✅ Badge con tipo de plugin (`entity` / `extension`)
  - ✅ Acceso restringido a admin
  - ✅ Tests: render lista, click activar/desactivar
- **IA Usage:** Scaffolding página + estilos + tests
- **Dependencias:** STORY 5.2, STORY 4.5
- **Blockers:** Ninguno

---

## EPIC 7: Actualizaciones de Plugins y Rollback (Fase 7)

Objetivo: Ciclo de vida completo de plugins con versionado, actualización controlada y rollback ante fallos.

### STORY 7.1: Detección de actualizaciones disponibles en PluginLoader
- **Points:** 3
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ PluginLoader compara versión instalada (plugins_registry) vs versión en disco (manifest.json)
  - ✅ Método `getOutdated()` devuelve lista de plugins con actualización disponible
  - ✅ Endpoint GET `/api/v1/plugins/updates` expone lista
  - ✅ Tests: versión igual, mayor y menor detectados correctamente
- **IA Usage:** Lógica comparación semver + tests de casos límite
- **Dependencias:** STORY 4.1, STORY 4.6
- **Blockers:** Ninguno

### STORY 7.2: Proceso de actualización con migración de schema
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Endpoint POST `/api/v1/plugins/{slug}/update` ejecuta actualización
  - ✅ Si el plugin tiene `onUpdate()` en Hooks.php, se ejecuta antes de activar nueva versión
  - ✅ Schema diff: si hay nuevos campos en `schema.json`, se aplican a `entity_metadata` con versión incrementada
  - ✅ Actualización falla atómicamente (transacción) si onUpdate lanza excepción
  - ✅ Tests: actualización exitosa, fallo con rollback automático
- **IA Usage:** Lógica de diff + transacción + tests de error
- **Dependencias:** STORY 7.1, STORY 4.5, STORY 2.2
- **Blockers:** Definir estructura de `onUpdate()` en contrato de plugin

### STORY 7.3: Rollback manual de plugin a versión anterior
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Backend
- **Criteria:**
  - ✅ Endpoint POST `/api/v1/plugins/{slug}/rollback` restaura versión anterior
  - ✅ Requiere que exista snapshot del schema anterior en `entity_metadata`
  - ✅ Ejecuta `onRollback()` del plugin si existe
  - ✅ Estado plugin vuelve a versión registrada antes del update
  - ✅ Tests: rollback exitoso, error si no hay snapshot previo
- **IA Usage:** Lógica de restauración + tests de rollback
- **Dependencias:** STORY 7.2
- **Blockers:** Ninguno

### STORY 7.4: Frontend - UI de actualización y rollback en PluginManager
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Badge "Actualización disponible" en plugin con versión desactualizada
  - ✅ Botón "Actualizar" llama a endpoint y muestra feedback
  - ✅ Botón "Rollback" disponible si hay versión anterior
  - ✅ Modal de confirmación antes de actualizar/rollback
- **IA Usage:** UI badges + modal confirmación + feedback estados
- **Dependencias:** STORY 6.4, STORY 7.2, STORY 7.3
- **Blockers:** Ninguno

### STORY 7.5: Frontend - Página de configuración de plugin activado
- **Points:** 5
- **Priority:** MUST
- **Type:** Fullstack
- **Descripción:**
  Cuando un plugin de tipo `entity` está activo, el admin puede entrar a su pantalla de configuración
  para personalizar el schema de la entidad: activar/desactivar `custom_fields` sugeridos por el plugin
  y añadir campos adicionales libres. Los cambios generan una nueva versión en `entity_metadata`.
- **Criteria:**
  - ✅ Ruta `/plugins/{slug}/config` renderiza página de configuración del plugin
  - ✅ Se listan los `custom_fields` del schema del plugin con checkbox activar/desactivar
  - ✅ Sección "Campos adicionales" permite añadir campos libres (nombre, tipo, requerido)
  - ✅ Guardar llama a PUT `/api/v1/plugins/{slug}/config` y genera nueva versión en `entity_metadata`
  - ✅ Solo visible para plugins de tipo `entity` que estén en estado `active`
  - ✅ Backend valida que los campos obligatorios del plugin (`fields`) no sean eliminables desde UI
  - ✅ Tests backend: update schema + versión incrementada + campos base intocables
  - ✅ Tests frontend: render custom_fields, toggle, añadir campo libre, guardar
- **IA Usage:** Endpoint PUT config + lógica diff de schema + página frontend con form dinámico
- **Dependencias:** STORY 4.7, STORY 6.4, STORY 7.2
- **Blockers:** Ninguno

---

## EPIC 8: Operación Técnica y Observabilidad (Fase 8)

Objetivo: Sistema observable con health checks, preparado para deployment en RPi5 con backup automatizado y hardening básico de seguridad.

### STORY 8.1: Endpoint de health técnico del sistema
- **Points:** 3
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ GET `/api/v1/system/health` devuelve: DB status, plugins activos, hooks registrados, uptime
  - ✅ Respuesta incluye version del core y timestamp
  - ✅ Sin autenticación para monitoreo externo (o token de lectura separado)
  - ✅ Tests: health cuando DB está up, degradado cuando DB falla
- **IA Usage:** Boilerplate endpoint + checks de subsistemas
- **Dependencias:** STORY 0.5, STORY 4.1
- **Blockers:** Ninguno

### STORY 8.2: Backup automático de base de datos
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Infrastructure
- **Criteria:**
  - ✅ Script `tools/backup.php` genera dump PostgreSQL con timestamp
  - ✅ Retención configurable (N últimos backups)
  - ✅ Endpoint POST `/api/v1/system/backup` para trigger manual (solo admin)
  - ✅ Log de backups en tabla o fichero
- **IA Usage:** Script pg_dump wrapper + rotación de backups
- **Dependencias:** STORY 0.5, STORY 1.4
- **Blockers:** `pg_dump` disponible en entorno

### STORY 8.3: Docker Compose para deployment en RPi5
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Infrastructure
- **Criteria:**
  - ✅ `docker-compose.yml` con servicios: `app-php`, `db-postgres`, `nginx`
  - ✅ Variables de entorno externalizadas vía `.env`
  - ✅ Volúmenes para persistencia de DB y backups
  - ✅ README con instrucciones de despliegue en RPi5 (arm64)
- **IA Usage:** Compose file completo + nginx.conf + instrucciones ARM
- **Dependencias:** STORY 0.5b
- **Blockers:** Acceso a RPi5 para validación (puede validarse en local x86)

### STORY 8.4: Hardening básico de seguridad (headers + rate limiting)
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Backend
- **Criteria:**
  - ✅ Headers de seguridad en todas las respuestas: `X-Content-Type-Options`, `X-Frame-Options`, `Content-Security-Policy` básico
  - ✅ Rate limiting por IP en endpoints de auth (máx. 10 intentos/minuto)
  - ✅ Tests: headers presentes, rate limit dispara 429
- **IA Usage:** Middleware de headers + implementación rate limit en memoria/Redis
- **Dependencias:** STORY 0.4, STORY 1.3
- **Blockers:** Decidir almacenamiento rate limit (APCu vs Redis vs tabla DB)

---

## EPIC 9: Marketplace de Plugins (Fase 9)

Objetivo: Repositorio central de plugins publicados, browseable e instalable desde la UI de Xestify.

### STORY 9.1: Schema y modelo de datos del marketplace
- **Points:** 3
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Tabla `marketplace_plugins` (id, slug, name, description, version, author, download_url, compatible_from, published_at)
  - ✅ Tabla `marketplace_plugin_versions` (plugin_slug, version, changelog, published_at)
  - ✅ Migración idempotente + seed con plugins de ejemplo
- **IA Usage:** SQL + seeds
- **Dependencias:** STORY 2.4
- **Blockers:** Definir si marketplace es local (mismo repo) o remoto (URL externa)

### STORY 9.2: API de marketplace (browse, search, detalle)
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ GET `/api/v1/marketplace` — lista plugins publicados con filtros (tipo, compatible, search)
  - ✅ GET `/api/v1/marketplace/{slug}` — detalle + versiones disponibles
  - ✅ POST `/api/v1/marketplace/{slug}/install` — descarga y registra plugin (solo admin)
  - ✅ Validación de compatibilidad de versión antes de instalar
  - ✅ Tests: listado, filtros, instalación, incompatibilidad rechazada
- **IA Usage:** Controlador + lógica de descarga + tests
- **Dependencias:** STORY 9.1, STORY 4.1, STORY 4.5
- **Blockers:** Ninguno

### STORY 9.3: Frontend - UI de marketplace en PluginManager
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Tab "Marketplace" en PluginManager muestra catálogo de plugins disponibles
  - ✅ Cards con nombre, descripción, tipo, versión y botón "Instalar"
  - ✅ Buscador en tiempo real por nombre/descripción
  - ✅ Feedback visual durante instalación (loading, éxito, error)
  - ✅ Plugin instalado muestra estado "Instalado" en lugar de botón
- **IA Usage:** UI cards + buscador + feedback de estado
- **Dependencias:** STORY 9.2, STORY 6.4
- **Blockers:** Ninguno

### STORY 9.4: Publicación de plugin al marketplace
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Backend
- **Criteria:**
  - ✅ POST `/api/v1/marketplace/publish` — registra plugin desde zip o directorio local (solo admin)
  - ✅ Valida estructura de plugin (manifest.json, Hooks.php)
  - ✅ Calcula checksum del paquete para verificación de integridad
  - ✅ Tests: publicación válida, inválida por manifest incorrecto
- **IA Usage:** Lógica de validación + checksum + tests
- **Dependencias:** STORY 9.1, STORY 4.6
- **Blockers:** Ninguno

---

## EPIC 10: QA y Calidad (Fase 10)

Objetivo: Suite de tests completa, automatización CI y coverage mínimo establecido para el proyecto.

### STORY 10.1: Suite de tests de integración E2E backend
- **Points:** 5
- **Priority:** MUST
- **Type:** Testing
- **Criteria:**
  - ✅ Flujo completo: login → crear entidad → guardar registro → instalar plugin → activar
  - ✅ Tests que usan DB real (test database separada)
  - ✅ Setup/teardown limpio entre tests
  - ✅ Scripts ejecutables vía `php backend/tests/integration/RunAll.php`
- **IA Usage:** Generación masiva de fixtures + helpers de test
- **Dependencias:** STORY 1.x, STORY 3.x, STORY 4.x
- **Blockers:** Base de datos de test configurada

### STORY 10.2: Coverage mínimo 80% en servicios core
- **Points:** 5
- **Priority:** MUST
- **Type:** Testing
- **Criteria:**
  - ✅ Tests unitarios para: `ValidationService`, `EntityService`, `JwtService`, `HookDispatcher`, `AuditService`
  - ✅ Coverage medido con script de conteo de casos (sin PHPUnit, compatible con setup actual)
  - ✅ Cada servicio tiene al menos: happy path, edge case, error case
  - ✅ Tabla de coverage documentada en `docs/ia/sesion.md`
- **IA Usage:** Generación de casos de test por método
- **Dependencias:** STORY 3.1, STORY 3.2, STORY 4.2, STORY A1.2
- **Blockers:** Ninguno

### STORY 10.3: GitHub Actions CI pipeline
- **Points:** 3
- **Priority:** SHOULD
- **Type:** DevOps
- **Criteria:**
  - ✅ Workflow `.github/workflows/ci.yml` ejecuta tests en cada push/PR
  - ✅ Steps: checkout, setup PHP 8.1, setup PostgreSQL, run migrations, run tests
  - ✅ Falla el pipeline si algún test falla
  - ✅ Badge de CI en README
- **IA Usage:** Workflow YAML completo + setup actions
- **Dependencias:** STORY 10.1
- **Blockers:** Acceso a secrets de PostgreSQL en GitHub Actions

### STORY 10.4: Tests de rendimiento básicos (API response times)
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Testing
- **Criteria:**
  - ✅ Script `tools/perf/benchmark.php` mide tiempos de respuesta de endpoints clave
  - ✅ Umbrales definidos: login < 200ms, list < 300ms, create < 400ms
  - ✅ Genera informe CSV con percentiles p50/p95
  - ✅ Tests fallidos si p95 supera umbral
- **IA Usage:** Script de benchmark + parser CSV + thresholds
- **Dependencias:** STORY 3.3, STORY 1.3
- **Blockers:** Ninguno

---

## EPIC A1: Auditoría Funcional (Adición MVP)

Objetivo: Trazabilidad de acciones críticas sobre configuración, usuarios y plugins.

### STORY A1.1: Crear tabla `audit_logs` y migración
- **Points:** 3
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Tabla `audit_logs` con campos: id, user_id, action, resource, resource_id, payload_json, ip, user_agent, created_at
  - ✅ Índices por `user_id`, `resource` y `created_at`
  - ✅ Migración idempotente
- **IA Usage:** Generar SQL + índices + script de verificación
- **Dependencias:** STORY 0.1, STORY 0.5
- **Blockers:** Ninguno

### STORY A1.2: Crear AuditService y helper de registro
- **Points:** 3
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Servicio `AuditService::log()` reutilizable
  - ✅ Registro de payload seguro (sin secretos/sin password_hash)
  - ✅ Tipado estricto y tests unitarios de inserción
- **IA Usage:** Boilerplate de servicio + tests + sanitización base de payload
- **Dependencias:** STORY A1.1, STORY 0.2
- **Blockers:** Definir lista de campos sensibles a excluir

### STORY A1.3: Auditar acciones de usuarios y configuración
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Se audita crear/editar/desactivar usuario
  - ✅ Se audita cambios de configuración global
  - ✅ Se audita activar/desactivar plugin
  - ✅ Cada registro incluye `who`, `what`, `when`, `where`
- **IA Usage:** Inyección de hooks de auditoría en controladores/servicios
- **Dependencias:** STORY A1.2, STORY 7.1, STORY 6.2, STORY 9.2 (o equivalentes)
- **Blockers:** Disponibilidad de endpoints de gestión

### STORY A1.4: Endpoint y vista básica de auditoría (solo admin)
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Fullstack
- **Criteria:**
  - ✅ GET `/api/v1/audit-logs` con filtros (fecha, usuario, recurso)
  - ✅ Tabla frontend de auditoría con paginación
  - ✅ Solo visible para rol admin
- **IA Usage:** Query con filtros + página frontend de lectura
- **Dependencias:** STORY A1.3, STORY 5.3
- **Blockers:** Ninguno

---

## EPIC A2: Matriz de Permisos Fina (Adición MVP)

Objetivo: Permisos granulares por recurso/acción, más allá de admin/no-admin.

### STORY A2.1: Modelo de permisos granular en base de datos
- **Points:** 5
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Tablas `roles`, `permissions`, `role_permissions` (si no existen)
  - ✅ Permisos por recurso + acción (`users.read`, `users.update`, `plugins.toggle`, etc.)
  - ✅ Seed inicial para roles base (admin, operador, lectura)
- **IA Usage:** SQL + seeds + tests de idempotencia
- **Dependencias:** STORY 1.x (auth base)
- **Blockers:** Catálogo inicial de permisos

### STORY A2.2: AuthorizationService con permisos por acción
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Método `can(user, permission)` con verificación real contra DB
  - ✅ Cache opcional en request para reducir queries repetidas
  - ✅ Tests allow/deny por rol
- **IA Usage:** Implementación del servicio + tests de matriz
- **Dependencias:** STORY A2.1, STORY 1.4
- **Blockers:** Ninguno

### STORY A2.3: Enforcement en endpoints críticos
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Endpoints de usuarios/config/plugins validan permisos finos
  - ✅ Respuesta `403` consistente en denegación
  - ✅ Logs de denegación integrados con auditoría (A1)
- **IA Usage:** Inserción de guardas de autorización + tests de integración
- **Dependencias:** STORY A2.2, STORY A1.2
- **Blockers:** Mapa endpoint → permiso

### STORY A2.4: UI condicional por permisos
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Ocultar acciones no permitidas (botones/links/secciones)
  - ✅ Mostrar mensaje informativo cuando falte permiso
  - ✅ Sin romper navegación existente
- **IA Usage:** Guards en renderizado frontend
- **Dependencias:** STORY A2.3, STORY 5.x
- **Blockers:** Endpoint/mechanismo para exponer permisos efectivos al frontend

---

## 📊 Resumen del Backlog Académico (EPIC 0-10 + A1/A2)

### Conteo de Puntos por EPIC (MUST priority)

| EPIC | Título | Puntos | Historias | Semana(s) |
|------|--------|--------|-----------|-----------|
| 0 | Setup técnico | 15 pts | 0.1-0.6 | Semana 1 |
| 1 | Autenticación | 12 pts | 1.1-1.4 | Semana 1-2 |
| 2 | Modelo de datos | 10 pts | 2.1-2.6 | Semana 2 |
| 3 | Motor de entidades | 23 pts | 3.1-3.11 | Semana 2-3 |
| 4 | Sistema de plugins | 13 pts | 4.1-4.5 | Semana 3 |
| 5 | Frontend base | 15 pts | 5.1-5.3 | Semana 3-4 |
| **TOTAL** | **40 puntos MUST** | **88 pts** | **25 historias** | **4 semanas** |

> **Nota:** Los 88 puntos incluyen 40 MUST + 8 SHOULD. Para Master, enfocarse en completar 40 MUST en 4 semanas.

### Breakdown por Semana (con IA)

**Semana 1: EPIC 0 + EPIC 1 (inicio)**
- Setup repo, DI container, router HTTP, Docker
- Setup database schema, users table
- **Estimado sin IA:** 54 horas
- **Estimado con IA:** 30 horas (44% ahorro)

**Semana 2: EPIC 1 (fin) + EPIC 2 + EPIC 3 (inicio)**
- JWT auth, AuthController, AuthMiddleware
- System entities schema, plugins_registry tabla
- ValidationService, EntityService inicio
- **Estimado sin IA:** 60 horas
- **Estimado con IA:** 35 horas (42% ahorro)

**Semana 3: EPIC 3 (fin) + EPIC 4 + EPIC 5 (inicio)**
- EntityController REST, CRUD dinámico
- PluginLoader, plugin ciclo de vida
- Frontend login, navbar, Entity list/edit dinámico
- **Estimado sin IA:** 56 horas
- **Estimado con IA:** 32 horas (43% ahorro)

**Semana 4: Polish + Testing + Documentation**
- Integración E2E (login → crear cliente → guardar)
- Tests unitarios críticos
- README, guía instalación
- Documentar IA usage metrics
- **Estimado sin IA:** 40 horas
- **Estimado con IA:** 24 horas (40% ahorro)

### Aceleración Total Esperada

- **Horas sin IA:** 210 horas (~5.25 semanas full-time)
- **Horas con IA:** 121 horas (~3 semanas full-time)
- **Factor de aceleración:** **1.74x** (42% de ahorro promedio)

### Instrucciones de Uso

1. **Lee primero:** [MASTER-brief.md](MASTER-brief.md) - scope reducido, timeline, entregas
2. **Copia template:** `ia-productivity-template.md` → `ia-productivity-analysis.md`
3. **Para cada STORY:**
   - Estima horas (ver tabla "Sin IA")
   - Trabaja con IA (CodeVibe/Copilot)
   - Registra tiempo real en `ia-productivity-analysis.md`
   - Calcula aceleración %
4. **Al final de Semana 4:** Compila análisis final de IA acceleration

### Criterios de Aceptación Global

✅ Repositorio clonado y funcional  
✅ Login con JWT funciona  
✅ CRUD dinámico: crear cliente, guardar, listar  
✅ Plugin sistema básico operativo  
✅ Frontend carga sin errores  
✅ Docker Compose sube sin problemas  
✅ Tests unitarios core: ValidationService, EntityService, JWT  
✅ ia-productivity-analysis.md completado con métricas reales  

---

## Notas Importantes

- **Puntos son relativos:** Si una historia toma más de lo previsto, ajusta estimación en tiempo real.
- **IA va a acelerar:** Usa CodeVibe para generar boilerplate, tests, documentación.
- **Foco en flujo E2E:** Semana 4 debe tener el flujo completo: login → crear entidad → guardar funcionando end-to-end.
- **OUT OF SCOPE para Master:** A3 (Hardening sesiones), A4 (Panel health técnico), A5 (Export/import config).

---

Referencia: Ver [MASTER-brief.md](MASTER-brief.md) para scope académico completo y estrategia de demostración.
