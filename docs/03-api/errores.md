# Catálogo de errores y respuestas

## Estructura estándar de error
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
    "message": "El campo 'name' es obligatorio",
    "details": { "name": ["Obligatorio"] }
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
