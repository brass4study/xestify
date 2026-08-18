# Progreso de corrección — auditoría 2026-08-18

← [Índice de esta auditoría](README.md) · [Plan de corrección](plan-correccion.md) · [Reglas de corrección](../../../fix-technical-debt/SKILL.md) · [Formato de esta tabla](../../SKILL.md)

**Antes de tocar código, lee [`skills/fix-technical-debt/SKILL.md`](../../../fix-technical-debt/SKILL.md)** (reglas de sesión, commit único, formato de asunto) y la sección "Formato de `progreso.md`" de [`skills/audit-technical-debt/SKILL.md`](../../SKILL.md). Este fichero solo trae el estado real de esta auditoría concreta.

## Resumen

| Fichero | Total | ✅ Resuelto | 🔧/⏳ Pendiente | 🚫 Descartado |
|---|---|---|---|---|
| [01](01-backend-core-auth-usuarios.md) — Core/Auth/Users | 25 | 0 | 25 | 0 |
| [02](02-backend-motor-entidades-validacion.md) — Motor de entidades | 18 | 0 | 18 | 0 |
| [03](03-backend-motor-plugins-nucleo.md) — Motor de plugins | 21 | 0 | 21 | 0 |
| [04](04-backend-plugins-schema-extensiones.md) — Schema/extensiones | 17 | 0 | 17 | 0 |
| [05](05-frontend-arquitectura-spa.md) — Arquitectura SPA | 25 | 0 | 25 | 0 |
| [06](06-frontend-toolkit-ui.md) — Toolkit UI | 20 | 0 | 20 | 0 |
| [07](07-frontend-modulos-dinamicos.md) — Módulos dinámicos | 18 | 0 | 18 | 0 |
| [08](08-frontend-paginas.md) — Páginas | 22 | 0 | 22 | 0 |
| [09](09-plugins-demo-seeders.md) — Plugins demo/seeders | 13 | 0 | 13 | 0 |
| **Total** | **179** | **0** | **179** | **0** |

Los 5 hallazgos de la Fase 1 ("antes de la defensa") corresponden a: **P1**=`04.01`, **P2**=`05.01`, **P3**=`03.01`, **P4**=`03.02`, **P5**=`01.01`. No los cuentes dos veces al planificar las sesiones de la Fase 2.

---

## 01 — Core / Auth / Users

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 01.01 | ⏳ | Mayor | Tokens irrevocables + sesión deslizante: usuario borrado/degradado conserva acceso (= **P5**) | | |
| 01.02 | ⏳ | Mayor | Sin manejador global de excepciones: fatales rompen el envelope | | ⚠️ solapa con 02.02 (catch global vs 404 específico) |
| 01.03 | ⏳ | Mayor | endpoints.md omite los 10 endpoints de users/configurations | | |
| 01.04 | ⏳ | Menor | Enumeración de usuarios por canal de tiempo en login | | |
| 01.05 | ⏳ | Menor | Update vacío sobre usuario inexistente devuelve 200 con data vacía | | |
| 01.06 | ⏳ | Menor | `updatePasswordIfNeeded()` sin try/catch; perfil no atómico | | |
| 01.07 | ⏳ | Menor | Sin validación de formato/tipos en payloads de usuarios | | |
| 01.08 | ⏳ | Menor | ConfigurationController filtra detalles internos en 500 | | |
| 01.09 | ⏳ | Menor | `password_hash` seleccionado siempre; sanitización de frontera | | |
| 01.10 | ⏳ | Menor | Docblocks `apiSuccess`/`apiError` prometen exit; API muerta | | |
| 01.11 | ⏳ | Menor | `ignoreParams($params)` en métodos que sí usan `$params` | | |
| 01.12 | ⏳ | Menor | Tres fallbacks de `Request` distintos; docblock invertido | | |
| 01.13 | ⏳ | Menor | `seedIfEmpty()` ya no hace lo que su nombre dice | | |
| 01.14 | ⏳ | Menor | Dos versiones de core contradictorias (/health 0.1.0 vs validador 1.0.0) | | |
| 01.15 | ⏳ | Menor | Stories 1.5-1.7 con ✅ sin implementación ni nota SUPERSEDED | | |
| 01.16 | ⏳ | Menor | AGENTS.md congelado en Story 6.4, contradicho por el código | | ⚠️ editar AGENTS.md junto con 09.01/09.04 |
| 01.17 | ⏳ | Nit | `buildPattern()` sin `preg_quote`; doble sintaxis `{param}`/`:param` | | |
| 01.18 | ⏳ | Nit | Container retiene instancia obsoleta al re-registrar singleton | | |
| 01.19 | ⏳ | Nit | `PHP_AUTH_DIGEST` como fallback de Authorization | | |
| 01.20 | ⏳ | Nit | Parser .env + autoloader duplicados en ~8 sitios, sin comillas | | |
| 01.21 | ⏳ | Nit | `JWT_ALGORITHM`/`PLUGINS_PATH`/`APP_URL` configuración muerta | | |
| 01.22 | ⏳ | Nit | Configuration `index()` devuelve claves que `show()` rechaza | | |
| 01.23 | ⏳ | Nit | `exp` opcional en decode; TTL sin validar | | |
| 01.24 | ⏳ | Nit | Binding `Database::class` devuelve PDO; idiomas de error mezclados | | |
| 01.25 | ⏳ | Nit | Fixtures con slug `client` en RouterTest/RequestResponseTest | | |

## 02 — Motor de entidades / Validación

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 02.01 | ⏳ | Mayor | `updateRecord` no comprueba pertenencia del registro al slug | | ⚠️ mismo arreglo aplica a 02.16 (`show()`) |
| 02.02 | ⏳ | Mayor | `GET records` con slug inexistente → 500 sin envelope | | ⚠️ solapa con 01.02 |
| 02.03 | ⏳ | Mayor | Update parcial permite vaciar campo `required`; `''` salta validación de tipo | | |
| 02.04 | ⏳ | Mayor | Orden JSONB siempre textual: columnas `number` ordenan mal | | |
| 02.05 | ⏳ | Menor | CRUD de registros no filtra por plugin entity activo | | |
| 02.06 | ⏳ | Menor | `restore()` muerto; cascada hard-delete lo dejaría cojo; docblock invertido | | |
| 02.07 | ⏳ | Menor | Comentario promete resolución entity-side de `auto_generated` inexistente | | |
| 02.08 | ⏳ | Menor | `relations` mapa vs lista con soporte asimétrico | | |
| 02.09 | ⏳ | Menor | `respondEntityWriteFailure` mapea errores de persistencia a 404 | | |
| 02.10 | ⏳ | Menor | Registro de validadores duplicado (app.php vs factory) | | |
| 02.11 | ⏳ | Menor | Guard de dependientes fuera de transacción; solo ve plugins activos | | |
| 02.12 | ⏳ | Menor | Falta `GET /entities/{slug}/options` en endpoints.md | | |
| 02.13 | ⏳ | Menor | AGENTS.md `email` vs schema real `mail` en persons | | ⚠️ mismo fondo que 09.04 |
| 02.14 | ⏳ | Nit | Docblocks de clase desactualizados en las tres piezas | | |
| 02.15 | ⏳ | Nit | Patrón `$hasError` innecesario en create/update | | |
| 02.16 | ⏳ | Nit | `show()` ignora el slug de la URL | | ⚠️ ver 02.01 |
| 02.17 | ⏳ | Nit | Combos de `EntityOptionLabelBuilder` ignoran `summaryView`; fields en lista sin label | | |
| 02.18 | ⏳ | Nit | `listRecords()` e `includeDeleted` sin consumidor de producción | | |

## 03 — Motor de plugins (núcleo)

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 03.01 | ⏳ | Mayor | `STDERR` inexistente bajo Apache: fallo de hook after* → 500 tras persistir (= **P3**) | | |
| 03.02 | ⏳ | Mayor | Boot de hooks sin aislamiento: plugin roto tumba toda la API (= **P4**) | | |
| 03.03 | ⏳ | Mayor | `registerNew()` deja instancia activa a medio configurar si fallan overrides | | |
| 03.04 | ⏳ | Mayor | Salida per-plugin de `sync-plugins.php` rota tras refactor multi-instancia | | ⚠️ relacionado con 03.19 (wiring triplicado) |
| 03.05 | ⏳ | Menor | Rollback machaca ediciones admin y cambia status sin `onDeactivate` | | |
| 03.06 | ⏳ | Menor | Un manifest corrupto tumba `/available`, `/updates` y el alta | | |
| 03.07 | ⏳ | Menor | `target_entity` como override se ignora sin `fields` | | |
| 03.08 | ⏳ | Menor | Tríada carpeta/plugin_name/slug sin invariante (SchemaReader por name) | | |
| 03.09 | ⏳ | Menor | `activate()`/`deactivate()` no idempotentes: re-disparan hooks | | |
| 03.10 | ⏳ | Menor | `requires` satisfecho por plugin inactivo; sync sin orden topológico | | |
| 03.11 | ⏳ | Menor | Deriva doc↔código en 3 puntos del manager (updates, multi-instancia, available) | | |
| 03.12 | ⏳ | Menor | Unicidad check-then-act sin respaldo de índice (concurrencia) | | |
| 03.13 | ⏳ | Menor | `instantiateLifecycle()` sin `instanceof`: TypeError en vez de error de dominio | | |
| 03.14 | ⏳ | Nit | `in_array(type, [...])` siempre verdadero (código muerto) | | |
| 03.15 | ⏳ | Nit | `catch (Exception)` vs `catch (\Throwable)` inconsistente; mensajes crudos | | |
| 03.16 | ⏳ | Nit | Orden de bloqueo opuesto en `move()`: deadlock posible | | |
| 03.17 | ⏳ | Nit | Guard de idempotencia del registrar keyed por instancia, no por dispatcher | | |
| 03.18 | ⏳ | Nit | switch-demoinventory escribe manifest+schema sin atomicidad | | |
| 03.19 | ⏳ | Nit | Wiring DI de sync triplicado (app.php, tool, tests) | | |
| 03.20 | ⏳ | Nit | Clave `requires[].slug` se resuelve semánticamente como plugin_name | | |
| 03.21 | ⏳ | Nit | `backfillSchemaIfMissing` puede mezclar versión vieja con schema nuevo | | |

## 04 — Schema/configuración de plugins y extensiones

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 04.01 | ⏳ | Crítico | Guardar config marca el plugin "corrupto" en sync y bloquea updates (= **P1**) | | Regresión parcial del 04.01 de 20260811 |
| 04.02 | ⏳ | Mayor | Payload parcial borra sugerencias del catálogo permanentemente | | |
| 04.03 | ⏳ | Mayor | `options` de select base de extensión editables vía API | | |
| 04.04 | ⏳ | Mayor | Cada PUT reescribe `stamp`: editar un comentario pierde su fecha | | |
| 04.05 | ⏳ | Menor | Camino entity pierde metadatos author-locked al guardar | | |
| 04.06 | ⏳ | Menor | Sin bypass admin en `guardOwnership`: no hay moderación posible | | |
| 04.07 | ⏳ | Menor | `target '*'` no valida entidad activa: extensión sobre entidades desactivadas | | |
| 04.08 | ⏳ | Menor | Extensión activa sin schema persiste contenido arbitrario | | |
| 04.09 | ⏳ | Menor | `author_name` expone el email de los usuarios | | |
| 04.10 | ⏳ | Menor | Doc y test afirman un versionado de schema que ya no existe | | |
| 04.11 | ⏳ | Menor | contratos/plugins.md niega relations en extensiones (obsoleto 10.5); omite layers | | |
| 04.12 | ⏳ | Menor | Flecos de e1df7d0: auto-relaciones invisibles, N+1, keys sin formato | | |
| 04.13 | ⏳ | Nit | Fallback de `author_id` al payload sin `sub` (suplantación latente) | | |
| 04.14 | ⏳ | Nit | Mensaje engañoso cuando la entidad no casa con `target_entity` | | |
| 04.15 | ⏳ | Nit | Docblocks con inversiones sutiles (rawFields, mensaje entity) | | |
| 04.16 | ⏳ | Nit | Spread conserva `name`/`key` dentro de la definición persistida | | |
| 04.17 | ⏳ | Nit | Redundancias `??` muertas y duplicación asumida entre servicios de config | | |

## 05 — Frontend arquitectura SPA

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 05.01 | ⏳ | Crítico | 401 a mitad de navegación: `showPlaceholder` borra el login (= **P2**) | | |
| 05.02 | ⏳ | Mayor | Carrera de navegaciones async sin cancelación: contenido stale | | E2E `_helpers.js` lo esquiva con wait |
| 05.03 | ⏳ | Mayor | Host de notificaciones desconectado en login: toasts invisibles | | |
| 05.04 | ⏳ | Mayor | `confirm()` nunca resuelve con ESC/backdrop; overlay huérfano | | |
| 05.05 | ⏳ | Mayor | `#/login` autenticado detiene el router (back/forward muertos) | | |
| 05.06 | ⏳ | Mayor | AppController god object: 6 responsabilidades extraíbles | | ⚠️ converge con 08.07 |
| 05.07 | ⏳ | Menor | Título del toast de error usa la clave i18n equivocada | | |
| 05.08 | ⏳ | Menor | Back/forward entre tabs pierde el resumen del breadcrumb | | |
| 05.09 | ⏳ | Menor | `showPluginItemEdit` no aborta con schema/registro nulos | | |
| 05.10 | ⏳ | Menor | Deriva docs↔código en mapa de rutas (`/new` vs `#new`, reservadas) | | |
| 05.11 | ⏳ | Menor | CDN runtime (Google Fonts + Font Awesome) contra same-origin | | |
| 05.12 | ⏳ | Menor | Build de Tailwind no reproducible (globs CWD, sin script) | | |
| 05.13 | ⏳ | Menor | Sin timeout ni cancelación en fetch | | |
| 05.14 | ⏳ | Menor | JWT en localStorage sin comprobación local de `exp` | | |
| 05.15 | ⏳ | Menor | Expiración de sesión pierde el deep-link | | |
| 05.16 | ⏳ | Menor | i18n muerta en producción; literales hardcodeados en AppController | | |
| 05.17 | ⏳ | Menor | AppController sin `destroy()`: listeners globales imposibles de liberar | | |
| 05.18 | ⏳ | Nit | `setClassName` del diálogo asignado dos veces (código muerto) | | |
| 05.19 | ⏳ | Nit | Generador de id de notificación con doble `Date.now()` | | |
| 05.20 | ⏳ | Nit | `notificationRenderScheduled` propiedad implícita | | |
| 05.21 | ⏳ | Nit | `setEntities` no notifica a los suscriptores | | |
| 05.22 | ⏳ | Nit | Tokens de ruta con `:` construidos con segmentos decodificados | | |
| 05.23 | ⏳ | Nit | E2ETest.html: infraestructura duplicada, nombre engañoso | | |
| 05.24 | ⏳ | Nit | Preset `cyan` de ThemeModel inalcanzable | | |
| 05.25 | ⏳ | Nit | `getUiPreferences` re-normaliza ~5 veces por llamada | | |

## 06 — Frontend toolkit UI

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 06.01 | ⏳ | Mayor | Herencia ficticia de BaseComponent: métodos ligados copiados por instancia | | |
| 06.02 | ⏳ | Mayor | InputSelect: panel portalado y 5 listeners globales huérfanos al desmontar | | |
| 06.03 | ⏳ | Mayor | `fixedHeader`/`fixedSidebar` no-op: `top-0` sin `sticky` | | |
| 06.04 | ⏳ | Mayor | Semántica inconsistente de `setClassName()`: pérdidas de estilo reales | | |
| 06.05 | ⏳ | Menor | `setTitle`/`setMessage` de Alert destruyen la estructura (API muerta y rota) | | |
| 06.06 | ⏳ | Menor | ARIA de Tabs rota: tablist/tab desconectados, sin aria-controls | | |
| 06.07 | ⏳ | Menor | Breadcrumb: `<a>` dentro de `<button>`, href muerto, cierre por mouseleave | | |
| 06.08 | ⏳ | Menor | InputSelect: teclado incompleto y desincronización con valores inexistentes | | |
| 06.09 | ⏳ | Menor | `text-${color}` dinámico sin safelist en Typography | | |
| 06.10 | ⏳ | Menor | Id `xestify-modal-content` duplicado entre modales | | ⚠️ mismo hallazgo que 07.10 |
| 06.11 | ⏳ | Menor | Código muerto verificado: Spinner, Skeleton, InputRadio, mutadores | | |
| 06.12 | ⏳ | Menor | Dos mecanismos de breadcrumbs en PageHeader/PageLayout, uno muerto | | |
| 06.13 | ⏳ | Menor | `loadOptions().then()` sin `.catch`: select en "Cargando…" eterno | | |
| 06.14 | ⏳ | Menor | Clase `cursor-inherit` inexistente en el CSS compilado | | |
| 06.15 | ⏳ | Menor | Deriva docs acumulada (layouts-guide, factory, iconos, backlog) | | |
| 06.16 | ⏳ | Nit | Estilos inline duplicando Tailwind en `shell-floating-ui` | | |
| 06.17 | ⏳ | Nit | `const showMenuConfig = hasMenuConfig;` alias literal | | |
| 06.18 | ⏳ | Nit | Heurística de icono de Button rompe `fa-regular` | | |
| 06.19 | ⏳ | Nit | `addCell()` engañoso; `add*` de Table re-renderizan todo | | |
| 06.20 | ⏳ | Nit | Variables muertas en EmptyState; FormField muta el input recibido | | |

## 07 — Frontend módulos dinámicos

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 07.01 | ⏳ | Mayor | Navbar no limpia `linksContainer`: links duplicados en modo mixed | | |
| 07.02 | ⏳ | Mayor | Fuga acumulativa de listeners de document en UserMenu (x4 por sync) | | |
| 07.03 | ⏳ | Mayor | DynamicFormTest en rojo permanente: `type: 'email'` vs `'mail'` | | |
| 07.04 | ⏳ | Mayor | DynamicTable god class: 7 responsabilidades mezcladas | | ⚠️ comparte normalización con 07.13 |
| 07.05 | ⏳ | Menor | Columnas `timestamp`/`time` sin formatear (fleco de 6ade511) | | |
| 07.06 | ⏳ | Menor | Menús de toolbar/paginación no se cierran al click fuera | | |
| 07.07 | ⏳ | Menor | Columnas select ordenan por value crudo, no por label | | |
| 07.08 | ⏳ | Menor | `setSchema()` muerto con bug latente; `nextPage/prevPage` inconsistentes | | |
| 07.09 | ⏳ | Menor | Cookie de pageSize pisa la opción explícita (asimetría con density) | | |
| 07.10 | ⏳ | Menor | Id de contenido de Modal duplicado entre instancias | | ⚠️ mismo hallazgo que 06.10 |
| 07.11 | ⏳ | Menor | RelatedRecordsPanel sin paginación y con "Refrescar" inerte | | |
| 07.12 | ⏳ | Menor | `userContainer` obligatorio incluso con `showUserMenu: false` | | |
| 07.13 | ⏳ | Menor | Triple normalización de schema; `resolveContainer` clonado en 7 clases | | |
| 07.14 | ⏳ | Nit | `content()` invocado dos veces en el primer render de DynamicTabs | | |
| 07.15 | ⏳ | Nit | Selectores/URLs interpolados sin escapar en EntityEdit y Tabs | | ⚠️ solapa con 08.18 |
| 07.16 | ⏳ | Nit | Inconsistencias internas de ThemeSettingsPanel (is-active, estilos inline) | | |
| 07.17 | ⏳ | Nit | Fricciones ARIA: aria-current="false", roles duplicados, paginador div | | |
| 07.18 | ⏳ | Nit | Derivas docs puntuales (19 vs 22 runners, A1.7 ya implementado) | | |

## 08 — Frontend páginas

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 08.01 | ⏳ | Mayor | EntityEdit: flush fallido tras POST deja alta repetible (registro duplicado) | | |
| 08.02 | ⏳ | Mayor | Sin guard de doble submit en PluginConfig/UserConfig/PluginItemEdit | | |
| 08.03 | ⏳ | Mayor | Cualquier error de acción destruye la lista de PluginManager | | |
| 08.04 | ⏳ | Mayor | PluginItemEdit resuelve plugin por tabId: rompe multi-instancia | | |
| 08.05 | ⏳ | Mayor | «Reset password» sin confirmación previa en UserConfig | | |
| 08.06 | ⏳ | Mayor | PluginConfig.js: 1.449 líneas con 5 responsabilidades extraíbles | | |
| 08.07 | ⏳ | Mayor | Feedback de página reimplementado en 4 páginas contra la regla | | ⚠️ converge con 05.06 |
| 08.08 | ⏳ | Menor | `showPluginConfigPage` sin guard admin (inconsistencia de gating) | | |
| 08.09 | ⏳ | Menor | Comprobaciones `response?.ok === false` inalcanzables en PluginManager | | |
| 08.10 | ⏳ | Menor | Doble canal de error en UserConfig, uno inoperante | | |
| 08.11 | ⏳ | Menor | Login descarta los detalles de error del backend («Requerido.») | | |
| 08.12 | ⏳ | Menor | PluginItemEdit carga la colección completa para una ficha | | |
| 08.13 | ⏳ | Menor | UserManager: constructor legacy muerto, doble render, mapping duplicado | | |
| 08.14 | ⏳ | Menor | Borrar en EntityList vuelve a página 1 y resetea el orden | | |
| 08.15 | ⏳ | Menor | `loadActiveEntityOptions` silencia el fallo (target sin opciones) | | |
| 08.16 | ⏳ | Menor | Deriva docs (rutas reservadas, inventarios, recuentos, #/usuarios) | | |
| 08.17 | ⏳ | Menor | Credenciales semilla en el bundle público de Login | | |
| 08.18 | ⏳ | Nit | Selector `[name="${fieldName}"]` sin `CSS.escape` en EntityEdit | | ⚠️ solapa con 07.15 |
| 08.19 | ⏳ | Nit | `#canRollback` acepta `'t'`/`1`/`'1'` (booleano PG filtrado) | | |
| 08.20 | ⏳ | Nit | Badge de «Actualización disponible» sin colores | | |
| 08.21 | ⏳ | Nit | Avatar sin clases de tamaño en la tabla de UserManager | | |
| 08.22 | ⏳ | Nit | Indentación anómala y literales sin `t()` en PluginConfig | | |

## 09 — Plugins demo y seeders

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 09.01 | ⏳ | Mayor | AGENTS.md prohíbe `clients` mientras la demo entera lo usa | | ⚠️ editar AGENTS.md junto con 01.16/09.04 |
| 09.02 | ⏳ | Mayor | Demo no reproducible: el seeder asume instancias/relations solo en la BD real | | |
| 09.03 | ⏳ | Mayor | contact_lenses/Hooks.php copy-paste de optometries (y 3ª copia en comments) | | |
| 09.04 | ⏳ | Mayor | AGENTS.md `email`/`creation_stamp`/`is_active` vs schema real `mail` | | ⚠️ mismo fondo que 02.13 |
| 09.05 | ⏳ | Menor | README lista `sales` como plugin cuando es instancia de `orders` | | |
| 09.06 | ⏳ | Menor | `"editable": false` en comments.body: flag muerto contradicho por la UI | | |
| 09.07 | ⏳ | Menor | demoinventory/products vivos pero invisibles para el backlog | | |
| 09.08 | ⏳ | Menor | Clave técnica en español `numero` sembrada en JSONB | | |
| 09.09 | ⏳ | Menor | Panel de contact_lenses/plugin.js duplicado de optometries | | |
| 09.10 | ⏳ | Nit | Resto `client` singular en docblock de comments/plugin.js | | |
| 09.11 | ⏳ | Nit | Labels sin tilde en persons; capitalización en products | | |
| 09.12 | ⏳ | Nit | `origin: "suggested"` redundante en demoinventory | | |
| 09.13 | ⏳ | Nit | `sampleWithoutReplacement` degrada en silencio con pool corto | | |
