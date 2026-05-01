# Plantilla: Plugin de Entidad

## Objetivo

Definir el esqueleto minimo para crear una entidad base reusable.

## Estructura

```text
plugins/entity_<slug>/
  manifest.json
  schema.json
  Hooks.php
  api/
    routes.php
    Controller.php
  ui/
    index.js
    components/
  migrations/
    001_init.sql
```

## manifest.json base

```json
{
  "slug": "entity_client",
  "name": "Clientes",
  "version": "1.0.0",
  "type": "entity",
  "owner_entity": null,
  "compatibility": {
    "core": ">=1.0.0"
  },
  "requires": []
}
```

## schema.json base

```json
{
  "entity": "client",
  "version": "1.0.0",
  "fields": [
    {"name": "nombre", "type": "string", "required": true},
    {"name": "telefono", "type": "string", "required": true}
  ]
}
```

## Checklist

- Slug unico
- Version semantica
- Schema valido
- Hooks declarados
- Migraciones incluidas
- Pruebas minimas del CRUD
