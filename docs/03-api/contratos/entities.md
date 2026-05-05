# Contrato: Entidades

## GET /api/v1/entities
- Respuesta:
```json
{
  "ok": true,
  "data": [
    { "slug": "clients", "name": "Clientes", "status": "active" },
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
    "entity": "clients",
    "fields": [ ... ],
    "custom_fields": [ ... ]
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
