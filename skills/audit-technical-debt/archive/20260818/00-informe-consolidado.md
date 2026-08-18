# Auditoría de deuda técnica — Informe consolidado (2026-08-18)

**Alcance:** EPIC 0 a EPIC 10 — todo el código del MVP en `main` (commit `81d0106`), incluida la EPIC 10 completa (login, renombrado `persons`, identidad de plugins, plugins demo, extensiones optometries/contact_lenses, seeder de negocio) y los fixes posteriores a la auditoría de 2026-08-11.
**Hallazgos:** 179 totales — **2 crítico · 38 mayor · 85 menor · 54 nit**.
**Método:** 9 agentes de investigación en paralelo, uno por subsistema, con lectura estática íntegra de los ficheros de su ámbito (no solo grep) y contraste contra `docs/` y `docs/11-backlog/backlog.md`. Ver [README.md](README.md) para el índice.

> **Límite:** auditoría **estática** de lectura de código — no se ejecutó la aplicación ni la suite de tests como método de detección. Los hallazgos de "esto rompe en producción" deben confirmarse manualmente antes de citarlos en una defensa o entrega.

---

## Veredicto global

El código está sustancialmente más sano que en la auditoría de 2026-08-11: las verificaciones puntuales confirman que los 85 hallazgos de aquella auditoría siguen corregidos (transacciones, allow-list de validación, password_hash, despacho dinámico del Router, propiedad de comentarios…), la seguridad estructural del frontend es notable (cero `innerHTML` en todo `frontend/src/js` — la clase entera de XSS por interpolación está eliminada por construcción), y el backend de plugins usa locking pesimista y validación de slugs correcta. El volumen de hallazgos (179 vs 85) no indica empeoramiento: esta pasada cubre ~9.000 líneas más (EPIC 10 entera), baja más al detalle y dedica dos subsistemas nuevos al código que la anterior no auditó.

La deuda de esta generación tiene tres focos claros:

1. **Dos críticos de integración que ninguna suite detecta.** Guardar la configuración de un plugin corrompe su verificación de integridad y bloquea sus updates (04.01) — una regresión parcial del fix del antiguo 04.01, re-roto por features posteriores (`summaryView`, `layer`). Y la expiración de sesión a mitad de navegación borra el formulario de login recién pintado (05.01). Ambos viven en la costura entre módulos que los tests unitarios no cruzan.
2. **Deriva normativa:** `AGENTS.md` — la fuente canónica — contradice al código en tres puntos de peso (slug `clients`, clave `mail`, corte del MVP en 6.4), y `docs/03-api/endpoints.md` va un EPIC por detrás. Quien obedezca la norma rompe la demo.
3. **Refactors y patrones aplicados a medias:** la guarda de 401 existe en 3 cargadores y falta en 5 flujos; el guard de doble submit existe en Login/EntityEdit y falta en las 3 páginas nuevas; `AbstractUniqueFieldHook` eliminó un copy-paste mientras los hooks de pestañas acumulan 3 copias idénticas.

Ningún hallazgo invalida la arquitectura: son deudas de borde y de proceso sobre una base sólida.

## Antes de la defensa (top 5)

| P | ID | Sev. | Hallazgo |
|---|---|---|---|
| **P1** | [`04.01`](04-backend-plugins-schema-extensiones.md) | Crítico | Guardar la config de un plugin lo marca "corrupto" en sync y **bloquea sus updates**: los verificadores comparan por igualdad estricta definiciones que el guardado "engorda" con `summaryView`/`layer`/`origin`. |
| **P2** | [`05.01`](05-frontend-arquitectura-spa.md) | Crítico | Sesión caducada a mitad de navegación: `showPlaceholder` **borra el formulario de login** recién renderizado por el interceptor 401 y deja al usuario sin forma de autenticarse salvo recargar. |
| **P3** | [`03.01`](03-backend-motor-plugins-nucleo.md) | Mayor | `HookDispatcher::logWarning()` usa `STDERR`, inexistente bajo Apache: el fallo "non-blocking" de un hook after* se convierte en un **500 tras haber persistido el registro**. Los tests corren en CLI y no lo ven. |
| **P4** | [`03.02`](03-backend-motor-plugins-nucleo.md) | Mayor | El boot de hooks no aísla errores: un plugin activo con `Hooks.php` roto **tumba toda la API en cada request**, incluido el endpoint para desactivarlo. |
| **P5** | [`01.01`](01-backend-core-auth-usuarios.md) | Mayor | Tokens irrevocables + sesión deslizante: un usuario **borrado o degradado conserva acceso indefinidamente** mientras siga haciendo peticiones (cada request renueva el token con las claims viejas). |

P1, P3 y P4 tienen arreglos baratos (whitelist de claves en los comparadores; `error_log` en vez de `STDERR`; try/catch por plugin en el registrar). P2 y P5 requieren algo más de diseño pero tienen mitigaciones acotadas descritas en sus fichas.

## Patrones transversales

1. **La norma canónica contradice al código.** `AGENTS.md` prohíbe `clients` mientras los manifests, el seeder y la skill de seed lo usan como slug demo oficial (09.01); ordena la clave `email` cuando el schema, el hook de unicidad y los tests fijan `mail` (02.13/09.04); y congela el corte del MVP en la Story 6.4 cuando el repo va por la EPIC 10 (01.16). `endpoints.md` no conoce los 10 endpoints de users/configurations (01.03) y los contratos van una story por detrás (04.10/04.11). Causa raíz de proceso: el checklist obligatorio de cierre de story cubre `sesion.md`/`backlog.md`/`README.md` pero no `docs/03-api/` ni `AGENTS.md`.
2. **Patrones correctos aplicados a medias.** El proyecto ya tiene la solución de casi todos sus bugs, aplicada en el sitio de al lado: guarda-401 en 3 cargadores pero no en 5 flujos de página (05.01); doble submit protegido en Login/EntityEdit pero no en PluginConfig/UserConfig/PluginItemEdit (08.02); `density` pinned pero `pageSize` pisado por la cookie (07.09); `AbstractUniqueFieldHook` extraído mientras los tab-hooks acumulan 3 copias (09.03); el fix del antiguo 04.01 con `ignoreKeys: ['origin']` en un comparador pero no en el otro ni para las claves nuevas (04.01). El barrido de corrección debería buscar sistemáticamente "¿dónde más aplica este mismo patrón?".
3. **Infraestructura duplicada que ya divergió.** El wiring DI de sync triplicado produjo un tool con la salida rota (03.04); el parser de `.env` está copiado en ~8 sitios sin soporte de comillas (01.20); la normalización de schema→campos está triplicada en frontend y ya divergió (`slug` aceptado en el form pero no en la tabla, 07.13); `resolveContainer` clonado en 7 páginas con dos variantes (08.OT); el registro de validadores duplicado entre `app.php` y la factory que usan los tests (02.10).
4. **Frontend sin ciclo de vida de desmontaje.** Ningún componente del toolkit ni la mitad de los módulos tienen `destroy()`: UserMenu fuga 4 listeners de `document` por sync de sesión (07.02), InputSelect deja panel y 5 listeners huérfanos si se desmonta abierto (06.02), `confirm()` nunca resuelve si se cierra con ESC (05.04), y AppController registra handlers globales imposibles de liberar (05.17). Un contrato mínimo `destroy()` cerraría la clase entera.
5. **Manejo de errores heterogéneo y sin red final.** Backend: sin manejador global de excepciones (01.02), `catch (Exception)` vs `catch (\Throwable)` según el fichero (03.15), 404 comodín para errores de persistencia (02.09), fugas de mensajes internos (01.08). Frontend: cuatro estrategias de feedback distintas por página (08.07) y un `renderError` que destruye la lista entera ante un fallo puntual (08.03). Una tabla única excepción→status + un catch global en cada extremo normalizaría todo.

## Resueltos desde la auditoría 2026-08-11

No es una re-auditoría incremental formal, pero los agentes verificaron de forma independiente los hallazgos críticos/mayores de la auditoría anterior en sus ámbitos: los 85 constan corregidos y las verificaciones puntuales lo confirman (01.01 password_hash, 01.14 despacho dinámico del Router, 02.01-02.04 transacciones/validación, 04.03 propiedad de comentarios, etc.), con **una excepción material**: la corrección del antiguo `04.01` (custom_fields que bloqueaban updates) quedó incompleta y las features posteriores la re-rompieron por otra vía — es el nuevo crítico [`04.01`](04-backend-plugins-schema-extensiones.md) de esta auditoría (P1).

## Tabla resumen por subsistema

| Fichero | Subsistema | EPICs | Crít. | Mayor | Menor | Nit | Total |
|---|---|---|---|---|---|---|---|
| [01](01-backend-core-auth-usuarios.md) | Backend core, auth y usuarios | 0, 1, 8 | 0 | 3 | 13 | 9 | 25 |
| [02](02-backend-motor-entidades-validacion.md) | Motor de entidades y validación | 2, 3 | 0 | 4 | 9 | 5 | 18 |
| [03](03-backend-motor-plugins-nucleo.md) | Motor de plugins (núcleo) | 4, 7 | 0 | 4 | 9 | 8 | 21 |
| [04](04-backend-plugins-schema-extensiones.md) | Schema/config de plugins y extensiones | 6, 7, 10 | 1 | 3 | 8 | 5 | 17 |
| [05](05-frontend-arquitectura-spa.md) | Frontend arquitectura SPA | 9 | 1 | 5 | 11 | 8 | 25 |
| [06](06-frontend-toolkit-ui.md) | Frontend toolkit UI | 9 | 0 | 4 | 11 | 5 | 20 |
| [07](07-frontend-modulos-dinamicos.md) | Frontend módulos dinámicos | 3, 5, 9 | 0 | 4 | 9 | 5 | 18 |
| [08](08-frontend-paginas.md) | Frontend páginas | 5-8, 10 | 0 | 7 | 10 | 5 | 22 |
| [09](09-plugins-demo-seeders.md) | Plugins demo y seeders | 6.4, 10 | 0 | 4 | 5 | 4 | 13 |
| **Total** | | | **2** | **38** | **85** | **54** | **179** |

## Cobertura de tests (visión agregada)

La suite es amplia (55+ ficheros backend, 22 runners HTML, 13 tests E2E Playwright) y en general honesta con el código. Los huecos sistemáticos que esta auditoría destapa:

- **Los dos críticos viven en costuras sin test:** no existe ningún test `saveConfig()` real → `syncAll()`/`update()` (el test de sync siembra a mano un estado que el código nunca produce), ni ninguno de expiración de sesión a mitad de navegación.
- **Tests que blindan bugs o comportamientos ya inexistentes:** `DynamicFormTest` tiene dos asserts imposibles de pasar (`type: 'email'` vs `'mail'` — el runner está en rojo hoy, 07.03); `PluginManagerApiTest` asserta un `schema_version` que la API real ya no emite, contra un fake de ~400 líneas que reimplementa el servicio (04.10).
- **Código de STORY 10.5/10.6 sin red:** `PluginItemEdit` no tiene ningún runner ni spec, y todo el subsistema de seeders de STORY 10.6 tiene cero tests.
- **El contrato "non-blocking" de hooks solo se verifica en CLI**, donde el bug de `STDERR` (P3) es invisible; nada testea `tools/setup/`.

## Estadísticas

- **~31.500 líneas** de código fuente auditadas (sin tests ni generados): backend 12.451, frontend 16.907, plugins 3.238, tools/seeders ~900.
- **Ficheros más señalados:** `AppController.js` (1.829 líneas, 10 hallazgos), `PluginConfig.js` (1.449, 6), `DynamicTable.js` (801, 6), `PluginConfigService.php` (597, 4), `AGENTS.md` (4 contradicciones normativas).
- **~40 commits** auditados por primera vez (EPIC 10 completa + fixes posteriores al 2026-08-11).

## Cómo continuar

- Plan de corrección por fases y sesiones: [plan-correccion.md](plan-correccion.md), ejecutable con `skills/fix-technical-debt/SKILL.md`.
- Estado vivo de cada hallazgo: [progreso.md](progreso.md) (única pieza mutable de esta auditoría).
- Versión navegable (HTML) publicada como artifact: <https://claude.ai/code/artifact/1eee7f42-6ae3-4288-9922-77eceb48d427>.
