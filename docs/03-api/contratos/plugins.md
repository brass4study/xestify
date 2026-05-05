# Contrato: Plugins

## GET /api/v1/plugins
- Respuesta:
```json
{
  "ok": true,
  "data": {
    "plugins": [
      { "slug": "clients", "name": "Clientes", "type": "entity", "status": "active" },
      { "slug": "comments", "name": "Comentarios", "type": "extension", "status": "active" }
    ]
  }
}
```

## PUT /api/v1/plugins/{slug}/status
- Request:
```json
{
  "status": "active"
}
```
- Respuesta:
```json
{
  "ok": true,
  "data": { "slug": "clients", "status": "active" }
}
```
