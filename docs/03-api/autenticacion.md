# Autenticación y autorización

## Flujo
- Login vía `POST /api/v1/auth/login` con email y password
- Respuesta: `{ ok, data: { accessToken } }`
- Usar token en header `Authorization: Bearer <token>`

## Roles y permisos
- admin: acceso total
- operador: acceso a entidades y registros
- lectura: solo consulta

## Ejemplo de login
```json
{
  "email": "admin@demo.com",
  "password": "demo1234"
}
```

## Ejemplo de respuesta
```json
{
  "ok": true,
  "data": {
    "accessToken": "...jwt..."
  }
}
```
