# Contrato: Autenticación

## POST /api/v1/auth/login
- Request:
```json
{
  "email": "admin@demo.com",
  "password": "demo1234"
}
```
- Respuesta:
```json
{
  "ok": true,
  "data": {
    "access_token": "...jwt...",
    "email": "admin@demo.com"
  }
}
```
- Sin refresh token: la sesión expira a los `JWT_EXPIRY` segundos y hay que volver a hacer login.
