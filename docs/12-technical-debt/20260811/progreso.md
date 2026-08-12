# Progreso de corrección — auditoría 2026-08-11

← [Índice de esta auditoría](README.md) · [Plan de corrección](plan-correccion.md) · [Convenciones de corrección](../convenciones-correccion.md) · [Formato de esta tabla](../convenciones-progreso.md)

**Antes de tocar código, lee [`../convenciones-correccion.md`](../convenciones-correccion.md)** (reglas de sesión, commit único, formato de asunto) y [`../convenciones-progreso.md`](../convenciones-progreso.md) (qué es este fichero, columnas, leyenda de estado). Este fichero solo trae el estado real de esta auditoría concreta, no repite esas reglas.

## Resumen

| Fichero | Total | ✅ Resuelto | 🔧/⏳ Pendiente | 🚫 Descartado |
|---|---|---|---|---|
| [01](01-backend-core-auth-usuarios.md) — Core/Auth/Users | 14 | 6 | 8 | 0 |
| [02](02-backend-modelo-datos-validacion.md) — Modelo de datos | 12 | 4 | 8 | 0 |
| [03](03-backend-motor-plugins.md) — Motor de plugins | 13 | 5 | 8 | 0 |
| [04](04-backend-plugins-actualizacion-extension.md) — Plugin update/extension | 11 | 5 | 6 | 0 |
| [05](05-frontend-arquitectura-spa.md) — Arquitectura SPA | 16 | 8 | 8 | 0 |
| [06](06-frontend-toolkit-ui.md) — Toolkit UI | 12 | 7 | 5 | 0 |
| [07](07-frontend-paginas-modulos.md) — Páginas/módulos | 7 | 5 | 2 | 0 |
| **Total** | **85** | **40** | **45** | **0** |

Los 5 hallazgos de la Fase 1 ("antes de la defensa") corresponden a: **P1**=`01.01`, **P2**=`07.01`, **P3**=`07.02`, **P4**=`04.01`, **P5**=`04.03`. No los cuentes dos veces al planificar las sesiones de la Fase 2.

---

## 01 — Core / Auth / Users

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 01.01 | ✅ | Crítico | `password_hash` filtrado en respuestas de usuario (= **P1**) | `2e5d117` | ⚠️ Fallo preexistente no relacionado detectado de pasada (test de roles en `UserController::update`), resuelto luego en `04.01`. |
| 01.02 | ✅ | Mayor | No hay `AuthorizationService`; check admin duplicado en 3 controladores | `766f8dc` | |
| 01.03 | ✅ | Mayor | `AuthController` no usa `UserRepository`, SQL a mano | `1765439` | |
| 01.04 | ✅ | Mayor | `UserRepository::update()` no captura `PDOException` | `28f066f` | |
| 01.05 | ✅ | Mayor | `UserSeeder` docblock dice auto-run al arrancar; es falso | `b38310a` | |
| 01.06 | ✅ | Mayor | `JwtService::base64UrlDecode()` padding mal calculado | `b99b169` | |
| 01.07 | ⏳ | Menor | Prefijos protegidos en lista paralela a `routes.php` | | |
| 01.08 | ⏳ | Menor | `UserController::destroy()` usa bandera en vez de returns | | |
| 01.09 | ⏳ | Menor | Normalización de avatar duplicada 3 veces | | |
| 01.10 | ⏳ | Menor | Desfases doc/código (login camelCase, refresh_token, endpoint password) | | |
| 01.11 | ⏳ | Menor | No existe `POST /api/v1/users` (probablemente intencional) | | |
| 01.12 | ⏳ | Nit | `RuntimePathNormalizer` más fragmentado de lo necesario | | |
| 01.13 | ⏳ | Nit | `JWT_SECRET` default `'changeme'` fail-open | | |
| 01.14 | ⏳ | Nit | `NOSONAR` en instanciación dinámica del Router | | |

## 02 — Modelo de datos / Validación

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 02.01 | ✅ | Mayor | `EntityService` sin transacción en create/update | `10ab801` | |
| 02.02 | ✅ | Mayor | `ValidationService` no rechaza campos no declarados | `691f3d2` | |
| 02.03 | ✅ | Mayor | `TimestampFieldValidator` no valida formato; el test blinda el bug | `6f59d18` | |
| 02.04 | ✅ | Mayor | `StringFieldValidator`/`TextFieldValidator` duplicados al 100% | `a1d8b3d` | |
| 02.05 | ⏳ | Menor | Comentarios obsoletos referencian `plugin_entity_metadata` | | |
| 02.06 | ⏳ | Menor | `ORDER BY schema_version` sobre columna `UNIQUE(slug)` — código muerto | | |
| 02.07 | ⏳ | Menor | `SchemaFieldExtractor` vs `sortableSchemaFields()` inconsistentes | | |
| 02.08 | ⏳ | Menor | `plugins.schema_json` sin `CHECK` de estructura | | |
| 02.09 | ⏳ | Menor | `MigrationIdempotenceTest` con ruta Windows hardcodeada, no portable | | |
| 02.10 | ⏳ | Menor | Tests referencian migraciones inexistentes (`002_core.sql`, `010_drop...`) | | |
| 02.11 | ⏳ | Menor | `EntityController` create() vs update()/destroy() — asimetría de excepciones | | |
| 02.12 | ⏳ | Menor | Sin validador para `type: "uuid"` | | |

## 03 — Motor de plugins (núcleo)

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 03.01 | ✅ | Mayor | Tabla `plugin_hooks` desconectada del runtime real | `36e96a7` | ⚠️ Fallo preexistente no relacionado: `CommentsPluginTest.php::Comentarios tab does not appear for non-target entities` falla por estado de la BD de desarrollo (`comments` con `target_entity: '*'`), no por código; ya fallaba antes de esta sesión, fuera de alcance. |
| 03.02 | ✅ | Mayor | 3 tests sin `exit()` — falso verde en runner agrupado | `156636c` | ⚠️ Mismo preexistente de `03.01`, no relacionado. |
| 03.03 | ✅ | Mayor | `PluginClassLoader` instancia Hooks vs Lifecycle de forma distinta | `1a8f4a7` | ⚠️ Mismo preexistente de `03.01`, no relacionado. |
| 03.04 | ⏳ | Menor | Duplicación `clients`/`products` (~90 líneas) | | |
| 03.05 | ⏳ | Menor | `EntityController` con `HookDispatcher` por defecto oculto | | |
| 03.06 | ⏳ | Menor | `PluginBootTest`/`PluginHookRegistrarTest` casi duplicados | | |
| 03.07 | ⏳ | Menor | `ClientsPluginTest` usa `assert()` nativo de PHP | | |
| 03.08 | ✅ | Menor | Docblock de `HookDispatcher::register()` engañoso (by reference) | `8e1aaa9` | |
| 03.09 | ✅ | Menor | `products/Lifecycle.php` import redundante de su propio namespace | `6356ce0` | |
| 03.10 | ⏳ | Menor | `PluginLifecycleInterface` no refleja `onUpdate`/`onRollback` | | |
| 03.11 | ⏳ | Menor | `PluginSchemaReader` rechaza `"fields": {}` vacío incorrectamente | | |
| 03.12 | ⏳ | Menor | before/after solo por prefijo de string, sin red de seguridad | | |
| 03.13 | ⏳ | Nit | 3 capas de carpetas para ~21 clases — ceremonia pesada | | |

## 04 — Plugin update / extension / config

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 04.01 | ✅ | Crítico | `custom_fields` cambia de significado tras `saveConfig()` (= **P4**) | `dc57714` | |
| 04.02 | ✅ | Mayor | Update de plugins `extension` es no-op silencioso de schema | `a87b1ac` | |
| 04.03 | ✅ | Mayor | Comments: sin control de propiedad en `PUT` (= **P5**) | `2250b73` | |
| 04.04 | ✅ | Mayor | Atomicidad inconsistente: `syncAll`/`activate`/`deactivate` sin transacción | `307e7be` | |
| 04.05 | ✅ | Mayor | `PluginAdministrationService` no es fachada limpia (closures) | `d3f6e38` | |
| 04.06 | ⏳ | Menor | `ensureInstalledTypeMatchesManifest()` duplicado en 2 servicios | | |
| 04.07 | ⏳ | Menor | `normalizeForComparison` duplicado entre 2 clases hermanas | | |
| 04.08 | ⏳ | Menor | Normalización de `fields` reimplementada en 2 capas distintas | | |
| 04.09 | ⏳ | Menor | `ConfigurationController` no cachea `RequestFactory` | | |
| 04.10 | ⏳ | Menor | `plugin_update_history` sin política de retención | | |
| 04.11 | ⏳ | Nit | `demoinventory` doble ping sin propósito | | |

## 05 — Arquitectura SPA

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 05.01 | ✅ | Mayor | `handleError()` nunca detecta errores de red reales | `c93c1c4` | ⚠️ toca el mismo fichero que 05.02 y 05.05 |
| 05.02 | ✅ | Mayor | Triple canal redundante para notificaciones | `bbba45a` | ⚠️ toca el mismo fichero que 05.01/05.05 (`UiResilienceService.js`) y 05.03/05.07 (`AppController.js`) |
| 05.03 | ✅ | Mayor | Parser `entity-record:` duplicado en 2 ficheros | `bd3f32d` | ⚠️ toca el mismo fichero que 05.02/05.07 (`AppController.js`) y 05.06 (`RouteMapController.js`) |
| 05.04 | ✅ | Mayor | Persistencia de usuario duplicada `StateModel`/`SessionModel` | `b16b534` | |
| 05.05 | ✅ | Mayor | `confirm()` llama `modal.show()` dos veces, frágil | `8be4e64` | ⚠️ toca el mismo fichero que 05.01/05.02 |
| 05.06 | ✅ | Mayor | `HASH_ROUTE_MAP` mayoritariamente decorativo | `902f9b3` | ⚠️ toca el mismo fichero que 05.03 |
| 05.07 | ✅ | Mayor | Manejo de 401 inconsistente entre llamadas hermanas | `79b6822` | ⚠️ toca el mismo fichero que 05.02/05.03 (`AppController.js`) |
| 05.08 | ⏳ | Menor | `renderLogin()` no resincroniza hash tras expirar sesión | | |
| 05.09 | ✅ | Menor | `RouteController.resolveFromHash()` código muerto | `a11d6e4` | |
| 05.10 | ⏳ | Menor | Ruta `'ui'` reconocida pero sin handler | | |
| 05.11 | ⏳ | Menor | Token de ruta de plugins rompe convención `namespace:param` | | |
| 05.12 | ⏳ | Menor | `AppState` "god object" | | |
| 05.13 | ⏳ | Menor | `RouteController.navigate()` API dual sin uso real | | |
| 05.14 | ⏳ | Nit | `ThemeModel` fallback `'light'` como string mágico | | |
| 05.15 | ⏳ | Nit | Host flotante con Tailwind + inline duplicados | | |
| 05.16 | ⏳ | Nit | Doc `renderizado-dinamico.md` nombra componentes obsoletos | | |

## 06 — Toolkit UI

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 06.01 | ✅ | Mayor | Fuga de DOM + `id` duplicados en cada modal de confirmación | `f0b2300` | |
| 06.02 | ✅ | Mayor | `label`/`for` roto en `Login.js` | `ca300cb` | |
| 06.03 | ✅ | Mayor | Clase Tailwind base duplicada en 9 ficheros `Input*.js` | `be6da3d` | |
| 06.04 | ✅ | Mayor | `setError()` no quita clase base — borde de error gris en reposo | `fcd891c` | |
| 06.05 | ⏳ | Menor | `variant: 'ghost'` de `Button` inerte | | |
| 06.06 | ✅ | Menor | `id` del título del modal fijado dos veces | `f0b2300` | ⚠️ Resuelto como efecto colateral de 06.01 (id ahora generado dinámicamente) |
| 06.07 | ✅ | Menor | Rama muerta en `Breadcrumb.resolveItems` | `364d7a7` | |
| 06.08 | ⏳ | Menor | Componentes registrados sin uso real (`inputRadio`, `inputTime`...) | | ⚠️ `inputTime` ya no aplica: conectado en `07.04` (`DynamicForm` ahora lo usa para `type: 'time'`). Queda `inputRadio` y el resto. |
| 06.09 | ⏳ | Menor | `PageLayout` acoplado al DOM interno de `PageHeaderComponent` | | |
| 06.10 | ⏳ | Nit | `setHtml()`/`options.html` sin uso, vector XSS latente | | |
| 06.11 | ✅ | Nit | `aria-disabled` redundante con `disabled` nativo | `21b48e3` | |
| 06.12 | ⏳ | Nit | Breadcrumb dropdown solo cierra con `mouseleave` | | |

## 07 — Páginas / módulos de negocio

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 07.01 | ✅ | Crítico | `EntityEdit.submit()` bloquea el formulario tras error (= **P2**) | `199e957` | |
| 07.02 | ✅ | Crítico | Botones `PluginManager`/`PluginConfig` rotos tras usar el toolbar (= **P3**) | `beb2da7` | |
| 07.03 | ✅ | Mayor | `normalizeRoleList()` duplicada `UserManager`/`UserConfig` | `d70bfa9` | |
| 07.04 | ✅ | Mayor | `DynamicForm` sin rama `number`/`time`; `inputTime` sin conectar | `4b689aa` | ⚠️ Como efecto colateral, `inputTime` deja de estar sin uso (ver nota en 06.08) |
| 07.05 | ✅ | Mayor | Dos patrones distintos para página CRUD con formulario | `3052724` | |
| 07.06 | ⏳ | Menor | `Login.js` sin guarda de re-entrada | | |
| 07.07 | ⏳ | Menor | `UserConfig.js` (908 líneas), demasiada responsabilidad | | |
