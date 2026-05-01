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

### ✅ IN SCOPE (40 puntos MUST)
- EPIC 0: Setup técnico
- EPIC 1: Autenticación
- EPIC 2: Modelo de datos
- EPIC 3: CRUD dinámico
- EPIC 4: Plugins backend (básico)
- EPIC 5: Frontend base

### ❌ OUT OF SCOPE (para futuro/thesis)
- EPIC 6: Extensiones complejas
- EPIC 7: Actualizaciones avanzadas
- EPIC 8-10: Operación, marketplace, QA exhaustivo

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
  - ✅ Obtiene schema vigente
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
  - ✅ GET `/api/entities/{slug}/schema` → schema_json
  - ✅ GET `/api/entities/{slug}/records` → listado paginado
  - ✅ POST `/api/entities/{slug}/records` → crear registro
  - ✅ GET `/api/entities/{slug}/records/{id}` → registro por id
  - ✅ PUT `/api/entities/{slug}/records/{id}` → actualizar
  - ✅ DELETE `/api/entities/{slug}/records/{id}` → soft delete
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

### STORY 4.4: Crear plugin de entidad base (entity_client)
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

- **Blockers:** Ninguno

---

## 📊 Resumen del Backlog Académico (EPIC 0-5 Only)

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
- **OUT OF SCOPE para Master:** EPIC 6-10 (extensiones avanzadas, marketplace, operación RPi5 real, QA exhaustivo).

---

Referencia: Ver [MASTER-brief.md](MASTER-brief.md) para scope académico completo y estrategia de demostración.
