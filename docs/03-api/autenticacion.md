# Autenticación y autorización

## Flujo
- Login vía `POST /api/v1/auth/login` con email y password
- Respuesta: `{ ok, data: { access_token, email } }`
- Usar token en header `Authorization: Bearer <token>`
- Sesión deslizante (sliding session), sin endpoint de refresh dedicado: `AuthMiddleware`
  reemite el token en la cabecera de respuesta `X-Refreshed-Token` en cualquier request
  autenticado cuya vida restante sea menor que la mitad de `JWT_EXPIRY`. Mientras el
  usuario siga haciendo peticiones autenticadas, la sesión no caduca; un usuario
  realmente inactivo caduca entre `JWT_EXPIRY` y `1.5 × JWT_EXPIRY` segundos después de
  su última actividad. El frontend (`ApiClientModel.js`) aplica ese token renovado de
  forma transparente y lo persiste vía `SessionModel`.

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
