# Brief Académico - Xestify MVP

## Estado actual auditado (2026-08-10)

El corte funcional defendible queda cerrado hasta **STORY 9.6 incluida**. Las
EPIC 0-8 están completadas y la EPIC 9 sigue en progreso. El sistema dispone de
pipeline `Router -> Middleware -> Controller`, entidades y extensiones basadas en
plugins, gestión de usuarios, actualizaciones con rollback y frontend MVC con
shell SPA persistente, layouts reutilizables y routing hash completo.

**Siguiente foco:** STORY 9.7, infraestructura transversal y resiliencia del
frontend. Después quedan UX y testing UI de EPIC 9, operación,
marketplace, QA, auditoría y permisos finos.

## Contexto: Proyecto de Master en Desarrollo con IA

Este documento partió de una hipótesis de ejecución de **4-5 semanas** para
Xestify como proyecto demostrativo de un **Master en Desarrollo Asistido por
IA**. El estado auditado y las métricas reales sustituyen esa estimación inicial.

---

## Objetivo Académico

Demostrar que:
1. Arquitectura modular (plugins, hooks) es viable en timeline acelerado.
2. IA (CodeVibe/Copilot) acelera 1.4-1.6x el desarrollo de sistemas repetitivos.
3. Decisiones arquitectónicas se benefician POCO de IA, pero implementación mucho.
4. Un MVP funcional + documentación de proceso > código perfecto sin contexto.

---

## Alcance Reducido: MVP "Proof of Concept"

### ✅ IN SCOPE (MVP completo)

**EPIC 0 - Preparación técnica**
- ✅ Setup Git + estructura carpetas
- ✅ Entorno local: PHP 8.1+ nativo + PostgreSQL (sin Docker)
- ✅ Container DI casero + Router
- ✅ Frontend skeleton

**EPIC 1 - Autenticación**
- ✅ JWT + tabla users
- ✅ AuthController (login)
- ✅ AuthMiddleware

**EPIC 2 - Datos**
- ✅ Tablas core: users, plugin_entity_data, plugins, plugin_hooks, plugin_extension_data
- ✅ GenericRepository JSONB

**EPIC 3 - CRUD Dinámico**
- ✅ ValidationService (schema custom con identities/fields/custom_fields/relations)
- ✅ EntityService (CRUD + hooks)
- ✅ EntityController (API REST + GET /api/v1/entities con label_singular)
- ✅ DynamicForm + DynamicTable + State.js + Api.js (frontend)

**EPIC 4 - Plugins Backend**
- ✅ PluginLoader + HookDispatcher
- ✅ Plugin `clients` de ejemplo (tipo entity)
- ✅ Hooks beforeSave/afterSave
- ✅ Ciclo de vida completo (onInstall, onActivate, onDeactivate)
- ✅ Schema extendido: identities + fields + custom_fields + relations

**EPIC 5 - Frontend Dinámico Base**
- ✅ Login página
- ✅ Navbar dinámica por entidades + PluginManager link
- ✅ EntityList + EntityEdit con datos reales
- ✅ Modal/Dialog reutilizable
- ✅ Estilos responsive + iconografía Font Awesome

**EPIC 6 - Plugins tipo Extension**
- ✅ DynamicTabs + hooks registerTabs/registerActions
- ✅ Plugin de ejemplo tipo extension (`comments`)
- ✅ Página PluginManager (listar, activar y desactivar)

**EPIC 7 - Actualizaciones, Rollback y Configuración**
- ✅ Detección, sincronización y actualización explícita de plugins
- ✅ Snapshots y rollback manual a versión anterior
- ✅ Página de configuración de plugin desde UI

**EPIC 8 - Gestión de usuarios**
- ✅ Perfil propio y cambio seguro de credenciales
- ✅ Administración de usuarios y borrado lógico
- ✅ Menú de usuario integrado en la navegación

**EPIC 9 - Sistema UI, Shell Frontend y Arquitectura SPA**
- ✅ STORY 9.1-9.6: diseño, navegación, componentes, MVC, shell y routing completo
- ⏭ STORY 9.7-9.9: resiliencia, UX y testing UI

**EPIC 10 - Operación Técnica y Observabilidad**
- ⏭ Health operativo, backup, despliegue y hardening

**EPIC 11 - Marketplace de Plugins** (⏭ pendiente)
**EPIC 12 - QA y Calidad** (⏭ pendiente)

**A1 - Auditoría funcional** (⏭ pendiente)
**A2 - Matriz de permisos fina** (⏭ pendiente)

### ❌ OUT OF SCOPE (thesis posterior)

- A3: Hardening de sesiones (expiración, refresh tokens)
- A4: Panel de health técnico visual
- A5: Exportación/importación de configuración entre entornos

---

## Estructura de Entregables Académicos

### 1. Código Funcional (50% de la nota)
- ✅ Repositorio GitHub con commits claros
- ✅ Demo en vivo: login → gestionar entidad → crear registro → operar plugins
- ✅ Reproducible con Apache+PHP y PostgreSQL mediante la documentación operativa

### 2. Documentación de Proceso (30% de la nota)
- ✅ [ia-productivity-template.md](../10-productivity/ia-productivity-template.md) — análisis de cómo IA aceleró
- ✅ Comparativa: tiempo sin IA vs con IA por tarea
- ✅ Prompts efectivos guardados + análisis
- ✅ Problemas encontrados y soluciones

### 3. Análisis Técnico (20% de la nota)
- ✅ Decisiones arquitectónicas: por qué PHP nativo, Container casero, Vanilla
- ✅ Trade-offs: flexibilidad vs velocidad
- ✅ Lecciones aprendidas sobre plugins y hooks
- ✅ Limitaciones del MVP y rutas futuras

---

## Timeline ejecutado y fase actual

### ✅ Semana 1-2: EPIC 0 + EPIC 1 + EPIC 2 (COMPLETADO)
**Entregable logrado:** Proyecto arranca, login funciona, modelo de datos estable.

### ✅ Semana 3-4: EPIC 3 + EPIC 4 (COMPLETADO)
**Entregable logrado:** CRUD dinámico, plugins con hooks, schema extendido con relations.

### ✅ Semana 4-5: EPIC 5 (COMPLETADO)
**Entregable logrado:** Frontend completo: login → entidades → registros → iconos → responsive.

### ✅ Consolidación posterior: EPIC 6 + EPIC 7 + EPIC 8 (COMPLETADO)
**Entregable logrado:** extensiones, PluginManager, configuración, update/rollback y gestión de usuarios.

### 🔄 Fase actual: EPIC 9 (STORY 9.1-9.6 COMPLETADAS)
**Entregable logrado:** sistema visual, componentes base, MVC frontend estricto,
shell SPA persistente, plantillas de página reutilizables y routing hash completo.

### ⏭ Próximas fases: STORY 9.7-9.9 + EPIC 10-12 + A1 + A2
**Objetivo:** completar resiliencia y UX frontend, operación, permisos,
auditoría, marketplace y QA.

---

## Entregables Finales para Defensa

### 📦 Package 1: Código
```
xestify/
├── backend/                    (PHP nativo + API)
├── frontend/                   (Vanilla JS MVC + Tailwind generado)
├── plugins/                    (entidades y extensiones)
├── docs/
│   ├── 01-architecture/        (arquitectura y decisiones vigentes)
│   ├── 05-frontend/            (UI, navegación y layouts)
│   ├── 08-operations/          (Apache, despliegue y actualizaciones)
│   ├── 09-history/             (brief e historial técnico)
│   ├── 10-productivity/        (sesión, métricas y prompts)
│   └── 11-backlog/             (backlog y roadmap)
├── skills/                     (skills locales de agentes)
├── tools/                      (setup y utilidades)
└── .git/                       (con commits descriptivos)
```

### 📄 Package 2: Documentación Académica
- `MASTER-brief.md` (este archivo)
- `docs/10-productivity/productividad.md` (datos reales por story)
- `docs/10-productivity/prompts.md` (prompts, resultados e iteraciones)
- `docs/09-history/decisiones-tecnicas.md` (decisiones y trade-offs)
- `docs/11-backlog/roadmap.md` (estado y fases pendientes)

### 🎬 Package 3: Demo
- Video de 10-15min mostrando:
  1. Login
  2. Crear registro de cliente desde EntityList
  3. Ver que datos se guardan en JSONB
  4. Gestionar plugin desde PluginManager
  5. Mostrar tabs de extensión inyectadas por `comments`
  6. Actualizar y hacer rollback de un plugin
  7. Gestionar perfil y usuarios
  8. Mostrar navegación, shell y layouts compartidos

---

## Criterios de Éxito

| Criterio | Cumple | Evidencia |
|----------|--------|-----------|
| **Funcionalidad** | ✅ | CRUD dinámico funciona E2E |
| **Arquitectura** | ✅ | Plugins loadable, hooks ejecutables |
| **Proceso con IA** | ✅ | Análisis de productividad documentado |
| **Documentación** | ✅ | Cada decisión justificada |
| **Reproducible** | ✅ | Guía Apache+PHP, setup local y despliegue RPi5 documentados |
| **Git visible** | ✅ | Commits muestran progreso semana a semana |

---

## Presentación en Clase

### Estructura sugerida (20-30 min)
1. **Problema:** Arquitectura modular en pequeños negocios (5 min)
2. **Solución:** Xestify micro-kernel con plugins (5 min)
3. **Proceso IA:** Cómo usaste IA, qué aceleró, qué no (5 min)
4. **Demo en vivo:** Mostrar flujo completo (5-10 min)
5. **Análisis:** Comparativa sin IA vs con IA (3-5 min)

---

## Métricas de Aceleración

La fuente de verdad de tiempos, estimaciones, iteraciones y decisiones manuales
es [productividad.md](../10-productivity/productividad.md). Los prompts exactos y
sus resultados se conservan en [prompts.md](../10-productivity/prompts.md).

No se calcula aceleración cuando una sesión retoma trabajo previo sin un tiempo
acumulado fiable. Para la defensa deben usarse únicamente métricas registradas,
sin completar retrospectivamente valores desconocidos.

---

## Cambios frente a MVP "producción"

| Item | MVP Producción | MVP Master |
|------|---|---|
| Scope | Roadmap completo | EPIC 0-8 + STORY 9.1-9.6 implementadas |
| Timeline | Evolución continua | Corte académico incremental documentado |
| IA | Accesible | **Primario** |
| Testing | Quality gate completo | Suites PHP + 17 runners HTML |
| Documentación | Operativa | **Académica + técnica** |
| Plugins | Marketplace y catálogo remoto | Entidades, extensiones, update y rollback local |

---

## Próximo Paso

1. Implementar STORY 9.7 y consolidar estado transversal, resiliencia, i18n y theming.
2. Continuar con STORY 9.8-9.9 sin adelantar EPIC 10.
3. Mantener [sesion.md](../10-productivity/sesion.md),
   [productividad.md](../10-productivity/productividad.md) y
   [prompts.md](../10-productivity/prompts.md) al cerrar cada story.
4. Preparar el guion de defensa usando solo funcionalidades y métricas verificadas.

---

## Preguntas Frecuentes

**P: ¿Cuántas horas por semana dedicar?**  
R: La referencia inicial fue una dedicación full-time de 40 horas. Para el
análisis académico deben usarse los tiempos realmente registrados en
[productividad.md](../10-productivity/productividad.md), no esa hipótesis.

**P: ¿Qué ocurre si una story futura bloquea la defensa?**
R: Se conserva como corte defendible STORY 9.6 y se documenta el bloqueo. No se
deben presentar como completadas funciones no verificadas.

**P: ¿Necesito aprender PHP de cero?**  
R: No. IA genera 80% del código repetitivo. Enfócate en lógica y decisiones.

**P: ¿Cómo demuestro que IA aceleró?**  
R: Commits + tiempos reales vs estimados. Si generaste validador en 2 horas (vs 8 estimado), eso es 75% aceleración.
