# Especificacion REST

## Base URL local

/api/v1

## Convenciones

- JSON request/response
- Fechas en ISO8601
- Errores con codigo, mensaje y detalles

## Autenticacion

Header recomendado:

Authorization: Bearer <token>

## Endpoints Core

## Entidades

- GET /entities
- GET /entities/{entity_slug}/schema
- GET /entities/{entity_slug}/records
- POST /entities/{entity_slug}/records
- GET /entities/{entity_slug}/records/{id}
- PUT /entities/{entity_slug}/records/{id}
- DELETE /entities/{entity_slug}/records/{id}

## Plugins

- GET /plugins
- POST /plugins/install
- POST /plugins/{plugin_slug}/activate
- POST /plugins/{plugin_slug}/deactivate
- POST /plugins/{plugin_slug}/update

## Actualizaciones

- GET /updates/check
- POST /updates/download/{plugin_slug}
- POST /updates/apply/{plugin_slug}
- POST /updates/rollback/{plugin_slug}

## Respuesta de exito (ejemplo)

```json
{
  "ok": true,
  "data": {
    "id": "8d1a0b2f-90f0-49d8-a09f-03f09f5ab770",
    "entity_slug": "client",
    "content": {
      "nombre": "Ana Ruiz"
    }
  },
  "meta": {
    "schema_version": "1.0.0"
  }
}
```

## Respuesta de error (ejemplo)

```json
{
  "ok": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Payload invalido",
    "details": [
      {
        "field": "telefono",
        "reason": "required"
      }
    ]
  }
}
```
