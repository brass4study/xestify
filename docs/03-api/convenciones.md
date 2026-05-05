# Convenciones de la API REST

## Nomenclatura y rutas
- Pluralización para recursos: `/entities`, `/plugins`
- Versionado en URL: `/api/v1/`
- Uso de slugs en minúsculas y snake_case

## Parámetros y filtros
- Paginación: `?page=1&page_size=20`
- Filtros: `?field=value`
- Ordenación: `?sort=campo,-campo2`

## Status codes
- 200 OK, 201 Created, 204 No Content
- 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found
- 422 Unprocessable Entity (errores de validación)
- 500 Internal Server Error

## Headers
- `Authorization: Bearer <token>`
- `Content-Type: application/json`

## Formato de respuesta
- Siempre JSON
- Envoltorio estándar: `{ ok, data, error }`

## Ejemplo de error
```json
{
  "ok": false,
  "error": {
    "code": 422,
    "message": "Campo requerido: email",
    "details": { "email": ["Obligatorio"] }
  }
}
```
