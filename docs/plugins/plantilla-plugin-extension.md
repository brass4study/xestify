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
  "slug": "optometria",
  "name": "Optometrias",
  "version": "1.0.0",
  "type": "extension",
  "core_version": "1.0.0",
  "target_entity": "clients"
}
```

## schema.json base

```json
{
  "plugin": "optometria",
  "version": "1.0.0",
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
- beforeDelete owner (limpieza de registros hijos)

## Checklist

- `target_entity` definido (`clients` o `*`)
- Dependencias declaradas (si aplica)
- Tab UI desacoplada del Core
- Integridad referencial logica con owner_id
- Pruebas sobre alta, edicion y baja del owner
