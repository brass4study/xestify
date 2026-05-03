# Roadmap de Implementación - Xestify

> **Última actualización:** 2026-05-02  
> **Estado del proyecto:** EPIC 6 en progreso — EPIC 0-5 completados, Release B aplicado

---

## 1. Objetivo del roadmap

Este documento traduce las funcionalidades definidas en el backlog a un plan de implementación ejecutable, incremental y con control de riesgo.

El enfoque prioriza:

- Entrega temprana de valor funcional (MVP operable).
- Base técnica estable para evolución por plugins.
- Seguridad y mantenibilidad desde el inicio.
- Compatibilidad con despliegue local en Raspberry Pi 5.

---

## 2. Decisiones técnicas tomadas

Todas las decisiones de stack están resueltas. No hay bloqueantes técnicos pendientes.

| Decisión | Elegido | Razón |
|----------|---------|-------|
| Backend | PHP 8.1+ nativo | Máximo control, sin overhead de framework |
| DI Container | Casero (`Xestify\Core\Container`) | Cero magia, control total del ciclo de vida |
| Frontend | Vanilla JS ES2020+ | Sin build step, sin dependencias externas |
| Autenticación | JWT HS256 | Simple, sin sesiones de servidor |
| Base de datos | PostgreSQL + JSONB | Schemas dinámicos sin migraciones por entidad |
| Autoload | Manual (`spl_autoload_register`) | Sin Composer, sin PSR-4 externo |
| Entorno dev | PHP nativo + PostgreSQL local | Sin Docker en desarrollo |
| Despliegue prod | Docker Compose (RPi5) | Definido en STORY 8.3 |

---

## 3. Funcionalidades por área

### Core
- Autenticación y autorización local con roles granulares.
- API REST para entidades dinámicas.
- Motor metadata-driven para schema y validación.
- Persistencia en PostgreSQL con JSONB.
- Cargador de plugins y registro de hooks.
- CRUD genérico de entidades.

### Extensibilidad
- Plugins de tipo `entity` (definen entidades con su schema).
- Plugins de tipo `extension` (inyectan tabs/acciones en entidades existentes).
- Hooks de backend (`beforeSave`, `afterSave`, `registerTabs`, `registerActions`).
- Ciclo de vida completo: install → activate → configure → update → rollback → deactivate.
- Configuración de `custom_fields` por plugin activo desde UI.

### Seguridad y auditoría
- Matriz de permisos granular por recurso+acción.
- Auditoría de acciones críticas (login, cambios de config, plugins).
- Rate limiting en endpoints de auth.
- Headers de seguridad HTTP.

### Operativa
- Health check técnico del sistema.
- Backup automatizado de base de datos.
- Despliegue reproducible con Docker Compose en RPi5.
- Marketplace de plugins (browse, install, publicar).
- CI pipeline con GitHub Actions.

---

## 4. Estado de fases

### ✅ Fase 0 — Preparación técnica (COMPLETADO)

**Objetivo:** Entorno dev reproducible y baseline de arquitectura.

**Completado:**
- Estructura `backend/`, `frontend/`, `docs/`, `tools/`.
- Container DI + Router HTTP + Request/Response helpers.
- Frontend skeleton (index.html, main.js, CSS base).
- Entorno local PHP + PostgreSQL + proxy de desarrollo.

---

### ✅ Fase 1 — Core de autenticación y seguridad (COMPLETADO)

**Objetivo:** Acceso seguro por JWT antes del CRUD dinámico.

**Completado:**
- Tabla `users` + seeder admin.
- `JwtService` (HS256 puro PHP).
- `AuthController` (POST `/api/auth/login`).
- `AuthMiddleware` (valida Bearer token, inyecta `$request->user()`).

---

### ✅ Fase 2 — Modelo de datos Core (COMPLETADO)

**Objetivo:** Tablas PostgreSQL estables con JSONB.

**Completado:**
- Migraciones idempotentes: `entity_metadata`, `entity_data`, `plugins` (catálogo de entidades + extensiones), `plugin_hooks`, `plugin_extension_data`.
- `GenericRepository` (find, all, create, update, soft-delete, restore).

> **Nota:** La tabla `system_entities` fue creada en esta fase pero eliminada en EPIC 6 / Release B (`010_drop_system_entities.sql`). El catálogo de entidades vive ahora en `plugins WHERE plugin_type = 'entity'`.

---

### ✅ Fase 3 — Motor de entidades dinámicas (COMPLETADO)

**Objetivo:** CRUD genérico con validación por schema.

**Completado:**
- `ValidationService` (valida contra schema JSONB, soporte `identities`/`fields`/`custom_fields`/`relations`).
- `EntityService` (orquestación CRUD con hooks).
- `EntityController` (endpoints REST `/api/v1/entities`).
- Frontend: `Api.js`, `State.js`, `DynamicForm.js`, `DynamicTable.js`, páginas `EntityList` y `EntityEdit`.

---

### ✅ Fase 4 — Sistema de plugins y hooks backend (COMPLETADO)

**Objetivo:** Extensibilidad sin modificar el Core.

**Completado:**
- `PluginLoader` (descubre, valida, registra plugins desde disco).
- `HookDispatcher` (hooks con prioridades, `action` y `filter`).
- Hooks `beforeSave`/`afterSave` en `EntityService`.
- Ciclo de vida completo: `onInstall`, `onActivate`, `onDeactivate`.
- Schema extendido: `identities` + `fields` + `custom_fields` + `relations` opcionales.
- Plugin `clients` de ejemplo (tipo `entity`).

---

### 🔄 Fase 5 — Frontend dinámico base (EN PROGRESO)

**Objetivo:** UI funcional para entidades dinámicas.

**Completado:**
- Página Login.
- Navbar dinámica con entidades desde API + sección plugins.
- Integración E2E EntityList + EntityEdit.
- Modal/Dialog reutilizable.
- Estilos CSS responsive + refinamientos de UX (iconos FA, paginación, botones).

**Pendiente:**
- Validar cobertura de tests frontend antes de cerrar EPIC.

---

### 🔄 Fase 6 — Plugins tipo Extension (EN PROGRESO)

**Objetivo:** Soporte para plugins que inyectan tabs y acciones en entidades existentes.

**Completado:**
- `DynamicTabs.js` (frontend, tabs registrables desde plugins).
- Hook `registerTabs` y `registerActions` en `HookDispatcher`.
- **STORY 6.3** — Release B: eliminación de `system_entities`; migración `010_drop_system_entities.sql`; `plugins` como única fuente de verdad.
- **STORY 6.4** — Plugin `comments` (tipo `extension`, target cualquier entidad).

**Pendiente:**
- **STORY 6.5** — Página `PluginManager` (listar, activar, desactivar plugins).

**Criterios de salida:**
- Al abrir un registro, se muestran tabs de extensiones activas.
- `PluginManager` muestra estado de cada plugin.

**Dependencias:** Fase 4 + Fase 5.

---

### ⏭ Fase 7 — Actualizaciones, rollback y configuración de plugins (PENDIENTE)

**Objetivo:** Ciclo de vida completo de plugins con versionado, actualización controlada y configuración de campos por admin.

**Entregables:**
- Detección de plugins desactualizados (versión disco vs. registry).
- Proceso de actualización atómico con migración de schema.
- Rollback manual a versión anterior.
- UI de actualización/rollback en `PluginManager`.
- **Página de configuración de plugin** (`/plugins/{slug}/config`): activar/desactivar `custom_fields` sugeridos y añadir campos libres → genera nueva versión en `entity_metadata`.

**Criterios de salida:**
- Actualización de plugin N→N+1 con registro en DB.
- Rollback funcional si `onUpdate` lanza excepción.
- Admin puede configurar campos del plugin desde UI sin tocar código.

**Dependencias:** Fase 4 + Fase 6.

---

### ⏭ Fase 8 — Operación técnica y observabilidad (PENDIENTE)

**Objetivo:** Sistema observable, desplegable en RPi5 y con hardening básico de seguridad.

**Entregables:**
- GET `/api/v1/system/health` (DB, plugins activos, hooks, uptime, versión).
- Script `tools/backup.php` + endpoint trigger backup (solo admin).
- `docker-compose.yml` con `app-php` + `db-postgres` + `nginx` para RPi5 (arm64).
- Middleware de headers de seguridad + rate limiting en auth (429 ante abuso).

**Criterios de salida:**
- Instalación limpia en RPi5 documentada y repetible.
- Restore de backup exitoso en entorno de prueba.

**Dependencias:** Fases 1-7.

---

### ⏭ Fase A1 — Auditoría funcional (PENDIENTE)

**Objetivo:** Trazabilidad de acciones críticas sobre configuración, usuarios y plugins.

**Entregables:**
- Tabla `audit_logs` + migración.
- `AuditService::log()` con sanitización de payload sensible.
- Hooks de auditoría en acciones críticas (usuarios, config, plugins).
- Endpoint + vista de auditoría para admin.

**Criterios de salida:**
- Cada acción crítica genera registro con `who`, `what`, `when`, `where`.
- Vista de auditoría filtrable por fecha/usuario/recurso.

**Dependencias:** Fase 1 + Fase 7 + Fase 6.

---

### ⏭ Fase A2 — Matriz de permisos fina (PENDIENTE)

**Objetivo:** Permisos granulares por recurso+acción, más allá de admin/no-admin.

**Entregables:**
- Tablas `roles`, `permissions`, `role_permissions` con seed base (admin/operador/lectura).
- `AuthorizationService::can(user, permission)` con cache por request.
- Enforcement en endpoints críticos (403 consistente + log de denegación).
- UI condicional: ocultar acciones según permisos del usuario activo.

**Criterios de salida:**
- Operador no puede gestionar plugins ni usuarios.
- Rol lectura solo puede listar registros.

**Dependencias:** Fase 1 + Fase A1.

---

### ⏭ Fase 9 — Marketplace de plugins (PENDIENTE)

**Objetivo:** Repositorio central de plugins browseable e instalable desde la UI.

**Entregables:**
- Tablas `marketplace_plugins` + `marketplace_plugin_versions`.
- API: browse, search, detalle, install, publicación con checksum.
- UI tab "Marketplace" en `PluginManager` con cards, buscador y feedback de instalación.

**Criterios de salida:**
- Plugin listado en marketplace instalable desde UI en un clic.
- Incompatibilidad de versión rechazada antes de instalar.

**Dependencias:** Fase 7 + Fase 6.

---

### ⏭ Fase 10 — QA y calidad (PENDIENTE)

**Objetivo:** Suite de tests completa, CI automatizado y coverage mínimo establecido.

**Entregables:**
- Tests E2E de integración backend (flujo completo en DB de test).
- Coverage ≥ 80% en servicios core (`ValidationService`, `EntityService`, `JwtService`, `HookDispatcher`, `AuditService`).
- GitHub Actions CI pipeline (PHP 8.1 + PostgreSQL + run tests).
- Script de benchmarks con umbrales p95 por endpoint.

**Criterios de salida:**
- CI verde en cada push/PR.
- p95 login < 200ms, list < 300ms, create < 400ms en entorno local.

**Dependencias:** Fases 1-9 + A1-A2.

---

## 5. Corte MVP — mínimo para producir valor

El MVP funcional incluye Fases 0-7 + A1 + A2. El valor académico y demostrativo no requiere Marketplace ni QA exhaustivo para ser convincente.

| Fase | Estado | Prioridad MVP |
|------|--------|---------------|
| 0-4 | ✅ Completado | MUST |
| 5 | 🔄 En progreso | MUST |
| 6 | ⏭ Pendiente | MUST |
| 7 | ⏭ Pendiente | MUST |
| 8 | ⏭ Pendiente | MUST |
| A1 | ⏭ Pendiente | MUST |
| A2 | ⏭ Pendiente | MUST |
| 9 | ⏭ Pendiente | SHOULD |
| 10 | ⏭ Pendiente | SHOULD |

---

## 6. Hitos

| Hito | Semana | Descripción | Estado |
|------|--------|-------------|--------|
| A | 7 | CRUD dinámico E2E funcional | ✅ |
| B | 10 | Plugins y hooks backend operativos | ✅ |
| C | 14 | Extensions con tabs visibles en UI + PluginManager | ⏭ |
| D | 16 | Update/rollback + configuración de campos desde UI | ⏭ |
| E | 18 | Operación en RPi5 + auditoría + permisos finos | ⏭ |
| F | 20 | Marketplace instalable desde UI | ⏭ |
| G | 24 | CI verde + coverage 80% + beta lista | ⏭ |

---

## 7. Métricas de seguimiento

- Tiempo de alta de nueva entidad sin tocar código de dominio.
- Cantidad de errores de validación por release.
- Tiempo medio de instalación/actualización de plugin.
- Tasa de rollback por fallos de actualización.
- Latencia p95 de endpoints CRUD en RPi5.
- Coverage de tests en servicios core.

---

## 8. Definición de listo por fase (DoD)

Una fase se considera completa cuando:

- Tiene funcionalidad demostrable en entorno local.
- Tiene tests mínimos automatizados del flujo agregado.
- Tiene documentación actualizada en `docs/`.
- No introduce deuda crítica de seguridad o integridad de datos.
- `docs/ia/sesion.md` refleja el estado actualizado.


Este documento traduce las funcionalidades definidas en la documentacion actual a un plan de implementacion ejecutable, incremental y con control de riesgo.

El enfoque prioriza:

- Entrega temprana de valor funcional (MVP operable).
- Base tecnica estable para evolucion por plugins.
- Seguridad y mantenibilidad desde el inicio.
- Compatibilidad con despliegue local en Raspberry Pi 5.

## 2. Resumen de funcionalidades definidas

## Funcionalidades nucleares (Core)

- Autenticacion y autorizacion local.
- API REST para entidades dinamicas.
- Motor metadata-driven para schema y validacion.
- Persistencia en PostgreSQL con JSONB.
- Cargador de plugins y registro de hooks.
- CRUD generico de entidades.

## Funcionalidades de extensibilidad

- Plugins de entidad base.
- Plugins de extension sobre entidad base.
- Hooks de backend y hooks de UI.
- Ciclo de vida de plugins (install/activate/update/deactivate/uninstall).

## Funcionalidades operativas

- Despliegue con contenedores en RPi5.
- Actualizaciones manuales y automaticas de plugins.
- Integridad de paquetes y rollback.
- Auditoria minima de acciones sensibles.

## 3. Estrategia de implementacion

Se propone una estrategia por fases con puertas de calidad (gates):

- Cada fase deja un entregable verificable.
- No se avanza si no se cumplen criterios de aceptacion.
- La complejidad de plugins avanzados se posterga hasta estabilizar Core.

## 4. Fases de implementacion

## Fase 0 - Preparacion tecnica (Semana 1)

Objetivo: dejar entorno de desarrollo reproducible y baseline de arquitectura.

Entregables:

- Estructura inicial de repositorio backend/frontend/docker.
- Convenciones de codigo y versionado.
- Plantillas base de modulos MVC y servicios.
- Pipeline local de calidad (lint/tests basicos).

Criterios de aceptacion:

- El proyecto arranca localmente con un comando estandar.
- Existen scripts de arranque para entorno dev.

Riesgos:

- Divergencia de stack si no se decide framework rapidamente.

## Fase 1 - Core de autenticacion y seguridad minima (Semanas 2-3)

Objetivo: habilitar acceso seguro antes del CRUD dinamico.

Entregables:

- AuthController y middleware de autenticacion.
- Modelo de roles base (admin, operador, lectura).
- Politica de permisos por accion de entidad.
- Registro de auditoria para login y cambios criticos.

Criterios de aceptacion:

- Endpoints protegidos por token/sesion.
- Usuario sin permisos recibe error consistente.

Dependencias:

- Fase 0.

## Fase 2 - Modelo de datos Core + migraciones (Semanas 3-4)

Objetivo: implementar base relacional + JSONB estable.

Entregables:

- Migraciones para entity_metadata, entity_data, plugins, plugin_hooks y plugin_extension_data.
- Repositorios/modelos para operaciones base.
- Indices JSONB y de consulta frecuente.

Criterios de aceptacion:

- Migraciones idempotentes en entorno limpio.
- Insercion y consulta de registros con content JSONB.

Dependencias:

- Fase 0.

## Fase 3 - Motor de entidades dinamicas (Semanas 5-7)

Objetivo: CRUD generico con validacion por schema.

Entregables:

- EntityController y EntityService.
- ValidationService basado en schema_json.
- Endpoints REST de entidades (list/schema/records CRUD).
- Manejo de errores uniforme.

Criterios de aceptacion:

- Se crea una entidad via metadata sin tocar codigo de dominio.
- Payload invalido devuelve VALIDATION_ERROR estructurado.

Dependencias:

- Fase 1 y Fase 2.

## Fase 4 - Sistema de plugins y hooks backend (Semanas 8-10)

Objetivo: habilitar extensibilidad sin modificar Core.

Entregables:

- PluginLoader (descubrir, validar, registrar plugins).
- HookDispatcher con prioridades y control de fallos.
- Implementacion de hooks before/after de entidad.
- Ciclo de vida de plugin en backend.

Criterios de aceptacion:

- Plugin de entidad instalable y activable.
- Hook beforeSave puede bloquear operacion con error controlado.

Dependencias:

- Fase 3.

## Fase 5 - Frontend dinamico base (Semanas 9-12)

Objetivo: UI funcional para entidades dinamicas.

Entregables:

- DynamicForm y DynamicTable.
- Vistas EntityList y EntityEdit.
- Integracion con API REST y manejo de errores de campo.
- Cache basica de metadata en cliente.

Criterios de aceptacion:

- Crear/editar/listar registros de cualquier entidad declarada.
- Errores de validacion visibles por campo.

Dependencias:

- Fase 3.

## Fase 6 - Extensiones de entidad y hooks de UI (Semanas 12-14)

Objetivo: soportar plugins que amplian entidades base.

Entregables:

- DynamicTabs y carga de componentes de extension.
- Hooks registerTabs/registerActions.
- Caso completo: extension_optometria sobre clients.

Criterios de aceptacion:

- Al abrir cliente, se monta pestana de extension si plugin activo.
- Operaciones CRUD de extension respetan owner_id.

Dependencias:

- Fase 4 y Fase 5.

## Fase 7 - Actualizaciones y rollback de plugins (Semanas 14-16)

Objetivo: evolucion segura del ecosistema local.

Entregables:

- UpdateManager (check/download/apply/rollback).
- Validacion de checksum y compatibilidad.
- Registro de historial de actualizaciones.
- Politica de no auto-aplicar versiones major.

Criterios de aceptacion:

- Se actualiza plugin de version N a N+1 con registro.
- Se ejecuta rollback funcional ante fallo controlado.

Dependencias:

- Fase 4.

## Fase 8 - Operacion en RPi5 y hardening inicial (Semanas 16-18)

Objetivo: asegurar que el producto funcione de forma estable en escenario real.

Entregables:

- Docker Compose para app + postgres + scheduler.
- Backups automatizados y prueba de restauracion.
- Hardening basico de red y credenciales.
- Guia operativa de instalacion y mantenimiento.

Criterios de aceptacion:

- Instalacion limpia en RPi5 documentada y repetible.
- Restore de backup exitoso en ambiente de prueba.

Dependencias:

- Fases 1 a 7.

## Fase 9 - Marketplace central minimo viable (Semanas 18-20)

Objetivo: habilitar distribucion de plugins versionados.

Entregables:

- Catalogo de plugins (metadata y versiones).
- Endpoint de consulta de actualizaciones.
- Paquetes versionados para descarga.

Criterios de aceptacion:

- Nodo local detecta version nueva disponible.
- Paquete descargado se puede aplicar en staging.

Dependencias:

- Fase 7.

## Fase 10 - QA integral y beta controlada (Semanas 20-24)

Objetivo: validar estabilidad, seguridad y experiencia de uso.

Entregables:

- Suite de pruebas unitarias/integracion/E2E del flujo principal.
- Test de regresion sobre plugins y hooks.
- Checklist de seguridad y auditoria.
- Beta con 2-3 negocios piloto.

Criterios de aceptacion:

- Flujo principal estable: entidad base + extension + actualizacion.
- Incidencias criticas cerradas antes de release.

Dependencias:

- Fases 1 a 9.

## 5. Priorizacion MVP (lo minimo para producir valor)

Para obtener un MVP util en menor tiempo, el corte recomendado incluye:

- Fase 0 a Fase 6 completas.
- De Fase 7, solo flujo manual de actualizacion + rollback basico.
- Sin marketplace completo; actualizacion desde fuente controlada inicial.

Resultado MVP esperado:

- Negocio puede gestionar al menos 2 entidades dinamicas.
- Puede instalar un plugin de extension sobre cliente.
- Puede actualizar ese plugin manualmente con trazabilidad.

## 6. Consideraciones iniciales clave

## Decisiones tecnicas pendientes (bloqueantes suaves)

1. Framework backend:
- Opcion A: Laravel (productividad alta).
- Opcion B: Symfony (estructura estricta).
- Opcion C: PHP nativo estructurado (maximo control, mas esfuerzo).

2. Framework frontend:
- Opcion A: Vue (simple para componentes dinamicos).
- Opcion B: React (ecosistema amplio).

3. Formato de autentificacion:
- Token JWT o sesion local segun topologia de red y uso real.

4. Contrato formal de schema:
- Definir si se usa JSON Schema estandar completo o subset propio.

## Riesgos tempranos y mitigaciones

1. Riesgo: explosion de complejidad en plugins.
- Mitigacion: reglas estrictas de contrato, plantillas y tests de compatibilidad.

2. Riesgo: regresiones por actualizaciones.
- Mitigacion: staging, checksum, backups y rollback obligatorio.

3. Riesgo: rendimiento degradado por consultas JSONB sin estrategia.
- Mitigacion: indices GIN y consultas observables desde el inicio.

4. Riesgo: acoplamiento accidental Core-negocio.
- Mitigacion: revisiones de arquitectura y prohibicion de logica vertical en Core.

5. Riesgo: seguridad insuficiente en entorno local.
- Mitigacion: hardening base, control de accesos y auditoria activa.

## 7. Definicion de listo por fase (DoD)

Una fase se considera completa cuando:

- Tiene funcionalidad demostrable en entorno local.
- Tiene pruebas minimas automatizadas del flujo agregado.
- Tiene documentacion actualizada en docs/.
- No introduce deuda critica de seguridad o integridad de datos.

## 8. Hitos recomendados

- Hito A (Semana 7): CRUD dinamico funcional de extremo a extremo.
- Hito B (Semana 10): Plugins y hooks backend operativos.
- Hito C (Semana 14): Extensiones de entidad visibles en UI.
- Hito D (Semana 16): Actualizacion y rollback de plugins funcionando.
- Hito E (Semana 24): Beta controlada lista para validacion comercial.

## 9. Metricas iniciales de seguimiento

- Tiempo de alta de nueva entidad sin codigo.
- Cantidad de errores de validacion por release.
- Tiempo medio de instalacion/actualizacion de plugin.
- Tasa de rollback por fallos de actualizacion.
- Latencia p95 de endpoints CRUD en RPi5.

## 10. Proximo paso recomendado

Iniciar inmediatamente Fase 0 y Fase 1 en paralelo ligero:

- Definir stack final (backend/frontend).
- Crear esqueleto de proyecto y autenticacion base.
- Dejar preparada la capa de migraciones para arrancar Fase 2 sin friccion.
