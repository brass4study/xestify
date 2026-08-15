# Sistema de Plugins

## Tipos de plugin

1. Plugin de entidad (`type: 'entity'` en el manifest)
- Define una entidad reusable con su schema.
- Al instalarse, registra una fila en `plugins` con `slug`, `status`, `manifest_json`
  (refleja el `manifest.json` del plugin) y `schema_json` (refleja el `schema.json`).
  No hay columnas `plugin_type`/`name`/`version`/`description` separadas
  (STORY 10.3 §2bis) — todo eso vive dentro de `manifest_json`.
- Es el catalogo de entidades del sistema.
- No existe tabla separada `system_entities`.
- Ejemplo: `persons`.

2. Plugin de extension (`type: 'extension'` en el manifest)
- Se acopla a una entidad existente mediante hooks.
- Inyecta tabs, acciones o logica sin modificar el Core.
- Persiste sus datos en `plugin_extension_data` (tabla generica JSONB).
- Ejemplo: `comments`.

## Estructura minima real de un plugin

```text
plugins/<plugin_slug>/
  manifest.json
  schema.json
  Hooks.php
  Lifecycle.php
  plugin.js
```

Notas:
- `manifest.json` es obligatorio.
- `schema.json` es obligatorio para plugins `entity` y recomendado para `extension`.
- `Hooks.php`, `Lifecycle.php` y `plugin.js` son opcionales segun cada plugin.
- La estructura es plana por plugin (sin carpetas `backend/` o `frontend/` dentro del plugin).

## Convenciones

- Frontend del plugin: nombre fijo `plugin.js`.
- Backend por convencion:
  - `Hooks.php`
  - `Lifecycle.php`
- Namespace PHP por plugin:
  - `Xestify\plugins\<slug>\`

**Catálogo y tests:**
- Los tests de catálogo de entidades, validación y CRUD deben operar siempre sobre la tabla `plugins` y nunca sobre tablas legacy.
- Los seeders y fixtures deben poblar la tabla `plugins` para entidades base.
- El modelo `SystemEntity` fue eliminado por completo (no queda como facade ni en ninguna otra forma).

## manifest.json (minimo)

```json
{
  "name": "persons",
  "label": "Personas",
  "version": "1.0.0",
  "type": "entity",
  "core_version": "1.0.0"
}
```

`name` es la identidad técnica fija (= carpeta, nunca editable); `label` es el
nombre de negocio, editable por instancia desde `PluginConfig`. No existe una
clave `slug` en el manifest — `slug` es solo una columna de la tabla `plugins`,
editable a nivel de aplicación. Ver `docs/04-plugins/plantilla-plugin-entidad.md`
para el detalle completo.

## Descubrimiento y registro

El subsistema de plugins descubre plugins leyendo
`plugins/<slug>/manifest.json` y valida campos obligatorios
(`PluginManifestReader::REQUIRED_FIELDS`):

- `name`
- `label`
- `version`
- `type`
- `core_version`

En boot de aplicacion, `PluginHookRegistrar` registra los hooks de plugins
activos en el `HookDispatcher`.

## Registro en base de datos

`PluginSyncService` es la operacion explicita que registra plugins nuevos en la
tabla `plugins` sin consumir updates pendientes de plugins ya instalados (usada
por "Sincronizar" en `PluginManager`). También existe el alta manual,
plugin a plugin, desde `PluginManager`/`PluginConfig` (`POST /api/v1/plugins`,
`PluginAdministrationService::registerNew()`) — a diferencia de la sincronización
masiva (que deja el plugin `inactive`), el alta manual **activa el plugin
automáticamente** justo después de insertarlo.

Para plugins de tipo `entity`, `schema.json` es obligatorio y se persiste en
`plugins.schema_json`; si falta o no contiene `fields`, la sincronizacion se
rechaza. `schema_json` es puramente estructural: `identities`/`fields`/
`custom_fields`/`relations`/`plugin_suggested_custom_fields`/`ui_field_order`.

Para plugins de tipo `entity`, el filtro:

`plugins WHERE manifest_json->>'type' = 'entity' AND status = 'active'`

es el catalogo completo de entidades del sistema. No hay otra fuente.

El slug canonico de personas (clientes/distribuidores/oculistas) en el MVP es `persons`.

## Integracion frontend de plugins

- `EntityEdit` es agnostico a plugins concretos.
- Flujo (tabs aportadas por un plugin, vía `registerTabs`):
  1. Obtiene tabs desde `/api/v1/entities/{slug}/tabs`.
  2. Importa dinamicamente `/plugins/{plugin_slug}/plugin.js`.
  3. Construye panel usando `PluginPanelRegistry`.
- Excepción (STORY 10.3 §9): `/api/v1/entities/{slug}/tabs` también incluye tabs
  `type: 'relation'`, generadas automáticamente por el núcleo
  (`ReverseRelationTabResolver`) cuando otra entidad declara una relación
  `belongs_to` hacia esta — no vienen de ningún `plugin.js` ni pasan por
  `PluginPanelRegistry`; `EntityEdit` las detecta por `type` y construye
  directamente un `RelatedRecordsPanel` genérico.

Contrato de panel frontend:

- `element: HTMLElement`
- `flush(resolvedId): Promise<void>`

## API generica para extensiones

Controlador: `PluginExtensionController`.

Rutas:

- `GET    /api/v1/plugins/{plugin_slug}/{entity}/{id}`
- `POST   /api/v1/plugins/{plugin_slug}/{entity}/{id}`
- `PUT    /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}`
- `DELETE /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}`

## Servido de assets de plugins

El runtime canonico bajo Apache+PHP sirve `/plugins/{plugin_slug}/plugin.js`
desde la carpeta raiz `plugins` en el mismo origen que la API y el frontend.
Opcionalmente, tambien expone `/plugins/{plugin_slug}/assets/*` para assets
estaticos del plugin.

## Ciclo de vida soportado actualmente

- `onInstall`
- `onActivate`
- `onDeactivate`

`onUpdate(array $context): void` se soporta como convencion opcional durante la
actualizacion explicita del plugin; no forma parte del contrato obligatorio de
`PluginLifecycleInterface`.

La extension `comments` se muestra dentro de `EntityEdit`. La pagina
`PluginManager` (listado/activar/desactivar/actualizar/revertir/alta manual/
borrado de plugins) y `PluginConfig` (identidad, campos, relaciones) ya están
implementadas — ver `docs/04-plugins/README.md`.

## Reglas

- No modificar tablas core sin migracion declarada.
- Toda metadata base debe declararse en `manifest.json`.
- Toda UI especifica de plugin debe vivir en su `plugin.js`, no en el Core.
- El frontend Core debe permanecer agnostico respecto a plugins concretos.
- Los plugins de tipo `entity` no deben escribir en tablas separadas de catalogo.
- Los plugins de tipo `extension` discriminan sus datos por `plugin_slug` en `plugin_extension_data`.

## Caso ejemplo

- `persons` aporta CRUD base (registrado en `plugins` con `manifest_json->>'type' = 'entity'`).
- `comments` registra tab en ficha de cliente via hook `registerTabs`.
- `comments` usa frontend propio en `plugin.js`.
- `comments` persiste sus datos en `plugin_extension_data` con `plugin_slug='comments'`.
