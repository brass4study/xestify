# Modelo de Datos PostgreSQL + JSONB

## Objetivo

Combinar integridad relacional con flexibilidad para campos variables por entidad.

## Tablas Core

## plugins

Catalogo unificado de plugins instalados. Incluye tanto plugins de tipo `entity`
(que definen entidades del negocio) como de tipo `extension` (que añaden tabs/acciones).

**Esta tabla es la unica fuente de verdad para el catalogo de entidades.**
La antigua tabla `system_entities` fue eliminada en Release B (migracion `010_drop_system_entities.sql`).

Columnas reales (`backend/database/schema/003_plugins.sql`, STORY 10.3 §2bis
— no hay columnas propias `plugin_type`/`name`/`version`/`description`/
`schema_version`, todo eso vive dentro de `manifest_json`):

- id (uuid)
- slug (text unique) — identificador editable de navegacion/URL
- status (text) — 'active' | 'inactive' | 'error'
- manifest_json (jsonb, not null) — refleja el `manifest.json` del plugin en disco:
  `{name, label, label_singular, version, type, core_version, target_entity,
  description}`. Columna viva, no una foto fija del install:
  `name`/`version`/`type`/`core_version`/`label_singular` siempre reflejan el
  manifest en disco (se refrescan en cada actualizacion, nunca editables);
  `label`/`description`/`target_entity` (solo `extension`) son editables por el
  admin desde `PluginConfig` y se preservan en cada actualizacion (merge, nunca
  overwrite). `manifest_json->>'name'` es la identidad tecnica fija del plugin
  (= carpeta / namespace PHP), no unica: el mismo folder puede tener varias
  instancias, cada una con su propio `slug`.
- schema_json (jsonb, nullable) — puramente estructural: `identities`/`fields`/
  `custom_fields`/`relations`/`plugin_suggested_custom_fields`/`ui_field_order`.
- installed_at (timestamp)
- updated_at (timestamp)

Indices:
- `idx_plugins_type_status` en `(manifest_json->>'type', status)`
- `idx_plugins_plugin_name` en `(manifest_json->>'name')`
- UNIQUE en slug

Para listar entidades activas:

```sql
SELECT slug, manifest_json->>'label' AS label, schema_json
FROM plugins
WHERE manifest_json->>'type' = 'entity' AND status = 'active' AND schema_json IS NOT NULL
ORDER BY manifest_json->>'label' ASC;
```

El contrato completo de `schema_json` para un plugin `entity` se define con:
- `identities` (identidad tecnica del sistema, p. ej. `id`)
- `fields` (campos funcionales obligatorios)
- `custom_fields` (sugerencias opcionales para frontend)
- `relations` (relaciones `belongs_to` hacia otras entidades — STORY 10.3 §8,
  primera implementacion funcional real de este bloque)

## plugin_entity_data

Registros de negocio.

- id (uuid)
- entity_slug (text)
- owner_id (uuid null)
- content (jsonb)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp null)

## plugin_extension_data

Tabla generica para datos de plugins tipo extension.

- id (uuid)
- plugin_slug (text)
- entity_slug (text)
- record_id (uuid)
- content (jsonb)
- created_at (timestamp)

## Indices reales

- `idx_plugin_entity_data_slug` en `plugin_entity_data(entity_slug)`
- `idx_plugin_entity_data_owner` en `plugin_entity_data(owner_id)`
- `idx_plugin_entity_data_content_gin` en `plugin_entity_data using gin(content)`
- `idx_plugin_extension_data_record` en
  `plugin_extension_data(plugin_slug, entity_slug, record_id)`

## Ejemplo content JSONB

Las claves tecnicas de `content` van siempre en ingles (ver `AGENTS.md`, sección
"Schemas y datos"):

```json
{
  "name": "Ana Ruiz",
  "phone": "600000001",
  "mail": "ana@demo.local",
  "is_active": true
}
```

## Consultas frecuentes

Registros por entidad:

```sql
select id, content
from plugin_entity_data
where entity_slug = 'persons' and deleted_at is null;
```

Filtro por campo JSONB:

```sql
select id, content
from plugin_entity_data
where entity_slug = 'persons'
  and content->>'phone' = '600000001';
```
