# Auditoría — Motor de plugins backend (núcleo)

**Subsistema:** Descubrimiento, ciclo de vida, hooks y administración de plugins
**EPIC cubiertas:** EPIC 4, 7
**Severidades:** 0 crítico · 4 mayor · 9 menor · 8 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de `backend/src/core/HookDispatcher.php`, `backend/src/plugins/` (discovery, lifecycle, contracts, guards — la subcarpeta `schema/` se audita en [04](04-backend-plugins-schema-extensiones.md)), `PluginAdministrationService`, `PluginManagerController`, los repositorios de plugins, `tools/setup/sync-plugins.php` y `switch-demoinventory-version.php`, con contraste contra el wiring real (`bootstrap.php` → `src/app.php` → `config/app.php`), docs y tests.

---

## Resumen

El motor de plugins backend está en buen estado general: separación limpia discovery/lifecycle/guards, transacciones con lock pesimista (`FOR UPDATE`) en update/rollback/order/config, comprobación de rol admin presente en los 13 endpoints del manager, y validación estricta de slugs (`^[a-z][a-z0-9_]*$`) que cierra el path traversal desde HTTP. No hay hallazgos críticos. Los mayores: el logger de hooks usa `STDERR`, inexistente en el runtime canónico Apache; el boot de hooks sin aislamiento de errores puede tumbar toda la API; `registerNew` deja estado parcial ante fallo de overrides; y la salida per-plugin de `tools/setup/sync-plugins.php` quedó rota tras el refactor multi-instancia.

## Hallazgos por severidad

### MAYOR

**1. `STDERR` no existe en el runtime web: el fallo "non-blocking" de un hook after* se convierte en fatal**
- `backend/src/core/HookDispatcher.php:142`
- `logWarning()` hace `fwrite(STDERR, $line)`. Las constantes `STDIN/STDOUT/STDERR` solo están definidas en el SAPI CLI; el runtime canónico es Apache+PHP (AGENTS.md:183-184). En PHP 8, usar una constante indefinida lanza `Error`, y `logWarning()` se invoca precisamente **dentro de los catch** de `invokeCallback()` (líneas 121, 131) y `applyFilter()` (línea 100). Resultado: si un hook `afterSave`/`afterDelete` falla en producción web, en lugar del warning prometido el `Error` escapa del catch, atraviesa `EntityService::dispatchAfter()` (sin try/catch propio, EntityService.php:393-400) y devuelve un 500 **después de haber persistido el registro** — lo contrario del contrato documentado en el docblock (líneas 13-14) y verificado por los tests, que corren en CLI y por eso no detectan el bug. Lo mismo aplica a un filtro `registerTabs` que falle: `applyFilter` promete "skips failing callbacks" y en Apache tumbaría el endpoint de tabs.
- Sugerencia: sustituir `fwrite(STDERR, ...)` por `error_log($line)` (funciona en todos los SAPI), o fallback `defined('STDERR') ? fwrite(...) : error_log(...)`.

**2. El boot de hooks no aísla errores: un plugin activo defectuoso tumba toda la API, incluido el endpoint para desactivarlo**
- `backend/src/config/app.php:356` + `backend/src/plugins/lifecycle/PluginHookRegistrar.php:30-40`
- `xestifyBootPluginHooks()` se ejecuta inline al cargar `config/app.php` (356), antes de construir el Router (`src/app.php:13-15`), y ni `bootstrap.php`, ni `src/app.php`, ni `registerActiveHooks()` envuelven en try/catch la carga (`require_once` en `PluginClassLoader::requirePluginFile()`, línea 54) ni la llamada sin contrato `$hooks->register($dispatcher)` (PluginHookRegistrar.php:37). Un `Hooks.php` con `ParseError`, una clase `Hooks` sin `register()`, o una excepción en el constructor de un plugin **activo** produce un fatal en cada request: caen todos los endpoints, incluidos `/health`, login y `PUT /api/v1/plugins/{slug}/status` — justo el que el admin necesitaría para desactivar el plugin roto. Única recuperación: editar la BD a mano o el fichero en disco. Escenario alcanzable con flujos normales: `activate()` no toca `Hooks.php` (solo `Lifecycle.php`), así que activar un plugin con `Hooks.php` roto tiene éxito y el fatal aparece en la request siguiente.
- Sugerencia: en `registerActiveHooks()`, envolver cada plugin en `try { ... } catch (\Throwable $e) { /* log + continuar */ }` (ParseError es capturable en PHP 8), y verificar `method_exists($hooks, 'register')` o introducir un `PluginHooksInterface`. Así un plugin roto pierde sus hooks pero la API sigue viva.

**3. `registerNew()` deja una instancia activa a medio configurar si fallan los overrides (tres transacciones sin compensación)**
- `backend/src/services/PluginAdministrationService.php:131-164`
- El alta manual se compone de tres transacciones independientes: `installFromManifest()` (tx1, 131-134), `activate()` (tx2, 135) e identidad+config (tx3, 144-163). Si tx3 falla (p. ej. `fields` inválido), el catch de la línea 156 solo revierte tx3: el plugin queda **registrado y activo** sin la configuración pedida, y el endpoint devuelve 422/500. El controller (PluginManagerController.php:122-126) presenta el error como si el alta no hubiera ocurrido; el siguiente intento con el mismo payload fallará además con "El slug ya está en uso" (127). El docblock (100-111) documenta el diseño en tres transacciones pero no la falta de compensación.
- Sugerencia: validar los overrides *antes* de tx1, o compensar: si tx3 falla, borrar la instancia recién creada (ya existe `PluginDeletionService`) antes de propagar, dejando semántica todo-o-nada.

**4. La salida per-plugin de `tools/setup/sync-plugins.php` quedó rota tras el refactor multi-instancia**
- `tools/setup/sync-plugins.php:53-63`
- `PluginSyncService::syncAll()` devuelve desde STORY 10.3 `plugins` como mapa `plugin_name => lista de resultados por instancia` (docblock correcto en PluginSyncService.php:41-44). El script CLI sigue asumiendo la forma plana antigua: `isset($plugin['result'])` (54) nunca se cumple porque `$plugin` es una lista, así que **cada** línea per-plugin imprime `unknown (installed=n/a, available=n/a)` sin mensaje — un operador no puede distinguir un plugin registrado de uno con error (el summary agregado sí es correcto). Refactor perdido: el cambio de forma se propagó a los tests pero no al tool. Además, el docblock de `PluginAdministrationService::syncAll()` (170) conserva la anotación antigua.
- Sugerencia: iterar el nivel de instancias (`foreach ($plugins as $pluginName => $instances) { foreach ($instances as $instance) { ... } }`) usando `$instance['slug']`; corregir la anotación a `array<string, array<int, array<string, mixed>>>`.

### MENOR

**5. Rollback machaca ediciones admin posteriores al update y cambia `status` sin disparar `onDeactivate`**
- `backend/src/plugins/lifecycle/PluginRollbackService.php:64-70` + `backend/src/repositories/PluginWriteRepository.php:340-369`
- `restoreFromSnapshot()` reemplaza `manifest_json` completo y `status` con el snapshot. Dos asimetrías: (1) `persistUpdate()` preserva `label/description/target_entity` editados por el admin (`ADMIN_EDITABLE_MANIFEST_KEYS`, PluginWriteRepository.php:28, 321-333), pero el rollback restaura los valores pre-update, perdiendo silenciosamente ediciones posteriores; (2) si el admin desactivó el plugin tras el update y el snapshot era `active`, el rollback lo **reactiva** disparando `onActivate` (69) sin acción explícita; en el sentido contrario el plugin queda desactivado sin que se invoque `onDeactivate`.
- Sugerencia: aplicar en `restoreFromSnapshot` el mismo merge que `persistUpdate` (preservar claves admin-editables), y conservar el `status` actual o disparar el hook de transición correspondiente.

**6. Un solo manifest corrupto en disco tumba `/available`, `/updates` y el alta manual**
- `backend/src/services/PluginAdministrationService.php:76-88` y `backend/src/plugins/lifecycle/PluginOutdatedService.php:40-49`
- `listAvailableForRegistration()` y `getOutdated()` llaman `readManifest()` para **cada** carpeta sin try/catch por plugin; `PluginManifestReader::read()` lanza ante JSON inválido. Un único `manifest.json` corrupto hace que `GET /plugins/available`, `GET /plugins/updates` y `POST /plugins` devuelvan 500 para todos — mientras `syncAll()` sí aísla el error por plugin y continúa (PluginSyncService.php:67-74, con test dedicado). Convención inconsistente entre lecturas del mismo disco.
- Sugerencia: try/catch `PluginException` en el bucle, omitiendo (o marcando con `error`) la carpeta ilegible, replicando el patrón de `syncAll()`.

**7. `target_entity` como override de alta se ignora silenciosamente si no viene acompañado de `fields`**
- `backend/src/services/PluginAdministrationService.php:138`
- El controller permite `target_entity` en el body de `POST /plugins` (PluginManagerController.php:99, whitelist en 119), pero `registerNew()` solo aplica configuración cuando existe `fields`: `$needsConfigUpdate = array_key_exists('fields', $overrides);`. Un alta de extension con `{"plugin_name": "comments", "target_entity": "persons"}` sin `fields` registra y activa el plugin descartando el `target_entity` sin error — queda el del manifest (`'*'`).
- Sugerencia: `array_key_exists('fields', ...) || array_key_exists('target_entity', ...)` (y que `applyConfigPayload` tolere payload sin `fields`), o rechazar con 422 la combinación no soportada.

**8. El schema se resuelve por `manifest['name']` mientras el manifest se resuelve por carpeta: la tríada carpeta/plugin_name/slug no tiene invariante**
- `backend/src/plugins/discovery/PluginSchemaReader.php:24-25`
- `PluginManifestReader::read($slug)` construye el path con el **nombre de carpeta**, pero `PluginSchemaReader::read()` usa `$manifest['name']` (24-25), y `PluginClassLoader::requirePluginFile()` el `plugin_name` de BD (50). Nadie valida `manifest.name == carpeta`. Con carpeta `foo` cuyo manifest declare `name: "bar"`: el schema se busca en `plugins/bar/`, el sync registra `plugin_name='bar'`, y en la siguiente pasada `findAllByPluginName('foo')` no encuentra nada, con lo que `syncAll()` reintenta el install y falla cada vez por unique de slug. Foot-gun de autoría que produce errores confusos y estado BD↔disco divergente.
- Sugerencia: en `PluginManifestReader::validateStructure()` (o `PluginSourceService::readValidatedManifest()`), exigir `manifest['name'] === $slug` de la carpeta.

**9. `activate()`/`deactivate()` no son idempotentes: re-disparan hooks de ciclo de vida en transiciones no-op**
- `backend/src/plugins/lifecycle/PluginStatusService.php:24-46`
- `activate()` ejecuta `updateStatus($slug, 'active')` sin comprobar el estado previo (29) e invoca `onActivate()` incondicionalmente (34). `PUT /status` con `"active"` sobre un plugin ya activo vuelve a ejecutar `onActivate()` (que puede no ser idempotente: seeds, side effects), y lo mismo `deactivate()`. El contrato del interface dice "when the plugin status **changes** to 'active'" (PluginLifecycleInterface.php:15-16) — el código dispara el hook aunque no haya cambio.
- Sugerencia: leer el status previo (p. ej. `lockBySlug` primero, como los demás servicios) y saltar el hook cuando no hay transición real.

**10. Dependencias: un plugin inactivo satisface `requires`, y el sync en una pasada no ordena por dependencias**
- `backend/src/repositories/PluginRepository.php:212-234` + `backend/src/plugins/lifecycle/PluginSyncService.php:60-75`
- (1) `findInstalledVersion()` no filtra por `status`: "A requiere B" se satisface con un B instalado pero **inactivo**, cuyos hooks no estarán en runtime — la garantía queda a medias. (2) `syncAll()` procesa carpetas en orden de `scandir()` y `readValidatedManifest()` valida dependencias contra la BD: en instalación desde cero, si A requiere B y "a" < "b", la primera pasada de sync reporta error para A y registra B; hace falta una segunda. Hoy ningún plugin del repo usa `requires` (deuda latente), pero hay 10 tests que consagran la feature.
- Sugerencia: decidir y documentar si `requires` exige "instalado" o "activo"; en `syncAll()`, ordenar topológicamente o reintentar los fallidos en una segunda pasada interna.

**11. Deriva documentación↔código en tres puntos del manager**
- `backend/src/controllers/PluginManagerController.php:21-33, 77, 98` y `docs/03-api/endpoints.md:17`
- (1) El listado de rutas del docblock de clase omite `GET /api/v1/plugins/updates`, que existe (routes.php:67) y cuyo handler `listPluginUpdates()` (269) es el único sin docblock de ruta. (2) `registerPlugin()` dice "registers the **first** instance of a plugin discovered on disk" (98), cuando `registerNew()` soporta instancias adicionales (PluginAdministrationService.php:92-99). (3) `listAvailablePlugins()` dice "Disk plugin_name folders **not yet registered**" (77) y endpoints.md:17 "carpetas de disco aún no registradas", pero `listAvailableForRegistration()` devuelve **todas** las carpetas siempre — comportamiento deliberado documentado en su propio docblock (61-69).
- Sugerencia: completar el docblock de clase y el de `listPluginUpdates()`; reescribir (2) y (3) en controller y endpoints.md con la semántica multi-instancia real.

**12. Unicidad check-then-act sin respaldo de índice: duplicados posibles bajo concurrencia**
- `backend/src/plugins/contracts/AbstractUniqueFieldHook.php:78-95`
- La unicidad de email/sku se implementa como `SELECT COUNT(*)` seguido de inserción en otra sentencia, sin índice único sobre `content->>'{campo}'` ni bloqueo. Dos guardados concurrentes del mismo valor pasan ambos el COUNT y ambos insertan. Con el perfil actual la ventana es minúscula, pero es una promesa de negocio sin respaldo transaccional.
- Sugerencia: documentar la limitación como mínimo; para cerrarla, índice único parcial por expresión `(entity_slug, (content->>'email'))` creado en `onInstall()`, o `SELECT ... FOR UPDATE` sobre fila de sincronización.

**13. `instantiateLifecycle()` sin comprobación `instanceof`: un Lifecycle mal tipado revienta con `TypeError` en vez de error de dominio**
- `backend/src/plugins/lifecycle/PluginClassLoader.php:36-46`
- Declara retorno `?PluginLifecycleInterface` pero `instantiateWithOptionalPdo()` devuelve `object` sin verificar el contrato; si el `Lifecycle.php` de un plugin no implementa la interfaz, PHP lanza `TypeError` al retornar. Sí se revierte transaccionalmente, pero llega al admin como mensaje interno de tipos en vez de "Lifecycle de 'X' no implementa PluginLifecycleInterface". Relacionado: `instantiateHooks()` devuelve `?object` sin contrato alguno (alimenta el hallazgo 2).
- Sugerencia: comprobar `instanceof` y lanzar `PluginException` con mensaje claro; considerar un `PluginHooksInterface` con `register(HookDispatcher): void`.

### NIT

**14. Condición `in_array($manifest['type'], ['entity','extension'])` siempre verdadera**
- `backend/src/plugins/lifecycle/PluginSyncService.php:177` y `backend/src/plugins/lifecycle/PluginUpdateService.php:73`
- `PluginManifestReader::validateStructure()` ya garantiza que `type` es válido (VALID_TYPES, 11 y 59-65) y ambos servicios usan `readValidatedManifest()`. El `in_array` es código muerto que sugiere un tercer tipo inexistente.
- Sugerencia: eliminar la condición o sustituirla por comentario que remita a la validación del reader.

**15. Inconsistencia `catch (Exception)` vs `catch (\Throwable)` y fuga de mensajes internos**
- `backend/src/controllers/PluginManagerController.php:70` vs `281/302`
- `listPluginUpdates()` y `syncPlugins()` capturan `\Throwable`; el resto captura `Exception`, con lo que un `TypeError`/`Error` en esos flujos escapa. Además todos los 500 concatenan `$e->getMessage()` crudo, exponiendo detalle interno — mitigado por ser endpoints solo-admin.
- Sugerencia: unificar en `\Throwable`, loggear el detalle en servidor y devolver mensaje genérico.

**16. Orden de bloqueo opuesto en movimientos concurrentes: deadlock posible**
- `backend/src/plugins/lifecycle/PluginOrderService.php:52-63`
- `move()` bloquea primero la fila propia y luego la vecina. "A move-down" y "B move-up" simultáneos (B vecino de A) adquieren los mismos dos locks en orden inverso; PostgreSQL aborta una con 40P01 → 500. Probabilidad mínima con un solo admin.
- Sugerencia: bloquear en orden global determinista (id menor primero) o capturar 40P01 y reintentar una vez.

**17. El guard de idempotencia del registrar está keyed por instancia, no por dispatcher**
- `backend/src/plugins/lifecycle/PluginHookRegistrar.php:12-33`
- `registeredPluginNames` marca plugins registrados "por este registrar"; si un caller reutilizara el registrar con un **segundo** dispatcher, la llamada no registraría nada silenciosamente. El docblock (22-27) documenta la restricción 1:1, pero el guard protege exactamente el caso contrario al peligroso.
- Sugerencia: incluir el dispatcher en la clave (`spl_object_id($dispatcher) . ':' . $pluginName`) o añadir advertencia explícita.

**18. Escritura en dos pasos sin atomicidad en switch-demoinventory: manifest y schema pueden quedar de versiones distintas**
- `tools/setup/switch-demoinventory-version.php:35-43`
- Se escribe `manifest.json` y después `schema.json` con dos `file_put_contents`; si el segundo falla (40), queda manifest v2 + schema v1, estado mixto que el siguiente sync/update leería como coherente.
- Sugerencia: escribir a temporales y renombrar ambos al final, o escribir primero el schema.

**19. El wiring DI de sync está triplicado (app.php, tool y tests/helpers) y ya divergió una vez**
- `tools/setup/sync-plugins.php:24-39`
- El tool construye a mano el grafo `PluginSyncService` que `config/app.php:191-199` y `tests/helpers/plugins/plugin_services.php:63-74` también construyen. Tres copias del mismo ensamblaje son el caldo de cultivo del hallazgo 4.
- Sugerencia: reutilizar el Container real desde el tool (bootstrap + `xestifyRegisterPluginServices`).

**20. La clave `requires[].slug` del manifest se resuelve semánticamente como `plugin_name`**
- `backend/src/plugins/guards/PluginDependencyValidator.php:34`
- `$depPluginName = $dependency['slug']` se compara contra `manifest_json->>'name'` (plugin_name, fijo), no contra la columna `slug` (renombrable). Comportamiento correcto post-STORY 10.3 (con test), pero la clave se llama `slug`, invitando a poner el slug de instancia.
- Sugerencia: aceptar (y documentar) la clave `plugin_name` en `requires[]`, con `slug` como alias legacy.

**21. `backfillSchemaIfMissing` puede rellenar una instancia antigua con el schema de una versión más nueva**
- `backend/src/plugins/lifecycle/PluginSyncService.php:184, 210-221`
- El backfill corre también cuando el resultado es `outdated` (184, sin condición sobre `$result`): una fila legacy sin `schema_json` cuya carpeta ya va por versión superior recibe el schema nuevo, quedando `manifest_json.version` antigua con `schema_json` nuevo — contradice que `sync` preserva el runtime y que los campos nuevos solo entran vía `update`.
- Sugerencia: limitar el backfill a `RESULT_UNCHANGED`, o registrar el mismatch como advertencia.

## Cobertura de tests

La cobertura a nivel de servicio es notablemente buena y alineada con el código: `PluginSyncServiceTest` (11 casos: registro, atomicidad ante fallo de `onInstall`, preservación del nombre admin, schemas corruptos, backfill, multi-instancia, renombrado 10.3), `PluginUpdateServiceTest` (10, incluida atomicidad plugin+snapshot), `PluginStatusServiceTest` (incluye rollback de status si `onActivate` falla), `PluginIdentityServiceTest`, `PluginDeletionServiceTest`, `PluginOrderServiceTest`, `PluginDependenciesTest`/`PluginDependencyValidatorTest`, `PluginOutdatedServiceTest`, `PluginRegistrationServiceTest`, readers de discovery, `HookDispatcherTest` (12 casos, contrato before/after completo), `HookFilterTest`/`HookFilterApiTest` y `PluginBootTest`. Huecos: (1) `PluginRollbackServiceTest` solo 2 casos — falta la atomicidad cuando `onRollback` lanza y el status divergente snapshot↔actual; (2) `PluginCompatibilityValidator` sin test que rechace `core_version` incompatible (criterio de STORY 4.1 marcado ✅); (3) `PluginManagerApiTest` no cubre `DELETE /plugins/{slug}` ni `GET /plugins/available`, y el rechazo a no-admin solo se prueba en `GET /plugins`; (4) `PluginClassLoaderTest` un único caso; (5) nada testea `tools/setup/` — el bug del output habría saltado con un test mínimo; (6) el doble `TestPluginAdministrationService` (~400 líneas en PluginManagerApiTest.php:45-330) reimplementa a mano el servicio completo, con riesgo de deriva silenciosa; (7) el contrato "non-blocking" de after-hooks solo se verifica en CLI, donde el bug de `STDERR` es invisible. Los patrones `PluginLoader*`/`HookIntegration*` de auditorías anteriores ya no existen como ficheros — renombrados a los tests de discovery/lifecycle y `HookFilterApiTest`; no hay tests huérfanos.

## Observaciones transversales

- **Boilerplate repetido en el controller**: los 13 handlers repiten idéntico el bloque admin-check (4 líneas) y 10 de ellos el slug-check — unas 90 líneas que un middleware de rol por grupo de rutas o un helper eliminarían, garantizando que ningún endpoint futuro olvide el check.
- **Patrón transaccional duplicado seis veces**: `beginTransaction/try/commit/catch/inTransaction/rollBack/throw` calcado en `PluginSyncService`, `PluginUpdateService`, `PluginRollbackService`, `PluginStatusService` (x2), `PluginOrderService` y `PluginAdministrationService` (x3). Un helper `Tx::run(PDO, callable)` unificaría el manejo.
- **Dos convenciones de tolerancia a fallos frente al disco**: `syncAll()` aísla errores por plugin; `listAvailableForRegistration()`/`getOutdated()` fallan en bloque al primer manifest ilegible. Conviene elegir la primera como norma.
- **Tensión BD↔disco inherente al diseño**: la BD versiona manifest/schema, pero el código PHP del plugin se ejecuta siempre desde disco sin versionar: tras un rollback, la BD declara v1 mientras el código en disco es v2. Decisión de alcance razonable, pero no documentada en endpoints.md ni en el contrato de plugins — y explica por qué el rollback "transaccional" solo lo es para metadatos.
- **Identidad triple sin invariante**: carpeta de disco, `manifest.name` y `slug` de instancia conviven sin aserción que ligue las dos primeras (hallazgo 8).
- **Calidad general alta**: docblocks inusualmente informativos, locking pesimista bien aplicado, y validaciones de slug que cierran el path traversal desde HTTP; los ficheros de plugin solo se cargan por `plugin_name` originado en manifests de disco, que en este modelo de amenaza ya son código confiable.
