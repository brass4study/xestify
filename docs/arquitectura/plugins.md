# Sistema de Plugins

## Tipos de plugin

1. Plugin de entidad
- Define una entidad base reusable
- Ejemplo: client

2. Plugin de extension
- Se acopla a una entidad existente
- Ejemplo: optometria sobre client

## Estructura minima de plugin

```text
plugins/<plugin_slug>/
  manifest.json
  schema.json
  Hooks.php
  api/
  ui/
  migrations/
```

## manifest.json (minimo)

```json
{
  "slug": "entity_client",
  "name": "Clientes",
  "version": "1.0.0",
  "type": "entity",
  "compatibility": {
    "core": ">=1.0.0"
  },
  "requires": []
}
```

## Ciclo de vida

- onInstall
- onActivate
- onDeactivate
- onUpdate
- onUninstall

## Reglas

- No modificar tablas core sin migracion declarada
- Toda dependencia debe estar en manifest
- Todo hook debe declararse explicitamente
- Toda extension debe identificar owner_entity

## Caso ejemplo

- entity_client aporta CRUD base
- extension_optometria registra tab en ficha de cliente
- extension_optometria persiste sus datos en su propio espacio logico
