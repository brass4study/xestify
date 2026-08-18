# Plan de corrección — auditoría 2026-08-18

← [Índice de esta auditoría](README.md) · [Informe consolidado](00-informe-consolidado.md) · [Progreso](progreso.md)

Las reglas de ejecución (sesiones, commit único por hallazgo, formato de asunto, verificación) viven en [`skills/fix-technical-debt/SKILL.md`](../../../fix-technical-debt/SKILL.md). Este fichero solo contiene los datos específicos de esta auditoría: qué hallazgos van en cada fase y cómo se agrupan las sesiones.

## Fase 1 — Prioritarios "antes de la defensa"

En este orden (los cinco tienen impacto real de demo o seguridad; P1/P3/P4 son además baratos):

| P | ID | Arreglo en una línea |
|---|---|---|
| P1 | `04.01` | Whitelist compartida de claves de contrato en `InstalledPluginSchemaValidator` + `PluginSchemaMergeService` (excluir `summaryView`/`layer`/`origin`) + test saveConfig→sync→update real |
| P2 | `05.01` | Guarda 401 en los flujos de página (`showEntityEdit`/`showEntityList`/`showUserConfigPage`/`showPluginItemEdit`) o no-op de `showPlaceholder` con `dashboardApi === null` |
| P3 | `03.01` | `error_log()` en vez de `fwrite(STDERR, ...)` en `HookDispatcher::logWarning()` |
| P4 | `03.02` | try/catch `\Throwable` por plugin en `PluginHookRegistrar::registerActiveHooks()` + `method_exists` |
| P5 | `01.01` | Re-validar `sub` contra `users` (al menos en la rama de refresh de `AuthMiddleware`) y reconstruir roles desde BD |

## Fase 2 — Barrido MAYOR por subsistema

Una sesión por fichero de subsistema; solo hallazgos MAYOR aún pendientes (los de Fase 1 no se cuentan dos veces). Orden sugerido: primero backend (2.1-2.4), luego frontend (2.5-2.8), demo al final (2.9).

| Sesión | Fichero | IDs MAYOR | Nota |
|---|---|---|---|
| 2.1 | [01](01-backend-core-auth-usuarios.md) | `01.02`, `01.03` | Manejador global de excepciones + endpoints.md de users/configurations |
| 2.2 | [02](02-backend-motor-entidades-validacion.md) | `02.01`, `02.02`, `02.03`, `02.04` | Pertenencia slug↔registro, 500 en index, required vaciable, orden numérico |
| 2.3 | [03](03-backend-motor-plugins-nucleo.md) | `03.03`, `03.04` | Compensación en registerNew + salida de sync-plugins.php |
| 2.4 | [04](04-backend-plugins-schema-extensiones.md) | `04.02`, `04.03`, `04.04` | Catálogo ante payload parcial, options de select base, stamp inmutable |
| 2.5 | [05](05-frontend-arquitectura-spa.md) | `05.02`, `05.03`, `05.04`, `05.05`, `05.06` | Carrera de navegación, host de notificaciones, confirm ESC, #/login autenticado, extracciones de AppController (esta última puede partirse si crece) |
| 2.6 | [06](06-frontend-toolkit-ui.md) | `06.01`, `06.02`, `06.03`, `06.04` | BaseComponent, InputSelect huérfano, fixedHeader no-op, setClassName |
| 2.7 | [07](07-frontend-modulos-dinamicos.md) | `07.01`, `07.02`, `07.03`, `07.04` | Navbar mixed, fuga UserMenu, DynamicFormTest en rojo, extracciones de DynamicTable (la última puede partirse) |
| 2.8 | [08](08-frontend-paginas.md) | `08.01`, `08.02`, `08.03`, `08.04`, `08.05`, `08.06`, `08.07` | La sesión más larga (7 IDs); si se alarga, cortar tras 08.05 y continuar en 2.8b con 08.06-08.07 (extracción PluginConfig + feedback compartido) |
| 2.9 | [09](09-plugins-demo-seeders.md) | `09.01`, `09.02`, `09.03`, `09.04` | Los dos primeros son de gobernanza (AGENTS.md/documentación de aprovisionamiento): decisión + docs más que código |

Advertencias de solape entre sesiones:

- `01.02` (manejador global) y `02.02` (500 en index) tocan el mismo problema desde dos capas: si se implementa el catch global en 2.1, el arreglo de 2.2 se reduce al 404 específico.
- `05.06` (extraer NotificationView/PageTemplateResolver de AppController) y `08.07` (feedback compartido de páginas) convergen en el mismo diseño — coordinar antes de empezar la segunda.
- `07.04` (extraer SchemaColumns de DynamicTable) comparte la normalización con `07.03` (alias email/mail) y con `06.04` solo tangencialmente; revisar `progreso.md` antes de tocar `DynamicForm`.
- `09.01` y `09.04` (AGENTS.md) deberían resolverse en una única edición coherente de AGENTS.md junto con `01.16` (corte MVP), aunque `01.16` sea MENOR.

## Fase 3 — Limpieza MENOR/NIT (opcional, solo bajo petición explícita)

85 MENOR + 54 NIT repartidos en los nueve ficheros. Prioridad sugerida dentro de la fase: (1) los que son pura edición de docs/AGENTS.md (cierran el patrón transversal nº1: `01.15`, `01.16`, `02.12`, `02.13`, `03.11`, `04.10`, `04.11`, `05.10`, `06.15`, `07.18`, `08.16`, `09.05`); (2) código muerto y docblocks invertidos (arreglo trivial, riesgo nulo); (3) los que exigen tocar varios ficheros (duplicaciones) — estos últimos solo si sobra tiempo.

## Fase 4 — Cierre

Re-auditoría incremental vía `skills/audit-technical-debt/SKILL.md` comparando contra esta subcarpeta `20260818/`.
