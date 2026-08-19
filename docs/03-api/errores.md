# Catálogo de errores y respuestas

## Estructura estándar de error
```json
{
  "ok": false,
  "error": {
    "code": 422,
    "message": "Validation failed.",
    "details": [
      { "field": "email", "code": "required", "message": "Obligatorio" }
    ]
  }
}
```

## Códigos comunes
- 400: Petición inválida
- 401: No autenticado
- 403: Prohibido
- 404: No encontrado
- 422: Error de validación
- 500: Error interno

## Ejemplos
- Validación fallida:
```json
{
  "ok": false,
  "error": {
    "code": 422,
    "message": "Validation failed.",
    "details": [
      { "field": "name", "code": "required", "message": "Obligatorio" }
    ]
  }
}
```
- Token inválido:
```json
{
  "ok": false,
  "error": {
    "code": 401,
    "message": "Token inválido o expirado"
  }
}
```
