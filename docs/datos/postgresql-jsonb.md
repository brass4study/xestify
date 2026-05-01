# Modelo de Datos PostgreSQL + JSONB

## Objetivo

Combinar integridad relacional con flexibilidad para campos variables por entidad.

## Tablas Core

## system_entities

Catalogo de entidades instaladas.

Campos sugeridos:

- id (uuid)
- slug (text unique)
- name (text)
- source_plugin_slug (text)
- is_active (boolean)
- created_at (timestamp)
- updated_at (timestamp)

## entity_metadata

Definicion de campos por entidad y version.

- id (uuid)
- entity_slug (text)
- schema_version (text)
- schema_json (jsonb)
- created_at (timestamp)

## entity_data

Registros de negocio.

- id (uuid)
- entity_slug (text)
- owner_id (uuid null)
- content (jsonb)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp null)

## plugins_registry

Plugins instalados localmente.

- id (uuid)
- plugin_slug (text unique)
- plugin_type (text)
- version (text)
- status (text)
- installed_at (timestamp)
- updated_at (timestamp)

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
