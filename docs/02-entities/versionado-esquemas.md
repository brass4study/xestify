# Versionado de Esquemas

## Objetivo

Permitir evolucion de campos por plugin sin romper registros existentes.

## Estado real (no hay versionado de schema por-campo)

No existe un mecanismo de `schema_version` por campo ni una tabla
`plugin_migrations`.

Lo que existe hoy es una actualización **de plugin completo**, no de campos
individuales versionados:

- `manifest_json.version` (semver, p. ej. `1.2.0`) siempre refleja el
  `manifest.json` real en disco del plugin — comparado con `version_compare()` para
  decidir si hay una versión nueva disponible (`PluginUpdateService::update()`).
- `schema_json` se fusiona **aditivamente**: `PluginSchemaMergeService::mergeAdditively()`
  añade los campos nuevos del `schema.json` de disco sin tocar los campos ya
  existentes en la fila instalada. No hay pasos de migración de datos ni scripts
  declarados por el plugin.
- Antes de aplicar la actualización, `InstalledPluginSchemaValidator::assertCanApplyUpdate()`
  comprueba que los campos ya instalados (canónicos) siguen presentes en el schema
  nuevo — si el plugin nuevo elimina o cambia un campo existente, la actualización
  se rechaza.
- Antes de escribir nada, se guarda una foto completa de la fila actual
  (`manifest_json`/`schema_json`/`status`/`target_version`) en
  `plugin_update_history` (`PluginUpdateHistoryRepository::insertSnapshot()`).

## Rollback

El rollback no restaura una "versión de schema" concreta por campo: restaura la
**última foto completa** guardada en `plugin_update_history` para ese `slug`
(`PluginRollbackService::rollback()` + `PluginWriteRepository::restoreFromSnapshot()`),
sobrescribiendo `manifest_json`/`schema_json`/`status` con los de esa foto. No hay
tabla `plugin_migrations` ni ejecución de pasos de migración inversos — es una
restauración de snapshot, no una migración con estado intermedio.

## Compatibilidad de datos existentes

Como la fusión de schema es solo aditiva (nunca se borran ni retipan campos ya
instalados sin rechazar la actualización), los registros existentes en
`plugin_entity_data`/`plugin_extension_data` siguen siendo válidos tal cual tras
una actualización — un campo nuevo simplemente no está presente en el `content`
JSONB de los registros antiguos hasta que se edite ese registro.

## Ejemplo de cambio compatible

- Se agrega un campo opcional `notes` de tipo `string` al `schema.json` del plugin.
- No se requiere reescritura masiva de registros: los registros existentes
  simplemente no tienen esa clave en `content` hasta que se editen.

## Ejemplo de cambio incompatible (rechazado)

- Un campo existente como `mail` desaparece del `schema.json` nuevo, o cambia de
  tipo (`string` → objeto complejo).
- `InstalledPluginSchemaValidator::assertCanApplyUpdate()` rechaza la actualización
  porque el campo canónico ya instalado no está presente (o no coincide) en el
  schema nuevo — no existe hoy un mecanismo de transformación automática de
  contenido JSONB para este caso; requeriría una intervención manual fuera de este
  flujo.
