# Backend

El backend de Xestify usa PHP nativo con autoload manual, contenedor propio,
router HTTP y PostgreSQL. No introduce frameworks ni Composer.

## Responsabilidades

- Exponer la API REST y el endpoint de salud.
- Aplicar el pipeline `Router -> Middleware -> Controller`.
- Orquestar autenticación, entidades dinámicas, plugins, hooks y usuarios.
- Validar payloads y persistir datos core y JSONB.

## Referencias

- [Arquitectura general](../01-architecture/overview.md)
- [Arquitectura MVC](../01-architecture/mvc.md)
- [API y contratos](../03-api/README.md)
- [Seguridad](../07-security/README.md)
- [Operación y despliegue](../08-operations/README.md)
