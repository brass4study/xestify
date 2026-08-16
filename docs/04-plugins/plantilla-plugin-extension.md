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

Un plugin `extension` solo declara `fields` — no tiene `identities` ni `relations`
propios (esos son conceptos de la entidad a la que se acopla, no de la extensión).

```json
{
  "fields": {
    "fecha_revision": {"type": "date", "required": true, "label": "Fecha revision"},
    "ojo_izquierdo": {"type": "string", "required": false, "label": "Ojo izquierdo"},
    "ojo_derecho": {"type": "string", "required": false, "label": "Ojo derecho"}
  }
}
```

## Hooks esperados

- registerTabs (agrega pestana en ficha de cliente)
- registerActions (botones contextuales)

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
