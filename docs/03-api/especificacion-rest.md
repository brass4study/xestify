# Especificación REST: Arquitectura y principios generales

## Base URL
`/api/v1/`

## Principios
- API RESTful, orientada a recursos
- Versionado explícito en la URL
- Contratos y convenciones alineados con OpenAPI/Swagger

## Navegación
- [convenciones.md](convenciones.md): Convenciones de diseño y uso
- [endpoints.md](endpoints.md): Lista completa de endpoints
- [contratos/](contratos/): Contratos y ejemplos por recurso
- [errores.md](errores.md): Catálogo de errores y respuestas
- [autenticacion.md](autenticacion.md): Autenticación y autorización

## Respuesta exitosa (ejemplo)

```json
{
  "ok": true,
  "data": {},
  "meta": {}
}
```

## Respuesta de error (ejemplo)

```json
{
  "ok": false,
  "error": {
    "code": 422,
    "message": "Validation failed.",
    "details": [
      {
        "field": "email",
        "code": "required",
        "message": "Obligatorio"
      }
    ]
  }
}
```
