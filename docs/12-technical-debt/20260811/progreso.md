# Progreso de corrección — auditoría 2026-08-11

← [Índice de esta auditoría](README.md) · [Plan de corrección](plan-correccion.md) · [Convenciones de corrección](../convenciones-correccion.md) · [Formato de esta tabla](../convenciones-progreso.md)

**Antes de tocar código, lee [`../convenciones-correccion.md`](../convenciones-correccion.md)** (reglas de sesión, commit único, formato de asunto) y [`../convenciones-progreso.md`](../convenciones-progreso.md) (qué es este fichero, columnas, leyenda de estado). Este fichero solo trae el estado real de esta auditoría concreta, no repite esas reglas.

## Resumen

| Fichero | Total | ✅ Resuelto | 🔧/⏳ Pendiente | 🚫 Descartado |
|---|---|---|---|---|
| [01](01-backend-core-auth-usuarios.md) — Core/Auth/Users | 14 | 1 | 13 | 0 |
| [02](02-backend-modelo-datos-validacion.md) — Modelo de datos | 12 | 0 | 12 | 0 |
| [03](03-backend-motor-plugins.md) — Motor de plugins | 13 | 0 | 13 | 0 |
| [04](04-backend-plugins-actualizacion-extension.md) — Plugin update/extension | 11 | 1 | 10 | 0 |
| [05](05-frontend-arquitectura-spa.md) — Arquitectura SPA | 16 | 0 | 16 | 0 |
| [06](06-frontend-toolkit-ui.md) — Toolkit UI | 12 | 0 | 12 | 0 |
| [07](07-frontend-paginas-modulos.md) — Páginas/módulos | 7 | 2 | 5 | 0 |
| **Total** | **85** | **4** | **81** | **0** |

Los 5 hallazgos de la Fase 1 ("antes de la defensa") corresponden a: **P1**=`01.01`, **P2**=`07.01`, **P3**=`07.02`, **P4**=`04.01`, **P5**=`04.03`. No los cuentes dos veces al planificar las sesiones de la Fase 2.

---

## 01 — Core / Auth / Users

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 01.01 | ✅ | Crítico | `password_hash` filtrado en respuestas de usuario (= **P1**) | `cdd1982` | `UserController` añade `sanitizeUser()` y lo aplica en `me()`, `updateMe()`, `listUsers()`, `show()`, `update()`. El repositorio sigue devolviendo `password_hash` (lo necesita `passwordMatches()` para verificar contraseña actual); el filtrado es solo en el borde de respuesta HTTP. Tests: `UserControllerTest.php` ahora comprueba ausencia de `password_hash` en `me`, `listUsers`, `update`. ⚠️ Detectado fallo preexistente no relacionado en `UserController::update lets admins edit user roles` ("Roles should be updated") — falla igual antes y después de este cambio, no se toca aquí. |
| 01.02 | ⏳ | Mayor | No hay `AuthorizationService`; check admin duplicado en 3 controladores | | |
| 01.03 | ⏳ | Mayor | `AuthController` no usa `UserRepository`, SQL a mano | | |
| 01.04 | ⏳ | Mayor | `UserRepository::update()` no captura `PDOException` | | |
| 01.05 | ⏳ | Mayor | `UserSeeder` docblock dice auto-run al arrancar; es falso | | |
| 01.06 | ⏳ | Mayor | `JwtService::base64UrlDecode()` padding mal calculado | | |
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
| 02.01 | ⏳ | Mayor | `EntityService` sin transacción en create/update | | |
| 02.02 | ⏳ | Mayor | `ValidationService` no rechaza campos no declarados | | |
| 02.03 | ⏳ | Mayor | `TimestampFieldValidator` no valida formato; el test blinda el bug | | |
| 02.04 | ⏳ | Mayor | `StringFieldValidator`/`TextFieldValidator` duplicados al 100% | | |
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
| 03.01 | ⏳ | Mayor | Tabla `plugin_hooks` desconectada del runtime real | | |
| 03.02 | ⏳ | Mayor | 3 tests sin `exit()` — falso verde en runner agrupado | | |
| 03.03 | ⏳ | Mayor | `PluginClassLoader` instancia Hooks vs Lifecycle de forma distinta | | |
| 03.04 | ⏳ | Menor | Duplicación `clients`/`products` (~90 líneas) | | |
| 03.05 | ⏳ | Menor | `EntityController` con `HookDispatcher` por defecto oculto | | |
| 03.06 | ⏳ | Menor | `PluginBootTest`/`PluginHookRegistrarTest` casi duplicados | | |
| 03.07 | ⏳ | Menor | `ClientsPluginTest` usa `assert()` nativo de PHP | | |
| 03.08 | ⏳ | Menor | Docblock de `HookDispatcher::register()` engañoso (by reference) | | |
| 03.09 | ⏳ | Menor | `products/Lifecycle.php` import redundante de su propio namespace | | |
| 03.10 | ⏳ | Menor | `PluginLifecycleInterface` no refleja `onUpdate`/`onRollback` | | |
| 03.11 | ⏳ | Menor | `PluginSchemaReader` rechaza `"fields": {}` vacío incorrectamente | | |
| 03.12 | ⏳ | Menor | before/after solo por prefijo de string, sin red de seguridad | | |
| 03.13 | ⏳ | Nit | 3 capas de carpetas para ~21 clases — ceremonia pesada | | |

## 04 — Plugin update / extension / config

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 04.01 | ⏳ | Crítico | `custom_fields` cambia de significado tras `saveConfig()` (= **P4**) | | |
| 04.02 | ⏳ | Mayor | Update de plugins `extension` es no-op silencioso de schema | | |
| 04.03 | ✅ | Mayor | Comments: sin control de propiedad en `PUT` (= **P5**) | `c258dc2` | `ExtensionPluginContentService::normalizeContentBySchema()` ya no recalcula `author_id` en `update` (parámetro `$isUpdate`); `author_id` queda inmutable tras la creación. `PluginExtensionController` añade `guardOwnership()` en `update()`/`delete()`: compara `content.author_id` con `request.user.sub` y responde 403 si no coincide (permisivo si el item no tiene concepto de autoría). Sin excepción para admin — no estaba en el alcance del hallazgo. Tests: 3 nuevos en `CommentsPluginTest.php` (author_id inmutable ante spoofing, PUT ajeno → 403 sin tocar contenido, DELETE ajeno → 403). Confirmado por reversión manual: 2 de los 3 tests nuevos fallan sin el fix. |
| 04.04 | ⏳ | Mayor | Atomicidad inconsistente: `syncAll`/`activate`/`deactivate` sin transacción | | |
| 04.05 | ⏳ | Mayor | `PluginAdministrationService` no es fachada limpia (closures) | | |
| 04.06 | ⏳ | Menor | `ensureInstalledTypeMatchesManifest()` duplicado en 2 servicios | | |
| 04.07 | ⏳ | Menor | `normalizeForComparison` duplicado entre 2 clases hermanas | | |
| 04.08 | ⏳ | Menor | Normalización de `fields` reimplementada en 2 capas distintas | | |
| 04.09 | ⏳ | Menor | `ConfigurationController` no cachea `RequestFactory` | | |
| 04.10 | ⏳ | Menor | `plugin_update_history` sin política de retención | | |
| 04.11 | ⏳ | Nit | `demoinventory` doble ping sin propósito | | |

## 05 — Arquitectura SPA

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 05.01 | ⏳ | Mayor | `handleError()` nunca detecta errores de red reales | | |
| 05.02 | ⏳ | Mayor | Triple canal redundante para notificaciones | | |
| 05.03 | ⏳ | Mayor | Parser `entity-record:` duplicado en 2 ficheros | | |
| 05.04 | ⏳ | Mayor | Persistencia de usuario duplicada `StateModel`/`SessionModel` | | |
| 05.05 | ⏳ | Mayor | `confirm()` llama `modal.show()` dos veces, frágil | | |
| 05.06 | ⏳ | Mayor | `HASH_ROUTE_MAP` mayoritariamente decorativo | | |
| 05.07 | ⏳ | Mayor | Manejo de 401 inconsistente entre llamadas hermanas | | |
| 05.08 | ⏳ | Menor | `renderLogin()` no resincroniza hash tras expirar sesión | | |
| 05.09 | ⏳ | Menor | `RouteController.resolveFromHash()` código muerto | | |
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
| 06.01 | ⏳ | Mayor | Fuga de DOM + `id` duplicados en cada modal de confirmación | | |
| 06.02 | ⏳ | Mayor | `label`/`for` roto en `Login.js` | | |
| 06.03 | ⏳ | Mayor | Clase Tailwind base duplicada en 9 ficheros `Input*.js` | | |
| 06.04 | ⏳ | Mayor | `setError()` no quita clase base — borde de error gris en reposo | | |
| 06.05 | ⏳ | Menor | `variant: 'ghost'` de `Button` inerte | | |
| 06.06 | ⏳ | Menor | `id` del título del modal fijado dos veces | | |
| 06.07 | ⏳ | Menor | Rama muerta en `Breadcrumb.resolveItems` | | |
| 06.08 | ⏳ | Menor | Componentes registrados sin uso real (`inputRadio`, `inputTime`...) | | |
| 06.09 | ⏳ | Menor | `PageLayout` acoplado al DOM interno de `PageHeaderComponent` | | |
| 06.10 | ⏳ | Nit | `setHtml()`/`options.html` sin uso, vector XSS latente | | |
| 06.11 | ⏳ | Nit | `aria-disabled` redundante con `disabled` nativo | | |
| 06.12 | ⏳ | Nit | Breadcrumb dropdown solo cierra con `mouseleave` | | |

## 07 — Páginas / módulos de negocio

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 07.01 | ✅ | Crítico | `EntityEdit.submit()` bloquea el formulario tras error (= **P2**) | `9f5571b` | El `return` de la rama de validación fallida estaba fuera del `try/finally` que resetea `#isSubmitting`; ahora todo el cuerpo (validación incluida) vive dentro del `try`. Test nuevo en `EntityEditTest.html`: envía con "name" vacío (falla validación, 0 llamadas POST), corrige y reenvía (debe llegar a la API). Verificado con Playwright contra Apache real (`http://127.0.0.1/xestify/`): sin el fix, el segundo intento nunca llega al backend (botón atascado); con el fix, 18/18 tests pasan. |
| 07.02 | ✅ | Crítico | Botones `PluginManager`/`PluginConfig` rotos tras usar el toolbar (= **P3**) | `ad86222` | Ambos ficheros creaban los botones de acción con `onClick: () => {}` y los enganchaban una sola vez por `querySelectorAll` tras el primer render, patrón que `DynamicTable.render()` invalida en cuanto el usuario toca densidad/columnas/orden (rebuild interno del DOM sin avisar a la página). Arreglo: pasar el handler real directamente en `onClick` al construir el botón (mismo patrón que ya usa `EntityList`), eliminando el binding posterior. `PluginManager`: nuevo método `#handlePluginAction()` centraliza el switch por `action`; se elimina `#bindActionButton()`. `PluginConfig`: `buildRowActionButton()` recibe el `onClick` como parámetro; se elimina `bindTableEvents()`. Tests nuevos en `PluginManagerTest.html` y `PluginConfigTest.html`: disparan un cambio de densidad de la tabla y comprueban que los botones siguen funcionando. Verificado con Playwright contra Apache real: sin el fix, ambos tests nuevos fallan (el PUT/reordenado no llega); con el fix, 21/21 y 8/8 pasan. También verificado el spec E2E real `plugin-manager.spec.js` (login + activar/desactivar), sigue en verde. |
| 07.03 | ⏳ | Mayor | `normalizeRoleList()` duplicada `UserManager`/`UserConfig` | | |
| 07.04 | ⏳ | Mayor | `DynamicForm` sin rama `number`/`time`; `inputTime` sin conectar | | |
| 07.05 | ⏳ | Mayor | Dos patrones distintos para página CRUD con formulario | | |
| 07.06 | ⏳ | Menor | `Login.js` sin guarda de re-entrada | | |
| 07.07 | ⏳ | Menor | `UserConfig.js` (908 líneas), demasiada responsabilidad | | |
