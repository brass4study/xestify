# Plantilla: Plugin de Extension

## Objetivo

Definir plugins que se acoplan a una entidad existente sin modificar su plugin base.

## Estructura

```text
plugins/<slug>/
  manifest.json
  schema.json
  Hooks.php
  Lifecycle.php
  plugin.js
```

Notas:
- `manifest.json` es obligatorio.
- `Hooks.php`, `Lifecycle.php` y `plugin.js` son opcionales segun el plugin.
- La carpeta es plana por plugin (sin `backend/` ni `frontend/` internos).

## manifest.json base

```json
{
  "name": "optometria",
  "label": "Optometria",
  "version": "1.0.0",
  "type": "extension",
  "core_version": "1.0.0",
  "target_entity": "persons",
  "description": "Añade una ficha de revisión optométrica a las personas."
}
```

`label` es **obligatorio** (`PluginManifestReader::REQUIRED_FIELDS`) — omitirlo hace
que el plugin no se pueda leer. `name` es la identidad técnica fija (= carpeta) y
`label` el nombre editable por instancia desde `PluginConfig`; ver
`plantilla-plugin-entidad.md` para la explicación completa de esa diferencia
(idéntica para plugins `entity` y `extension`).

## schema.json base

Un plugin `extension` declara `fields` y, opcionalmente, `relations`
(mismo `belongs_to` que las entidades — STORY 10.5): no tiene `identities`
(eso sigue siendo un concepto exclusivo de la entidad a la que se acopla),
pero sí puede enlazar sus propios campos a entidades catálogo reales.

```json
{
  "fields": {
    "fecha_revision": {"type": "date", "required": true, "label": "Fecha revision", "layer": "top"},
    "ojo_izquierdo": {"type": "string", "required": false, "label": "Ojo izquierdo", "layer": "general"},
    "ojo_derecho": {"type": "string", "required": false, "label": "Ojo derecho", "layer": "general"}
  },
  "relations": [
    {
      "key": "oftalmologo", "type": "belongs_to", "target_entity": "ophthalmologists",
      "target_field": "id", "required": false, "label": "Oftalmólogo", "layer": "general"
    }
  ]
}
```

`layer` (opcional, por defecto `general`) asigna el campo/relación a una
zona de UI declarada en `manifest.json` (ver "Convención `layers`" en
`docs/01-architecture/plugins.md`) — es metadata de configuración, no
dirige el renderizado; el `plugin.js` sigue construyendo el formulario a
mano y su autor mantiene la asignación sincronizada. `resortable: false`
(opcional, por defecto `true`) oculta Subir/Bajar y bloquea el selector de
Capa de ese campo en `PluginConfig` cuando su posición está fija en el
HTML del plugin (ej. una tabla de medidas dibujada con claves literales).

## Hooks esperados

- registerTabs (agrega pestana en ficha de cliente) — si el plugin declara
  `relations`, su `Hooks.php` debe embeberlas (junto con `entity`) en el
  tab que devuelve, ya que `PluginPanelRegistry.build()` no pasa el schema
  al panel. Ver `docs/01-architecture/hooks.md`.
- registerActions (botones contextuales)

## Página independiente de ítem (historial de varios registros por owner)

Cuando el plugin guarda **varias fichas por owner** (una por fecha, no un
único registro — ej. `optometries`/`contact_lenses`), el panel inline
dentro de `EntityEdit` no basta para editar cada ficha; en su lugar:
- `plugin.js` exporta `buildDetailForm(content, relations, loadOptions,
  extraFields)`, reutilizado por la página genérica
  `frontend/src/js/views/pages/PluginItemEdit.js` (no específica de
  ningún plugin — no hace falta tocarla al añadir un plugin nuevo).
- El panel inline (`{element, flush}`) queda reducido a listar el
  historial con `DynamicTable` y navegar a la ficha (`flush()` como
  no-op, sin staging en memoria).
Ver `docs/01-architecture/plugins.md` para el flujo completo.

La limpieza de `plugin_extension_data` cuando se borra el registro owner ya la hace el
Core automáticamente (`EntityService::deleteRecord()` borra físicamente todo lo que
apunte al registro, sin distinguir plugin) — el plugin de extensión no necesita
implementar su propio `beforeDelete` para esto.

## Checklist

- `target_entity` definido (`persons` o `*`)
- Dependencias declaradas (si aplica)
- Tab UI desacoplada del Core
- Integridad referencial logica con owner_id
- Pruebas sobre alta, edicion y baja del owner
