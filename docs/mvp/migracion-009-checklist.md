# Migracion 009 - Checklist de Transicion

## Objetivo
Consolidar el catalogo funcional de entidades sobre `plugins` (filtro `plugin_type = 'entity'`), manteniendo compatibilidad temporal con `system_entities`.

## SQL aplicado en Release A
- [backend/database/migrations/009_unify_entities_into_plugins.sql](backend/database/migrations/009_unify_entities_into_plugins.sql)

## Cambios de codigo (por archivo)

1. EntityController
- Archivo: [backend/src/controllers/EntityController.php](backend/src/controllers/EntityController.php)
- Cambiar `listEntities()` para consultar solo `plugins`:
  - `FROM plugins`
  - `WHERE plugin_type = 'entity' AND status = 'active' AND schema_json IS NOT NULL`
  - `SELECT slug, name AS label, schema_json, schema_version`

2. PluginLoader
- Archivo: [backend/src/plugins/PluginLoader.php](backend/src/plugins/PluginLoader.php)
- En `registerPlugin()` insertar/actualizar tambien `name` desde `manifest.json`.
- Validar `manifest.name` no vacio para plugins de tipo `entity`.

3. EntitySeeder
- Archivo: [backend/src/database/Seeders/EntitySeeder.php](backend/src/database/Seeders/EntitySeeder.php)
- Eliminar escritura en `system_entities`.
- Mantener UPSERT en `plugins` incluyendo `name`.

4. Clients Installer (y futuros entity installers)
- Archivo: [backend/plugins/clients/Installer.php](backend/plugins/clients/Installer.php)
- Quitar dependencia de `system_entities`.
- Mantener solo ajustes de schema en `plugins`.

5. Modelo de lectura de entidades (si aplica)
- Archivo: [backend/src/models/SystemEntity.php](backend/src/models/SystemEntity.php)
- Opciones:
  - Mantener archivo como facade sobre `plugins`, o
  - Renombrar a `EntityCatalog` y migrar consumidores.

## Cambios de tests (por archivo)

1. Catalogo de entidades
- Archivo: [backend/tests/integration/SystemEntitiesTableTest.php](backend/tests/integration/SystemEntitiesTableTest.php)
- Dejar en modo compatibilidad temporal o reemplazar por test de catalogo en `plugins`.

2. Tabla de plugins
- Archivo: [backend/tests/integration/PluginsRegistryTableTest.php](backend/tests/integration/PluginsRegistryTableTest.php)
- Anadir aserciones para columna `name` e indice `idx_plugins_type_status`.

3. EntityController
- Archivo: [backend/tests/integration/EntityControllerTest.php](backend/tests/integration/EntityControllerTest.php)
- Ajustar seeds para no requerir `system_entities`.

4. EntityService
- Archivo: [backend/tests/integration/EntityServiceTest.php](backend/tests/integration/EntityServiceTest.php)
- Mantener seeds sobre `plugins`; no usar `system_entities`.

5. Idempotencia
- Archivo: [backend/tests/integration/MigrationIdempotenceTest.php](backend/tests/integration/MigrationIdempotenceTest.php)
- Incluir `009_unify_entities_into_plugins.sql` en lista de migraciones.

## Validacion de rollout (Release A)

1. Ejecutar migracion 009 en dev
- `psql -U postgres -d xestify_dev -f backend/database/migrations/009_unify_entities_into_plugins.sql`

2. Verificar backfill de nombres
- `SELECT slug, name, plugin_type, status FROM plugins ORDER BY slug;`

3. Verificar idempotencia
- Ejecutar 009 dos veces y confirmar exit code 0.

4. Ejecutar test suite afectada
- EntityController, EntityService, PluginLoader, MigrationIdempotence, PluginsRegistryTable.

## Release B (limpieza) ✅ COMPLETADO

1. Prerrequisito ✅
- Confirmado: cero lecturas/escrituras a `system_entities` en codigo y tests.

2. Migracion de limpieza ✅
- Creado y aplicado `010_drop_system_entities.sql`:
  - `DROP TABLE IF EXISTS system_entities;`

3. Archivos actualizados ✅
- `backend/src/models/SystemEntity.php` — queries a `plugins WHERE plugin_type='entity'`
- `backend/tests/integration/SystemEntitiesTableTest.php` — verifica que la tabla NO existe (3 tests)
- `backend/tests/integration/MigrationIdempotenceTest.php` — system_entities eliminado; test datos en plugins; migración 010 añadida
- `backend/tests/integration/SystemEntityTest.php` — fixtures INSERT/DELETE en plugins

4. Post-check ✅
- Suite completa tras Release B: EntityControllerTest 9/9, EntityServiceTest 6/6, ClientsPluginTest 14/14, PluginLifecycleTest 8/8, PluginDependenciesTest 6/6, HookFilterApiTest 10/10, CommentsPluginTest 9/9, PluginsRegistryTableTest 6/6, MigrationIdempotenceTest 3/3, SystemEntitiesTableTest 3/3, SystemEntityTest 7/7 — **0 fallos**
