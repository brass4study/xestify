# Backlog Ejecutable - MVP Xestify (MASTER - 1 mes)

## Estado implementado auditado (2026-08-10)

El corte funcional actual queda fijado en **STORY 9.7 incluida**.

- **EPIC 9 en progreso** con `STORY 9.1` a `STORY 9.7` implementadas y `STORY 9.8` como siguiente foco.
- **STORY 9.6** ya implementada: mapa hash completo, navegación programática, entrada directa, refresh y back/forward con persistencia de contexto.
- **STORY 9.7** ya implementada: estado global ampliado, resiliencia de UI, feedback compartido, i18n base y theming persistido.
- Nota de trazabilidad: la decision arquitectonica final usa `plugins` como catalogo unico de entidades. Las referencias historicas a `system_entities`, `entity_metadata` o migraciones `009/010` describen decisiones/refactors previos, pero el repo actual usa las migraciones `001-005` y `plugins.schema_json`.

## Objetivo

Backlog reducido para completar Xestify MVP en **4-5 semanas** como proyecto de Master en Desarrollo con IA.

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
- EPIC 8: Gestión de usuarios
- EPIC 9: Sistema UI, shell frontend y arquitectura SPA
- EPIC 10: Login, Persons y Plugins de Demostración
- EPIC 11: Cierre Formal y Exhaustivo del MVP

### ❌ OUT OF SCOPE (para futuro/thesis)
- Adición post-MVP A1: Ajustes finos de UI/UX (i18n, búsqueda en tablas, rendimiento, accesibilidad, CRUD avanzado)
- Adición post-MVP A2: Operación técnica y observabilidad (health, backup, despliegue, hardening)
- Adición post-MVP A3: Marketplace de plugins
- Adición post-MVP A4: QA y calidad
- Adición post-MVP A5: Auditoría funcional (cambios en configuración, usuarios y plugins)
- Adición post-MVP A6: Matriz de permisos fina (más granular que admin/no-admin)
- Adición post-MVP A7: Hardening de sesiones (expiración, revocación, refresh)
- Adición post-MVP A8: Panel de health técnico (DB, hooks, plugins activos)
- Adición post-MVP A9: Exportación/importación de configuración entre entornos

### 📌 Decisiones de Alcance (2026-05-02)
- **IN SCOPE MVP:** EPIC 0-9
- **POSTERIOR A MVP:** A7 (Hardening de sesiones) + A8 (Panel health técnico) + A9 (Export/import configuración)

### 📌 Actualización de Alcance (2026-08-11)
- Las adiciones `A2` (Operación técnica), `A3` (Marketplace de plugins), `A4` (QA y calidad), `A5` (Auditoría funcional) y `A6` (Matriz de permisos fina) pasan de MVP a post-MVP.
- **IN SCOPE MVP (vigente):** EPIC 0-9
- **POSTERIOR A MVP (vigente):** A2 + A3 + A4 + A5 + A6 + A7 + A8 + A9

### 📌 Nueva Adición post-MVP (2026-08-11)
- Se define `A1` (Ajustes Finos de UI/UX, 7 stories, 37 pts): internacionalización real, búsqueda server-side en tablas, documentación WYSIWYG (movida desde STORY 9.10), rendimiento/skeleton loaders, animaciones/transiciones, accesibilidad WCAG + testing UI, y funcionalidad avanzada de tablas/CRUD.
- **POSTERIOR A MVP (vigente):** A1 + A2 + A3 + A4 + A5 + A6 + A7 + A8 + A9

### 📌 Cierre de MVP para defensa de TFM (2026-08-11)
- Se incorporan `EPIC 10` (Login, Persons y Plugins de Demostración, 6 stories, 31 pts) y `EPIC 11` (Cierre Formal y Exhaustivo del MVP, 4 stories, 18 pts) como parte del MVP académico, necesarios para la defensa del TFM.
- **IN SCOPE MVP (vigente):** EPIC 0-11

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
  - ✅ Estructura `backend/`, `frontend/`, `docker/`, `context/`
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
  - ✅ `frontend/src/js/app.js` carga sin errores
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
  - ✅ Responder con access_token (sin refresh_token: la sesión expira a los `JWT_EXPIRY` segundos, sin renovación)
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

### STORY 2.1: Crear tabla `system_entities` (registro de entidades) ~~SUPERSEDED~~
- **Points:** 2
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Campos: id (UUID), slug, name, source_plugin_slug, is_active, created_at, updated_at
  - ✅ Índice en slug (unique)
  - ✅ Migración idempotente
- **Nota:** Esta tabla fue **eliminada en EPIC 6 / Release B** (`010_drop_system_entities.sql`). El catalogo de entidades vive ahora en `plugins WHERE plugin_type='entity'`. Ver DECISION 6.
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

### STORY 2.5: Crear repositorio GenericRepository (CRUD JSONB)
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

### STORY 2.6: Crear migraciones para tablas core
- **Points:** 3
- **Priority:** MUST
- **Type:** Database
- **Criteria:**
  - ✅ Un archivo `.sql` con todas las tablas
  - ✅ Migraciones en `backend/database/migrations/001_init.sql`
  - ✅ Se ejecutan una sola vez
  - ✅ Idempotentes
- **Dependencias:** STORY 2.1, 2.2, 2.3, 2.4
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
  - ✅ Obtiene schema vigente
  - ✅ Valida con ValidationService
  - ✅ Persiste en entity_data
  - ✅ Dispara hooks (implementado vacío por ahora)
  - ✅ Tests: create/update/delete básicos
- **Dependencias:** STORY 3.1, STORY 2.5
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
  - ✅ Sin Proxy (Vanilla puro) — restricción vigente
  - ⚠️ "Sin listeners" fue una restricción inicial abandonada deliberadamente en STORY 9.7 (que exige expresamente "gestión de estado ampliada... notificaciones... theming en tiempo real"): `AppState` implementa hoy tres mecanismos `subscribe/unsubscribe/notify` (usuario/sesión, UI, notificaciones), evolución coherente pero no reflejada aquí hasta ahora
  - ⚠️ `AppState`/`StateModel.js` se eliminó por completo en el hallazgo 05.12 de la auditoría técnica (20260811): `setCurrentEntity()` y el resto de campos sin consumidores reales (`records`, `metadata`, `loading`, `error`, `navigationState`) eran código muerto y se retiraron; los tres mecanismos `subscribe/notify` de la nota anterior se repartieron por dominio en `SessionModel.js` (usuario, token, entidades), `ThemeModel.js` (preferencias UI) y `NotificationModel.js` (notificación global) — mismo comportamiento observable, sin objeto único
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
  - ✅ Explora `plugins/` y lee manifest.json
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
- **Dependencias:** STORY 0.2
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
  - ✅ Schema define claves tecnicas `name`, `email`, `phone`, `is_active` con labels en castellano
  - ✅ Hook de validación personalizada (ej. email único)
  - ✅ Installer registra entidad en plugins
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
  - ⚠️ `plugins.schema_json` (tabla consolidada; `entity_metadata` nunca llegó a existir en las migraciones actuales) no tiene ningún `CHECK` de estructura — decisión consciente, no olvido: la validación de forma del schema vive en la capa de aplicación (`SchemaFieldExtractor`/`ValidationService`), igual que `plugin_entity_data.content` ("*content is an untyped JSONB bag; schema validated at application layer*", `002_plugin_entity_data.sql:3`)
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
  - ✅ Redirigir a home si exitoso
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

### STORY 6.3: Release B — `plugins` como unica fuente de verdad (eliminar system_entities)
- **Points:** 3
- **Priority:** MUST
- **Type:** Refactor / Database
- **Criteria:**
  - ✅ Migración `010_drop_system_entities.sql` — `DROP TABLE IF EXISTS system_entities`
  - ✅ `SystemEntity.php` consulta `plugins WHERE plugin_type='entity'` (no system_entities)
  - ✅ `SystemEntitiesTableTest.php` verifica que la tabla ya NO existe + catálogo en plugins
  - ✅ `MigrationIdempotenceTest.php` actualizado (sin system_entities, con migración 010)
  - ✅ `SystemEntityTest.php` fixtures redirigidos a tabla plugins
  - ✅ Suite completa verde: 11 suites, 0 fallos
- **Decisión técnica:** DECISION 6 — ver docs/mvp/decisiones-tecnicas.md
- **Dependencias:** STORY 6.2, Release A (migración 009)
- **Blockers:** Ninguno

### STORY 6.4: Plugin de ejemplo tipo extension (`comments`)
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

### STORY 6.5: Frontend - Página PluginManager (listar, activar, desactivar)
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Status:** ✅ COMPLETADO (`7d2d313`)
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
  - ✅ PluginLoader compara versión instalada (plugins) vs versión en disco (manifest.json)
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
  - ✅ Endpoint POST `/api/v1/plugins/sync` sincroniza plugins presentes en disco con la tabla `plugins`
  - ✅ La sincronización registra plugins nuevos, preserva la versión/schema runtime de plugins ya instalados y devuelve resumen de cambios/errores
  - ✅ Endpoint POST `/api/v1/plugins/{slug}/update` ejecuta actualización
  - ✅ Si el plugin tiene `onUpdate()` en Lifecycle.php, se ejecuta antes de activar nueva versión
  - ✅ Schema diff solo aditivo: si hay nuevos campos en `schema.json`, se aplican al schema vivo en `plugins.schema_json` con versión incrementada
  - ✅ Actualización falla atómicamente (transacción) si onUpdate lanza excepción
  - ✅ La actualización persiste snapshot previo en `plugin_update_history` para preparar rollback manual futuro
  - ✅ Tests: sincronización de plugin nuevo, plugin sin cambios, manifest inválido, actualización exitosa y fallo con rollback automático
- **IA Usage:** Lógica de sync + diff + transacción + tests de error
- **Dependencias:** STORY 7.1, STORY 4.5, STORY 2.2
- **Blockers:** Definir estructura de `onUpdate()` en contrato de plugin

### STORY 7.3: Frontend - Página de configuración de plugin activado
- **Points:** 5
- **Priority:** MUST
- **Type:** Fullstack
- **Descripción:**
  Cuando un plugin de tipo `entity` está activo, el admin puede entrar a su pantalla de configuración
  para personalizar el schema de la entidad: activar/desactivar `custom_fields` sugeridos por el plugin
  y añadir campos adicionales libres. Los cambios generan una nueva versión en `plugins.schema_json`.
- **Criteria:**
  - ✅ Ruta `#/plugins/{slug}` renderiza página de configuración del plugin
  - ✅ Se listan los `custom_fields` del schema del plugin con checkbox activar/desactivar
  - ✅ Sección "Campos adicionales" permite añadir campos libres (nombre, tipo, requerido)
  - ✅ Guardar llama a PUT `/api/v1/plugins/{slug}/config` y genera nueva versión en `plugins.schema_json`
  - ✅ Solo visible para plugins de tipo `entity` que estén en estado `active`
  - ✅ Backend valida que los campos obligatorios del plugin (`fields`) no sean eliminables desde UI
  - ✅ Tests backend: update schema + versión incrementada + campos base intocables
  - ✅ Tests frontend: render custom_fields, toggle, añadir campo libre, guardar
- **IA Usage:** Endpoint PUT config + lógica diff de schema + página frontend con form dinámico
- **Dependencias:** STORY 4.7, STORY 6.4, STORY 7.2
- **Blockers:** Ninguno

#### Refuerzo STORY 7.3 (ampliación funcional)

- Extender la pantalla `#/plugins/{slug}` para plugins `extension` en estado `active`.
- Permitir configuración de campos del `schema` del plugin extension desde la misma tabla unificada.
- Añadir configuración de relación de extensión mediante `target_entity`:
  - `*` para aplicar a cualquier entidad.
  - slug explícito (por ejemplo `persons`) para restringir el alcance.
- Mantener compatibilidad completa con configuración de plugins `entity` ya implementada.
- Actualizar tests backend/frontend para cubrir:
  - visibilidad de `Configure` en plugins extension activos,
  - lectura/escritura de `target_entity`,
  - guardado de campos en plugins extension.


### STORY 7.4: Rollback manual de plugin a versión anterior
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Backend
- **Criteria:**
  - ✅ Endpoint POST `/api/v1/plugins/{slug}/rollback` restaura versión anterior
  - ✅ Requiere que exista snapshot previo en `plugin_update_history`
  - ✅ Ejecuta `onRollback()` del plugin si existe
  - ✅ Estado plugin vuelve a versión registrada antes del update
  - ✅ Tests: rollback exitoso, error si no hay snapshot previo
  - ⚠️ Deuda técnica reconocida: `plugin_update_history` no tiene política de retención ni limpieza de snapshots antiguos — crecimiento no acotado. Aceptable para el alcance de TFM/MVP; pendiente de decidir un TTL o límite de filas por plugin si el proyecto avanza más allá de esa escala.
- **IA Usage:** Lógica de restauración + tests de rollback
- **Dependencias:** STORY 7.2
- **Blockers:** Ninguno

### STORY 7.5: Frontend - UI de actualización y rollback en PluginManager
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Botón "Sincronizar" en PluginManager, visible solo para admin
  - ✅ Botón "Sincronizar" llama a POST `/api/v1/plugins/sync`, muestra feedback y recarga la lista
  - ✅ Badge "Actualización disponible" en plugin con versión desactualizada
  - ✅ Botón "Actualizar" llama a endpoint y muestra feedback
  - ✅ Botón "Rollback" disponible si hay versión anterior
  - ✅ Modal de confirmación antes de actualizar/rollback
- **IA Usage:** UI de sincronización + badges + modal confirmación + feedback estados
- **Dependencias:** STORY 6.5, STORY 7.2, STORY 7.3, STORY 7.4
- **Blockers:** Ninguno

---

## EPIC 8: Gestión de usuarios (Fase 8)

> Estado de avance: las stories 8.1 a 8.5 ya están implementadas en el repo; la Epic 8 queda cerrada y el siguiente foco pasa a la consolidación SPA y operación avanzada.

Objetivo: Incorporar gestion de perfil propio para todos los usuarios y panel de administracion de usuarios para el rol admin, con avatar por iniciales, menu emergente en la barra superior y rutas hash propias.

### STORY 8.1: Backend - Perfil de usuario, avatar binario y soft delete
- **Points:** 3
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Migracion `001_users.sql`: añade columnas `name VARCHAR(255)`, `avatar BYTEA` y `deleted_at TIMESTAMPTZ` a la tabla `users`
  - ✅ Migracion idempotente
  - ✅ `UserRepository` con metodos `find(id)`, `all()`, `update(id, data)`, `delete(id)` y `updatePassword(id, hash)`
  - ✅ `delete(id)` implementa borrado logico mediante `deleted_at` y excluye usuarios dados de baja de las lecturas/actualizaciones normales
  - ✅ `UserSeeder` actualizado para incluir `name` en el usuario admin por defecto
  - ✅ Tests: migracion idempotente, CRUD basico de UserRepository y regresion de login para usuarios eliminados
- **IA Usage:** SQL de migracion + boilerplate de repositorio + tests de regresion
- **Dependencias:** STORY 1.1
- **Blockers:** Ninguno

### STORY 8.2: Backend - UserController y rutas REST
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ `GET  /api/v1/users/me` — perfil propio (cualquier usuario autenticado)
  - ✅ `PUT  /api/v1/users/me` — actualizar nombre, email y avatar propio (requiere `current_password` si cambia email)
  - ✅ Cambio de contraseña propia embebido en `PUT /api/v1/users/me` (campo `password`, requiere `current_password`; no hay ruta `/users/me/password` separada)
  - ✅ `GET  /api/v1/users` — listar usuarios (solo admin)
  - ✅ `GET  /api/v1/users/{id}` — ver usuario (solo admin)
  - ✅ `PUT  /api/v1/users/{id}` — editar usuario, nombre, email y roles (solo admin)
  - ✅ `PUT  /api/v1/users/{id}/password` — reset de contraseña (solo admin, genera password aleatoria visible una sola vez)
  - ✅ `DELETE /api/v1/users/{id}` — borrar usuario (solo admin; no puede borrarse a si mismo)
  - ✅ Tests: autorizacion, happy path y casos de error para cada endpoint
  - ✅ Sin `POST /api/v1/users` (decision de alcance, no olvido): el unico usuario que existe al arrancar es el admin sembrado por `UserSeeder`; la gestion de usuarios permite editar, resetear contraseña y borrar, pero no dar de alta nuevos usuarios
- **IA Usage:** Controlador + logica de autorizacion + tests de integracion
- **Dependencias:** STORY 8.1, STORY 1.4
- **Blockers:** Ninguno

### STORY 8.3: Frontend - UserMenu dropdown en Navbar
- **Points:** 3
- **Priority:** MUST
- **Type:** Frontend
- **Status:** ✅ Implementada
- **Criteria:**
  - ✅ Avatar circular con iniciales (1-2 letras) sobre fondo de color determinista (hash del email); si el usuario tiene un `avatar` disponible se usa como contenido visual del avatar
  - ✅ Muestra nombre del usuario (o email como fallback) junto al avatar en la barra superior
  - ✅ Hover/Click sobre el avatar/nombre despliega menu emergente con: **Mi Perfil**, **Gestión de usuarios** (solo admin) y **Cerrar sesion**
  - ✅ Nuevo componente `UserMenu.js` independiente del resto de la navbar
  - ✅ Sustituye el email plano y el boton Logout actuales de `Navbar.js`
  - ✅ Tests: render avatar con iniciales, render con avatar disponible, visibilidad de Gestión de usuarios segun rol
- **IA Usage:** Componente UserMenu + logica de avatar por iniciales + tests
- **Dependencias:** STORY 8.2, STORY 5.2
- **Blockers:** Ninguno

### STORY 8.4: Frontend - Pagina Mi Perfil (`#/profile`)
- **Points:** 3
- **Priority:** MUST
- **Type:** Frontend
- **Status:** ✅ Implementada
- **Criteria:**
  - ✅ Ruta hash `#/profile` renderiza la pagina de perfil propio
  - ✅ Formulario: nombre, email y avatar propio (campo para subir o gestionar la imagen asociada al perfil)
  - ✅ Guardar llama a `PUT /api/v1/users/me` y actualiza el estado global y el UserMenu
  - ✅ Seccion independiente para cambio de contraseña: contraseña actual, nueva y confirmacion
  - ✅ Accesible para cualquier usuario autenticado
  - ✅ Tests: render formulario, validacion de contraseñas, actualizacion del avatar en tiempo real
- **IA Usage:** Pagina de perfil + preview de avatar + tests
- **Dependencias:** STORY 8.2, STORY 8.3
- **Blockers:** Ninguno

### STORY 8.5: Frontend - Pagina gestión de usuarios (`#/usuarios`)
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Ruta hash `#/usuarios` renderiza la tabla de usuarios (solo admin)
  - ✅ Tabla con columnas: avatar, nombre, email, roles, fecha de alta y acciones
  - ✅ Accion Editar: modal con nombre, email y roles editables
  - ✅ Accion Reset password: modal que llama a `PUT /api/v1/users/{id}/password` y muestra la contraseña generada una sola vez (con opcion copiar)
  - ✅ Accion Borrar: modal de confirmacion; deshabilitada para el usuario actualmente autenticado
  - ✅ Ruta `#/usuarios/:id` como acceso directo a ficha/datos del usuario
  - ✅ Acceso denegado con mensaje informativo si el usuario no es admin
  - ✅ Tests: render tabla, modal editar, modal reset, restriccion de borrado propio
- **IA Usage:** Tabla de usuarios + modales de accion + guards de rol + tests
- **Dependencias:** STORY 8.2, STORY 8.3
- **Blockers:** Ninguno

---

## EPIC 9: Sistema UI, Shell Frontend y Arquitectura SPA (Fase 9)

Objetivo: Definir un sistema UI inspirado conceptualmente en Ant Design, consolidar la navegacion y el shell SPA, y dejar el frontend preparado para crecer con una arquitectura modular, resiliente y consistente antes de escalar operacion, marketplace y QA avanzada, incluyendo edicion visual WYSIWYG y personalizacion basica de marca por cliente.

### STORY 9.1: Fundamentos de diseño
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Status:** ✅ Implementada
- **Criteria:**
  - ✅ Principios visuales y de interaccion documentados para una UI enterprise consistente, jerarquica y con feedback inmediato
  - ✅ Tailwind CSS servido como hoja local generada (`tailwind.generated.css`) a partir de `tailwind.src.css` y `tailwind.config.cjs`, sin dependencia runtime del Play CDN
  - ✅ Decision tecnica documentada: CSS con Tailwind CSS como framework de estilos, abandonando el CSS propio actual
  - ✅ `main.css` deja de participar en runtime y los overrides necesarios pasan a capas `@layer` dentro del pipeline de Tailwind
  - ✅ Tokens y reglas base definidos para spacing, tipografia, color, bordes, sombras, estados, iconografia y densidad
  - ✅ Requisito funcional documentado: nueva UI con enfoque WYSIWYG para que cambios visuales se previsualicen de forma inmediata
  - ✅ Requisito funcional documentado: personalizacion por cliente de colores y diseno basico alineado con su imagen de marca
  - ✅ Todos los componentes nuevos usan clases Tailwind; sin CSS custom paralelo
- **IA Usage:** Integracion Tailwind + migracion a CSS generado localmente + unificacion visual de tablas/tabs/formularios + UI WYSIWYG
- **Dependencias:** STORY 5.2, STORY 6.5, STORY 8.3
- **Blockers:** Ninguno

### STORY 9.2: Fundamentos de navegacion y anatomia de paginas
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Status:** ✅ Implementada
- **Criteria:**
  - ✅ Arquitectura de informacion definida para menu principal, areas y tipos de pagina
  - ✅ Plantillas objetivo definidas para login, home, list page, detail/form page, plugin management y estados result/empty/error
  - ✅ Preparados los contratos base para breadcrumbs y copy/i18n; STORY 9.5 ya consume la estructura y STORY 9.7 completara la externalizacion transversal
  - ✅ Decision tecnica documentada: routing SPA basado en hash (`#/ruta`) como convencion de navegacion, compatible con Apache+PHP y refresh
  - ✅ Mapa de rutas hash definido para todas las vistas actuales y futuras (incluidas `#/profile`, `#/users` y `#/users/:id`)
- **IA Usage:** Sintesis de sistema UI + mapa de navegacion + definicion de plantillas y reglas base
- **Dependencias:** STORY 9.1
- **Blockers:** Ninguno

**Nota de alcance:** STORY 9.2 dejó listos los contratos base de breadcrumbs y copy/i18n. STORY 9.5 ya materializa el render reusable en UI y shell; la externalizacion real de textos sigue reservada para STORY 9.7.

### STORY 9.3: Libreria de componentes UI base
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Status:** ✅ Implementada
- **Criteria:**
  - ✅ Libreria base de controles reutilizables con categorias tipo Ant: general, layout, navigation, data entry, data display y feedback
  - ✅ Componentes base definidos al menos para Button, Typography, Page, PageHeader, Section, Breadcrumb, Tabs, Inputs, FormFields, Table, Empty, Alert, Modal y Spinner/Skeleton
  - ✅ API estable de componentes y clases/tokens comunes para estilo, composicion y variantes de tema
  - ✅ DynamicForm, DynamicTable, Modal y PluginManager pueden migrar a esta base sin patrones visuales paralelos
  - ✅ Tests o smoke checks de componentes base y estados visuales principales
- **IA Usage:** Extraccion de controles base + contrato de componentes + alineacion visual de la UI
- **Dependencias:** STORY 3.8, STORY 3.9, STORY 5.4, STORY 9.1
- **Blockers:** Ninguno
- **Implementacion verificada:** `frontend/src/js/views/modules/ComponentFactory.js` y `frontend/tests/ComponentsTest.html`; API única `component.create()` / `component.getCatalog()` con registro estricto.

**Nota de cierre:** la implementación quedó validada con tests y navegación real en navegador y sirve como base de los layouts cerrados en STORY 9.5.

### STORY 9.4: Arquitectura frontend y modularizacion
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ El entrypoint frontend (`app.js`) deja de concentrar la mayor parte del wiring de UI y el arranque queda integrado en la capa `controllers`
  - ✅ Separacion explicita segun arquitectura MVC estricta: toda la logica frontend debe organizarse bajo `controllers`, `views` y `models` como unicas capas principales; el `app.js` raiz se limita al bootstrap tecnico y delega en `AppController`; router, layout, components, pages, estado, consumo API y adaptadores de plugin viven dentro de una de esas tres capas, sin crear carpetas o capas paralelas al mismo nivel fuera del modelo MVC
  - ✅ Libreria de componentes frontend con imports estables y convencion clara de composicion
  - ✅ Estructura preparada para crecer sin crear archivos monoliticos
  - ✅ Tests frontend actualizados a la nueva organizacion sin romper ejecucion standalone
- **IA Usage:** Refactor de arquitectura cliente + modularizacion + ajuste de imports y tests
- **Dependencias:** STORY 3.6, STORY 5.2, STORY 9.1, STORY 9.2
- **Blockers:** Ninguno

### STORY 9.5: Shell SPA y plantillas de navegacion
- **Estado:** ✅ COMPLETADA (2026-08-10)
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Shell comun para navegacion principal, cabecera, breadcrumbs, toolbar contextual, area principal de contenido y pie opcional
  - ✅ Aplicacion de las plantillas definidas en 9.2 a Login, EntityList, EntityEdit, PluginManager, UserProfile, UserManagement y vistas futuras
  - ✅ Contenedores reutilizables para page header, acciones contextuales y layout de pagina
  - ✅ Shell y plantillas dejan zonas claras de extension para tabs, paneles, acciones y bloques aportados por plugins
  - ✅ Integracion del shell sin romper flujos existentes ni generar layouts paralelos
  - ✅ Tests frontend de render, navegacion basica del shell y encaje de paginas principales
- **IA Usage:** Construccion del layout compartido + page templates + integracion con paginas existentes
- **Dependencias:** STORY 9.1, STORY 9.2, STORY 9.3
- **Blockers:** Ninguno
- **Verificacion:** 17/17 runners HTML y 166/166 assertions en navegador integrado; Login y shell real sin errores de consola

### STORY 9.6: Implementacion del routing SPA
- **Points:** 3
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Router cliente basado en `hashchange` + `window.location.hash` con convencion `#/segmento/param`
  - ✅ Mapa de rutas hash completo implementado:
    - `#/home` y `#/` — aliases de inicio con redireccion a la primera `#/entity/:slug` activa
    - `#/profile` — perfil propio
    - `#/users` — gestión de usuarios (admin)
    - `#/users/:id` — ficha de usuario (admin)
    - `#/entity/:slug` — listado de registros de una entidad
    - `#/entity/:slug/new` — alta de registro
    - `#/entity/:slug/:id` — detalle/edicion de registro
    - `#/entity/:slug/:id/:tab` — tab de registro
    - `#/plugins` — PluginManager
    - `#/plugins/:slug` — configuracion de plugin
    - `#/login` — pantalla de autenticacion
  - ✅ Navegacion programatica via `router.navigate('#/ruta')` sin recargar pagina
  - ✅ Refresh del navegador mantiene la vista activa (hash preservado en URL)
  - ✅ Back/forward del navegador navega correctamente entre vistas
  - ✅ Tests de navegacion: entrada directa por hash, refresh, back/forward y persistencia de contexto
- **IA Usage:** Implementacion del router hash + mapa de rutas + navegacion programatica + tests
- **Nota:** Convencion de tabs: preferir subruta `#/entity/:slug/:id/:tab` sobre query param para mantener consistencia con el resto del mapa de rutas. Usar `?tab=` solo si un tab necesita estado adicional en query string.
- **Nota:** La pestaña base usa el id tecnico `data`; seleccionar una pestaña debe sincronizar el hash sin reconstruir `EntityEdit`. Back/forward entre tabs del mismo registro reutiliza formulario y paneles precargados.
- **Dependencias:** STORY 9.1, STORY 9.3, STORY 9.4
- **Blockers:** Ninguno
- **Verificacion:** 17/17 runners HTML y 169/169 assertions; mapa completo, entrada directa, refresh, back/forward, navegación por tabs y fallback de inicio en navegador integrado

### STORY 9.7: Infraestructura transversal de frontend y resiliencia
- **Estado:** ✅ Implementada
- **Points:** 5
- **Priority:** MUST
- **Type:** Frontend
- **Criteria:**
  - ✅ Gestion de estado global ampliada para shell, usuario, notificaciones, modales globales y estado transversal de navegacion
  - ✅ Mecanismo global de manejo de errores de frontend con fallback UI y mensajes amigables para errores JS y de red
  - ✅ Servicios/frontend helpers comunes para feedback global, modales y errores sin soluciones paralelas por pagina
  - ✅ Base preparada para i18n y theming: textos externalizables y tokens compatibles con tema claro/oscuro sin exigir aun catalogo completo de traducciones
  - ✅ Infraestructura de tema con tokens configurables por cliente (paleta principal, secundaria, acentos, tipografia base y radios/bordes)
  - ✅ Persistencia de configuracion visual por cliente y aplicacion global en tiempo real
  - ✅ Pages y componentes consumen la infraestructura comun de estado y resiliencia sin multiplicar stores o handlers ad hoc
- **IA Usage:** Consolidacion de estado global + error handling + servicios UI transversales + bases de i18n/theming
- **Dependencias:** STORY 3.7, STORY 9.2, STORY 9.3, STORY 9.5
- **Blockers:** Ninguno
- **Verificación:** 17/17 runners HTML, smoke test de resiliencia/tema y checks de sintaxis en verde; estado global, notificaciones, confirmaciones, i18n y theming persistido aplicados en shell y páginas principales

### STORY 9.8: UX transversal, accesibilidad y microinteracciones
- **Estado:** ✅ Implementada
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Estados unificados de loading, vacio, error y exito en flujos principales
  - ✅ Confirmacion consistente en acciones destructivas o sensibles
  - ✅ Prevencion de doble submit y feedback claro de operaciones en curso
  - ✅ Mejora de foco, accesibilidad basica por teclado y continuidad de contexto tras guardar/cancelar
  - ✅ Responsive, densidad razonable y microinteracciones suaves aplicadas al menos en Login, EntityList, EntityEdit, PluginManager y UserConfig
- **IA Usage:** Refinamiento UX + estados comunes + accesibilidad basica + microinteracciones y continuidad de contexto
- **Dependencias:** STORY 9.2, STORY 9.4, STORY 9.5, STORY 9.6
- **Blockers:** Ninguno
- **Verificación:** 13/13 assertions en UiResilienceTest y 7/7 assertions en UserManagementTest; validación en navegador integrado con foco, modal de confirmación y notificaciones globales/página verificados

### STORY 9.9: Documentacion de arquitectura frontend y testing UI automatizado
- **Estado:** ✅ Implementada
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Documentacion de arquitectura frontend con estructura de carpetas, convenciones de componentes, flujo SPA y decisiones de routing
  - ✅ Guia de extension para nuevas paginas, patrones de datos y puntos de integracion de plugins en UI
  - ✅ Base de tests UI automatizados con preferencia por Playwright para smoke/E2E de navegador
  - ✅ Cobertura minima de flujos UI: login, navegacion shell, listado/edicion de entidad y PluginManager
  - ✅ Cobertura minima de flujo WYSIWYG: editar tema, previsualizar cambios, guardar y recuperar configuracion visual por cliente
  - ✅ Ejecucion documentada y estable sobre el runtime Apache+PHP same-origin del proyecto
- **IA Usage:** Documentacion tecnica frontend + bootstrap de Playwright + casos smoke/E2E de UI
- **Dependencias:** STORY 9.3, STORY 9.4, STORY 9.5, STORY 9.7
- **Blockers:** Ninguno
- **Verificación:** `frontend/tests/integration/` (19/19 runners HTML re-verificados en verde tras reubicarlos, con 2 aserciones obsoletas y 1 mensaje de error corregidos) + `frontend/tests/e2e/` (7/7 specs Playwright en verde: login, shell-navigation, entity-crud, plugin-manager, theme-wysiwyg) contra el runtime real Apache+PHP; 2 bugs reales de producción detectados y corregidos durante la verificación (`UserConfig.js` sin importar `AppState`, `EntityList.js` borraba su propio estado vacío)

---

## EPIC 10: Login, Persons y Plugins de Demostración (Fase 10)

Objetivo: Completar el MVP para la defensa del TFM: pulir la experiencia de login, generalizar el modelo de personas, flexibilizar la identidad de los plugins, y dotar al sistema de plugins de demostración con datos reales de un caso de uso óptico.

### STORY 10.1: Mejoras en la sección de login
- **Estado:** ✅ Implementada
- **Points:** 5
- **Priority:** MUST
- **Type:** Fullstack
- **Criteria:**
  - ✅ Logo tipo wordmark ("Xestify" estilizado) visible en la pantalla de login
  - ✅ Nombre de la aplicación y una descripción breve visibles junto al logo
  - ✅ `APP_DEBUG` se expone al frontend (vía `/health` o endpoint equivalente) para poder condicionar UI de desarrollo
  - ✅ Solo si `APP_DEBUG=true`, aparecen dos botones de acceso rápido: "Entrar como admin" y "Entrar como usuario", que inician sesión con credenciales fijas de los usuarios seed sin que el usuario las escriba
  - ✅ Nuevo `UserSeeder` para un usuario "normal" (rol no-admin) fijo, análogo al seed de `admin@xestify.local` ya existente
  - ✅ Ambos usuarios seed (admin y normal) quedan protegidos: no editables ni eliminables desde Gestión de Usuarios, para no romper el login automático (incluye autoservicio: `PUT /users/me` rechaza cambios de email/password propios de un usuario seed)
  - ✅ Los botones de acceso rápido no aparecen si `APP_DEBUG=false`
  - ✅ Ampliación acordada antes de implementar (no un desvío posterior): refactor completo del sistema de mensajes/carga/validación de `Login.js` — zona de feedback única con `aria-live` (sustituye los errores por campo), loader rediseñado como componente `Loader` propio (no reutiliza el `UiResilienceService.setViewState` genérico, pensado para estados vacíos), animación shake accesible (respeta `prefers-reduced-motion`), inputs y botón deshabilitados durante el envío, duración mínima de loading (~400ms) para evitar parpadeo, toggle de mostrar/ocultar contraseña integrado en `InputPassword`, validación de cliente (campos vacíos/formato de email) con foco automático al primer campo inválido, y texto fijo y neutro para credenciales inválidas
  - ✅ Detección de sesión caducada centralizada en un único interceptor (`setSessionExpiredHandler` en `ApiClientModel.js`): cualquier 401 autenticado, desde cualquier página, redirige a login con aviso — no solo el que se originaba en Login
  - ✅ Identidad visual nueva y reutilizable: componentes `Logo`/`BrandLogo`/`Loader`, hoja de estilos dedicada `frontend/src/css/brand.css` (única excepción vigente a "Tailwind como capa principal", ver `ui-foundations-ant.md`), reactiva al `themeColor`/`pageStyle` configurados
  - ✅ Corregidos varios defectos de adaptación a `pageStyle` dark detectados tras la implementación inicial: login no aplicaba el tema global tras logout (`clearAuth()` reseteaba `ui-preferences`, que es config global de la instalación, no de sesión), `inputEmail`/botones sin `variant`/loader con fondo claro fijo, borde de `login-quick-access` y color de foco del toggle de contraseña sin seguir el tema
- **IA Usage:** Diseño del wordmark + wiring de `APP_DEBUG` a `/health` + nuevo seeder + botones de login automático + protección de usuarios seed + refactor UX completo de mensajes/carga/validación + interceptor de sesión caducada centralizado + identidad visual (Logo/BrandLogo/Loader) + adaptación a tema claro/oscuro
- **Dependencias:** STORY 1.2, STORY 1.3, STORY 8.2
- **Blockers:** Ninguno
- **Verificación:** Backend `php backend/tests/run.php` 56/56 ficheros en verde (incluye `AuthControllerTest`/`MigrationIdempotenceTest`, existentes pero nunca registrados en el runner hasta esta verificación — corregido). Frontend `frontend/tests/integration/` 229/229 assertions en verde (21 runners HTML) + `frontend/tests/e2e/` 12/12 specs Playwright contra el runtime real Apache+PHP+Postgres. 2 aserciones obsoletas en `UiResilienceTest.html` corregidas (asumían el comportamiento previo, con bug, de `clearAuth()`/`hydrateUiPreferences()` que esta misma story corrigió) y añadida cobertura unitaria antes ausente del interceptor de sesión caducada en `ApiTest.html`

### STORY 10.2: Renombrar plugin `clients` a `persons`
- **Estado:** ✅ Implementada
- **Points:** 5
- **Priority:** MUST
- **Type:** Fullstack
- **Criteria:**
  - ✅ Carpeta `plugins/clients/` renombrada a `plugins/persons/`
  - ✅ Namespace PHP actualizado de `Xestify\plugins\clients` a `Xestify\plugins\persons` en `Hooks.php`, `Installer.php`, `Lifecycle.php`
  - ✅ `manifest.json` y `schema.json` actualizados (`slug`, `entity`) a `persons`
  - ✅ Filas existentes en `plugins.slug`, `plugin_entity_data.entity_slug` y `plugin_extension_data.entity_slug` renombradas de `clients` a `persons` sin pérdida de datos (243 + 5 filas en BD local); aplicado como ajuste puntual documentado vía `psql`, no como migración committeada — el proyecto está en fase MVP sin ninguna instalación real que migrar de forma incremental (decisión acordada con el usuario, ver `docs/10-productivity/sesion.md`)
  - ✅ Mismos campos que tenía `clients` (rename directo, sin ampliar el modelo): `name`, `surnames`, `email` + `custom_fields` opcionales (`phone`, `creation_stamp`, `is_active`)
  - ✅ Regla de `AGENTS.md` actualizada: `persons` pasa a ser el slug canónico de personas, documentando el motivo del cambio; se retira la prohibición sobre `client`
- **IA Usage:** Exploración paralela con 3 agentes + rename asistido de carpeta/namespace/manifest/schema + ajuste puntual de datos en BD vía `psql` + fix de `schema_json.entity` desincronizado (hallazgo durante la verificación) + actualización de AGENTS.md y 14 ficheros de documentación viva
- **Dependencias:** Ninguna (plugin ya existente desde EPIC 3)
- **Blockers:** Ninguno

### STORY 10.3: Desacoplar `plugin_name` de `slug`, identidad editable y consolidación en `manifest_json`
- **Estado:** ✅ Implementada
- **Points:** 8 (AC original) — el alcance real entregado creció con 4 ampliaciones
  acordadas explícitamente con el usuario durante la sesión (§6-§9 más abajo),
  muy por encima de la estimación inicial
- **Priority:** MUST
- **Type:** Fullstack
- **Criteria (AC original):**
  - ✅ Identidad técnica fija = nombre de carpeta/namespace PHP (`plugin_name`),
    usada por `PluginClassLoader`/`PluginDiscoveryService`/`PluginHookRegistrar`/
    `PluginLifecycleInvoker` para determinar tipo y construcción del plugin
    (sustituye el uso anterior del slug). No es columna propia: vive dentro de
    `manifest_json.name` (ver "Refactor añadido" más abajo)
  - ✅ `description` editable por instancia, sin patrón i18n (texto plano — STORY
    A1.1 queda fuera del alcance, decisión cerrada con el usuario)
  - ✅ `slug` editable desde `PluginConfig`, usado solo para navegación/URL
    (`RouteMapController`) y como clave visible de datos
  - ✅ Al renombrar el `slug` desde `PluginConfig`, se actualiza en cascada
    (transaccional) `plugin_entity_data.entity_slug`,
    `plugin_extension_data.entity_slug`/`plugin_slug`, `plugin_update_history.slug`
    y el `target_entity` de otros plugins `extension` que apuntaran al slug viejo
  - ✅ La navegación (`#/entity/:slug`, `#/plugins/:slug`) sigue funcionando por
    `slug`, no por `plugin_name`
  - ✅ Edición de `name` (mapea a `manifest_json.label`) y `description` del
    plugin añadida a `PluginConfig`
  - ✅ Tests de regresión que confirman que renombrar un slug no rompe la carga
    del plugin (que sigue dependiendo de `plugin_name`, no del slug)
- **Refactor añadido durante la sesión (§2bis, no en el AC original)**:
  consolidación de las columnas `plugin_name`/`plugin_type`/`version`/`name`/
  `description` de `plugins` en una única columna `manifest_json JSONB` viva que
  refleja el `manifest.json` real en disco, y eliminación de la columna
  `schema_version` sin reemplazo (de `plugins` y de `plugin_update_history`) por
  ser residual y sin consumidores reales. Contrato JSON público de la API sin
  cambios (verificado exhaustivamente). Ver entrada de sesión
  2026-08-16 en `docs/10-productivity/sesion.md` y nueva decisión en
  `docs/09-history/decisiones-tecnicas.md`.
- **§6 — Alta manual de plugin (ampliación acordada con el usuario)**: nuevo
  flujo en `PluginManager`/`PluginConfig` (modo `create`) para registrar una
  instancia nueva plugin a plugin, con "Campos"/"Relación de extensión"
  editables y guardables ya en el alta; el plugin se activa automáticamente al
  registrarse (decisión de cierre de sesión).
- **§7 — Borrado de plugin (ampliación acordada con el usuario)**: borrado
  físico en cascada de todos los datos asociados desde `PluginConfig`, permitido
  en cualquier estado (desactiva primero si está activo).
- **§8 — Grid "Relaciones" editable en `PluginConfig` (ampliación acordada con
  el usuario)**: primera implementación funcional real del bloque `relations`
  del schema (declarado desde STORY 4.7, ignorado silenciosamente hasta ahora).
- **§9 — Tab de relación inversa en `EntityEdit` (ampliación acordada con el
  usuario)**: cuando otra entidad declara una relación hacia la entidad vista,
  aparece automáticamente una tab con sus registros relacionados — capacidad de
  núcleo, no de plugin.
- **IA Usage:** Diseño de la separación `plugin_name`/`slug` + consolidación en
  `manifest_json` (4 preguntas dirigidas al usuario) + alta manual/borrado en
  cascada/grid de relaciones/tab de relación inversa + fix de sincronización de
  estado en `PluginConfig.js` reportado por el usuario + ~20 ficheros de test
  backend nuevos/reescritos
- **Dependencias:** STORY 7.3 (`PluginConfig`)
- **Blockers:** Ninguno

### STORY 10.4: Plugins de demostración — entidades
- **Estado:** ✅ Implementada
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria (AC original, con dos ajustes acordados con el usuario — ver notas debajo):**
  - ✅ Plugin de entidad `orders` (pedidos): campos fecha del pedido, estado (pendiente/en_proceso/entregado/cancelado), importe total, notas
  - ⛔ ~~Plugin de entidad `sales`~~ — descartado (ver nota 1)
  - ✅ Plugin de entidad `invoices` (facturas): relación `belongs_to` → `orders` (primer uso real end-to-end del bloque `relations` del schema, declarado desde STORY 4.7 pero sin uso hasta ahora), campos: número de factura (único, vía `Hooks.php`), fecha de emisión, importe, estado de pago (pendiente/pagada)
  - ✅ Plugin de entidad `basic` (básico): un plugin básico solamente con el campo `name`. Base para construir en el futuro entidades básicas (categorías, familias, ubicaciones...). Queda solo como plantilla en disco, sin ninguna instancia activada
- **Nota 1 — `sales` descartado:** el AC original pedía `orders → distributors` y `sales → clients` como dos ejemplos gemelos de `relations`, heredado de cuando `clients`/`distributors` eran plugins separados. Tras STORY 10.2 (unificación en `persons`), `sales` habría sido un plugin redundante de `orders` (mismos campos, mismo target conceptual). Decisión acordada con el usuario: un único plugin `orders`, sin duplicarlo.
- **Nota 2 — relación de `orders` no fijada en disco:** el `schema.json` de `orders` se entrega con `relations: []`. La BD local del usuario ya tiene varias instancias reales y en uso del plugin `persons` (con slugs propios, no `persons`) para su caso de uso del TFM — fijar `target_entity` en el schema de disco habría atado `orders` a una de ellas de forma incorrecta para cualquier otra instalación. La relación real `belongs_to` se añade después, por instalación, desde el grid "Relaciones" de `PluginConfig` (STORY 10.3 §8), apuntando a la instancia de `persons` que corresponda en cada caso.
- **Nota 3 — patrón de disco actualizado:** el patrón real vigente (ver `plugins/persons/`, `docs/04-plugins/plantilla-plugin-entidad.md`) es `manifest.json` + `schema.json` + `Hooks.php`/`Lifecycle.php` opcionales — sin `Installer.php`, eliminado como código huérfano en STORY 10.3.
- **IA Usage:** Generación de manifest/schema/Lifecycle/Hooks siguiendo el patrón vigente de `plugins/persons/` + primer caso de uso real end-to-end de `relations` (`invoices → orders`) + tests unitarios de contrato (manifest/schema) y de `Hooks.php` (unicidad de `invoice_number`) siguiendo el patrón de `PersonsPluginTest.php`/`ProductsPluginTest.php`
- **Dependencias:** STORY 10.2 (`persons`), STORY 10.3 (`plugin_name`)
- **Blockers:** Ninguno

### STORY 10.5: Plugins de demostración — extensiones
- **Estado:** ✅ Implementada
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria (AC original, con ajustes acordados con el usuario — ver notas debajo):**
  - ✅ Plugin de extensión `optometries` (ficha de graduación óptica), `target_entity: clients` (no `persons` — ver nota 3), historial de varias fichas por persona: fecha, esfera/cilindro/eje OD y OI en 4 secciones (para lejos, constante, para cerca, lentilla), adición OD/OI, distancia pupilar, relaciones Oftalmólogo/Optometrista (→ `ophthalmologists`), notas
  - ✅ Plugin de extensión `contact_lenses` (ficha de adaptación de lentillas — ver nota 2 sobre el nombre), `target_entity: clients`, historial de varias fichas: fecha, esfera/cilindro/eje/adición OD y OI en 2 secciones (contacto, queratometría), radio/diámetro/uso/pack OD y OI, relaciones Marca/Fabricante/Distribuidor por ojo (→ `brands`/`manufacturers`/`distributors`), notas
  - ✅ Ambos parten del patrón de `plugins/comments/` pero lo amplían: historial de varios registros por owner (página independiente de ficha, `PluginItemEdit.js`, en vez de panel inline), relaciones `belongs_to` propias del plugin de extensión (capacidad de núcleo nueva, no existía antes de esta story), gauge visual del eje (SVG, componente compartido `AxisGauge.js`) y tabla de medidas por ojo como `DynamicTable`
- **Nota 1 — "tipo de lentilla" eliminado:** el AC original de backlog incluía "tipo de lentilla" como campo de `contact_lenses`; no aparece en las capturas de referencia aportadas por el usuario y se descartó, igual que hizo STORY 10.4 con `sales`.
- **Nota 2 — nombres técnicos ajustados:** `optometry`→`optometries` (convención de slugs en plural del proyecto); `contact-lenses`→`contact_lenses` — el guion del AC original es estructuralmente inválido como `name`/slug de plugin (`PluginClassLoader::instantiateHooks()` lo usa literal como segmento de namespace PHP, donde un guion es un error de sintaxis; también falla `PluginIdentityService::SLUG_PATTERN`). La etiqueta visible sigue siendo "Lentillas", sin impacto para el usuario final.
- **Nota 3 — `target_entity: clients` no `persons`:** mismo motivo que STORY 10.4 — no existe ninguna entidad activa con slug `persons` en la BD real del usuario (el `plugin_name` interno sigue siendo `persons`, pero la instancia activa se renombró a `clients`).
- **Nota 4 — capacidades de núcleo nuevas, no solo dos plugins:** esta story amplió el sistema de plugins de extensión con soporte de `relations` (antes solo lo tenían los plugins `entity`), validación server-side de `content` contra schema (antes `PluginExtensionController` no validaba nada), la convención general `layers`/`layer`/`resortable` para organizar la UI de cualquier plugin (aplicable también a plugins `entity`, aunque hoy solo la usan `optometries`/`contact_lenses`) y el patrón de página independiente de ficha (`PluginItemEdit.js`) para plugins con historial de varios registros por owner — ver `docs/01-architecture/plugins.md`.
- **Límite conocido, aceptado y documentado:** `ReverseRelationTabResolver`/`EntityService::guardNoDependentRecords()` no cubren plugins `extension` — borrar un `ophthalmologists`/`distributors`/`brands`/`manufacturers` referenciado por una ficha no se bloquea ni muestra pestaña inversa.
- **IA Usage:** Generación de manifest/schema/Hooks/plugin.js siguiendo el patrón de extensión existente, ampliado con relaciones y capas nuevas; diseño del componente SVG `AxisGauge` reutilizable
- **Dependencias:** STORY 10.2 (`persons`/`clients`), STORY 6.2 (hooks `registerTabs`), STORY 10.4 (patrón de multi-instancia reutilizado para `brands`/`manufacturers`)
- **Blockers:** Ninguno

### STORY 10.6: Datos de ejemplo para los plugins de demostración
- **Estado:** ✅ Implementada
- **Points:** 3
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Nuevo seeder de datos de negocio (no solo usuario admin) que carga registros de ejemplo para las instancias de `persons` (`clients`, `distributors`, `ophthalmologists`), `comments`, `sales`, `orders`, `invoices`, `optometries`, `contact_lenses`, `brands` y `manufacturers`
  - ✅ Volumen mínimo: 200 personas — 200 `clients`, 25 `distributors`, 100 `ophthalmologists` (números exactos acordados con el usuario) — con comentarios/pedidos/ventas/facturas y fichas asociadas a todas de ellas, suficiente para una demo en vivo realista
  - ✅ Datos coherentes entre sí: `orders` (Pedidos, 300) siempre con `id_distributor` real; `invoices` (~270, 90% de `orders`) siempre con `id_order` real e `issue_date >= order_date`; `sales` (Ventas, 250) siempre con `id_client` real; `optometries`/`contact_lenses` cubren el 100% de los `clients`; marcas, fabricantes y fechas (últimos 2 años) plausibles
  - ✅ Seeder idempotente: "todo o nada por grupo" — si un grupo ya tiene registros, se salta entero y sus ids se reutilizan para los grupos dependientes en vez de duplicar
- **Nota 1 — `sales` es la antigua `purchases`, ya no descartada:** durante la preparación de esta story se descubrió que la instancia `purchases`, que STORY 10.4 daba por descartada, seguía existiendo en la BD real del usuario como trabajo en curso propio: la había renombrado a `sales` (Ventas a cliente, `id_client` opcional) como segunda instancia real y deliberada de `orders`, distinta de `orders` (Pedidos a distribuidor, `id_distributor` obligatoria, la única que factura). El alcance de esta story se amplió para sembrar ambas.
- **Nota 2 — limpieza puntual previa a la siembra:** antes de sembrar se vaciaron por completo `plugin_entity_data` (0 filas reales) y `plugin_extension_data` (46 filas: fixtures de tests de integración filtradas a la BD real desde STORY 10.4 y 1 fila huérfana del slug pre-rename `optometry` de STORY 10.5) — operación puntual pedida por el usuario, no automatizada dentro del seeder.
- **Nota 3 — apellidos únicos garantizados:** pedido explícito del usuario durante la revisión del plan — `clients` (200) y `ophthalmologists` (100) generan `surnames` sin repetidos dentro de cada grupo (par de apellidos muestreado sin reemplazo), lo que además garantiza `name + surnames` único como consecuencia.
- **Nota 4 — correlación "cliente VIP":** cada cliente recibe un único tier de actividad (alto 40% / medio 30% / bajo 30%) compartido entre `optometries`, `contact_lenses` y `comments` — los mismos clientes concentran más fichas y más comentarios a la vez, en vez de 3 repartos independientes.
- **IA Usage:** Diseño técnico validado con un agente Plan (arquitectura de clases, algoritmo de idempotencia con carga de ids existentes, generación de datos realistas) + generación de datos de ejemplo realistas en español (nombres, DNI con letra de control real, direcciones, notas) + script de seeder idempotente
- **Dependencias:** STORY 10.4, STORY 10.5
- **Blockers:** Ninguno

---

## EPIC 11: Cierre Formal y Exhaustivo del MVP (Fase 11)

Objetivo: Cerrar el proyecto con el rigor de un entregable de TFM: código limpio y auditado, documentación coherente con la implementación real, guion de defensa verificado, y una verificación funcional E2E completa.

### STORY 11.1: Auditoría de código limpio
- **Points:** 5
- **Priority:** MUST
- **Type:** QA
- **Criteria:**
  - ✅ Pase de SonarQube (`skills/review-sonarqube-clean-code/SKILL.md`) sobre backend y frontend, sin hallazgos críticos/bloqueantes pendientes
  - ✅ Eliminación de código muerto y TODOs obsoletos
  - ✅ Revisión y limpieza de decisiones técnicas superadas marcadas como vigentes por error en `decisiones-tecnicas.md`
  - ✅ Sin ningún rastro de `clients` que debiera ser `persons` tras STORY 10.2 (código, nombres de tabla/columna, comentarios, tests)
  - ✅ Naming consistente revisado en todo el proyecto (claves técnicas en inglés, labels en español, sin mezcla, según convención de `AGENTS.md`)
- **IA Usage:** Auditoría asistida de código con SonarQube + búsqueda de rastros obsoletos + revisión de naming
- **Dependencias:** Todas las stories MUST de EPIC 0-10
- **Blockers:** Ninguno

### STORY 11.2: Verificación funcional E2E final
- **Points:** 5
- **Priority:** MUST
- **Type:** QA
- **Criteria:**
  - ✅ Valoración previa de cobertura: antes de ejecutar el checklist, se valora si existen todos los tests unitarios, de integración y E2E necesarios para dar por cerrado el MVP. Cualquier test nuevo detectado como necesario se consulta con el usuario (preguntas + explicación) antes de generarlo; nunca se decide unilateralmente.
  - ✅ Checklist de verificación funcional E2E ejecutado y documentado: login (usuario normal + admin + botones de acceso rápido en debug) → crear/editar/eliminar persona → crear pedido/factura relacionados → gestionar plugins (activar/desactivar/desinstalar) → ficha de optometría/lentillas → todo sin errores en el runtime real Apache+PHP
  - ✅ Exportar CSV, búsqueda/filtro en tablas y selector de idioma visible quedan fuera de este checklist por no existir en el MVP: son funcionalidad reservada a EPIC A1 post-MVP (STORY A1.1 y STORY A1.7 respectivamente) y se documentan como tal, no como incidencia de esta story
  - ✅ Suite de tests (backend + frontend + E2E) ejecutada en verde como parte del checklist final, incluyendo los tests nuevos que resulten de la valoración previa (tests huérfanos registrados en el runner, cobertura del seeder de datos de negocio, specs E2E nuevos de flujos de negocio sin cubrir)
  - ✅ Cualquier incidencia detectada durante la verificación queda corregida o documentada explícitamente como limitación conocida
- **IA Usage:** Valoración asistida de cobertura de tests + generación del checklist E2E + generación de tests/specs nuevos consultados con el usuario + ejecución asistida de la suite de tests
- **Dependencias:** STORY 10.1, STORY 10.2, STORY 10.3, STORY 10.4, STORY 10.5, STORY 10.6, STORY 11.1
- **Blockers:** Ninguno

### STORY 11.3: Auditoría de coherencia de documentación
- **Points:** 5
- **Priority:** MUST
- **Type:** Documentacion
- **Criteria:**
  - ✅ Cada carpeta de `docs/` (01-architecture, 03-api, 04-plugins, 05-frontend, 07-security, 08-operations, 09-history, 10-productivity, 11-backlog) revisada contra el código real, sin contradicciones ni referencias rotas
  - ✅ Verificación de que la numeración de EPICs/STORIES es coherente entre `backlog.md`, `roadmap.md`, `MASTER-brief.md` e `ia-productivity-template.md`, sin restos de renombrados anteriores
  - ✅ `docs/03-api/endpoints.md` refleja todos los endpoints reales, incluyendo los nuevos de EPIC 10
  - ✅ `docs/04-plugins` refleja el nuevo modelo `slug`/manifest_json->>'name' y los plugins de demostración creados
  - ✅ `docs/10-productivity/sesion.md` refleja el estado final real del proyecto al cierre
- **IA Usage:** Auditoría cruzada de documentación vs código real + generación de matriz de discrepancias
- **Dependencias:** STORY 11.1
- **Blockers:** Ninguno

### STORY 11.4: Guion de defensa del TFM
- **Points:** 3
- **Priority:** MUST
- **Type:** Documentacion
- **Criteria:**
  - ✅ Guion de defensa del TFM redactado (en `docs/09-history` o ubicación equivalente), usando solo funcionalidades y métricas verificadas, no proyecciones
  - ✅ Flujo de demo en vivo documentado paso a paso, coherente con el checklist de STORY 11.4
  - ✅ `ia-productivity-analysis.md`/`productividad.md` completo con métricas reales de todas las stories cerradas del proyecto, no solo un subconjunto
- **IA Usage:** Borrador asistido del guion de defensa + consolidación de métricas de productividad
- **Dependencias:** STORY 11.3
- **Blockers:** Ninguno

---

## EPIC A1: Ajustes Finos de UI/UX (Adición post-MVP)

Objetivo: Cerrar brechas de experiencia de usuario y calidad frontend detectadas tras EPIC 9: internacionalización real, búsqueda en tablas, rendimiento percibido, consistencia visual, accesibilidad y operaciones avanzadas de tabla/CRUD.

### STORY A1.1: Internacionalización real con selector de idioma
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Selector de idioma visible en la zona `shell-menu-config` del shell, como sub-zona hermana de los controles de tema (`shell-menu-config-theme`) y usuario (`shell-menu-config-user`)
  - ✅ Idiomas soportados: español, inglés, gallego y portugués
  - ✅ `I18nModel.js` ampliado con las claves necesarias para cubrir las pantallas principales (Login, EntityList, EntityEdit, PluginManager, perfil, gestión de usuarios, configuración visual), no solo el subconjunto parcial actual
  - ✅ Preferencia de idioma persistida en una cookie (no localStorage, no columna en `users`)
  - ✅ Si no existe la cookie, el idioma inicial se determina a partir de `navigator.language`, mapeando al idioma soportado más cercano
  - ✅ Si `navigator.language` no coincide con ningún idioma soportado, se aplica español por defecto
  - ✅ Cambiar el idioma aplica la traducción en caliente en toda la interfaz visible, sin recargar la página
  - ✅ Cambiar el idioma actualiza la cookie para que la preferencia persista en la siguiente visita
  - ✅ Se define un patrón reutilizable para campos de contenido editables por el usuario en varios idiomas (no textos fijos de interfaz): columnas JSONB `<campo>_i18n` con objeto `{es, en, gl, pt}`, resolviendo el valor a mostrar según el idioma activo con fallback a español si falta la traducción para ese idioma
- **IA Usage:** Traducción asistida es→en/gl/pt de claves existentes y nuevas + wiring del selector, lectura de `navigator.language` y persistencia en cookie + diseño del patrón de columnas `*_i18n` reutilizable
- **Dependencias:** STORY 9.5, STORY 9.7
- **Blockers:** Ninguno

### STORY A1.2: Búsqueda server-side en tablas de entity
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Fullstack
- **Criteria:**
  - ✅ Cabecera de filtro sobre el grid de `DynamicTable`/`EntityList` con: combobox que lista los campos de la entidad activa, input de texto para el valor y botón con icono de lupa para ejecutar
  - ✅ Al pulsar el botón de lupa (o Enter en el input), se solicita el listado incluyendo `?field=<campo>&filter=<valor>`
  - ✅ El endpoint de listado paginado (`EntityController`/`EntityService`/`GenericRepository::paginate`) acepta esos parámetros y aplica coincidencia "contiene" case-insensitive (`ILIKE '%valor%'`) sobre el campo elegido
  - ✅ El nombre de campo recibido se valida contra el schema de la entidad antes de construir la consulta SQL, con el mismo criterio de seguridad ya aplicado al parámetro `sort`
  - ✅ El filtro aplicado (campo + valor) se refleja en el hash de la URL, compartible y recargable, consistente con el mapa hash de STORY 9.6
  - ✅ Al aplicar un nuevo filtro, la paginación vuelve a la página 1
  - ✅ Limpiar el input (o un botón de limpiar) retorna el listado sin filtro
- **IA Usage:** Diseño de la cabecera de filtro + extensión de `GenericRepository::paginate`/`EntityController` con validación de campo + wiring del estado del filtro al hash de la URL
- **Dependencias:** STORY 3.9, STORY 9.6
- **Blockers:** Ninguno

### STORY A1.3: Documentación funcional WYSIWYG y cobertura real de ThemeModel
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Frontend / Documentacion
- **Criteria:**
  - ✅ Documento funcional unico del flujo WYSIWYG actual: origen de preferencias, previsualizacion en tiempo real, persistencia local/remota y aplicacion en shell/componentes
  - ✅ Matriz trazable opcion -> implementacion para todas las claves de `UI_THEME_SCHEMA` en `ThemeModel.js`
  - ✅ Inventario de opciones ya operativas en runtime (con referencia a archivo/modulo que las consume)
  - ✅ Inventario de opciones definidas en `ThemeModel.js` que aun no se usan en UI o no tienen efecto visual completo
  - ✅ Para cada opcion no usada: estado (`sin UI`, `UI parcial`, `runtime parcial`), impacto esperado y propuesta de activacion
  - ✅ Actualizacion de docs de frontend para evitar divergencia entre lo "definido" y lo "realmente cableado"
  - ✅ Checklist de verificacion manual en navegador para validar que cada opcion aplicada en Configuracion UI se refleja inmediatamente (WYSIWYG)
- **IA Usage:** Auditoria cruzada de ThemeModel/UI/runtime + generacion de matriz de cobertura funcional
- **Dependencias:** STORY 9.1, STORY 9.7, STORY 9.9
- **Blockers:** Ninguno

### STORY A1.4: Optimización de tiempos de respuesta y construcción del front-end
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Medición de la línea base actual del tiempo de arranque de la SPA (desde carga de `index.html` hasta interfaz interactiva), usando Performance API del navegador o Lighthouse, documentada
  - ✅ Medición de la línea base actual del tiempo de carga de listados/tablas grandes (`EntityList`/`DynamicTable`) con datos reales
  - ✅ El componente `Skeleton.js` (ya definido en `ComponentFactory` desde STORY 9.3 pero sin uso en ningún flujo) se cablea en la construcción inicial de página, mostrándose mientras se resuelve el primer render
  - ✅ El componente `Skeleton.js` se cablea también en la carga de datos de `DynamicTable`/`EntityList`, sustituyendo el "Cargando…" genérico mientras llega la respuesta del backend
  - ✅ Revisión del orden de carga de scripts/módulos del bootstrap (`AppController.js`, `ShellLayout.js`, `RouteMapController.js`) para identificar y eliminar bloqueos innecesarios antes del primer render
  - ✅ Comparativa antes/después de las métricas base, documentada
- **IA Usage:** Análisis de performance con Performance API/Lighthouse + wiring de `Skeleton.js` en flujos de carga + propuestas de reordenación del bootstrap
- **Dependencias:** STORY 9.3, STORY 9.4, STORY 9.7
- **Blockers:** Ninguno

### STORY A1.5: Revisión y consistencia de animaciones/transiciones CSS
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Auditoría de elementos interactivos (botones, links, inputs, filas de tabla, tabs, dropdowns) sin ninguna transición en hover/focus/aparición
  - ✅ Auditoría de elementos que ya tienen transición pero con duración/easing inconsistente respecto al resto de la interfaz
  - ✅ Definición de tokens comunes de duración y easing, alineados con la configuración de Tailwind del proyecto
  - ✅ Aplicación de los tokens comunes a botones, modales, tabs, dropdowns y notificaciones globales
  - ✅ Verificación manual en navegador de que las transiciones aplicadas son suaves (sin saltos ni parpadeos) en las páginas ya cubiertas por STORY 9.8: Login, EntityList, EntityEdit, PluginManager, UserConfig
- **IA Usage:** Auditoría de clases `transition`/`duration`/`ease` existentes + propuesta de tokens comunes + aplicación donde falte
- **Dependencias:** STORY 9.7, STORY 9.8
- **Blockers:** Ninguno

### STORY A1.6: Accesibilidad WCAG y Auditoría de Testing UI
- **Points:** 8
- **Priority:** SHOULD
- **Type:** Frontend / QA
- **Criteria:**
  - ✅ Notificaciones y banners de `UiResilienceService` usan `role="status"` o `role="alert"` según severidad, con `aria-live="polite"` o `"assertive"` correspondiente
  - ✅ Landmarks semánticos añadidos al shell (`role="main"`, `role="navigation"`, `role="banner"`) en `ShellLayout.js`
  - ✅ Componentes interactivos existentes (`Modal`, `Tabs`, dropdown de `UserMenu`) revisados y corregidos para exponer roles/estados ARIA correctos (`aria-expanded`, `aria-haspopup`, `aria-modal`, etc.) de forma consistente
  - ✅ Integración de una herramienta de auditoría automatizada de accesibilidad (axe-core o pa11y) ejecutable sobre las páginas principales: Login, EntityList, EntityEdit, PluginManager, perfil y gestión de usuarios
  - ✅ Umbral mínimo definido: cero incidencias de severidad "critical" o "serious" en el reporte de la herramienta
  - ✅ Ejecución de la auditoría documentada y reproducible sobre el runtime Apache+PHP same-origin del proyecto
  - ✅ Reporte de hallazgos con su resolución o justificación de excepción documentado
- **IA Usage:** Auditoría de componentes existentes + inyección de atributos ARIA + bootstrap de axe-core/pa11y + triage y documentación de hallazgos
- **Dependencias:** STORY 9.7, STORY 9.8
- **Blockers:** Ninguno

### STORY A1.7: Funcionalidad Avanzada de Tablas y CRUD Completo
- **Points:** 8
- **Priority:** SHOULD
- **Type:** Fullstack
- **Criteria:**
  - ✅ Checkbox de selección por fila en `DynamicTable`, con opción de "seleccionar todas" las filas de la página actual
  - ✅ Barra de acciones en lote visible cuando hay una o más filas seleccionadas, con al menos la acción "eliminar en lote"
  - ✅ Confirmación modal consistente (STORY 9.8) antes de ejecutar cualquier acción en lote destructiva
  - ✅ Botón "Exportar CSV" en la cabecera de la tabla que descarga las filas actualmente visibles/filtradas
  - ✅ El CSV exportado usa codificación UTF-8 con BOM (compatibilidad con Excel) y cabeceras de columna traducidas según el idioma activo (STORY A1.1)
  - ✅ Acción "Eliminar" añadida a `EntityList` (hoy inexistente) con confirmación modal y feedback de éxito/error
  - ✅ Acción "Eliminar" añadida a `UserManager` (hoy inexistente en UI, aunque el soft delete ya existe en backend desde EPIC 8) con confirmación modal y feedback de éxito/error
  - ✅ Acción "Desinstalar" añadida a `PluginManager` para plugins inactivos, con confirmación modal
  - ✅ El backend impide desinstalar un plugin activo, exigiendo desactivarlo primero
  - ✅ El backend limpia de forma segura los registros asociados (`plugins`, etc.) al desinstalar
- **IA Usage:** Diseño de selección múltiple/bulk actions + export CSV + wiring de acciones de eliminación/desinstalación + confirmaciones + validaciones backend
- **Dependencias:** STORY 3.9, STORY 6.4, STORY 8.2, STORY 9.8, STORY A1.1, STORY A1.2
- **Blockers:** Confirmar si el backend ya soporta DELETE real de entidad y desinstalación de plugin, o si hay que implementarlo desde cero (revisar `EntityController`/`EntityService` y `PluginRepository`)

---

## EPIC A2: Operación Técnica y Observabilidad (Adición post-MVP)

Objetivo: Sistema observable con health checks, preparado para deployment en RPi5 con backup automatizado y hardening básico de seguridad.

### STORY A2.1: Endpoint de health técnico del sistema
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

### STORY A2.2: Backup automático de base de datos
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

### STORY A2.3: Docker Compose para deployment en RPi5
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

### STORY A2.4: Hardening básico de seguridad (headers + rate limiting)
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

## EPIC A3: Marketplace de Plugins (Adición post-MVP)

Objetivo: Repositorio central de plugins publicados, browseable e instalable desde la UI de Xestify.

### STORY A3.1: Schema y modelo de datos del marketplace
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

### STORY A3.2: API de marketplace (browse, search, detalle)
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
- **Dependencias:** STORY A3.1, STORY 4.1, STORY 4.5
- **Blockers:** Ninguno

### STORY A3.3: Frontend - UI de marketplace en PluginManager
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
- **Dependencias:** STORY A3.2, STORY 6.4
- **Blockers:** Ninguno

### STORY A3.4: Publicación de plugin al marketplace
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Backend
- **Criteria:**
  - ✅ POST `/api/v1/marketplace/publish` — registra plugin desde zip o directorio local (solo admin)
  - ✅ Valida estructura de plugin (manifest.json, Hooks.php)
  - ✅ Calcula checksum del paquete para verificación de integridad
  - ✅ Tests: publicación válida, inválida por manifest incorrecto
- **IA Usage:** Lógica de validación + checksum + tests
- **Dependencias:** STORY A3.1, STORY 4.6
- **Blockers:** Ninguno

---

## EPIC A4: QA y Calidad (Adición post-MVP)

Objetivo: Suite de tests completa, automatización CI y coverage mínimo establecido para el proyecto.

### STORY A4.1: Suite de tests de integración E2E backend
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

### STORY A4.2: Coverage mínimo 80% en servicios core
- **Points:** 5
- **Priority:** MUST
- **Type:** Testing
- **Criteria:**
  - ✅ Tests unitarios para: `ValidationService`, `EntityService`, `JwtService`, `HookDispatcher`, `AuditService`
  - ✅ Coverage medido con script de conteo de casos (sin PHPUnit, compatible con setup actual)
  - ✅ Cada servicio tiene al menos: happy path, edge case, error case
  - ✅ Tabla de coverage documentada en `docs/10-productivity/sesion.md`
- **IA Usage:** Generación de casos de test por método
- **Dependencias:** STORY 3.1, STORY 3.2, STORY 4.2, STORY A5.2
- **Blockers:** Ninguno

### STORY A4.3: GitHub Actions CI pipeline
- **Points:** 3
- **Priority:** SHOULD
- **Type:** DevOps
- **Criteria:**
  - ✅ Workflow `.github/workflows/ci.yml` ejecuta tests en cada push/PR
  - ✅ Steps: checkout, setup PHP 8.1, setup PostgreSQL, run migrations, run tests
  - ✅ Falla el pipeline si algún test falla
  - ✅ Badge de CI en README
- **IA Usage:** Workflow YAML completo + setup actions
- **Dependencias:** STORY A4.1
- **Blockers:** Acceso a secrets de PostgreSQL en GitHub Actions

### STORY A4.4: Tests de rendimiento básicos (API response times)
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

## EPIC A5: Auditoría Funcional (Adición post-MVP)

Objetivo: Trazabilidad de acciones críticas sobre configuración, usuarios y plugins.

### STORY A5.1: Crear tabla `audit_logs` y migración
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

### STORY A5.2: Crear AuditService y helper de registro
- **Points:** 3
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Servicio `AuditService::log()` reutilizable
  - ✅ Registro de payload seguro (sin secretos/sin password_hash)
  - ✅ Tipado estricto y tests unitarios de inserción
- **IA Usage:** Boilerplate de servicio + tests + sanitización base de payload
- **Dependencias:** STORY A5.1, STORY 0.2
- **Blockers:** Definir lista de campos sensibles a excluir

### STORY A5.3: Auditar acciones de usuarios y configuración
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Se audita crear/editar/desactivar usuario
  - ✅ Se audita cambios de configuración global
  - ✅ Se audita activar/desactivar plugin
  - ✅ Cada registro incluye `who`, `what`, `when`, `where`
- **IA Usage:** Inyección de hooks de auditoría en controladores/servicios
- **Dependencias:** STORY A5.2, STORY 7.1, STORY 6.2, STORY A2.2 (o equivalentes)
- **Blockers:** Disponibilidad de endpoints de gestión

### STORY A5.4: Endpoint y vista básica de auditoría (solo admin)
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Fullstack
- **Criteria:**
  - ✅ GET `/api/v1/audit-logs` con filtros (fecha, usuario, recurso)
  - ✅ Tabla frontend de auditoría con paginación
  - ✅ Solo visible para rol admin
- **IA Usage:** Query con filtros + página frontend de lectura
- **Dependencias:** STORY A5.3, STORY 5.3
- **Blockers:** Ninguno

---

## EPIC A6: Matriz de Permisos Fina (Adición post-MVP)

Objetivo: Permisos granulares por recurso/acción, más allá de admin/no-admin.

### STORY A6.1: Modelo de permisos granular en base de datos
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

### STORY A6.2: AuthorizationService con permisos por acción
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Método `can(user, permission)` con verificación real contra DB
  - ✅ Cache opcional en request para reducir queries repetidas
  - ✅ Tests allow/deny por rol
- **IA Usage:** Implementación del servicio + tests de matriz
- **Dependencias:** STORY A6.1, STORY 1.4
- **Blockers:** Ninguno

### STORY A6.3: Enforcement en endpoints críticos
- **Points:** 5
- **Priority:** MUST
- **Type:** Backend
- **Criteria:**
  - ✅ Endpoints de usuarios/config/plugins validan permisos finos
  - ✅ Respuesta `403` consistente en denegación
  - ✅ Logs de denegación integrados con auditoría (A5)
- **IA Usage:** Inserción de guardas de autorización + tests de integración
- **Dependencias:** STORY A6.2, STORY A5.2
- **Blockers:** Mapa endpoint → permiso

### STORY A6.4: UI condicional por permisos
- **Points:** 3
- **Priority:** SHOULD
- **Type:** Frontend
- **Criteria:**
  - ✅ Ocultar acciones no permitidas (botones/links/secciones)
  - ✅ Mostrar mensaje informativo cuando falte permiso
  - ✅ Sin romper navegación existente
- **IA Usage:** Guards en renderizado frontend
- **Dependencias:** STORY A6.3, STORY 5.x
- **Blockers:** Endpoint/mechanismo para exponer permisos efectivos al frontend

---

## EPIC A10: Relaciones Avanzadas — `has_many` / `has_one` (Adición post-MVP)

Objetivo: completar el modelo de relaciones documentado en `DECISION 6`
(`docs/09-history/decisiones-tecnicas.md`) más allá de `belongs_to` — el único
tipo con implementación real hoy, tanto en la configuración (`PluginConfig`
fuerza siempre `type: "belongs_to"` al guardar) como en la visibilidad del
campo en `EntityEdit` (que filtra explícitamente `type === 'belongs_to'` e
ignora en silencio cualquier otro valor). Permitir declarar y configurar
relaciones `has_many`/`has_one` desde `PluginConfig`, y darles visibilidad
real en `EntityEdit`/`EntityList` análoga a la ya construida para
`belongs_to`.

### STORY A10.1: Configuración de relaciones `has_many`/`has_one` en `PluginConfig`
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Fullstack
- **Criteria:**
  - El selector de `type` en el grid "Relaciones" de `PluginConfig` deja de
    estar fijo a `belongs_to`; permite elegir entre `belongs_to`/`has_many`/`has_one`
  - Validación de `target_entity`/`target_field` adaptada a la dirección de
    cada tipo: para `has_many`/`has_one` la FK vive en el registro *destino*,
    no en el que declara la relación (al revés que `belongs_to`), por lo que
    `target_field` debe restringirse a un campo del propio esquema en vez de
    a las `identities` de la entidad destino — semántica exacta a cerrar en
    el diseño de la story
  - Persistencia en `schema_json` sin romper compatibilidad con las
    relaciones `belongs_to` ya existentes ni con `PluginRelationsConfigTest.php`
- **IA Usage:** Extensión del grid "Relaciones" existente + validación por tipo + tests
- **Dependencias:** STORY 10.3 §8 (grid "Relaciones", `PluginConfigService.php`)
- **Blockers:** Cerrar la semántica exacta de `target_field` para las direcciones inversas

### STORY A10.2: Visibilidad de `has_many`/`has_one` en `EntityEdit`
- **Points:** 5
- **Priority:** SHOULD
- **Type:** Fullstack
- **Criteria:**
  - `DynamicForm`/`EntityEdit` dejan de ignorar en silencio las relaciones
    `type !== 'belongs_to'` y en su lugar renderizan la vista adecuada para
    cada dirección
  - Para `has_many`, el registro origen muestra una vista de "múltiples
    registros relacionados" editable/asignable (más allá de la tab de solo
    lectura ya existente para la relación inversa, STORY 10.3 §9)
  - Para `has_one`, se resuelve y expone el único registro relacionado, con
    opción de asignar/desasignar
- **IA Usage:** Nuevo componente de asignación múltiple/única + wiring en EntityEdit + tests
- **Dependencias:** STORY A10.1
- **Blockers:** Ninguno

---

## 📊 Resumen del Backlog Académico (MVP: EPIC 0-11 · post-MVP: A1-A6, A10)

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
- Subsistema de plugins y ciclo de vida
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

1. **Lee primero:** [MASTER-brief.md](../09-history/MASTER-brief.md) - scope reducido, timeline, entregas
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
- **OUT OF SCOPE para Master:** A1 (Ajustes finos de UI/UX), A2 (Operación técnica), A3 (Marketplace), A4 (QA y calidad), A5 (Auditoría funcional), A6 (Matriz de permisos), A7 (Hardening sesiones), A8 (Panel health técnico), A9 (Export/import config).

---

Referencia: Ver [MASTER-brief.md](../09-history/MASTER-brief.md) para scope académico completo y estrategia de demostración.
