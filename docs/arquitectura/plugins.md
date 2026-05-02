# Sistema de Plugins

## Tipos de plugin

1. Plugin de entidad (`plugin_type = 'entity'`)
- Define una entidad base reusable con su schema
- Al instalarse, registra una fila en `plugins` con `name`, `slug`, `schema_json`
- **Es el catalogo de entidades** — no existe tabla separada `system_entities`
- Ejemplo: `clients`

2. Plugin de extension (`plugin_type = 'extension'`)
- Se acopla a una entidad existente mediante hooks
- Inyecta tabs, acciones o lógica sin modificar el Core
- Persiste sus datos en `plugin_extension_data` (tabla genérica JSONB)
- Ejemplo: `comments`

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
  "slug": "clients",
  "name": "Clientes",
  "version": "1.0.0",
  "type": "entity",
  "compatibility": {
    "core": ">=1.0.0"
  },
  "requires": []
}
```

## Registro en base de datos

Cuando `PluginLoader` descubre un plugin, escribe en la tabla `plugins`:

```sql
INSERT INTO plugins (slug, name, plugin_type, version, status, schema_json, schema_version)
VALUES (:slug, :name, :type, :version, 'active', :schema_json, :schema_version)
ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name, ...
```

Para plugins de tipo `entity`, el filtro `plugins WHERE plugin_type = 'entity' AND status = 'active'`
es el catalogo completo de entidades del sistema. No hay otra fuente.

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
- Los plugins de tipo `entity` no deben escribir en tablas separadas de catalogo

## Caso ejemplo

- `clients` aporta CRUD base (registrado en `plugins` con `plugin_type='entity'`)
- `comments` registra tab en ficha de cliente via hook `registerTabs`
- `comments` persiste sus datos en `plugin_extension_data` con `plugin_slug='comments'`
