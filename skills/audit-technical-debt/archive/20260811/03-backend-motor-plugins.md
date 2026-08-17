# Auditoría — Motor de Plugins y Hooks (núcleo)

**Subsistema:** Plugin Core Engine
**EPIC cubiertas:** EPIC 4 (sistema de plugins y hooks backend)
**Severidades:** 0 crítico · 3 mayor · 9 menor · 1 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Revisión completa (lectura íntegra) de los ficheros indicados en `backend/src/plugins/`, `backend/src/exceptions/`, `plugins/clients/`, `plugins/products/`, `plugins/comments/` (para contraste), los tests unitarios/integración listados, y la documentación de referencia.

---

## Hallazgos por severidad

### MAYOR

**1. Registro de hooks en BD (`plugin_hooks`) completamente desconectado del runtime real**
- `plugins/comments/Lifecycle.php:29-63`, `backend/database/migrations/004_plugin_hooks.sql`, `docs/01-architecture/hooks.md:58-67`, `backend/src/plugins/runtime/PluginHookRegistrar.php:19-27`
- Categoría: refactor incompleto / diseño abandonado
- `comments/Lifecycle.php` inserta y actualiza filas en la tabla `plugin_hooks` (columnas `slug`, `target_entity_slug`, `hook_name`, `priority`, `enabled`) en `onInstall`/`onActivate`/`onDeactivate`, y `docs/01-architecture/hooks.md` documenta esa tabla como el mecanismo de "Registro en base de datos" de hooks. Pero `PluginHookRegistrar::registerActiveHooks()` — el único punto real donde se cablean hooks en el `HookDispatcher` — **nunca consulta `plugin_hooks`**: simplemente llama `Hooks->register($dispatcher)` para cada slug activo (`PluginRepository::listActiveSlugs()`), incondicionalmente. Es decir, el campo `enabled` que `onDeactivate()` pone a `false` no tiene ningún efecto sobre si el hook se ejecuta o no; lo único que realmente controla la visibilidad es `plugins.status`. Confirmado además por `CommentsPluginTest.php:316-331`, que desactiva el plugin vía `plugins.status` (no vía `plugin_hooks.enabled`) para probar que el tab desaparece.
- Es infraestructura de una versión anterior del diseño (probablemente STORY 2.5, previa a la convención `Hooks.php`) que quedó a medio migrar: se sigue escribiendo y testeando su existencia (`PluginHookRegistryTableTest.php`), pero es inerte. Para la defensa del TFM esto es importante identificarlo, porque documentar una tabla "fuente de verdad" que no lo es genera una discrepancia real entre diseño documentado y comportamiento.
- Sugerencia: o bien eliminar la tabla/escrituras (y actualizar `hooks.md`), o bien hacer que `PluginHookRegistrar` la consulte de verdad y sea la fuente de verdad para prioridad/activación por hook (lo que sí permitiría activar/desactivar hooks individuales, no solo el plugin entero).

**2. Tests del propio sistema de hooks que nunca pueden fallar en el runner agrupado**
- `backend/tests/unit/HookFilterTest.php` (falta línea final), `backend/tests/integration/HookFilterApiTest.php` (falta línea final), `backend/tests/integration/PluginHookRegistryTableTest.php` (falta línea final)
- Categoría: hueco de cobertura / bug en infraestructura de tests
- `backend/tests/run.php` decide si un fichero de test "falló" mirando el código de salida del proceso PHP (`passthru(...)`, `$exitCode !== 0`). Todos los ficheros de test siguen el patrón `TestSuite::summary(); exit(TestSuite::exitCode());` — **excepto estos tres**, a los que les falta el `exit(...)` final. Como PHP termina con código 0 por defecto, cualquier assertion fallida dentro de estos ficheros se imprime como `❌` en consola pero el runner agrupado (`php backend/tests/run.php unit|integration-plugins`) lo contará como "pasado". Esto afecta directamente a las pruebas de `registerTabs`/`registerActions` (`applyFilter`), que son el corazón del EPIC 4 filtrable. Es un falso verde silencioso justo en el subsistema auditado.
- Sugerencia: añadir `exit(TestSuite::exitCode());` a los tres ficheros (y de paso a `CommentsPluginTest.php`, `EntityDataTableTest.php`, `GenericRepositoryTest.php`, que tienen el mismo problema fuera del alcance de esta auditoría).

**3. `PluginClassLoader`: instanciación de `Hooks` vs `Lifecycle` inconsistente**
- `backend/src/plugins/infrastructure/PluginClassLoader.php:36-46` vs `:60-75`
- Categoría: duplicación / inconsistencia de patrón
- `instantiateHooks()` usa Reflection para inspeccionar el constructor de la clase `Hooks` y decide si pasarle el `PDO` o instanciarla sin argumentos (`instantiateWithOptionalPdo()`), lo que permite que un plugin como `comments` tenga `__construct(private ?PDO $pdo = null)`. `instantiateLifecycle()`, en cambio, siempre hace `new $class($this->pdo)` a pelo, sin esa comprobación. Es la misma responsabilidad ("instanciar una clase de plugin, inyectando PDO si aplica") resuelta con dos estrategias distintas en la misma clase — y la segunda es frágil: un plugin que escribiera un `Lifecycle` sin parámetro `PDO` en el constructor produciría un `TypeError` sin ningún mensaje de dominio (`PluginException`) que lo explique.
- Sugerencia: unificar en un único método privado de instanciación reflexiva usado por ambos casos.

### MENOR

**4. Duplicación casi literal entre `plugins/clients` y `plugins/products`**
- `plugins/clients/Installer.php` vs `plugins/products/Installer.php`; `plugins/clients/Hooks.php` vs `plugins/products/Hooks.php`
- Categoría: redundancia
- Los dos `Installer.php` son idénticos salvo el nombre/slug de entidad (registro en `plugins`, siembra de `schema_json`, mismo SQL, mismo manejo de `PDOException`). Los dos `Hooks.php` implementan el mismo patrón "unicidad de campo" (`enforceEmailUniqueness` / `enforceSkuUniqueness`) con la misma consulta SQL parametrizada, cambiando solo el nombre del campo. Al ser los dos únicos plugins de entidad de referencia del proyecto, esta duplicación (~90 líneas casi calcadas) es la señal más clara para extraer un `AbstractEntityInstaller` o un helper `assertFieldUnique(pdo, entitySlug, field, value, excludeId)` reutilizable por futuros plugins de entidad — especialmente relevante porque el propio EPIC 4 sienta el patrón que se pretende que otros plugins sigan.
- Sugerencia: extraer un trait/clase base común para "instalador idempotente de entidad" y "hook de unicidad de campo".

**5. `EntityController` con dependencia oculta por valor por defecto**
- `backend/src/controllers/EntityController.php:37` — `private HookDispatcher $hookDispatcher = new HookDispatcher()`
- Categoría: violación de clean code / DI implícita
- Si en algún momento se instancia `EntityController` sin pasar explícitamente el dispatcher (test ad-hoc, refactor futuro del `Container`, controlador construido a mano), se crea silenciosamente un `HookDispatcher` vacío: `registerTabs`/`registerActions` devolverían siempre `[]` sin ningún error ni log. En producción hoy no ocurre porque `config/app.php:222-227` siempre inyecta el singleton del contenedor, pero el valor por defecto es un footgun que contradice el principio de "fail fast" — es preferible que la ausencia de dependencia rompa explícitamente.
- Sugerencia: quitar el valor por defecto y forzar inyección explícita (o lanzar si es null).

**6. Tests casi duplicados que documentan, en vez de corregir, una falta de idempotencia**
- `backend/tests/integration/PluginBootTest.php:75-121` vs `backend/tests/integration/PluginHookRegistrarTest.php:36-65`
- Categoría: redundancia / refactor perdido
- Ambos ficheros prueban lo mismo casi palabra por palabra: activar `comments`, invocar `registerActiveHooks()` y comprobar que aparece el tab en `registerTabs`. Ambos incluyen además un test explícitamente llamado "preserves current repeated-registration behavior" / "keeps current non-idempotent behavior": si `registerActiveHooks()` se llama dos veces sobre el mismo dispatcher, el hook de `comments` se registra dos veces (duplicaría tabs/acciones en la UI si algún día se invocara más de una vez por request). El test documenta el comportamiento en vez de tratarlo como bug a corregir, y el fichero antiguo no se eliminó cuando se creó el nuevo.
- Sugerencia: consolidar en un único fichero de test y decidir conscientemente si `registerActiveHooks()` debe ser idempotente (guardar un `array_key_exists` por slug ya registrado).

**7. `ClientsPluginTest.php` usa `assert()` nativo de PHP en vez de los helpers del propio framework de test**
- `backend/tests/unit/ClientsPluginTest.php` (fichero completo)
- Categoría: inconsistencia / test frágil
- Es el único fichero de toda la suite que usa el `assert()` del lenguaje en lugar de `assertTrue()`/`assertEquals()` de `helpers.php`. El comportamiento de `assert()` depende de la directiva `zend.assertions` del `php.ini` activo: con `zend.assertions = -1` (típico de `php.ini-production`) las llamadas se eliminan en tiempo de compilación y el cuerpo de la aserción **ni siquiera se ejecuta**, con lo que estos tests pasarían siempre trivialmente sin verificar nada, sin que se note salvo leyendo el código. Con la configuración PHP por defecto (sin `php.ini` explícito, `zend.assertions = 1`) esto no ocurre, así que no es crítico en el entorno actual, pero es una dependencia implícita de configuración que ningún otro test del proyecto tiene.
- Sugerencia: sustituir todos los `assert(...)` por `assertTrue`/`assertEquals` del propio `TestSuite`.

**8. Docblock de `HookDispatcher::register()` engañoso sobre paso por referencia**
- `backend/src/plugins/HookDispatcher.php:28`
- Categoría: clean code / documentación incorrecta
- El comentario dice "Callback receiving a context array (passed by reference)", pero la implementación pasa el array por valor y propaga el cambio únicamente si el callback **devuelve** un array (`invokeCallback`, líneas 102-106). No hay ninguna referencia PHP (`&$context`) en ningún punto. Un autor de plugin que confíe en el comentario y mute `$ctx` sin hacer `return $ctx;` verá que su cambio desaparece silenciosamente — de hecho todos los hooks reales del repo (`clients`, `products`, `comments`) sí devuelven correctamente el array, así que hoy no ha causado un bug real, pero el comentario contradice el código.
- Sugerencia: corregir el docblock a "returned, not mutated in place".

**9. `plugins/products/Lifecycle.php` importa una clase de su propio namespace innecesariamente**
- `plugins/products/Lifecycle.php:8` — `use Xestify\plugins\products\Installer;`
- Categoría: nit de consistencia
- `Installer` ya está en el mismo namespace `Xestify\plugins\products`, por lo que el `use` es redundante (PHP lo resolvería igual sin él). `plugins/clients/Lifecycle.php` no tiene ese `use` para su propio `Installer`. Señal menor de que el segundo plugin se copió del primero sin homogeneizar del todo.

**10. `PluginLifecycleInterface` no refleja el contrato extendido que el runtime realmente invoca**
- `backend/src/plugins/PluginLifecycleInterface.php` vs `backend/src/plugins/runtime/PluginLifecycleInvoker.php:42-59`
- Categoría: inconsistencia de contrato
- La interfaz solo declara `onInstall/onActivate/onDeactivate`, pero `PluginLifecycleInvoker::onUpdate()`/`onRollback()` invocan esos métodos vía `method_exists($lifecycle, 'onUpdate')` con comentarios `@phpstan-ignore-line`. Es un patrón de "interfaz opcional" no formalizado (ni una segunda interfaz `UpdatableLifecycleInterface`, ni documentado en el propio fichero de la interfaz). Positivamente, `docs/01-architecture/hooks.md:14` sí deja constancia explícita de que `onUpdate` no forma parte del contrato — está reconocido, pero no resuelto a nivel de tipos.
- Sugerencia: introducir una interfaz opcional (`SupportsUpdateInterface`) en vez de `method_exists` + supresión de análisis estático.

**11. Bug de borde en `PluginSchemaReader`: un `schema.json` con `"fields": {}` se rechaza incorrectamente**
- `backend/src/plugins/infrastructure/PluginSchemaReader.php:41-47`
- Categoría: bug de correctitud (edge case)
- `json_decode('{"fields": {}}', true)` produce `['fields' => []]` en PHP; `array_is_list([])` devuelve `true` para un array vacío (PHP no distingue objeto-vacío de lista-vacía tras decodificar). La comprobación `if (array_is_list($decoded['fields'])) { throw ... }` rechazaría por tanto un `schema.json` legítimo cuyo plugin de entidad no defina campos propios más allá de `identities`/`custom_fields`. Ninguno de los plugins actuales (`clients`, `products`) dispara este caso porque ambos tienen al menos un campo en `fields`, pero es un bug real latente en la validación.
- Sugerencia: comprobar explícitamente `count($decoded['fields']) > 0 && array_is_list(...)`, o exigir al menos un campo con un mensaje de error distinto.

**12. Enrutamiento before/after basado solo en el prefijo del string, sin red de seguridad**
- `backend/src/plugins/HookDispatcher.php:53`
- Categoría: robustez / clean code
- `$isBefore = str_starts_with($hook, 'before')` es el único mecanismo que decide si una excepción bloquea la operación o solo se loguea. Un hook mal nombrado por error tipográfico (p. ej. `onSave` en vez de `beforeSave`) degradaría silenciosamente a semántica "no bloqueante" sin ningún aviso. No es un bug hoy (todos los hooks reales siguen la convención), pero es una convención implícita sin validación defensiva.

### NIT

**13. Tres capas de carpetas (`infrastructure/`, `runtime/`, `application/`) más `HookDispatcher.php` suelto para ≈21 clases**
- Categoría: complejidad. A nivel de clase el SRP está bien aplicado (cada fichero tiene una responsabilidad estrecha, testeada y con nombre preciso); a nivel de carpeta, la ceremonia de 3 capas arquitectónicas es más pesada de lo que el tamaño real del catálogo de plugins (2-3 plugins reales) exige, y obliga a saltar entre 4-6 ficheros para reconstruir mentalmente "qué pasa al instalar un plugin". No es incorrecto, pero es un buen punto a anticipar en la defensa si un tribunal pregunta "¿por qué tantas clases pequeñas?".

---

## Mapa del flujo real de carga de un plugin

**A. Arranque de cada request HTTP** (`backend/src/config/app.php`):
1. `xestifyRegisterEntityServices()` registra `HookDispatcher` como singleton **vacío** en el contenedor.
2. `xestifyRegisterPluginServices()` cablea las 8 clases de `infrastructure/`, las 2 de `runtime/` y las ~10 de `application/` — todo perezoso, nada se ejecuta todavía.
3. `xestifyBootPluginHooks()` obtiene `PluginHookRegistrar` y llama `registerActiveHooks($hookDispatcher)`:
   - `PluginRepository::listActiveSlugs()` → slugs con `plugins.status = 'active'` (tabla ya sincronizada de antemano; la sincronización disco→BD es explícita, no ocurre en cada request — ver comentario en `config/app.php:266-268`).
   - Por cada slug: `PluginClassLoader::instantiateHooks($slug)` hace `require_once plugins/{slug}/Hooks.php`, comprueba que exista `Xestify\plugins\{slug}\Hooks` y la instancia (con o sin `PDO` según Reflection del constructor).
   - Si la instancia existe, se llama a `$hooks->register($dispatcher)` — es el propio plugin quien decide qué hooks registra y con qué prioridad.
4. A partir de aquí el `HookDispatcher` singleton tiene todos los callbacks de los plugins activos para el resto de la request.
5. `EntityService::createRecord()/updateRecord()` disparan `execute('beforeSave'|'afterSave', ctx)`; `EntityController::tabs()/actions()` disparan `applyFilter('registerTabs'|'registerActions', ...)`.

**B. Instalación/activación de un plugin** (operación administrativa explícita, vía `application/PluginSyncService` y `PluginStatusService`, no en cada request):
1. `PluginSourceService::discover()` → `PluginDiscoveryService` escanea `plugins/` y devuelve slugs con `manifest.json`.
2. Por slug: `PluginManifestReader` (valida campos obligatorios y `type`) → `PluginCompatibilityValidator` (compara `core_version`) → `PluginDependencyValidator` (resuelve `requires` contra `PluginRepository`) → `PluginSchemaReader` (lee `schema.json`, obligatorio si `type=entity`).
3. `PluginSyncService` persiste en `plugins` vía `PluginRepository` y, si es alta nueva, invoca `PluginLifecycleInvoker::onInstall($slug)` → `PluginClassLoader::instantiateLifecycle($slug)` (`require_once Lifecycle.php` + `new Lifecycle($pdo)`) → `$lifecycle->onInstall()`, que en `clients`/`products` ejecuta el `Installer.php` correspondiente.
4. `PluginStatusService::activate()/deactivate()` cambian `plugins.status` y llaman `onActivate()`/`onDeactivate()` del lifecycle.

La fragmentación en 8+2 clases de `infrastructure/`+`runtime/` responde en la práctica a un patrón fachada: `PluginSourceService` agrupa 5 colaboradores de solo-lectura de disco/validación para que el resto del sistema no dependa de los 5 por separado; `PluginClassLoader` es el único punto que hace `require_once` + instanciación dinámica (una responsabilidad claramente distinta de "leer JSON"); `runtime/` separa el momento "cablear hooks en caliente" del momento "invocar callbacks de ciclo de vida". Es una separación defendible por SRP a nivel de clase, aunque más granular de lo estrictamente necesario a nivel de carpeta para el tamaño actual del proyecto.

---

## Resumen de salud del subsistema

El núcleo (`HookDispatcher`, `PluginClassLoader`, `PluginHookRegistrar`, `PluginLifecycleInvoker`, los validadores de `infrastructure/`) está bien escrito: tipado estricto, responsabilidades acotadas, manejo de excepciones before/after correcto y bien testeado en `HookDispatcherTest.php`. El problema principal no es la lógica del dispatcher en sí, sino la **coherencia entre lo documentado/scaffoldeado y lo que realmente se ejecuta**: la tabla `plugin_hooks` es infraestructura muerta desde el punto de vista funcional (se escribe y se testea su existencia, pero nada la lee), y dos ficheros de test del propio sistema de filtros (`HookFilterTest.php`, `HookFilterApiTest.php`) no pueden hacer fallar la ejecución agrupada por falta de un `exit()` — ambos son hallazgos "silenciosos" que un tribunal de TFM valorará positivamente que hayas detectado tú mismo. Los dos plugins de entidad de referencia (`clients`/`products`) están duplicados casi literalmente, lo cual es esperable en una primera generación de plugins pero sería el primer candidato a refactor si se añadiera un tercero. La separación `infrastructure/runtime/application` es un ejercicio de SRP genuino a nivel de clase, aunque probablemente más capas de las que el tamaño actual del catálogo de plugins justifica — es un punto defendible en la memoria como decisión consciente de diseño en lugar de necesidad estricta.

## Nota sobre cobertura de tests

La cobertura funcional es notable: hay tests unitarios exhaustivos de `HookDispatcher` (antes/after, prioridad, orden de registro, excepciones envueltas), de `applyFilter` (acumulación, fallos aislados), de descubrimiento/lectura de manifest y schema con fixtures reales, y tests de integración de extremo a extremo (boot completo, dependencias entre plugins, lifecycle con clases generadas dinámicamente, API de tabs/actions). Los huecos reales no son de "qué se prueba" sino de "fiabilidad de la señal": (a) tres ficheros no propagan el código de salida y por tanto no pueden bloquear el runner agrupado ante un fallo real; (b) un fichero usa `assert()` nativo, dependiente de configuración de PHP, en vez de los helpers propios del proyecto; (c) hay redundancia entre `PluginBootTest.php` y `PluginHookRegistrarTest.php` que aporta mantenimiento sin aportar cobertura nueva. No se encontró ningún camino de código del núcleo (antes/after, antes de activar/desactivar, carga de clases) sin al menos un test que lo ejerza.
