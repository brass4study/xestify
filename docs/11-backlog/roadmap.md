# Roadmap de Implementación - Xestify

> **Última actualización:** 2026-08-10
> **Estado del proyecto:** **STORY 9.8 incluida** — EPIC 8 cerrada, EPIC 9 en progreso y siguiente foco `STORY 9.9`

---

## 1. Objetivo del roadmap

Este roadmap resume la evolución real de Xestify y traduce el backlog vigente a
una hoja de ruta ejecutable, incremental y útil tanto para seguimiento
ejecutivo como para implementación técnica.

Su objetivo es:

- reflejar el estado real ya alcanzado en el repositorio
- conservar las decisiones técnicas que hoy ya están cerradas
- ordenar las fases pendientes sin arrastrar planes obsoletos
- ofrecer una estimación orientativa por fases, semanas y esfuerzo relativo
- servir de guía para las siguientes decisiones de producto y arquitectura

---

## 2. Resumen de funcionalidades definidas

### Corte funcional actual

El corte funcional vigente del producto queda fijado en:

- `STORY 9.1` completada
- `STORY 9.2` completada
- `STORY 9.3` completada
- `STORY 9.4` completada
- `STORY 9.5` completada
- `STORY 9.6` completada
- `EPIC 8` cerrada
- `EPIC 9` en progreso
- siguiente foco funcional: `STORY 9.9` (documentación de arquitectura frontend y testing UI automatizado)

### Funcionalidades nucleares (Core)

- autenticación JWT y control de acceso por middleware
- pipeline real `Router -> Middleware -> Controller`
- CRUD genérico de entidades dinámicas
- validación contra schema
- persistencia en PostgreSQL con JSONB

### Funcionalidades de extensibilidad

- plugins `entity` como fuente de verdad del catálogo de entidades
- plugins `extension` para tabs, acciones y datos relacionados
- hooks backend (`beforeSave`, `afterSave`, `registerTabs`, `registerActions`)
- lifecycle de plugins
- sync explícito desde disco y update controlado con snapshots

### Funcionalidades de frontend y UX

- frontend dinámico actual para login, listado y edición de entidades
- `PluginManager` básico para listar, activar y desactivar plugins
- nueva gestión de usuarios en el `EPIC 8` y consolidación visual/SPA iniciada en el `EPIC 9`
- shell persistente, librería de componentes, routing SPA con mapa canónico en inglés, resiliencia y UX transversal

### Funcionalidades operativas, seguridad y gobierno

- health técnico, backup y despliegue documentado
- hardening básico y observabilidad
- auditoría funcional
- permisos finos
- marketplace de plugins
- QA/CI y benchmarks

---

## 3. Estrategia de implementacion

### Estado actual resumido

Hoy Xestify ya tiene consolidados:

- autenticación y middleware
- CRUD dinámico de entidades
- catálogo de entidades basado en plugins
- plugins `entity` y `extension`
- `PluginManager` básico
- detección de updates (`7.1`)
- sync/update explícito de plugins con rollback transaccional y snapshots (`7.2`)
- configuración de plugins activos con schema versionado, campos configurables y `target_entity` para extensiones (`7.3`)
- runtime same-origin bajo Apache+PHP

### Decisiones técnicas tomadas

Estas decisiones están cerradas y la implementación futura debe respetarlas:

| Área | Decisión vigente | Impacto |
|------|------------------|---------|
| Backend | PHP 8.1+ nativo | Sin framework, máximo control |
| Contenedor | `Xestify\core\Container` casero | Wiring explícito, sin magia |
| Frontend | Vanilla JS modular + Tailwind generado localmente | Sin bundler; CSS servido como asset estático |
| Runtime web | Apache+PHP same-origin | Frontend, API y assets bajo un único origen |
| Base path frontend | Dinámico | Soporte para raíz `/` o subruta `/xestify/` |
| Catálogo de entidades | `plugins` (`plugin_type='entity'`) | No reintroducir `system_entities` |
| Schema vivo | `plugins.schema_json` | No usar `entity_metadata` como fuente activa |
| Updates de plugins | Explícitos | `sync` no consume updates; `update` sí los aplica |
| Boot de runtime | Ligero | Sin autodiscovery de plugins en cada request |
| Operación local | Apache+PHP + PostgreSQL | Docker/Nginx no son requisito base |
| Routing SPA futuro | Mapa hash canónico en inglés | Ya concretado en `EPIC 9` |

No deben reabrirse como decisiones pendientes:

- elección de framework backend/frontend
- JWT vs sesión como dilema abierto
- Docker/Nginx como runtime principal
- vuelta a `system_entities` o a `entity_metadata` como fuente activa del schema

### Estrategia por fases

La estrategia vigente de implementación es:

- cada fase deja un entregable verificable y demostrable
- no se deben mezclar decisiones históricas ya descartadas con el modelo actual
- el cierre de `EPIC 7` completa el ciclo operativo de plugins
- el siguiente bloque transversal prioritario ya abierto es `EPIC 9` (SPA y sistema UI)
- el MVP académico cierra con `EPIC 9`; ajustes finos de UI/UX, operación técnica, marketplace, QA, auditoría y permisos (`A1`-`A6`) quedan como adiciones post-MVP

---

## 4. Fases de implementacion

### Mapa de fases

| Fase | EPIC | Estado | Observación |
|------|------|--------|-------------|
| 0 | Preparación técnica | ✅ Completada | Base de proyecto y tooling |
| 1 | Autenticación y seguridad base | ✅ Completada | JWT + middleware |
| 2 | Modelo de datos core | ✅ Completada | Migraciones y persistencia base |
| 3 | Motor de entidades dinámicas | ✅ Completada | CRUD genérico + validación |
| 4 | Plugins y hooks backend | ✅ Completada | Loader, lifecycle y hooks |
| 5 | Frontend dinámico base | ✅ Completada | Login + EntityList + EntityEdit |
| 6 | Plugins tipo extension | ✅ Completada | Tabs, acciones y `PluginManager` básico |
| 7 | Actualizaciones y rollback de plugins | ✅ Completada | Ciclo operativo de plugins cerrado (`7.1`-`7.5`) |
| 8 | Gestión de usuarios | ✅ Completada | Perfil propio + administración de usuarios |
| 9 | Sistema UI, shell frontend y arquitectura SPA | 🔄 En progreso | STORY 9.1 a 9.7 cerradas; siguiente foco 9.8 |
| A1 | Ajustes finos de UI/UX | ⏭ Pendiente | i18n, búsqueda en tablas, rendimiento, accesibilidad, CRUD avanzado |
| A2 | Operación técnica y observabilidad | ⏭ Pendiente | Health, backup, despliegue, hardening |
| A3 | Marketplace de plugins | ⏭ Pendiente | Catálogo e instalación desde UI |
| A4 | QA y calidad | ⏭ Pendiente | CI, coverage, E2E y benchmarks |
| A5 | Auditoría funcional | ⏭ Pendiente | Trazabilidad de acciones críticas |
| A6 | Matriz de permisos fina | ⏭ Pendiente | Autorización granular |

### Fase 0 - Preparacion tecnica

**Consolidado**
- estructura de repositorio
- container DI
- router HTTP
- helpers de request/response
- frontend skeleton
- entorno local base

**Dependencias**
- ninguna

### Fase 1 - Core de autenticacion y seguridad base

**Consolidado**
- tabla `users`
- `JwtService`
- `AuthController`
- `AuthMiddleware`

**Dependencias**
- Fase 0

### Fase 2 - Modelo de datos core

**Consolidado**
- persistencia base en PostgreSQL
- migraciones consolidadas
- tablas de plugins, hooks y datos dinámicos

**Nota de trazabilidad**
- el catálogo de entidades vive en `plugins`
- el schema vivo activo está en `plugins.schema_json`

**Dependencias**
- Fase 0

### Fase 3 - Motor de entidades dinamicas

**Consolidado**
- `ValidationService`
- `EntityService`
- `EntityController`
- `Api.js`, `State.js`, `DynamicForm`, `DynamicTable`
- páginas `EntityList` y `EntityEdit`

**Dependencias**
- Fase 1
- Fase 2

### Fase 4 - Sistema de plugins y hooks backend

**Consolidado**
- subsistema backend de plugins (descubrimiento, sync, update, status y hooks)
- `HookDispatcher`
- hooks `beforeSave` y `afterSave`
- lifecycle de plugin
- plugin `clients` de ejemplo

**Dependencias**
- Fase 3

### Fase 5 - Frontend dinamico base

**Consolidado**
- login
- navbar dinámica
- modal reutilizable
- flujo base de listado/edición de entidades
- suites frontend ejecutables

**Dependencias**
- Fase 3

### Fase 6 - Plugins tipo extension

**Consolidado**
- `DynamicTabs`
- hooks `registerTabs` y `registerActions`
- plugin `comments`
- `PluginManager` básico (`6.5`) con listar/activar/desactivar

**Dependencias**
- Fase 4
- Fase 5

### Fase 7 - Actualizaciones, rollback y configuracion de plugins

**Objetivo**
Completar el ciclo de vida operativo de plugins con sync, update, configuración
avanzada y rollback manual.

**Cerrado ya en esta fase**
- `7.1` detección de actualizaciones disponibles
- `7.2` sync/update explícito con schema aditivo, rollback transaccional y snapshots en `plugin_update_history`
- `7.3` página de configuración del plugin activado
- `7.4` rollback manual a versión anterior
- `7.5` UI de actualización y rollback en `PluginManager`

**Criterio real de salida**
- el admin puede detectar, sincronizar, actualizar, configurar y revertir plugins
  desde la UI y backend sin mutaciones implícitas en el boot

**Dependencias**
- Fase 4
- Fase 6

### Fase 8 - Gestión de usuarios

**Objetivo**
Añadir gestión de usuarios en frontend y backend con perfil propio para todos y gestion administrativa para el rol admin.

**Estado real**
- ✅ Story 8.1 implementada: perfil y borrado lógico en `users`
- ✅ Story 8.2 implementada: `UserController` y rutas REST protegidas
- ✅ Story 8.3 implementada: dropdown de usuario en navbar con perfil, gestión de usuarios y cierre de sesión
- ✅ Story 8.4 implementada: página de perfil propio con formulario, validación inline, guardado y sincronización con navbar/estado global
- ✅ Story 8.5 implementada: consolidación de gestión administrativa real en la UI y flujos de usuarios

**Alcance**
- perfil propio (#/profile)
- menu de usuario en navbar con avatar, nombre y accesos rapidos
- gestión de usuarios admin (#/usuarios, #/usuarios/:id)
- acciones admin: editar, cambiar roles, reset password aleatoria visible una sola vez, borrar

**Dependencias**
- cierre operativo de Fase 7
- base de autenticacion de Fase 1

### Fase 9 - Sistema UI, shell frontend y arquitectura SPA

**Objetivo**
Consolidar la capa frontend como una SPA modular, consistente y extensible, con una experiencia WYSIWYG y capacidad de personalizacion visual por cliente.

**Estado real**
- ✅ Story 9.1 implementada: fundamentos visuales, tablas unificadas, tabs alineadas con Ant Design y pipeline Tailwind sin CDN runtime
- ✅ Story 9.2 implementada: contratos de navegación, anatomía de páginas y mapa hash base para la SPA
- ✅ Story 9.3 implementada: librería de componentes UI base consolidada en `ComponentFactory`, con los `modules\*` y `pages\*` alineados a la base común
- ✅ Story 9.4 implementada: frontend reorganizado bajo MVC estricto con `controllers`, `models` y `views` como únicas capas principales, entrypoint en `app.js` en la raíz de `frontend/src/js` y routing centralizado en controladores
- ✅ Story 9.5 implementada: shell persistente, plantillas `login`, `list`, `detail` y `plugin-management`, layouts reutilizables y zonas explícitas para extensiones
- ✅ Story 9.6 implementada: mapa hash bidireccional completo, navegación programática, entrada directa, refresh y back/forward con contexto persistente
- ⏭ Siguiente punto: Story 9.8 para consolidar UX transversal, accesibilidad y microinteracciones frontend

**Alcance**
- fundamentos de diseño, navegación y anatomía de páginas
- librería de componentes UI base
- modularización frontend
- shell SPA
- routing SPA basado en hash (`#/ruta`) como convencion principal, con mapa canónico en inglés y sin aliases legacy
- resiliencia, estado global, feedback e infraestructura transversal
- editor visual WYSIWYG para configurar apariencia sin tocar codigo
- personalizacion basica por cliente: colores y diseño base alineados con imagen de marca
- UX, accesibilidad y microinteracciones
- documentación de arquitectura frontend y testing UI automatizado

**Criterio real de salida**
- frontend coherente, navegable como SPA, con shell estable, sistema UI común
  y base de testing/documentación suficiente para escalar
- configuracion visual por cliente aplicable en tiempo real (WYSIWYG) y persistida

**Dependencias**
- cierre operativo de Fase 7
- base frontend ya consolidada en Fases 3, 5 y 6

### Fase A1 - Ajustes finos de UI/UX

**Objetivo**
Cerrar brechas de experiencia de usuario y calidad frontend detectadas tras EPIC 9.

**Alcance**
- internacionalización real con selector de idioma (es/en/gl/pt)
- búsqueda server-side en tablas de entity
- documentación funcional WYSIWYG y cobertura real de `ThemeModel` (`A1.3`, movida desde `STORY 9.10`)
- optimización de tiempos de respuesta y construcción del frontend (skeleton loaders, bootstrap)
- consistencia de animaciones/transiciones CSS
- accesibilidad WCAG y auditoría de testing UI (ARIA, axe-core/pa11y)
- funcionalidad avanzada de tablas y CRUD completo (bulk actions, export CSV, eliminar registro/usuario, desinstalar plugin)

**Dependencias**
- cierre operativo de Fase 7
- base frontend ya consolidada en Fases 3, 6 y 9

### Fase A2 - Operacion tecnica y observabilidad

**Objetivo**
Preparar operación real del sistema en entornos locales/productivos ligeros.

**Alcance**
- health técnico
- backup y restore
- despliegue documentado en Raspberry Pi 5
- hardening básico

**Nota**
- esta fase debe respetar el runtime canónico actual: Apache+PHP

**Dependencias**
- Fases 1 a 8

### Fase A3 - Marketplace de plugins

**Objetivo**
Permitir descubrir, publicar e instalar plugins desde una experiencia integrada.

**Alcance**
- schema marketplace
- API de catálogo e instalación
- UI de marketplace en `PluginManager`
- publicación de plugins

**Dependencias**
- Fase 7
- Fase 9

### Fase A4 - QA y calidad

**Objetivo**
Completar la base de calidad del proyecto antes de una beta más formal.

**Alcance**
- suites E2E backend
- coverage objetivo en servicios core
- CI en GitHub Actions
- benchmarks y umbrales de rendimiento

**Dependencias**
- Fases 1 a 9
- A2
- A5
- A6

### Fase A5 - Auditoria funcional

**Objetivo**
Trazabilidad de cambios críticos sobre usuarios, configuración y plugins.

**Dependencias**
- Fase 1
- Fase 7
- Fase 9

### Fase A6 - Matriz de permisos fina

**Objetivo**
Permisos granulares por recurso y acción más allá de `admin/no-admin`.

**Dependencias**
- Fase 1
- Fase A5

---

## 5. Priorizacion MVP (lo minimo para producir valor)

### Alcance académico vigente

El backlog vigente considera **in scope del MVP académico**:

- `EPIC 0` a `EPIC 9`

Y deja fuera, por ahora (post-MVP):

- `EPIC A1` (Ajustes finos de UI/UX)
- `EPIC A2` (Operación técnica y observabilidad)
- `EPIC A3` (Marketplace de plugins)
- `EPIC A4` (QA y calidad)
- `EPIC A5` (Auditoría funcional)
- `EPIC A6` (Matriz de permisos fina)
- `A7` — hardening avanzado de sesiones
- `A8` — panel health avanzado post-MVP
- `A9` — export/import de configuración entre entornos

### Corte MVP por fases

| Fase | Estado | Prioridad MVP |
|------|--------|---------------|
| 0 | ✅ Completada | MUST |
| 1 | ✅ Completada | MUST |
| 2 | ✅ Completada | MUST |
| 3 | ✅ Completada | MUST |
| 4 | ✅ Completada | MUST |
| 5 | ✅ Completada | MUST |
| 6 | ✅ Completada | MUST |
| 7 | ✅ Completada | MUST |
| 8 | ✅ Completada | MUST |
| 9 | 🔄 En progreso | SHOULD |
| A1 | ⏭ Pendiente | POST-MVP |
| A2 | ⏭ Pendiente | POST-MVP |
| A3 | ⏭ Pendiente | POST-MVP |
| A4 | ⏭ Pendiente | POST-MVP |
| A5 | ⏭ Pendiente | POST-MVP |
| A6 | ⏭ Pendiente | POST-MVP |

### Estimacion por fases, semanas y tiempo

> Estimación recalculada sobre el backlog vigente. Es una referencia de
> planificación por ventanas y esfuerzo relativo, no un calendario comprometido.

| Fase | EPIC | Estado | Semana(s) orientativas | Puntos aprox. | Prioridad dominante |
|------|------|--------|------------------------|---------------|---------------------|
| 0 | Preparación Técnica | ✅ | Semana 1 | 21 pts | MUST |
| 1 | Core Autenticación y Seguridad | ✅ | Semanas 2-3 | 21 pts | MUST |
| 2 | Modelo de Datos Core | ✅ | Semanas 3-4 | 18 pts | MUST |
| 3 | Motor de Entidades Dinámicas | ✅ | Semanas 5-7 | 43 pts | MUST |
| 4 | Sistema de Plugins y Hooks Backend | ✅ | Semanas 8-10 | 28 pts | MUST |
| 5 | Frontend Dinámico Base | ✅ | Semanas 9-12 | 15 pts | MUST |
| 6 | Plugins tipo Extension | ✅ | Fase 6 | 19 pts | MUST |
| 7 | Actualizaciones de Plugins y Rollback | ✅ | Fase 7 | 21 pts | MUST |
| 8 | Gestión de Usuarios | ✅ | Fase 8 | 19 pts | MUST |
| 9 | Sistema UI, Shell Frontend y Arquitectura SPA | 🔄 | Fase 9 | 38 pts | MUST |
| A1 | Ajustes Finos de UI/UX | ⏭ | Adición post-MVP | 37 pts | POST-MVP |
| A2 | Operación Técnica y Observabilidad | ⏭ | Fase A2 | 12 pts | POST-MVP |
| A3 | Marketplace de Plugins | ⏭ | Fase A3 | 16 pts | POST-MVP |
| A4 | QA y Calidad | ⏭ | Fase A4 | 16 pts | POST-MVP |
| A5 | Auditoría Funcional | ⏭ | Adición post-MVP | 16 pts | POST-MVP |
| A6 | Matriz de Permisos Fina | ⏭ | Adición post-MVP | 18 pts | POST-MVP |

---

## 6. Consideraciones iniciales clave

### Restricciones y principios vigentes

- el runtime base del proyecto es Apache+PHP same-origin
- no deben reintroducirse modelos superados como `system_entities`
- `plugins.schema_json` es la fuente viva del schema
- las operaciones de plugin deben seguir siendo explícitas, no mágicas en boot
- las correcciones puntuales de instalaciones locales no deben convertirse en
  automatismos permanentes del producto
- el `EPIC 9` (SPA y sistema UI) es el bloque transversal prioritario para evitar deriva funcional y visual en frontend

### Riesgos actuales y mitigaciones

| Riesgo | Impacto | Mitigación recomendada |
|--------|---------|------------------------|
| Complejidad creciente del ecosistema de plugins | Puede encarecer updates y rollback | Mantener contratos explícitos, snapshots y tests de integración |
| Retrasar el `EPIC 9` | Puede fragmentar UX y arquitectura frontend | Continuar inmediatamente con 9.3, shell y modularización tras cerrar 9.2 |
| Crecimiento de deuda frontend | Dificulta mantenimiento y testing UI | Consolidar componentes, routing y resiliencia en Fase 9 |
| Posponer observabilidad | Riesgo operativo al acercarse a despliegue real | No retrasar en exceso Fase 9 |

---

## 7. Métricas de seguimiento

- tiempo de alta de una nueva entidad sin tocar lógica de dominio
- número de errores de validación por release
- tiempo medio de sync/update de plugin
- ratio de rollback por fallo de actualización
- latencia p95 de endpoints clave en entorno local
- cobertura de tests en servicios core
- cobertura de tests en flujos UI críticos

---

## 8. Definición de listo por fase (DoD)

Una fase se considera completada cuando:

- deja funcionalidad demostrable en entorno local
- tiene tests automatizados razonables para su flujo agregado
- la documentación relevante del proyecto está alineada
- no introduce deuda crítica de integridad o seguridad
- `docs/10-productivity/sesion.md` refleja el estado real alcanzado

---

## 9. Hitos recomendados

| Hito | Semana orientativa | Descripción | Estado |
|------|--------------------|-------------|--------|
| A | 7 | CRUD dinámico funcional E2E | ✅ |
| B | 10 | Plugins y hooks backend operativos | ✅ |
| C | 14 | Extensions + `PluginManager` básico | ✅ |
| D | 16 | Sync, update, configuración y rollback de plugins | 🔄 |
| E | 18 | Sistema UI, shell SPA y arquitectura frontend consolidada | ⏭ |
| F | 20 | Operación técnica, auditoría y permisos finos (post-MVP: `A2`, `A5`, `A6`) | ⏭ |
| G | 22 | Marketplace instalable desde UI (post-MVP: `A3`) | ⏭ |
| H | 24 | CI verde, coverage suficiente y beta técnica estable (post-MVP: `A4`) | ⏭ |

---

## 10. Próximo paso recomendado

La secuencia recomendada, por fases, es:

1. **Abrir la Fase 8 como siguiente bloque transversal**
   - sistema UI
   - shell SPA
   - routing
   - resiliencia frontend
   - UX y testing UI

3. **Cerrar el MVP con la Fase 9 y luego abordar las adiciones post-MVP**
   - Fase 9 (dentro del MVP)
   - A1-A6 (post-MVP: ajustes finos de UI/UX, operación técnica, marketplace, QA, auditoría y permisos)
