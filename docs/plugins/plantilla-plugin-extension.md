# Plantilla: Plugin de Extension

## Objetivo

Definir plugins que se acoplan a una entidad existente sin modificar su plugin base.

## Estructura

```text
plugins/extension_<slug>/
  manifest.json
  schema.json
  Hooks.php
  api/
  ui/
    tabs/
    actions/
  migrations/
```

## manifest.json base

```json
{
  "slug": "extension_optometria",
  "name": "Optometrias",
  "version": "1.0.0",
  "type": "extension",
  "owner_entity": "client",
  "compatibility": {
    "core": ">=1.0.0"
  },
  "requires": ["entity_client>=1.0.0"]
}
```

## schema.json base

```json
{
  "entity": "optometria",
  "owner_entity": "client",
  "version": "1.0.0",
  "fields": [
    {"name": "fecha_revision", "type": "date", "required": true},
    {"name": "ojo_izquierdo", "type": "string", "required": false},
    {"name": "ojo_derecho", "type": "string", "required": false}
  ]
}
```

## Hooks esperados

- registerTabs (agrega pestana en ficha de cliente)
- registerActions (botones contextuales)
- beforeDelete owner (limpieza de registros hijos)

## Checklist

- owner_entity obligatorio
- Dependencias declaradas
- Tab UI desacoplada del Core
- Integridad referencial logica con owner_id
- Pruebas sobre alta, edicion y baja del owner
