# Sistema de Plugins

## Tipos de plugin

1. Plugin de entidad (`type: 'entity'` en el manifest)
- Define una entidad reusable con su schema.
- Al instalarse, registra una fila en `plugins` con `slug`, `status`, `manifest_json`
  (refleja el `manifest.json` del plugin, incluyendo tipo, nombre técnico,
  version y descripcion) y `schema_json` (refleja el `schema.json`).
- Es el catalogo de entidades del sistema.
- Ejemplo: `persons`.

2. Plugin de extension (`type: 'extension'` en el manifest)
- Se acopla a una entidad existente mediante hooks.
- Inyecta tabs, acciones o logica sin modificar el Core.
- Persiste sus datos en `plugin_extension_data` (tabla generica JSONB).
- Ejemplo: `comments` (panel inline simple, un solo registro por owner).
- Puede declarar `relations` en su `schema.json` (misma forma que las
  entidades — `{key, type: belongs_to, target_entity, target_field,
  required, label}` — más una clave `layer`) para enlazar a entidades
  catálogo reales; su propio `Hooks.php` debe embeberlas (junto con
  `entity`) en el tab que registra, porque `PluginPanelRegistry.build()` no
  pasa el schema al panel. Ejemplo con historial de varios
  registros por owner, relaciones y página de ficha independiente:
  `optometries`/`contact_lenses`.
- Puede declarar un catálogo `layers` (`[{key, label}]`) en su
  `manifest.json` — ver "Convención `layers`" más abajo.

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
- Los tests de catálogo de entidades, validación y CRUD deben operar siempre sobre la tabla `plugins`.
- Los seeders y fixtures deben poblar la tabla `plugins` para entidades base.

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
nombre de negocio, editable por instancia desde `PluginConfig`. `slug` vive
como columna editable de la tabla `plugins`, gestionada a nivel de aplicación.
Ver `docs/04-plugins/plantilla-plugin-entidad.md` para el detalle completo.

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
- Excepción: `/api/v1/entities/{slug}/tabs` también incluye tabs
  `type: 'relation'`, generadas automáticamente por el núcleo
  (`ReverseRelationTabResolver`) cuando otra entidad declara una relación
  `belongs_to` hacia esta — no vienen de ningún `plugin.js` ni pasan por
  `PluginPanelRegistry`; `EntityEdit` las detecta por `type` y construye
  directamente un `RelatedRecordsPanel` genérico.

Contrato de panel frontend:

- `element: HTMLElement`
- `flush(resolvedId): Promise<void>`

**Página independiente de ítem de plugin:** para un plugin
`extension` con **historial de varios registros por owner** (una ficha por
fecha, no un único registro), el panel inline de arriba no basta — crear o
editar cada ítem navega a una página propia y genérica,
`frontend/src/js/views/pages/PluginItemEdit.js` (no específica de ningún
plugin), con URL real (`#/entity/:slug/:id/:tab/:itemId` o
`.../:tab/#new`). Esa página:
1. Pide `GET /entities/{entitySlug}/tabs`, localiza el tab por
   `plugin_name`/`id` y saca `endpoint`/`relations`/`fields`.
2. Resuelve el contenido del ítem (lista completa + filtro por `id` en
   cliente si es edición; contenido en blanco si es alta — no existe una
   ruta de fetch de un único ítem en el backend genérico).
3. Importa dinámicamente el `plugin.js` del plugin y llama a su
   **`buildDetailForm(content, relations, loadOptions, extraFields)`
   exportado** — el plugin sigue siendo dueño de su formulario, la página
   solo aporta navegación y persistencia (Guardar/Cancelar/Eliminar).
El panel inline dentro de `EntityEdit` (`{element, flush}`) queda entonces
reducido a listar el historial (`DynamicTable`) y navegar a esta página —
`flush()` es un no-op, sin staging en memoria.

**Forma completa de un tab de plugin `extension`** devuelto por
`registerTabs` (más allá del mínimo `{id, label, endpoint}`):
`{id, label, icon, endpoint, plugin_name, entity, relations, fields}` —
`entity` es el slug de la entidad activa (necesario para que
`PluginItemEdit.js` pueda volver a pedir `GET /entities/{entity}/tabs`);
`relations` son las relaciones del plugin (ver arriba); `fields` son
**solo** los campos con `origin: 'additional'` (añadidos después de
instalar el plugin vía "Añadir campo" en `PluginConfig`) — los campos
originales del plugin siguen teniendo UI escrita a mano en su `plugin.js`
y nunca se embeben aquí; solo los que no tienen UI propia se renderizan de
forma genérica en el formulario, según su `type`.

## Convención `layers`

Un plugin (`entity` o `extension`) puede declarar opcionalmente un
catálogo de **capas/zonas de UI con nombre** en su `manifest.json`:

```json
"layers": [
    { "key": "top", "label": "Arriba" },
    { "key": "od", "label": "Ojo derecho" },
    { "key": "os", "label": "Ojo izquierdo" },
    { "key": "general", "label": "General" }
]
```

Cada campo/relación de `schema.json` se asigna a una capa con una clave
`layer` (string, por defecto `general`). Cuando un plugin declara
`layers`, `PluginConfig` muestra una columna adicional "Capa"
(`inputSelect`, acotado al catálogo del plugin) en las tablas de Campos y
Relaciones, permitiendo reasignar la capa de cada fila sin tocar disco.

Puntos clave:
- El catálogo vive en **`manifest.json`, no en `schema.json`** — no es
  editable desde `PluginConfig` (a diferencia de `fields`/`relations`/
  `ui_field_order`, que sí lo son), así que no pertenece al fichero
  mutable. Mismo precedente que `target_entity`.
- `layers` es **metadata de configuración, no dirige el renderizado**: el
  `plugin.js` de un plugin `extension` sigue escrito a mano (ver más
  abajo) y el autor del plugin mantiene la asignación de capa por campo
  sincronizada a mano con su HTML/CSS — igual que `resortable` (ver
  siguiente apartado), que tampoco dirige el renderizado.
- Un campo con `resortable: false` (ver siguiente apartado) tampoco puede
  reasignarse de capa desde `PluginConfig` — si su posición está fija en
  el HTML escrito a mano del plugin, su zona visual también lo está.

**`resortable` (booleano, opcional por campo en `schema.json`, por
defecto `true`):** cuando un campo declara `resortable: false`,
`PluginConfig` oculta los botones Subir/Bajar de esa fila y deshabilita su
selector de Capa — para plugins como `optometries`/`contact_lenses`, cuyo
`plugin.js` dibuja la posición de cada campo del grid a mano (reordenar en
`PluginConfig` no tendría ningún efecto visible en la ficha real).

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

**Caso ejemplo — historial con relaciones y capas:**
- `optometries` (ficha de graduación) y `contact_lenses` (ficha de
  lentillas) son plugins `extension` con `target_entity: "persons"`,
  historial de varias fichas por persona (cada guardado crea un registro
  nuevo con su propia fecha) y relaciones `belongs_to` hacia catálogos
  reales (`ophthalmologists`, `distributors`, `brands`, `manufacturers`).
- Ambos declaran un catálogo `layers` (`top`/`od`/`os`/`general`) en su
  `manifest.json` y asignan `layer` a cada campo/relación en
  `schema.json`, coherente con las zonas visuales de su `plugin.js`
  (escrito a mano, no *schema-driven* — `layers` documenta la posición,
  no la dirige).
- Crear/editar una ficha navega a `PluginItemEdit.js` (página genérica,
  no un formulario inline); el panel dentro de `EntityEdit` solo lista el
  historial con `DynamicTable` y navega.
