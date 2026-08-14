# Contrato: Entidades

## GET /api/v1/entities
- Respuesta:
```json
{
  "ok": true,
  "data": [
    { "slug": "persons", "name": "Clientes", "status": "active" },
    ...
  ]
}
```

## GET /api/v1/entities/{slug}/schema
- Respuesta:
```json
{
  "ok": true,
  "data": {
    "entity": "persons",
    "fields": [ ... ],
    "custom_fields": [ ... ]
  }
}
```

## GET /api/v1/entities/{slug}/records

Devuelve únicamente la página solicitada de registros activos.

Parámetros de query:

| Parámetro | Default | Descripción |
|-----------|---------|-------------|
| `page` | `1` | Página solicitada, empezando en 1 |
| `page_size` | `20` | Registros por página, máximo 200 |
| `sort` | `created_at` | Campo del schema o `id`, `created_at`, `updated_at` |
| `direction` | `asc` | Dirección `asc` o `desc` |

Ejemplo: `GET /api/v1/entities/persons/records?page=2&page_size=20&sort=name&direction=asc`

```json
{
  "ok": true,
  "data": [
    {
      "id": "...",
      "entity_slug": "persons",
      "content": "{\"name\":\"Cliente\"}"
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

## POST /api/v1/entities/{slug}/records
- Request:
```json
{
  "name": "Juan",
  "email": "juan@demo.com"
}
```
- Respuesta:
```json
{
  "ok": true,
  "data": { "id": "...", "name": "Juan", "email": "juan@demo.com" }
}
```
