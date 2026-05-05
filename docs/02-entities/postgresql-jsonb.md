# Modelo de Datos PostgreSQL + JSONB

## Objetivo

Combinar integridad relacional con flexibilidad para campos variables por entidad.

## Tablas Core

## plugins

Catalogo unificado de plugins instalados. Incluye tanto plugins de tipo `entity`
(que definen entidades del negocio) como de tipo `extension` (que añaden tabs/acciones).

**Esta tabla es la unica fuente de verdad para el catalogo de entidades.**
La antigua tabla `system_entities` fue eliminada en Release B (migracion `010_drop_system_entities.sql`).

Columnas principales:

- id (uuid)
- slug (text unique) — identificador del plugin/entidad
- name (text) — nombre legible (ej. "Clientes")
- plugin_type (text) — 'entity' | 'extension'
- version (text)
- status (text) — 'active' | 'inactive' | 'error'
- schema_json (jsonb) — schema vivo del plugin (para tipo entity)
- schema_version (text)
- installed_at (timestamp)
- updated_at (timestamp)

Indices:
- `idx_plugins_type_status` en (plugin_type, status)
- UNIQUE en slug

Para listar entidades activas:

```sql
SELECT slug, name, schema_json, schema_version
FROM plugins
WHERE plugin_type = 'entity' AND status = 'active' AND schema_json IS NOT NULL
ORDER BY name ASC;
```

## entity_metadata

Definicion de campos por entidad y version.

- id (uuid)
- entity_slug (text)
- schema_version (text)
- schema_json (jsonb)
- created_at (timestamp)

`schema_json` guarda el schema vivo usado en runtime por validacion/persistencia.
Actualmente mantiene la estructura de `fields` para compatibilidad con la constraint SQL.

El contrato completo del plugin (`schema.json`) se define con:
- `identities` (identidad tecnica del sistema)
- `fields` (campos funcionales obligatorios)
- `custom_fields` (sugerencias opcionales para frontend)
- `relations` (metadatos de relaciones opcionales)

## entity_data

Registros de negocio.

- id (uuid)
- entity_slug (text)
- owner_id (uuid null)
- content (jsonb)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp null)

## plugin_hooks

Registro de hooks activos por plugin.

- id (uuid)
- slug (text)
- target_entity_slug (text)
- hook_name (text)
- priority (integer)
- enabled (boolean)

## plugin_extension_data

Tabla generica para datos de plugins tipo extension.

- id (uuid)
- plugin_slug (text)
- entity_slug (text)
- record_id (uuid)
- content (jsonb)
- created_at (timestamp)

## Indices recomendados

- idx_entity_data_entity_slug en entity_data(entity_slug)
- idx_entity_data_owner_id en entity_data(owner_id)
- idx_entity_data_content_gin en entity_data using gin(content)
- idx_metadata_entity_version en entity_metadata(entity_slug, schema_version)

## Ejemplo content JSONB

```json
{
  "nombre": "Ana Ruiz",
  "telefono": "600000001",
  "email": "ana@demo.local",
  "activo": true
}
```

## Consultas frecuentes

Registros por entidad:

```sql
select id, content
from entity_data
where entity_slug = 'client' and deleted_at is null;
```

Filtro por campo JSONB:

```sql
select id, content
from entity_data
where entity_slug = 'client'
  and content->>'telefono' = '600000001';
```
