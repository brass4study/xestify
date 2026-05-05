# Ejemplos de payloads y respuestas

## Ejemplo: Crear entidad

### Request
```json
{
  "name": "Juan",
  "email": "juan@demo.com"
}
```

### Respuesta
```json
{
  "ok": true,
  "data": { "id": "...", "name": "Juan", "email": "juan@demo.com" }
}
```

## Ejemplo: Error de validación
```json
{
  "ok": false,
  "error": {
    "code": 422,
    "message": "El campo 'email' es obligatorio",
    "details": { "email": ["Obligatorio"] }
  }
}
```
