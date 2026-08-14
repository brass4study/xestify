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
- Sin endpoint de refresh dedicado: la sesión es deslizante. Cualquier ruta protegida
  reemite el token en la cabecera de respuesta `X-Refreshed-Token` cuando le queda menos
  de la mitad de su `JWT_EXPIRY`, extendiendo la sesión mientras haya actividad
  autenticada. Ver `docs/03-api/autenticacion.md` para el detalle del contrato.
