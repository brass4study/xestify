# Contrato: Entidades

## GET /api/v1/entities
- Lista las entidades activas (plugins `type: 'entity'`, `status: 'active'`).
- Respuesta:
```json
{
  "ok": true,
  "data": [
    {
      "slug": "persons",
      "label": "Personas",
      "label_singular": "Persona",
      "fields": { "name": { "type": "string", "required": true, "label": "Nombre" } },
      "custom_fields": [ { "key": "mail", "type": "mail", "required": true, "label": "Email" } ],
      "ui_field_order": ["name", "mail"],
      "identities": { "id": { "type": "uuid", "auto_generated": true, "editable": false } }
    }
  ]
}
```
  `identities` es necesario para poblar el selector de `target_field` al
  configurar relaciones desde `PluginConfig`.

## GET /api/v1/entities/{slug}/options
- Lista compacta de `{id, label}` de todos los registros activos de la
  entidad, pensada para poblar un `<select>` de relación `belongs_to` desde
  otra entidad. `label` se construye con el mismo algoritmo que el resumen de
  fila (ver `docs/05-frontend/arquitectura.md`, `EntityRecordModel.js`).
- Respuesta:
```json
{
  "ok": true,
  "data": [
    { "id": "...", "label": "Ana Ruiz" }
  ]
}
```
- Errores: `404` si el slug no corresponde a una entidad activa.

## GET /api/v1/entities/{slug}/schema
- Devuelve el `schema_json` completo y sin procesar de la entidad (estructural:
  `identities`/`fields`/`custom_fields`/`relations`/`ui_field_order`/
  `plugin_suggested_custom_fields`).
- Respuesta:
```json
{
  "ok": true,
  "data": {
    "entity_slug": "persons",
    "schema": {
      "identities": { "id": { "type": "uuid", "auto_generated": true, "editable": false } },
      "fields": { "name": { "type": "string", "required": true, "label": "Nombre" } },
      "custom_fields": [ ... ],
      "relations": [ ... ],
      "ui_field_order": [ ... ]
    }
  }
}
```

## GET /api/v1/entities/{slug}/records

Devuelve una página de registros activos.

Parámetros de query:

| Parámetro | Default | Descripción |
|-----------|---------|-------------|
| `page` | `1` | Página solicitada, empezando en 1 |
| `page_size` | `20` | Registros por página, máximo 200 |
| `sort` | `created_at` | Campo del schema o `id`, `created_at`, `updated_at` |
| `direction` | `asc` | Dirección `asc` o `desc` |
| `field` + `value` | — | Ver más abajo — cuando `field` está presente, sustituye por completo a la paginación |

Ejemplo: `GET /api/v1/entities/persons/records?page=2&page_size=20&sort=name&direction=asc`

```json
{
  "ok": true,
  "data": [
    {
      "id": "...",
      "entity_slug": "persons",
      "content": "{\"name\":\"Cliente\"}",
      "created_at": "...",
      "updated_at": "..."
    }
  ],
  "meta": {
    "page": 2,
    "page_size": 20,
    "total": 203,
    "total_pages": 11,
    "sort": "name",
    "direction": "asc"
  }
}
```
`content` viaja como cadena JSON (columna JSONB sin decodificar por PDO) — el
frontend debe parsearla.

### Filtro por campo (`?field=&value=`)

`GET /api/v1/entities/{slug}/records?field=id_person&value=<uuid>`

Filtro exacto (no búsqueda) sobre una única clave de `content`, sin paginar —
devuelve todos los registros que coinciden, como array plano (mismo shape de fila
que arriba, sin bloque `meta`). Pensado para la tab de relación inversa en
`EntityEdit` (lista los registros de otra entidad cuya relación apunta al
registro actual), pero es un mecanismo genérico.

`field` debe ser una clave declarada en el `schema_json` de esa entidad (campo,
custom_field, identidad editable, o clave de una relación `relations[].key`) —
en caso contrario responde `422`. Esta restricción evita usarlo como sonda de
claves arbitrarias del contenido JSONB.

## POST /api/v1/entities/{slug}/records
- Request: los campos declarados en el schema, en plano (no envueltos en `content`):
```json
{
  "name": "Juan",
  "mail": "juan@demo.com"
}
```
- Respuesta (201) — fila cruda tal cual la persiste `plugin_entity_data`, `content` sin decodificar:
```json
{
  "id": "...",
  "entity_slug": "persons",
  "owner_id": null,
  "content": "{\"name\":\"Juan\",\"mail\":\"juan@demo.com\"}",
  "created_at": "...",
  "updated_at": "...",
  "deleted_at": null
}
```
- Errores: `422` con `error.details` por campo si falla la validación de schema.

## PUT /api/v1/entities/{slug}/records/{id}
- Request: solo los campos a actualizar (merge parcial sobre `content`, no se
  exige que estén presentes los campos obligatorios que no cambian).
- Respuesta: misma forma que `POST`, con los valores ya fusionados.

## DELETE /api/v1/entities/{slug}/records/{id}
- Soft-delete (`deleted_at = NOW()`).
- Respuesta: `{ "ok": true, "data": { "deleted": true, "id": "..." } }`

## GET /api/v1/entities/{slug}/tabs
- Tabs adicionales a mostrar en `EntityEdit` para esa entidad. Dos orígenes,
  mezclados en el mismo array:
  - Tabs aportadas por un plugin `extension` vía el hook `registerTabs`
    (`{id, label, icon, endpoint, plugin_name}`) — el frontend importa
    dinámicamente su `plugin.js` y construye el panel con `PluginPanelRegistry`.
  - Tabs de **relación inversa**, generadas automáticamente por
    el núcleo (`ReverseRelationTabResolver`) cuando otra entidad activa declara
    una relación `belongs_to` hacia esta: `{id: "relation-{source}-{key}", label, type: "relation", source_entity, key}`.
    No pasan por `PluginPanelRegistry` ni tienen `plugin.js` propio — el
    frontend construye directamente un panel genérico (`RelatedRecordsPanel`)
    que consume `GET .../records?field={key}&value={id del registro actual}`
    sobre `source_entity`. `label` es el `label` de la entidad origen (no el
    label del campo de la relación), porque la tab lista *registros de esa
    entidad*; si la misma entidad origen declara más de una relación hacia
    esta (p. ej. "Comprador" y "Vendedor" ambas hacia `persons`), cada tab
    añade el label de su relación entre paréntesis para distinguirlas
    (`"Orders (Comprador)"`, `"Orders (Vendedor)"`).
- Respuesta: `{ "ok": true, "data": { "tabs": [ ... ], "entity": "persons" } }`

## GET /api/v1/entities/{slug}/actions
- Acciones contextuales de fila registradas por plugins vía el hook `registerActions`.
- Respuesta: `{ "ok": true, "data": { "actions": [ { "id", "label", "icon" } ], "entity": "persons" } }`
