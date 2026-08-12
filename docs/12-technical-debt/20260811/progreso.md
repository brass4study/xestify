# Progreso de corrección — auditoría 2026-08-11

← [Índice de esta auditoría](README.md) · [Plan de corrección](plan-correccion.md) · [Convenciones de corrección](../convenciones-correccion.md) · [Formato de esta tabla](../convenciones-progreso.md)

**Antes de tocar código, lee [`../convenciones-correccion.md`](../convenciones-correccion.md)** (reglas de sesión, commit único, formato de asunto) y [`../convenciones-progreso.md`](../convenciones-progreso.md) (qué es este fichero, columnas, leyenda de estado). Este fichero solo trae el estado real de esta auditoría concreta, no repite esas reglas.

## Resumen

| Fichero | Total | ✅ Resuelto | 🔧/⏳ Pendiente | 🚫 Descartado |
|---|---|---|---|---|
| [01](01-backend-core-auth-usuarios.md) — Core/Auth/Users | 14 | 6 | 8 | 0 |
| [02](02-backend-modelo-datos-validacion.md) — Modelo de datos | 12 | 4 | 8 | 0 |
| [03](03-backend-motor-plugins.md) — Motor de plugins | 13 | 1 | 12 | 0 |
| [04](04-backend-plugins-actualizacion-extension.md) — Plugin update/extension | 11 | 2 | 9 | 0 |
| [05](05-frontend-arquitectura-spa.md) — Arquitectura SPA | 16 | 0 | 16 | 0 |
| [06](06-frontend-toolkit-ui.md) — Toolkit UI | 12 | 0 | 12 | 0 |
| [07](07-frontend-paginas-modulos.md) — Páginas/módulos | 7 | 2 | 5 | 0 |
| **Total** | **85** | **15** | **70** | **0** |

Los 5 hallazgos de la Fase 1 ("antes de la defensa") corresponden a: **P1**=`01.01`, **P2**=`07.01`, **P3**=`07.02`, **P4**=`04.01`, **P5**=`04.03`. No los cuentes dos veces al planificar las sesiones de la Fase 2.

---

## 01 — Core / Auth / Users

| ID | Estado | Sev. | Resumen | Commit | Notas |
|---|---|---|---|---|---|
| 01.01 | ✅ | Crítico | `password_hash` filtrado en respuestas de usuario (= **P1**) | `cdd1982` | `UserController` añade `sanitizeUser()` y lo aplica en `me()`, `updateMe()`, `listUsers()`, `show()`, `update()`. El repositorio sigue devolviendo `password_hash` (lo necesita `passwordMatches()` para verificar contraseña actual); el filtrado es solo en el borde de respuesta HTTP. Tests: `UserControllerTest.php` ahora comprueba ausencia de `password_hash` en `me`, `listUsers`, `update`. ⚠️ Detectado en su momento un fallo preexistente no relacionado en `UserController::update lets admins edit user roles` — resuelto más tarde de pasada durante la verificación de `04.01` (bug real en `UserRepository`, no relacionado con `password_hash`; ver notas de `04.01`). |
| 01.02 | ✅ | Mayor | No hay `AuthorizationService`; check admin duplicado en 3 controladores | `7d59429` | Añadido `Request::hasRole(string $role): bool` en `core/Request.php` (único punto de verdad, opera sobre el `$user` ya cacheado por `AuthMiddleware`). `UserController::isAdmin()`, `ConfigurationController::isAdmin()` y `PluginManagerController::isAdminRequest()` eliminados; sus 9 call sites ahora llaman `$request->hasRole('admin')` directamente. No se implementa la matriz de permisos completa (`AuthorizationService`/tablas `roles`/`permissions`) — el propio hallazgo la describe como fuera de alcance del MVP (`EPIC A6` post-MVP), solo se centraliza el check binario admin/no-admin ya existente. Tests: 4 nuevos en `RequestResponseTest.php` cubriendo `hasRole()` (con rol, sin rol, sin usuario autenticado, `roles` no-array). Verificado con la suite completa de `unit` + `integration-db` + `integration-plugins` (usando el `php.ini` de Apache, `C:\apache2.4.66\config\php.ini`, para cargar `pdo_pgsql`/`mbstring` en CLI): 0 fallos.|
| 01.03 | ✅ | Mayor | `AuthController` no usa `UserRepository`, SQL a mano | `3a12d2a` | Añadido `UserRepository::findByEmail(string $email): ?array` (mismo patrón de `find()`: filtra `deleted_at`, decodifica `roles`/`avatar`). `AuthController` ya no llama `Database::connection()` ni repite el `SELECT` a mano: recibe `UserRepository` por constructor (nuevo parámetro entre `JwtService` y `RequestFactory`) y `login()` usa `findByEmail()`; los `roles` ya vienen decodificados por el repositorio, se elimina el `json_decode()` manual. Wiring actualizado en `config/app.php` (`AuthController::class` ahora inyecta `UserRepository::class`, ya registrado como singleton). Tests: 2 nuevos en `UserRepositoryTest.php` (`findByEmail` devuelve el usuario con roles decodificados; devuelve `null` para email desconocido o de usuario soft-deleted). `AuthControllerTest.php` actualizado para construir el controlador con el `UserRepository` real (su suite de 9 tests existente sigue en verde sin cambios de comportamiento). Verificado con la suite completa `unit` + `integration-db` + `integration-plugins`: 0 fallos.|
| 01.04 | ✅ | Mayor | `UserRepository::update()` no captura `PDOException` | `9b2e0d0` | `UserRepository::update()` ya no llama `$stmt->execute()` a pelo: el helper privado `execute()` gana un segundo parámetro `?array $params = null` (si es `null`, ejecuta el statement ya bindeado vía `bindValue` sin volver a pasar params, necesario porque `avatar` usa `PDO::PARAM_LOB`) y sigue capturando `PDOException → RepositoryException` como en `find()`/`delete()`/`updatePassword()`. En `UserController`, `update()` y `updateMe()` (los dos call sites de `repository->update()`) pasan ahora por un helper nuevo `applyUpdate()` que captura `RepositoryException`: si la causa es una violación de unicidad de Postgres (`SQLSTATE 23505`, comprobado vía `$e->getPrevious()->getCode()` — **no** por el texto del mensaje, que descubrí viene localizado en español en este entorno, p. ej. «llave duplicada viola restricción de unicidad») responde 409 con "El email ya está en uso."; cualquier otra causa (fila no encontrada) sigue respondiendo 404 como antes. En `updateMe()`, si `applyUpdate()` falla no se llega a `updatePasswordIfNeeded()` (mismo comportamiento implícito que antes, cuando la excepción no capturada cortaba la ejecución igual). Tests: 1 nuevo en `UserRepositoryTest.php` (update a email duplicado lanza `RepositoryException`, no `PDOException` sin capturar) y 1 nuevo en `UserControllerTest.php` (`PUT /users/{id}` con email duplicado devuelve 409, no un error sin envelope). Verificado con la suite completa `unit` + `integration-db` + `integration-plugins`: 0 fallos.|
| 01.05 | ✅ | Mayor | `UserSeeder` docblock dice auto-run al arrancar; es falso | `7863492` | Docblock corregido: ya no dice "Auto-runs on server boot", ahora indica explícitamente que no se invoca en el arranque real y que se ejecuta a mano vía `php tools/setup/seed-admin-user.php` (confirmado por grep: sigue siendo el único punto de invocación fuera de tests). Sin cambio de comportamiento — es una corrección de comentario, no hay bug de código que un test pueda detectar en rojo/verde; se deja constancia aquí en vez de forzar un test artificial.|
| 01.06 | ✅ | Mayor | `JwtService::base64UrlDecode()` padding mal calculado | `84246fe` | Sustituido el `str_pad(..., strlen($data) % 4, ...)` (pasaba una longitud objetivo casi siempre menor que la actual → no-op, nunca añadía relleno) por el idioma estándar sugerido en el hallazgo: calcular el resto y añadir `4 - resto` símbolos `=` solo si el resto es distinto de cero. Test: 1 nuevo en `JwtServiceTest.php` que invoca `base64UrlDecode()` por Reflection (es privado) con inputs de longitud base64 de resto 0, 2 y 3, incluyendo un payload realista de 111 caracteres. ⚠️ Confirmado empíricamente (ver auditoría) que este test **no puede fallar con el código antiguo** en este entorno: `base64_decode()` de PHP 8.5 es tolerante a la falta de relleno incluso en modo estricto (`$strict = true`) para estos tamaños de entrada, así que el bug es indetectable por comportamiento — coincide exactamente con la conclusión de la auditoría ("funciona por suerte"). Se añade igual como test de regresión estructural (documenta el contrato correcto del método) en vez de fingir una cobertura roja/verde que no es alcanzable sin acceder al string de relleno intermedio. Verificado con la suite completa `unit` + `integration-db` + `integration-plugins`: 0 fallos.|
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
| 02.01 | ✅ | Mayor | `EntityService` sin transacción en create/update | `97d21e2` | `EntityService::createRecord()`/`updateRecord()` envuelven ahora el bloque `dispatchBefore('beforeSave') → repository->create()/update() → dispatchAfter('afterSave')` en `$pdo->beginTransaction()/commit()/rollBack()`, mismo patrón que `PluginRollbackService`/`PluginUpdateService`. La validación de esquema sigue fuera de la transacción (no toca BD). `EntityServiceHooksTest.php::PdoStub` (stub de PDO sin conexión real) gana `beginTransaction()/commit()/rollBack()/inTransaction()` en memoria para no romper con la nueva llamada. Test nuevo en `EntityServiceTest.php` (uno para create, uno para update): un hook `beforeSave` inserta una fila "marcador" propia y luego se fuerza el fallo de `repository->create()/update()` pasando `NAN` en un campo `number` (pasa `NumberFieldValidator` porque es un float, pero `json_encode()` no puede codificar `NAN` → `RepositoryException` en `encodeJson()`). Confirmado por reversión manual (`git stash`): ambos tests fallan sin el fix (la fila del hook queda persistida, 1 y 2 filas respectivamente) y pasan con él (0 filas). Verificado con `unit` + `EntityServiceTest.php`: 0 fallos. |
| 02.02 | ✅ | Mayor | `ValidationService` no rechaza campos no declarados | `e5baf95` | `ValidationService::validate()` compara ahora las claves de `$data` contra el conjunto de campos conocidos (`SchemaFieldExtractor::extract($schema)`) y añade un error `unknown_field` por cada clave no declarada. Importante: las claves de `relations` (DECISION 6 — su existencia/tipo es responsabilidad del hook, no de `ValidationService`) se calculan aparte (`relationKeys()`) y se dejan pasar sin validar tipo, para no romper el contrato de 4 bloques; hoy `clients`/`products` tienen `relations: []` así que no se ejerce en producción, pero el allow-list ya está preparado para el día que una entidad declare una relación real. Tests nuevos en `ValidationServiceTest.php`: clave no declarada → `unknown_field`; clave de `relations` se acepta sin error de tipo. Confirmado por reversión manual (`git stash`): el test de campo no declarado falla sin el fix y pasa con él. Verificado con `unit` + `integration-db` + `integration-plugins` completos (usando `PHPRC=C:\apache2.4.66\config\php.ini` para que los procesos hijo de `run.php` carguen `pdo_pgsql`/`mbstring`): 0 fallos. |
| 02.03 | ✅ | Mayor | `TimestampFieldValidator` no valida formato; el test blinda el bug | `1d77b1c` | `TimestampFieldValidator::validate()` ya no acepta cualquier string: intenta `new DateTimeImmutable($value)` y rechaza (`invalid_type`) si lanza excepción o si el string es vacío/solo espacios (PHP trata `''` como sinónimo de `'now'`, así que se rechaza explícitamente). Deliberadamente **no** se restringe a ISO-8601 estricto (que es lo que sugería el hallazgo al pie de la letra): el propio catálogo de producción (`plugins/clients/schema.json`) usa `"default": "now"` para `creation_stamp`, y `DynamicForm.js` no tiene rama para `type: "timestamp"` — cae al input de texto genérico con `value: field.default`, así que un alta de cliente sin tocar ese campo somete literalmente el string `"now"` como dato. Usar el parser de fechas de PHP (no `createFromFormat` con un formato fijo) acepta `'now'`/ISO-8601/otras expresiones de fecha reales y rechaza basura genuina (`'not-a-real-timestamp'`, `'banana'` sí fallan, verificado con `php -r`), preservando ese flujo real sin blindar el bug. Test renombrado en `FieldValidatorsTest.php` (ya no afirma ciegamente que cualquier string pasa) + test nuevo que rechaza `'not-a-real-timestamp'` y `''`. Confirmado por reversión manual (`git stash`): el test nuevo falla sin el fix (warning de índice indefinido + error fatal) y pasa con él. Verificado con `unit` + `integration-db` + `integration-plugins` completos (`PHPRC` apuntando al `php.ini` de Apache): 0 fallos. |
| 02.04 | ✅ | Mayor | `StringFieldValidator`/`TextFieldValidator` duplicados al 100% | `a8c9fa3` | `TextFieldValidator.php` eliminado. `StringFieldValidator` gana un parámetro de constructor `$expectedLabel = 'string'` que compone el mensaje de error (`'Expected ' . $expectedLabel`); el tipo `'text'` se registra como `new StringFieldValidator('text')` en los dos sitios donde antes se instanciaba `TextFieldValidator` (`DefaultFieldValidatorRegistryFactory` y el wiring real `config/app.php`, que tenía el mismo registro duplicado por fuera de la factory). Mensajes de error preservados exactamente igual (`Expected string` / `Expected text`), sin cambio de comportamiento observable. Tests: `FieldValidatorsTest.php` ya no instancia una clase separada para "text", usa `new StringFieldValidator('text')` y comprueba explícitamente que el mensaje sigue siendo `Expected text` (antes ningún test comprobaba el mensaje de `TextFieldValidator`, solo el código). Verificado con `unit` + `integration-db` + `integration-plugins` completos (incluye `AppWiringTest.php`, que ejercita el `config/app.php` real): 0 fallos. |
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
| 03.01 | ✅ | Mayor | Tabla `plugin_hooks` desconectada del runtime real | `df3b3f4` | Eliminada por completo en vez de dejarla como infraestructura muerta documentada: migración `004_plugin_hooks.sql` borrada, tabla `DROP`eada en la BD de desarrollo, `plugins/comments/Lifecycle.php` ya no escribe en ella (`onInstall`/`onActivate`/`onDeactivate` quedan como no-ops — el wiring real de hooks sigue siendo `PluginHookRegistrar` vía `plugins.status`, sin cambio de comportamiento). `backend/tests/integration/PluginHookRegistryTableTest.php` eliminado (solo comprobaba el esquema de la tabla muerta). Migraciones posteriores renumeradas para no dejar hueco: `005→004_plugin_extension_data.sql`, `006→005_plugin_update_history.sql`, `007→006_configuration.sql`, con todas las referencias actualizadas (`MigrationIdempotenceTest.php`, `ConfigurationRepositoryTest.php`, `PluginUpdateHistoryTableTest.php`, `run.php`). `docs/01-architecture/hooks.md` y `docs/02-entities/postgresql-jsonb.md` corregidos para describir el mecanismo real (activación por `plugins.status`, sin registro por-hook) sin referenciar esta auditoría. Referencias a la tabla/`plugin_hook_registry` en `sesion.md`, `backlog.md`, `productividad.md` y `prompts.md` eliminadas igual que si nunca hubiese existido (incluye renumerar STORY 2.5→eliminada, 2.6→2.5, 2.7→2.6 en los cuatro ficheros); `docs/00-meta/plan-fundacional-gemini.md` (transcripción de la conversación fundacional) se dejó intacto a petición explícita. De paso, resuelto un hallazgo de SonarQube (`php:S1192`) en `CommentsPluginTest.php`: literal `"Created comment must have an id"` duplicado 3 veces, extraído a la constante `MSG_CREATED_COMMENT_MUST_HAVE_ID`. ⚠️ Detectado durante la verificación un fallo preexistente no relacionado: `CommentsPluginTest.php::Comentarios tab does not appear for non-target entities` falla porque el plugin `comments` está configurado en la BD de desarrollo con `target_entity: '*'` (deriva de estado de BD, no un bug de código); confirmado que ya fallaba igual en el `HEAD` previo a esta sesión. No se toca aquí — fuera de alcance de este hallazgo. Verificado con `unit` + `integration-db` + `integration-plugins` completos (`PHPRC` apuntando al `php.ini` de Apache): 0 fallos salvo ese preexistente. |
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
| 04.01 | ✅ | Crítico | `custom_fields` cambia de significado tras `saveConfig()` (= **P4**) | `436f293` | Causa raíz: `custom_fields` significa "catálogo" antes de la primera `saveConfig()` y "campos activos" después (el catálogo real se mueve a `plugin_suggested_custom_fields`), pero `PluginSchemaMergeService::mergeAdditively()` e `InstalledPluginSchemaValidator` (`assertCanApplyUpdate` y `assertContainsCanonical`) seguían comparando/fusionando siempre contra `custom_fields`. Arreglo: nuevo helper `installedCustomFieldsCatalog()`/`mergeCustomFieldsSection()` en ambas clases — usan `plugin_suggested_custom_fields` como catálogo canónico cuando existe (fallback a `custom_fields` si el plugin nunca se configuró), y el merge escribe los campos nuevos ahí, dejando `custom_fields` (activos) intacto. También arreglado un tercer síntoma de la misma causa, no descrito literalmente en el hallazgo: `syncAll()` reportaba "schema corrupto" en un plugin configurado con algún campo sugerido inactivo. Tests: 2 nuevos en `PluginSchemaMergeServiceTest.php` (edición de sugerido activo no rompe; campo nuevo va al catálogo, no se auto-activa), 1 en `PluginSyncServiceTest.php` (sync sin cambios no marca corrupción), 1 de integración en `PluginUpdateServiceTest.php` (configurar → actualizar conserva la edición del admin y no auto-activa el campo nuevo). Confirmado por reversión manual: los 4 tests nuevos fallan sin el fix, con el mensaje exacto del hallazgo. Aprovechando la verificación de la suite completa, se resolvieron también los 3 fallos preexistentes anotados en 01.01: (a) `mb_strlen` no era un bug — el `php.ini` mínimo usado para los tests CLI de esta sesión no cargaba `mbstring`; se cambió a usar el `php.ini` real de Apache (`C:\apache2.4.66\config\php.ini`), que sí lo tiene; (b) `EntityControllerTest.php::seedCtrlSchema()` insertaba el plugin fixture `test_entity_ctrl` sin `name` (columna `NOT NULL DEFAULT ''`), lo que hacía fallar de forma no determinista el `LIMIT 1` sin `ORDER BY` de `SystemEntitiesTableTest.php`; se añadió el `name` al INSERT/UPDATE; (c) bug real encontrado en `UserRepository`: `find()`/`all()`/`update()` nunca decodificaban la columna `roles` (JSONB) devuelta por PDO como string JSON crudo — el frontend lo compensaba silenciosamente con `JSON.parse` defensivo en `normalizeRoleList()` (ver hallazgo `07.03`). Añadido `normalizeRolesValue()` en `UserRepository` (mismo patrón que `normalizeBinaryValue()` para `avatar`), con test nuevo en `UserRepositoryTest.php`. Suite completa: 48 ficheros, 0 fallos. |
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
