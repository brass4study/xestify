# Sistema de Plugins

## Tipos de plugin

1. Plugin de entidad (`plugin_type = 'entity'`)
- Define una entidad reusable con su schema.
- Al instalarse, registra una fila en `plugins` con `name`, `slug`, `schema_json` y estado.
- Es el catalogo de entidades del sistema.
- No existe tabla separada `system_entities`.
- Ejemplo: `clients`.

2. Plugin de extension (`plugin_type = 'extension'`)
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
  Installer.php
```

Notas:
- `manifest.json` es obligatorio.
- `schema.json` es obligatorio para plugins `entity` y recomendado para `extension`.
- `Hooks.php`, `Lifecycle.php`, `plugin.js` e `Installer.php` son opcionales segun cada plugin.
- La estructura es plana por plugin (sin carpetas `backend/` o `frontend/` dentro del plugin).

## Convenciones

- Frontend del plugin: nombre fijo `plugin.js`.
- Backend por convencion:
  - `Hooks.php`
  - `Lifecycle.php`
- Namespace PHP por plugin:
  - `Xestify\plugins\<slug>\`

## manifest.json (minimo)

```json
{
  "slug": "clients",
  "name": "Clientes",
  "version": "1.0.0",
  "type": "entity",
  "core_version": "1.0.0"
}
```

## Descubrimiento y registro

`PluginLoader` descubre plugins leyendo `plugins/<slug>/manifest.json` y valida campos obligatorios:

- `slug`
- `name`
- `version`
- `type`
- `core_version`

En boot de aplicacion, se registran hooks de plugins activos en el `HookDispatcher`.

## Registro en base de datos

Cuando `PluginLoader` descubre un plugin, escribe en la tabla `plugins`.
Para plugins de tipo `entity`, `schema.json` es obligatorio y se persiste en
`plugins.schema_json`; si falta o no contiene `fields`, la carga se rechaza.

Para plugins de tipo `entity`, el filtro:

`plugins WHERE plugin_type = 'entity' AND status = 'active'`

es el catalogo completo de entidades del sistema. No hay otra fuente.

El slug canonico de cliente en el MVP es `clients`. Los registros legacy con
`entity_slug = 'client'` se migran a `clients` durante el arranque.

## Integracion frontend de plugins

- `EntityEdit` es agnostico a plugins concretos.
- Flujo:
  1. Obtiene tabs desde `/api/v1/entities/{slug}/tabs`.
  2. Importa dinamicamente `/plugins/{plugin_slug}/plugin.js`.
  3. Construye panel usando `PluginPanelRegistry`.

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

## Servido de assets de plugins en desarrollo

El router de desarrollo sirve `/plugins/*` desde la carpeta raiz `plugins`, permitiendo cargar `plugin.js` directamente en navegador.

## Ciclo de vida soportado actualmente

- `onInstall`
- `onActivate`
- `onDeactivate`

Nota: `onUpdate` y `onUninstall` no forman parte del contrato actual de `PluginLifecycleInterface`.

Hasta STORY 6.4, la extension `comments` se muestra dentro de `EntityEdit`.
Una vista de detalle dedicada y la pagina PluginManager quedan para STORY 6.5+.

## Reglas

- No modificar tablas core sin migracion declarada.
- Toda metadata base debe declararse en `manifest.json`.
- Toda UI especifica de plugin debe vivir en su `plugin.js`, no en el Core.
- El frontend Core debe permanecer agnostico respecto a plugins concretos.
- Los plugins de tipo `entity` no deben escribir en tablas separadas de catalogo.
- Los plugins de tipo `extension` discriminan sus datos por `plugin_slug` en `plugin_extension_data`.

## Caso ejemplo

- `clients` aporta CRUD base (registrado en `plugins` con `plugin_type='entity'`).
- `comments` registra tab en ficha de cliente via hook `registerTabs`.
- `comments` usa frontend propio en `plugin.js`.
- `comments` persiste sus datos en `plugin_extension_data` con `plugin_slug='comments'`.
