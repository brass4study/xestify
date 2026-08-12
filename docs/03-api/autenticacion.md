# Autenticación y autorización

## Flujo
- Login vía `POST /api/v1/auth/login` con email y password
- Respuesta: `{ ok, data: { access_token, email } }`
- Usar token en header `Authorization: Bearer <token>`
- No hay refresh token: la sesión expira a los `JWT_EXPIRY` segundos y hay que volver a hacer login

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
    "access_token": "...jwt...",
    "email": "admin@demo.com"
  }
}
```
