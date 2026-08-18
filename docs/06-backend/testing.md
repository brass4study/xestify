# Testing de Backend

## Arquitectura de los tests

Los tests de backend son scripts PHP **standalone**, sin PHPUnit ni ningún
framework de testing externo. Cada fichero:

- Define sus propios tests con `TestSuite::run('descripción', function () {...})`
  (helper compartido en `backend/tests/unit/helpers.php`).
- Usa `assertTrue()`, `assertEquals()`, `assertFalse()`, `assertNull()` como
  aserciones.
- Termina con `TestSuite::summary()` y `exit(TestSuite::exitCode())` — el
  código de salida es lo único que el runner agrupado usa para decidir si el
  fichero pasó o falló (ver `TestRunnerExitCodeTest.php` más abajo, que
  vigila precisamente esto).
- Se puede ejecutar solo (`php backend/tests/unit/RouterTest.php`) o en grupo.

## Ejecutar los tests

```bash
php backend/tests/run.php unit                  # 34 ficheros
php backend/tests/run.php integration-db        # 17 ficheros
php backend/tests/run.php integration-plugins   # 23 ficheros
php backend/tests/run.php all                   # los 74 ficheros
php backend/tests/unit/RouterTest.php           # un solo fichero, directo
```

Los tests de `backend/tests/integration/` requieren PostgreSQL real,
accesible según `backend/.env`. El patrón dominante en ese grupo es
comprobar la conexión al principio y hacer `[SKIP]` limpio (0 passed, 0
failed, exit 0) si la base de datos no está disponible, en vez de fallar
— así el runner agrupado no se rompe en un entorno sin BD configurada.

## ⚠️ "unit" no significa "aislado sin I/O"

`unit`/`integration-*` es la convención propia de este proyecto para
**"toca PostgreSQL o no"**, no la definición estricta de test unitario
(aislado, sin ningún I/O). De los 33 ficheros del grupo `unit`:

- **21 son unitarios puros**: ninguna dependencia más allá de cargar su
  propia clase de producción, datos siempre en memoria.
- **9 hacen I/O real de sistema de ficheros** (marcados 🗂️ abajo), aunque
  nunca tocan PostgreSQL:
  - 4 crean/borran directorios temporales reales vía `sys_get_temp_dir()`
    (`createPluginFixture()` en `backend/tests/helpers/plugins/plugin_fixtures.php`):
    `PluginDiscoveryServiceTest`, `PluginManifestReaderTest`,
    `PluginSchemaReaderTest`, `PluginClassLoaderTest`.
  - 5 cargan directamente el `Hooks.php` **real** de un plugin del repo
    (`require_once BASE_PATH . '/plugins/<slug>/Hooks.php'`), con PDO
    simulado pero el fichero fuente es el real: `PersonsPluginTest`,
    `OptometriesPluginTest`, `InvoicesPluginTest`, `ProductsPluginTest`,
    `ContactLensesPluginTest`.
- **2 comprueban `manifest.json`/`schema.json` reales del repo** sin cargar
  ninguna clase PHP (marcados 📄): `OrdersPluginTest`, `BasicPluginTest`.
- **1 es un meta-test** (marcado 🧪): `TestRunnerExitCodeTest` no prueba
  código de producción — lee el código fuente de *otros* ficheros de test
  para comprobar que propagan bien su código de salida.

## Grupo `unit` (34 ficheros)

| Fichero | Verifica |
|---|---|
| `RouterTest.php` | Matching de rutas estáticas/dinámicas, métodos HTTP y protección por defecto del Router |
| `RuntimePathNormalizerTest.php` | Normalización de raíz, alias `/xestify` y rutas de runtime |
| `RequestFactoryTest.php` | Construcción de `Request` desde datos explícitos (`create`) y desde variables globales (`fromGlobals`) |
| `AuthMiddlewareTest.php` | Solo deja pasar con JWT válido; rechaza tokens ausentes, expirados o inválidos |
| `EntityServiceHooksTest.php` | Integración de los hooks `beforeSave`/`afterSave` en `EntityService` con stubs, sin conexión real |
| `ValidationServiceTest.php` | Valida payloads contra el schema (tipos, requeridos, rangos, `custom_fields`) |
| `FieldValidatorsTest.php` | Validadores de campo individuales (string, number, email, date, uuid, etc.) |
| `SchemaFieldExtractorTest.php` | Extracción correcta de las definiciones de campos de un schema |
| `HookDispatcherTest.php` | Registro y disparo de acciones (hooks) |
| `HookFilterTest.php` | `HookDispatcher::applyFilter()` para los hooks de filtro `registerTabs`/`registerActions` |
| `JwtServiceTest.php` | Codificación, decodificación y validación (expiración, firma) de tokens JWT |
| `ProfileSecretVerifierTest.php` | Detección de cambios de email y de contraseña en un payload de perfil |
| `ProfileUpdateAuthorizerTest.php` | Reglas al cambiar email/password (seed protegido, secreto requerido, mismatch) |
| `UserAuthorizerTest.php` | Reglas para borrar usuarios (rol admin, auto-borrado, cuentas seed protegidas) |
| `RequestResponseTest.php` | Métodos de `Request` (query, body, headers, roles) y `Response` (json, error, shortcuts HTTP) |
| `HealthControllerTest.php` | Expone correctamente el flag `debug` según la variable `APP_DEBUG` |
| `ContainerTest.php` | Contenedor de inyección de dependencias (`register`, `singleton`, `has`, `get`) |
| `PersonsPluginTest.php` 🗂️ | `manifest.json`, `schema.json` y el hook de email único del plugin `persons` |
| `ProductsPluginTest.php` 🗂️ | `manifest.json`, `schema.json` y el hook de SKU único del plugin `products` |
| `OrdersPluginTest.php` 📄 | `manifest.json` y `schema.json` del plugin `orders` (sin `Hooks.php` ni restricción de unicidad) |
| `InvoicesPluginTest.php` 🗂️ | `manifest.json`, `schema.json` y el hook de número de factura único del plugin `invoices` |
| `BasicPluginTest.php` 📄 | `manifest.json` y `schema.json` del plugin `basic` (sin `Hooks.php` ni restricción de unicidad) |
| `OptometriesPluginTest.php` 🗂️ | `manifest.json` y `schema.json` del plugin `optometries` (campos, layers, orden y relaciones) |
| `ContactLensesPluginTest.php` 🗂️ | `manifest.json` y `schema.json` del plugin `contact_lenses` (campos, layers, orden y relaciones) |
| `PluginDiscoveryServiceTest.php` 🗂️ | Descubre los slugs de plugins a partir de sus `manifest.json` |
| `PluginManifestReaderTest.php` 🗂️ | Valida y lee correctamente el `manifest.json` de un plugin |
| `PluginSchemaReaderTest.php` 🗂️ | Lee y valida el `schema.json` de plugins `entity` y `extension` |
| `PluginSchemaMergeServiceTest.php` | Fusiona cambios de schema de forma aditiva sin romper compatibilidad |
| `PluginConfigFieldNormalizerTest.php` | Normaliza y valida definiciones de campos de configuración |
| `PluginClassLoaderTest.php` 🗂️ | Instancia las clases `Hooks`/`Lifecycle` del plugin inyectando PDO |
| `TestRunnerExitCodeTest.php` 🧪 | Que los ficheros de test señalizan fallos al runner agrupado vía código de salida |
| `PluginTypeGuardTest.php` | Rechaza cambios del campo `plugin_type` entre actualizaciones de manifest |
| `SchemaComparisonUtilTest.php` | Normaliza estructuras para comparar schemas ignorando el orden de claves |
| `ToolsCliGuardTest.php` | Guard estático de `tools/`: `bootstrap.php` empieza con la comprobación `PHP_SAPI !== 'cli'`, todo `tools/**/*.php` lo requiere como primera sentencia y ningún script acepta contraseñas por flag |

## Grupo `integration-db` (17 ficheros)

Tablas core, repositorios genéricos y controladores que las usan directamente.

| Fichero | Verifica |
|---|---|
| `DatabaseTest.php` | Conexión a PostgreSQL, saltando las pruebas si la base de datos no está disponible |
| `SchemaIdempotenceTest.php` | Los ficheros de `backend/database/schema/` se descubren en orden y se aplican dos veces sin error ni pérdida de datos vía `SchemaInstaller` (mismo camino que `tools/setup/install.php`, sin `psql`); comprobaciones de solo lectura de `DatabaseProvisioner` (existencia de rol/BD, rechazo de identificadores inválidos) |
| `EntityDataTableTest.php` | La tabla `plugin_entity_data` fue creada correctamente por su migración |
| `EntityMetadataTableTest.php` | `plugin_entity_metadata` ya no existe y `plugins` tiene la columna `schema_json` |
| `PluginsRegistryTableTest.php` | La tabla `plugins` fue creada correctamente por su migración |
| `PluginUpdateHistoryTableTest.php` | La tabla `plugin_update_history` fue creada correctamente por su migración |
| `SystemEntitiesTableTest.php` | La tabla obsoleta `system_entities` no existe en el esquema de migraciones actual |
| `GenericRepositoryTest.php` | Ciclo CRUD completo de `GenericRepository` contra PostgreSQL real |
| `UserRepositoryTest.php` | Migración de perfiles y superficie CRUD de `UserRepository` |
| `ConfigurationRepositoryTest.php` | `ConfigurationRepository` guarda y recupera configuraciones (p. ej. `ui-preferences`) en BD |
| `ConfigurationControllerTest.php` | Endpoints de `ConfigurationController` (listar, consultar, actualizar) con control de rol admin |
| `UserControllerTest.php` | Endpoints REST de perfil de usuario y administración de `UserController` |
| `AuthControllerTest.php` | Endpoint de login (`POST /api/v1/auth/login`) |
| `EntityServiceTest.php` | Operaciones CRUD (crear, actualizar, borrar, listar) de `EntityService` contra BD real |
| `EntityControllerTest.php` | Métodos de `EntityController` de extremo a extremo contra BD real |
| `BusinessDataSeederTest.php` | `BusinessDataSeeder` genera datos demo de forma idempotente y con relaciones cruzadas válidas (STORY 11.2) |
| `AdminUserCreatorTest.php` | `AdminUserCreator` crea un administrador real (`is_seed=false`, rol admin, hash bcrypt verificable), detecta si ya existe uno y rechaza email inválido/duplicado, nombre vacío y contraseña corta sin insertar |

## Grupo `integration-plugins` (23 ficheros)

Ciclo de vida de plugins, hooks activos en runtime y endpoints de gestión.

| Fichero | Verifica |
|---|---|
| `PluginDependencyValidatorTest.php` | Valida dependencias entre plugins (versión, presencia, renombrado) |
| `PluginIdentityServiceTest.php` | Actualiza slug/nombre/descripción de un plugin de forma segura |
| `PluginDeletionServiceTest.php` | Elimina plugins (`entity`/`extension`) y todos sus datos asociados |
| `PluginRegistrationServiceTest.php` | Activa plugins nuevos y dispara su hook `onActivate()` |
| `PluginRelationsConfigTest.php` | `saveConfig()` valida y persiste las relaciones configuradas de un plugin |
| `PluginFieldsConfigTest.php` | `saveConfig()` persiste cambios de `summaryView` en campos base y de extensión |
| `ReverseRelationTest.php` | Las relaciones inversas declaradas por un plugin generan tabs y listados filtrados |
| `EntityOptionsTest.php` | Endpoint `GET /entities/{slug}/options` usado para poblar selects de relación |
| `PluginSyncServiceTest.php` | `PluginSyncService` registra plugins, detecta deriva real del schema instalado (`identities`/`fields` base frente al disco, ignorando `summaryView`/`layer`/`origin`), no marca como corrupta la configuración permitida por PluginConfig (extensiones sin `identities`, campos sugeridos editados/eliminados, relaciones por instalación) y hace rollback en `syncAll()` |
| `PluginUpdateServiceTest.php` | `PluginUpdateService` actualiza versión y fusiona cambios de schema de forma aditiva |
| `PluginRollbackServiceTest.php` | `PluginRollbackService` restaura una versión anterior desde snapshot e invoca `onRollback()` |
| `PluginStatusServiceTest.php` | `PluginStatusService` activa/desactiva plugins e invoca sus hooks de ciclo de vida |
| `PluginOrderServiceTest.php` | `PluginOrderService` reordena plugins (`moveUp`/`moveDown`) respetando su tipo |
| `PluginOutdatedServiceTest.php` | `PluginOutdatedService` detecta plugins con versión en disco más reciente que la instalada |
| `PluginLifecycleInvokerTest.php` | `PluginLifecycleInvoker` invoca los métodos opcionales del ciclo de vida del plugin |
| `PluginDependenciesTest.php` | `syncAll()` respeta las dependencias (`requires`) declaradas en el manifest de un plugin |
| `PluginLifecycleTest.php` | Ciclo de vida completo de un plugin (instalación, activación, desactivación) vía `syncAll()` |
| `PluginBootTest.php` | El registro de hooks activos al arrancar la app es idempotente y expone tabs |
| `HookFilterApiTest.php` | El hook `registerTabs` de un plugin aparece en `GET /entities/{slug}/tabs` |
| `CommentsPluginTest.php` | Instalación del plugin `comments` y sus endpoints de listar/crear comentarios |
| `ExtensionRelationsTest.php` | Las extensiones validan su contenido contra su schema y soportan `relations` (STORY 10.5) |
| `AppWiringTest.php` | Cableado de producción del container y router (`AuthMiddleware`, `HookDispatcher` compartido) |
| `PluginManagerApiTest.php` | Endpoints REST de gestión de plugins (listar, sync, update, rollback, status, orden, config) |

## Referencias

- [Arquitectura general](../01-architecture/overview.md)
- [Arquitectura MVC](../01-architecture/mvc.md)
- [Hooks](../01-architecture/hooks.md)
- `AGENTS.md`, sección "Tests": política general de verificación del proyecto
- [testing.md](../05-frontend/testing.md): equivalente para frontend (integración con `fetch` mockeado + E2E Playwright)
