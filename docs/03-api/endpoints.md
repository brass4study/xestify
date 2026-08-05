# Endpoints disponibles

| Método | Path | Descripción | Autenticación |
|--------|------|-------------|---------------|
| GET    | /health | Estado del sistema | No |
| POST   | /api/v1/auth/login | Login y obtención de token JWT | No |
| GET    | /api/v1/entities | Listar entidades activas | Sí |
| GET    | /api/v1/entities/{slug}/schema | Obtener schema de entidad | Sí |
| GET    | /api/v1/entities/{slug}/tabs | Tabs disponibles para entidad | Sí |
| GET    | /api/v1/entities/{slug}/actions | Acciones disponibles para entidad | Sí |
| GET    | /api/v1/entities/{slug}/records | Listar registros de entidad | Sí |
| POST   | /api/v1/entities/{slug}/records | Crear registro de entidad | Sí |
| GET    | /api/v1/entities/{slug}/records/{id} | Obtener registro por ID | Sí |
| PUT    | /api/v1/entities/{slug}/records/{id} | Actualizar registro (merge JSONB) | Sí |
| DELETE | /api/v1/entities/{slug}/records/{id} | Eliminar (soft-delete) registro | Sí |
| GET    | /api/v1/plugins | Listar plugins instalados | Sí (admin) |
| POST   | /api/v1/plugins/sync | Sincronizar plugins desde disco sin consumir actualizaciones | Sí (admin) |
| GET    | /api/v1/plugins/updates | Listar plugins con actualización disponible | Sí (admin) |
| POST   | /api/v1/plugins/{slug}/update | Aplicar actualización explícita de plugin | Sí (admin) |
| POST   | /api/v1/plugins/{slug}/rollback | Revertir plugin a la versión previa desde snapshot | Sí (admin) |
| PUT    | /api/v1/plugins/{slug}/status | Cambiar estado de plugin (activar/desactivar) | Sí (admin) |
| GET    | /api/v1/plugins/{slug}/config | Obtener configuración de plugin activo | Sí (admin) |
| PUT    | /api/v1/plugins/{slug}/config | Guardar configuración y versionar schema de plugin activo | Sí (admin) |
| GET    | /api/v1/plugins/{plugin_slug}/{entity}/{id} | Listar items de extensión para entidad/registro | Sí |
| POST   | /api/v1/plugins/{plugin_slug}/{entity}/{id} | Crear item de extensión para entidad/registro | Sí |
| PUT    | /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id} | Actualizar (merge) item de extensión | Sí |
| DELETE | /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id} | Eliminar item de extensión | Sí |

> **Notas:**
> - "Sí" implica autenticación JWT válida (usuario logueado). "Sí (admin)" requiere además rol admin.
> - Los endpoints de plugins de extensión son genéricos y discriminan por plugin_slug y entity.
> - Ver [contratos](contratos/) para detalles de payload y respuesta.

> Ver contratos para detalles de payload y respuesta.
