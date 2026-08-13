# Progreso de corrección — auditoría 2026-08-11

← [Índice de esta auditoría](README.md) · [Plan de corrección](plan-correccion.md) · [Convenciones de corrección](../convenciones-correccion.md) · [Formato de esta tabla](../convenciones-progreso.md)

**Antes de tocar código, lee [`../convenciones-correccion.md`](../convenciones-correccion.md)** (reglas de sesión, commit único, formato de asunto) y [`../convenciones-progreso.md`](../convenciones-progreso.md) (qué es este fichero, columnas, leyenda de estado). Este fichero solo trae el estado real de esta auditoría concreta, no repite esas reglas.

## Resumen

| Fichero | Total | ✅ Resuelto | 🔧/⏳ Pendiente | 🚫 Descartado |
|---|---|---|---|---|
| [01](01-backend-core-auth-usuarios.md) — Core/Auth/Users | 14 | 14 | 0 | 0 |
| [02](02-backend-modelo-datos-validacion.md) — Modelo de datos | 12 | 12 | 0 | 0 |
| [03](03-backend-motor-plugins.md) — Motor de plugins | 13 | 13 | 0 | 0 |
| [04](04-backend-plugins-actualizacion-extension.md) — Plugin update/extension | 11 | 11 | 0 | 0 |
| [05](05-frontend-arquitectura-spa.md) — Arquitectura SPA | 16 | 16 | 0 | 0 |
| [06](06-frontend-toolkit-ui.md) — Toolkit UI | 12 | 12 | 0 | 0 |
| [07](07-frontend-paginas-modulos.md) — Páginas/módulos | 7 | 7 | 0 | 0 |
| **Total** | **85** | **85** | **0** | **0** |

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
| 01.07 | ✅ | Menor | Prefijos protegidos en lista paralela a `routes.php` | `a8b495b` | |
| 01.08 | ✅ | Menor | `UserController::destroy()` usa bandera en vez de returns | `feecf8a` | |
| 01.09 | ✅ | Menor | Normalización de avatar duplicada 3 veces | `3b6e846` | |
| 01.10 | ✅ | Menor | Desfases doc/código (login camelCase, refresh_token, endpoint password) | `0be3462` | |
| 01.11 | ✅ | Menor | No existe `POST /api/v1/users` (probablemente intencional) | `0594723` | |
| 01.12 | ✅ | Nit | `RuntimePathNormalizer` más fragmentado de lo necesario | `b6d81d7`, `70684d9` | Se sustituyeron las 3 ramas privadas (`isDirectRuntimePath`/`extractApiPath`/`extractHealthPath`) y sus 3 constantes por un único método (`findMarkerSegment`) que busca un marcador (`/api` o `/health`) en un límite de segmento válido, con reintento si la primera aparición no es válida. De paso se corrigió una inconsistencia real no testeada: para `/health` se aceptaba el alias con y sin subruta (`/xestify/health` y `/xestify/health/check`), pero para `/api` solo con subruta (`/xestify/api` a secas se devolvía sin recortar); ahora ambos se tratan igual, cambio de comportamiento acotado y aprobado explícitamente. `RuntimePathNormalizerTest.php` gana 3 casos nuevos (fix de la asimetría, reintento tras aparición inválida del marcador, rechazo de falsos positivos tipo `/apiary`), verificados por mutación. Comportamiento de enrutamiento real confirmado sin regresiones vía `RouterTest.php` (usa una instancia real de `RuntimePathNormalizer` dentro de `Router::match()`) y la suite completa de backend. |
| 01.13 | ✅ | Nit | `JWT_SECRET` default `'changeme'` fail-open | `eb3e246` | |
| 01.14 | ✅ | Nit | `NOSONAR` en instanciación dinámica del Router | `1523c57`, `247a980` | Se eliminó el despacho dinámico de raíz, no solo los 3 `// NOSONAR S5992`: `Router::get/post/put/delete` pasan a aceptar solo `callable` (nunca `array`), y `callHandler()`/`invokeController()` (junto con `new $target()`/`$instance->$method()`) se eliminaron por completo. `config/routes.php` registra sus 33 rutas con closures explícitas que resuelven el controller vía `$container->get(...)` y llaman al método por su nombre literal en el código — el "mapa explícito" que pide `CONTRIBUTING.md`. Se detectó y corrigió de paso que `HealthController` no estaba registrado en el contenedor (solo "funcionaba" por el fallback `new $target()` que ahora ya no existe). `CONTRIBUTING.md` se actualizó: la excepción controlada para despacho dinámico ya no incluye `Router`, solo `PluginClassLoader`/`PluginHookRegistrar` (cargan clases declaradas por plugins, un caso genuinamente dinámico y distinto). Verificado con `RouterTest.php` (14 casos, 2 reescritos a closures), `AppWiringTest.php` (6 casos, reconstruye la app real y dispara peticiones reales), suite completa de backend (55 ficheros), mutation test (quitar el registro de `HealthController` rompe `/health` real con error real de contenedor) y smoke test contra Apache real (`/health`, login, y 4 rutas protegidas con token real: entities, users, configurations, plugins). |

## 02 — Modelo de datos / Validación

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 02.01 | ✅ | Mayor | `EntityService` sin transacción en create/update | `10ab801` | |
| 02.02 | ✅ | Mayor | `ValidationService` no rechaza campos no declarados | `691f3d2` | |
| 02.03 | ✅ | Mayor | `TimestampFieldValidator` no valida formato; el test blinda el bug | `6f59d18` | |
| 02.04 | ✅ | Mayor | `StringFieldValidator`/`TextFieldValidator` duplicados al 100% | `a1d8b3d` | |
| 02.05 | ✅ | Menor | Comentarios obsoletos referencian `plugin_entity_metadata` | `e2fda11` | ⚠️ toca el mismo fichero que 02.06 (`EntityService.php`) |
| 02.06 | ✅ | Menor | `ORDER BY schema_version` sobre columna `UNIQUE(slug)` — código muerto | `10f6c00` | ⚠️ toca el mismo fichero que 02.05 (`EntityService.php`) |
| 02.07 | ✅ | Menor | `SchemaFieldExtractor` vs `sortableSchemaFields()` inconsistentes | `4852e97` | |
| 02.08 | ✅ | Menor | `plugins.schema_json` sin `CHECK` de estructura | `b2203ba` | |
| 02.09 | ✅ | Menor | `MigrationIdempotenceTest` con ruta Windows hardcodeada, no portable | `53644b9` | |
| 02.10 | ✅ | Menor | Tests referencian migraciones inexistentes (`002_core.sql`, `010_drop...`) | `1c4dd1d` | |
| 02.11 | ✅ | Menor | `EntityController` create() vs update()/destroy() — asimetría de excepciones | `eff6758` | |
| 02.12 | ✅ | Menor | Sin validador para `type: "uuid"` | `340b782` | |

## 03 — Motor de plugins (núcleo)

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 03.01 | ✅ | Mayor | Tabla `plugin_hooks` desconectada del runtime real | `36e96a7` | |
| 03.02 | ✅ | Mayor | 3 tests sin `exit()` — falso verde en runner agrupado | `156636c` | |
| 03.03 | ✅ | Mayor | `PluginClassLoader` instancia Hooks vs Lifecycle de forma distinta | `1a8f4a7` | ⚠️ (`9f45c6b`) El fallo recurrente `CommentsPluginTest.php::Comentarios tab does not appear for non-target entities` (documentado aquí en sesiones anteriores como "problema de BD, fuera de alcance") se investigó y corrigió en sesión posterior — no era dato sucio de BD: `target_entity: '*'` es el valor *de diseño* del propio plugin (`plugins/comments/schema.json`), reproducible en cualquier entorno limpio. El test asumía un `target_entity` restringido sin garantizarlo; ahora lo fija explícitamente y lo restaura, igual que ya hacía con `status`. |
| 03.04 | ✅ | Menor | Duplicación `clients`/`products` (~90 líneas) | `2bf306f` | |
| 03.05 | ✅ | Menor | `EntityController` con `HookDispatcher` por defecto oculto | `eb03e93` | |
| 03.06 | ✅ | Menor | `PluginBootTest`/`PluginHookRegistrarTest` casi duplicados | `84ab45d` | |
| 03.07 | ✅ | Menor | `ClientsPluginTest` usa `assert()` nativo de PHP | `391002d` | |
| 03.08 | ✅ | Menor | Docblock de `HookDispatcher::register()` engañoso (by reference) | `8e1aaa9` | |
| 03.09 | ✅ | Menor | `products/Lifecycle.php` import redundante de su propio namespace | `6356ce0` | |
| 03.10 | ✅ | Menor | `PluginLifecycleInterface` no refleja `onUpdate`/`onRollback` | `e7b5c1e` | ⚠️ De paso (detectado verificando este hallazgo, fuera de la auditoría original): `PluginClassLoaderTest.php` dependía de la extensión `pdo_sqlite` (ausente en este entorno) sin necesitarla — el test solo compara identidad de objeto, nunca ejecuta SQL. Corregido a `newInstanceWithoutConstructor()`, sin depender de ningún driver de PDO. |
| 03.11 | ✅ | Menor | `PluginSchemaReader` rechaza `"fields": {}` vacío incorrectamente | `e8972c2` | |
| 03.12 | ✅ | Menor | before/after solo por prefijo de string, sin red de seguridad | `001d231` | |
| 03.13 | ✅ | Nit | 3 capas de carpetas para ~21 clases — ceremonia pesada | `098b66c` | |

## 04 — Plugin update / extension / config

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 04.01 | ✅ | Crítico | `custom_fields` cambia de significado tras `saveConfig()` (= **P4**) | `dc57714` | |
| 04.02 | ✅ | Mayor | Update de plugins `extension` es no-op silencioso de schema | `a87b1ac` | |
| 04.03 | ✅ | Mayor | Comments: sin control de propiedad en `PUT` (= **P5**) | `2250b73` | |
| 04.04 | ✅ | Mayor | Atomicidad inconsistente: `syncAll`/`activate`/`deactivate` sin transacción | `307e7be` | |
| 04.05 | ✅ | Mayor | `PluginAdministrationService` no es fachada limpia (closures) | `d3f6e38` | |
| 04.06 | ✅ | Menor | `ensureInstalledTypeMatchesManifest()` duplicado en 2 servicios | `bc7f745` | |
| 04.07 | ✅ | Menor | `normalizeForComparison` duplicado entre 2 clases hermanas | `2fc806b` | ⚠️ Solo se extrae `normalizeForComparison` (idéntico byte a byte). `indexByKey`/`itemKey` de `PluginSchemaMergeService` vs `indexListSection` de `InstalledPluginSchemaValidator` se dejan intactos: la propia auditoría los describe como "mismo propósito, ligeras variaciones", no duplicado exacto — unificarlos es un cambio de comportamiento no pedido. |
| 04.08 | ✅ | Menor | Normalización de `fields` reimplementada en 2 capas distintas | `1083f2d` | |
| 04.09 | ✅ | Menor | `ConfigurationController` no cachea `RequestFactory` | `e904afd` | |
| 04.10 | ✅ | Menor | `plugin_update_history` sin política de retención | `e61e67d` | |
| 04.11 | ✅ | Nit | `demoinventory` doble ping sin propósito | `fc47b44` | |

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
| 05.08 | ✅ | Menor | `renderLogin()` no resincroniza hash tras expirar sesión | `dc0b03a` | |
| 05.09 | ✅ | Menor | `RouteController.resolveFromHash()` código muerto | `a11d6e4` | |
| 05.10 | ✅ | Menor | Ruta `'ui'` reconocida pero sin handler | `00f6bc3` | |
| 05.11 | ✅ | Menor | Token de ruta de plugins rompe convención `namespace:param` | `4342c19` | |
| 05.12 | ✅ | Menor | `AppState` "god object" | `8a49f71`, `1553790` | La división se completó en sesión posterior, en 5 lotes verificados de forma independiente. Investigación previa reveló que 2 de los 6 dominios que mezclaba `AppState` eran código muerto real (sin consumidores en producción, solo aserciones sintéticas en tests): `navigationState`, y `loading`/`error`/`currentEntity`/`records`/`metadata` (escritos por `EntityList.js` pero nunca releídos). Se eliminaron. Los 4 dominios restantes se repartieron a destinos ya existentes: notificaciones → `NotificationModel.js` (nuevo, mismo patrón subscribe/notify); preferencias de UI/tema → `ThemeModel.js` (que ganó su propio mecanismo `subscribeUi`/`notifyUi`, inexistente hasta entonces); sesión + `entities` → `SessionModel.js`, que pasa de envolver `AppState` a poseer el estado directamente. `AppController.clearAuth()` gana una llamada explícita a `ThemeModel.resetUiPreferences()`, junto a `SessionModel.reset()`/`clearStoredSession()`, para que cerrar sesión siga limpiando también el tema. `StateModel.js`/`AppState` y `StateTest.html` se eliminaron por completo; búsqueda final confirmó cero referencias colgantes. Cobertura nueva: `ThemeModelTest.html` y `SessionModelTest.html` (casos migrados desde `StateTest.html`), más un test de `AppController.clearAuth()` verificado por mutación (falla si se retira la llamada a `resetUiPreferences()`). Suite completa de integración en verde tras cada lote y smoke test manual en navegador real (login, cambio de tema, logout con reset de tema confirmado, cero errores de consola). |
| 05.13 | ✅ | Menor | `RouteController.navigate()` API dual sin uso real | `28ec6f3` | |
| 05.14 | ✅ | Nit | `ThemeModel` fallback `'light'` como string mágico | `956ec24` | |
| 05.15 | ✅ | Nit | Host flotante con Tailwind + inline duplicados | `c53f6cd` | |
| 05.16 | ✅ | Nit | Doc `renderizado-dinamico.md` nombra componentes obsoletos | `ef27e99` | |

## 06 — Toolkit UI

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 06.01 | ✅ | Mayor | Fuga de DOM + `id` duplicados en cada modal de confirmación | `f0b2300` | |
| 06.02 | ✅ | Mayor | `label`/`for` roto en `Login.js` | `ca300cb` | |
| 06.03 | ✅ | Mayor | Clase Tailwind base duplicada en 9 ficheros `Input*.js` | `be6da3d` | |
| 06.04 | ✅ | Mayor | `setError()` no quita clase base — borde de error gris en reposo | `fcd891c` | |
| 06.05 | ✅ | Menor | `variant: 'ghost'` de `Button` inerte | `14d7d0d` | |
| 06.06 | ✅ | Menor | `id` del título del modal fijado dos veces | `f0b2300` | ⚠️ Resuelto como efecto colateral de 06.01 (id ahora generado dinámicamente) |
| 06.07 | ✅ | Menor | Rama muerta en `Breadcrumb.resolveItems` | `364d7a7` | |
| 06.08 | ✅ | Menor | Componentes registrados sin uso real (`inputRadio`, `inputTime`...) | `96f6eca` | ⚠️ `inputTime` ya no aplica (ver `07.04`). Se elimina `inputCheckbox` (`ComponentFactory.js`): alias duplicado de `inputCheck` sin `category`/`description` y sin ninguna referencia ni siquiera en tests — código muerto real. `inputRadio`/`spinner`/`skeleton` NO se tocan: siguen sin consumidor en `views/pages`/`views/modules`, pero tienen metadatos de catálogo completos y cobertura de smoke-test (`ComponentsTest.html`) — son infraestructura de toolkit deliberada, no descuido, tal como concluye la propia auditoría. |
| 06.09 | ✅ | Menor | `PageLayout` acoplado al DOM interno de `PageHeaderComponent` | `7053602` | |
| 06.10 | ✅ | Nit | `setHtml()`/`options.html` sin uso, vector XSS latente | `36d45f1` | |
| 06.11 | ✅ | Nit | `aria-disabled` redundante con `disabled` nativo | `21b48e3` | |
| 06.12 | ✅ | Nit | Breadcrumb dropdown solo cierra con `mouseleave` | `1dd4b19` | |

## 07 — Páginas / módulos de negocio

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 07.01 | ✅ | Crítico | `EntityEdit.submit()` bloquea el formulario tras error (= **P2**) | `199e957` | |
| 07.02 | ✅ | Crítico | Botones `PluginManager`/`PluginConfig` rotos tras usar el toolbar (= **P3**) | `beb2da7` | |
| 07.03 | ✅ | Mayor | `normalizeRoleList()` duplicada `UserManager`/`UserConfig` | `d70bfa9` | |
| 07.04 | ✅ | Mayor | `DynamicForm` sin rama `number`/`time`; `inputTime` sin conectar | `4b689aa` | ⚠️ Como efecto colateral, `inputTime` deja de estar sin uso (ver nota en 06.08) |
| 07.05 | ✅ | Mayor | Dos patrones distintos para página CRUD con formulario | `3052724` | |
| 07.06 | ✅ | Menor | `Login.js` sin guarda de re-entrada | `74278fc` | |
| 07.07 | ✅ | Menor | `UserConfig.js` (908 líneas), demasiada responsabilidad | `b4d6ddc`, `2cad92d` | La división en colaboradores se completó en sesión posterior: `AvatarUpload.js` (validación de tamaño + lectura `FileReader`, sin DOM) y `ClipboardUtil.js` (`copyToClipboard`, única lógica genuinamente pura del flujo de contraseña temporal — `#resetPassword`/`#temporaryPasswordNode` se quedan en `UserConfig.js` por estar intrínsecamente ligados a API/DOM). `UserConfig.js` baja de 848 a 821 líneas. Añadida cobertura nueva para las ramas de error de `FileReader` (`>2MB`, `onerror`) que no existía en ninguna suite. |
