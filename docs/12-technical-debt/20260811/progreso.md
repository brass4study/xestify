# Progreso de corrección — auditoría 2026-08-11

← [Índice de esta auditoría](README.md) · [Plan de corrección](plan-correccion.md)

Este fichero **sí se actualiza** con el tiempo (a diferencia de los informes `00`-`07`, que son la fotografía inmutable de la auditoría). Es el mecanismo para que sesiones nuevas sepan qué está ya resuelto sin releer los informes completos ni repetir trabajo.

## Cómo usarlo

- **Al empezar una sesión de corrección:** lee este fichero primero (o la sección del subsistema que toque) antes de tocar código. Si una fila ya está `✅ Resuelto`, no la repitas — si crees que el arreglo fue incompleto, dilo y ábrelo como nota, no como si no se hubiera tocado.
- **Al terminar cada hallazgo:** actualiza su fila — `Estado`, `Commit` (hash corto) y `Notas` si hay algo relevante (p. ej. "arreglo parcial, falta X" o por qué se descartó).
- **Convención de commit:** incluye el ID entre corchetes en el asunto, p. ej. `fix: [01.1] password_hash ya no se filtra en /api/v1/users`. Así `git log --oneline --grep "\[04"` encuentra todo lo tocado de un fichero de un vistazo, sin depender de este documento si alguna vez se desincroniza.
- **Si dos sesiones podrían solaparse** (p. ej. vas a tocar `EntityController.php` en dos hallazgos de ficheros distintos): revisa aquí si el otro hallazgo ya tiene commit; si no, indícalo en `Notas` con `⚠️ toca el mismo fichero que 0X.Y` para que la siguiente sesión no pise el diff a medio hacer.

**Leyenda de estado:** ⏳ Pendiente · 🔧 En progreso · ✅ Resuelto · 🚫 Descartado (con motivo en Notas)

## Resumen

| Fichero | Total | ✅ Resuelto | 🔧/⏳ Pendiente | 🚫 Descartado |
|---|---|---|---|---|
| [01](01-backend-core-auth-usuarios.md) — Core/Auth/Users | 14 | 1 | 13 | 0 |
| [02](02-backend-modelo-datos-validacion.md) — Modelo de datos | 12 | 0 | 12 | 0 |
| [03](03-backend-motor-plugins.md) — Motor de plugins | 13 | 0 | 13 | 0 |
| [04](04-backend-plugins-actualizacion-extension.md) — Plugin update/extension | 11 | 0 | 11 | 0 |
| [05](05-frontend-arquitectura-spa.md) — Arquitectura SPA | 16 | 0 | 16 | 0 |
| [06](06-frontend-toolkit-ui.md) — Toolkit UI | 12 | 0 | 12 | 0 |
| [07](07-frontend-paginas-modulos.md) — Páginas/módulos | 7 | 0 | 7 | 0 |
| **Total** | **85** | **1** | **84** | **0** |

Los 5 hallazgos de la Fase 1 ("antes de la defensa") corresponden a: **P1**=`01.1`, **P2**=`07.1`, **P3**=`07.2`, **P4**=`04.1`, **P5**=`04.3`. No los cuentes dos veces al planificar las sesiones de la Fase 2.

---

## 01 — Core / Auth / Users

| ID | Sev. | Resumen | Estado | Commit | Notas |
|---|---|---|---|---|---|
| 01.1 | Crítico | `password_hash` filtrado en respuestas de usuario (= **P1**) | ✅ | `999e17e` | `UserController` añade `sanitizeUser()` y lo aplica en `me()`, `updateMe()`, `listUsers()`, `show()`, `update()`. El repositorio sigue devolviendo `password_hash` (lo necesita `passwordMatches()` para verificar contraseña actual); el filtrado es solo en el borde de respuesta HTTP. Tests: `UserControllerTest.php` ahora comprueba ausencia de `password_hash` en `me`, `listUsers`, `update`. ⚠️ Detectado fallo preexistente no relacionado en `UserController::update lets admins edit user roles` ("Roles should be updated") — falla igual antes y después de este cambio, no se toca aquí. |
| 01.2 | Mayor | No hay `AuthorizationService`; check admin duplicado en 3 controladores | ⏳ | | |
| 01.3 | Mayor | `AuthController` no usa `UserRepository`, SQL a mano | ⏳ | | |
| 01.4 | Mayor | `UserRepository::update()` no captura `PDOException` | ⏳ | | |
| 01.5 | Mayor | `UserSeeder` docblock dice auto-run al arrancar; es falso | ⏳ | | |
| 01.6 | Mayor | `JwtService::base64UrlDecode()` padding mal calculado | ⏳ | | |
| 01.7 | Menor | Prefijos protegidos en lista paralela a `routes.php` | ⏳ | | |
| 01.8 | Menor | `UserController::destroy()` usa bandera en vez de returns | ⏳ | | |
| 01.9 | Menor | Normalización de avatar duplicada 3 veces | ⏳ | | |
| 01.10 | Menor | Desfases doc/código (login camelCase, refresh_token, endpoint password) | ⏳ | | |
| 01.11 | Menor | No existe `POST /api/v1/users` (probablemente intencional) | ⏳ | | |
| 01.12 | Nit | `RuntimePathNormalizer` más fragmentado de lo necesario | ⏳ | | |
| 01.13 | Nit | `JWT_SECRET` default `'changeme'` fail-open | ⏳ | | |
| 01.14 | Nit | `NOSONAR` en instanciación dinámica del Router | ⏳ | | |

## 02 — Modelo de datos / Validación

| ID | Sev. | Resumen | Estado | Commit | Notas |
|---|---|---|---|---|---|
| 02.1 | Mayor | `EntityService` sin transacción en create/update | ⏳ | | |
| 02.2 | Mayor | `ValidationService` no rechaza campos no declarados | ⏳ | | |
| 02.3 | Mayor | `TimestampFieldValidator` no valida formato; el test blinda el bug | ⏳ | | |
| 02.4 | Mayor | `StringFieldValidator`/`TextFieldValidator` duplicados al 100% | ⏳ | | |
| 02.5 | Menor | Comentarios obsoletos referencian `plugin_entity_metadata` | ⏳ | | |
| 02.6 | Menor | `ORDER BY schema_version` sobre columna `UNIQUE(slug)` — código muerto | ⏳ | | |
| 02.7 | Menor | `SchemaFieldExtractor` vs `sortableSchemaFields()` inconsistentes | ⏳ | | |
| 02.8 | Menor | `plugins.schema_json` sin `CHECK` de estructura | ⏳ | | |
| 02.9 | Menor | `MigrationIdempotenceTest` con ruta Windows hardcodeada, no portable | ⏳ | | |
| 02.10 | Menor | Tests referencian migraciones inexistentes (`002_core.sql`, `010_drop...`) | ⏳ | | |
| 02.11 | Menor | `EntityController` create() vs update()/destroy() — asimetría de excepciones | ⏳ | | |
| 02.12 | Menor | Sin validador para `type: "uuid"` | ⏳ | | |

## 03 — Motor de plugins (núcleo)

| ID | Sev. | Resumen | Estado | Commit | Notas |
|---|---|---|---|---|---|
| 03.1 | Mayor | Tabla `plugin_hooks` desconectada del runtime real | ⏳ | | |
| 03.2 | Mayor | 3 tests sin `exit()` — falso verde en runner agrupado | ⏳ | | |
| 03.3 | Mayor | `PluginClassLoader` instancia Hooks vs Lifecycle de forma distinta | ⏳ | | |
| 03.4 | Menor | Duplicación `clients`/`products` (~90 líneas) | ⏳ | | |
| 03.5 | Menor | `EntityController` con `HookDispatcher` por defecto oculto | ⏳ | | |
| 03.6 | Menor | `PluginBootTest`/`PluginHookRegistrarTest` casi duplicados | ⏳ | | |
| 03.7 | Menor | `ClientsPluginTest` usa `assert()` nativo de PHP | ⏳ | | |
| 03.8 | Menor | Docblock de `HookDispatcher::register()` engañoso (by reference) | ⏳ | | |
| 03.9 | Menor | `products/Lifecycle.php` import redundante de su propio namespace | ⏳ | | |
| 03.10 | Menor | `PluginLifecycleInterface` no refleja `onUpdate`/`onRollback` | ⏳ | | |
| 03.11 | Menor | `PluginSchemaReader` rechaza `"fields": {}` vacío incorrectamente | ⏳ | | |
| 03.12 | Menor | before/after solo por prefijo de string, sin red de seguridad | ⏳ | | |
| 03.13 | Nit | 3 capas de carpetas para ~21 clases — ceremonia pesada | ⏳ | | |

## 04 — Plugin update / extension / config

| ID | Sev. | Resumen | Estado | Commit | Notas |
|---|---|---|---|---|---|
| 04.1 | Crítico | `custom_fields` cambia de significado tras `saveConfig()` (= **P4**) | ⏳ | | |
| 04.2 | Mayor | Update de plugins `extension` es no-op silencioso de schema | ⏳ | | |
| 04.3 | Mayor | Comments: sin control de propiedad en `PUT` (= **P5**) | ⏳ | | |
| 04.4 | Mayor | Atomicidad inconsistente: `syncAll`/`activate`/`deactivate` sin transacción | ⏳ | | |
| 04.5 | Mayor | `PluginAdministrationService` no es fachada limpia (closures) | ⏳ | | |
| 04.6 | Menor | `ensureInstalledTypeMatchesManifest()` duplicado en 2 servicios | ⏳ | | |
| 04.7 | Menor | `normalizeForComparison` duplicado entre 2 clases hermanas | ⏳ | | |
| 04.8 | Menor | Normalización de `fields` reimplementada en 2 capas distintas | ⏳ | | |
| 04.9 | Menor | `ConfigurationController` no cachea `RequestFactory` | ⏳ | | |
| 04.10 | Menor | `plugin_update_history` sin política de retención | ⏳ | | |
| 04.11 | Nit | `demoinventory` doble ping sin propósito | ⏳ | | |

## 05 — Arquitectura SPA

| ID | Sev. | Resumen | Estado | Commit | Notas |
|---|---|---|---|---|---|
| 05.1 | Mayor | `handleError()` nunca detecta errores de red reales | ⏳ | | |
| 05.2 | Mayor | Triple canal redundante para notificaciones | ⏳ | | |
| 05.3 | Mayor | Parser `entity-record:` duplicado en 2 ficheros | ⏳ | | |
| 05.4 | Mayor | Persistencia de usuario duplicada `StateModel`/`SessionModel` | ⏳ | | |
| 05.5 | Mayor | `confirm()` llama `modal.show()` dos veces, frágil | ⏳ | | |
| 05.6 | Mayor | `HASH_ROUTE_MAP` mayoritariamente decorativo | ⏳ | | |
| 05.7 | Mayor | Manejo de 401 inconsistente entre llamadas hermanas | ⏳ | | |
| 05.8 | Menor | `renderLogin()` no resincroniza hash tras expirar sesión | ⏳ | | |
| 05.9 | Menor | `RouteController.resolveFromHash()` código muerto | ⏳ | | |
| 05.10 | Menor | Ruta `'ui'` reconocida pero sin handler | ⏳ | | |
| 05.11 | Menor | Token de ruta de plugins rompe convención `namespace:param` | ⏳ | | |
| 05.12 | Menor | `AppState` "god object" | ⏳ | | |
| 05.13 | Menor | `RouteController.navigate()` API dual sin uso real | ⏳ | | |
| 05.14 | Nit | `ThemeModel` fallback `'light'` como string mágico | ⏳ | | |
| 05.15 | Nit | Host flotante con Tailwind + inline duplicados | ⏳ | | |
| 05.16 | Nit | Doc `renderizado-dinamico.md` nombra componentes obsoletos | ⏳ | | |

## 06 — Toolkit UI

| ID | Sev. | Resumen | Estado | Commit | Notas |
|---|---|---|---|---|---|
| 06.1 | Mayor | Fuga de DOM + `id` duplicados en cada modal de confirmación | ⏳ | | |
| 06.2 | Mayor | `label`/`for` roto en `Login.js` | ⏳ | | |
| 06.3 | Mayor | Clase Tailwind base duplicada en 9 ficheros `Input*.js` | ⏳ | | |
| 06.4 | Mayor | `setError()` no quita clase base — borde de error gris en reposo | ⏳ | | |
| 06.5 | Menor | `variant: 'ghost'` de `Button` inerte | ⏳ | | |
| 06.6 | Menor | `id` del título del modal fijado dos veces | ⏳ | | |
| 06.7 | Menor | Rama muerta en `Breadcrumb.resolveItems` | ⏳ | | |
| 06.8 | Menor | Componentes registrados sin uso real (`inputRadio`, `inputTime`...) | ⏳ | | |
| 06.9 | Menor | `PageLayout` acoplado al DOM interno de `PageHeaderComponent` | ⏳ | | |
| 06.10 | Nit | `setHtml()`/`options.html` sin uso, vector XSS latente | ⏳ | | |
| 06.11 | Nit | `aria-disabled` redundante con `disabled` nativo | ⏳ | | |
| 06.12 | Nit | Breadcrumb dropdown solo cierra con `mouseleave` | ⏳ | | |

## 07 — Páginas / módulos de negocio

| ID | Sev. | Resumen | Estado | Commit | Notas |
|---|---|---|---|---|---|
| 07.1 | Crítico | `EntityEdit.submit()` bloquea el formulario tras error (= **P2**) | ⏳ | | |
| 07.2 | Crítico | Botones `PluginManager`/`PluginConfig` rotos tras usar el toolbar (= **P3**) | ⏳ | | |
| 07.3 | Mayor | `normalizeRoleList()` duplicada `UserManager`/`UserConfig` | ⏳ | | |
| 07.4 | Mayor | `DynamicForm` sin rama `number`/`time`; `inputTime` sin conectar | ⏳ | | |
| 07.5 | Mayor | Dos patrones distintos para página CRUD con formulario | ⏳ | | |
| 07.6 | Menor | `Login.js` sin guarda de re-entrada | ⏳ | | |
| 07.7 | Menor | `UserConfig.js` (908 líneas), demasiada responsabilidad | ⏳ | | |
