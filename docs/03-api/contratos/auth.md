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
    "accessToken": "...jwt..."
  }
}
```
