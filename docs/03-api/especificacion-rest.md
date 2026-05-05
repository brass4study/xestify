# Especificacion REST

## Base URL local

`/api/v1`

## Convenciones

- JSON request/response
- Fechas en ISO8601
- Errores con codigo, mensaje y detalles

## Autenticacion

Header recomendado:

`Authorization: Bearer <token>`

Desde el cierre de STORY 6.4, las rutas bajo `/api/v1/entities` y
`/api/v1/plugins` requieren este header. Permanecen publicas:

- GET `/health`
- POST `/api/v1/auth/login`

## Endpoints disponibles actualmente

## Salud

- GET `/health`

## Auth

- POST `/api/v1/auth/login`

## Entidades

- GET `/api/v1/entities`
- GET `/api/v1/entities/{slug}/schema`
- GET `/api/v1/entities/{slug}/tabs`
- GET `/api/v1/entities/{slug}/actions`
- GET `/api/v1/entities/{slug}/records`
- POST `/api/v1/entities/{slug}/records`
- GET `/api/v1/entities/{slug}/records/{id}`
- PUT `/api/v1/entities/{slug}/records/{id}`
- DELETE `/api/v1/entities/{slug}/records/{id}`

## Plugins de extension (API generica)

- GET `/api/v1/plugins/{plugin_slug}/{entity}/{id}`
- POST `/api/v1/plugins/{plugin_slug}/{entity}/{id}`
- PUT `/api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}`
- DELETE `/api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}`

Nota: la API de extensiones la atiende `PluginExtensionController` y discrimina por `plugin_slug`.
El plugin debe existir, ser de tipo `extension` y estar `active`; el registro
padre `{entity}/{id}` tambien debe existir para evitar datos huerfanos.

## Respuesta de exito (ejemplo)

```json
{
  "ok": true,
  "data": {
    "id": "8d1a0b2f-90f0-49d8-a09f-03f09f5ab770",
    "entity_slug": "clients",
    "content": {
      "name": "Ana Ruiz",
      "email": "ana@example.com"
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
        "field": "email",
        "reason": "required"
      }
    ]
  }
}
```
