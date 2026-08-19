# Endpoints disponibles

| Método | Path | Descripción | Autenticación |
|--------|------|-------------|---------------|
| GET    | /health | Estado del sistema | No |
| POST   | /api/v1/auth/login | Login y obtención de token JWT | No |
| GET    | /api/v1/configurations | Listar configuración global | Sí (admin) |
| GET    | /api/v1/configurations/{key} | Obtener valor de configuración por clave | Sí |
| PUT    | /api/v1/configurations/{key} | Guardar valor de configuración por clave | Sí (admin) |
| GET    | /api/v1/users/me | Obtener perfil propio | Sí |
| PUT    | /api/v1/users/me | Actualizar perfil propio | Sí |
| GET    | /api/v1/users | Listar usuarios | Sí (admin) |
| GET    | /api/v1/users/{id} | Obtener usuario por ID | Sí (admin) |
| PUT    | /api/v1/users/{id} | Actualizar usuario | Sí (admin) |
| PUT    | /api/v1/users/{id}/password | Restablecer contraseña de usuario | Sí (admin) |
| DELETE | /api/v1/users/{id} | Borrado lógico de usuario | Sí (admin) |
| GET    | /api/v1/entities | Listar entidades activas | Sí |
| GET    | /api/v1/entities/{slug}/schema | Obtener schema de entidad | Sí |
| GET    | /api/v1/entities/{slug}/options | Listar opciones (id + etiqueta) de una entidad para selects de relación | Sí |
| GET    | /api/v1/entities/{slug}/tabs | Tabs disponibles para entidad | Sí |
| GET    | /api/v1/entities/{slug}/actions | Acciones disponibles para entidad | Sí |
| GET    | /api/v1/entities/{slug}/records | Listar registros de entidad | Sí |
| POST   | /api/v1/entities/{slug}/records | Crear registro de entidad | Sí |
| GET    | /api/v1/entities/{slug}/records/{id} | Obtener registro por ID | Sí |
| PUT    | /api/v1/entities/{slug}/records/{id} | Actualizar registro (merge JSONB) | Sí |
| DELETE | /api/v1/entities/{slug}/records/{id} | Eliminar (soft-delete) registro y limpiar en cascada su plugin_extension_data | Sí |
| GET    | /api/v1/plugins | Listar plugins instalados | Sí (admin) |
| GET    | /api/v1/plugins/available | Listar carpetas de disco aún no registradas | Sí (admin) |
| POST   | /api/v1/plugins | Alta manual de una instancia nueva (se activa automáticamente) | Sí (admin) |
| POST   | /api/v1/plugins/sync | Sincronizar plugins desde disco sin consumir actualizaciones (deja `inactive`) | Sí (admin) |
| GET    | /api/v1/plugins/updates | Listar plugins con actualización disponible | Sí (admin) |
| POST   | /api/v1/plugins/{slug}/update | Aplicar actualización explícita de plugin | Sí (admin) |
| POST   | /api/v1/plugins/{slug}/rollback | Revertir plugin a la versión previa desde snapshot | Sí (admin) |
| PUT    | /api/v1/plugins/{slug}/status | Cambiar estado de plugin (activar/desactivar) | Sí (admin) |
| POST   | /api/v1/plugins/{slug}/move-up | Subir un puesto el orden manual del plugin | Sí (admin) |
| POST   | /api/v1/plugins/{slug}/move-down | Bajar un puesto el orden manual del plugin | Sí (admin) |
| GET    | /api/v1/plugins/{slug}/config | Obtener configuración de plugin activo | Sí (admin) |
| PUT    | /api/v1/plugins/{slug}/config | Guardar configuración (campos, relaciones, target_entity) y versionar schema de plugin activo | Sí (admin) |
| DELETE | /api/v1/plugins/{slug} | Borrar plugin y todos sus datos asociados (cascada) | Sí (admin) |
| GET    | /api/v1/plugins/{plugin_slug}/{entity}/{id} | Listar items de extensión para entidad/registro | Sí |
| POST   | /api/v1/plugins/{plugin_slug}/{entity}/{id} | Crear item de extensión para entidad/registro | Sí |
| PUT    | /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id} | Actualizar (merge) item de extensión | Sí |
| DELETE | /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id} | Eliminar item de extensión | Sí |

> **Notas:**
> - "Sí" implica autenticación JWT válida (usuario logueado). "Sí (admin)" requiere además rol admin.
> - `GET /api/v1/plugins` incluye `can_rollback` por plugin para indicar si existe snapshot compatible para rollback.
> - `GET /api/v1/entities/{slug}/records` acepta también `?field=&value=` — filtro exacto sin paginar sobre una clave de `content`, distinto del listado paginado normal.
> - `GET /api/v1/entities/{slug}/tabs` incluye, además de las tabs aportadas por plugins vía `registerTabs`, tabs `type: "relation"` generadas automáticamente por el núcleo cuando otra entidad declara una relación hacia esta — no vienen de ningún `plugin.js`.
> - Para tabs de plugins `extension`, `GET /api/v1/entities/{slug}/tabs` puede incluir además `relations` y `entity`, junto con `fields` — solo los campos con `origin: "additional"` (añadidos después vía "Añadir campo" en `PluginConfig`), no los originales del plugin (esos tienen UI escrita a mano).
> - `POST`/`PUT /api/v1/plugins/{plugin_slug}/{entity}/{id}[/{item_id}]` validan `content` contra el schema del plugin (`ValidationService`) antes de persistir.
> - `DELETE /api/v1/entities/{slug}/records/{id}` devuelve `422` cuando otra entidad tiene registros que dependen de este vía `schema.relations[]` (no se borra hasta que se borren o desvinculen esos registros dependientes primero).
> - Los endpoints de plugins de extensión son genéricos y discriminan por plugin_slug y entity.
> - Ver [contratos](contratos/) para detalles de payload y respuesta.

> Ver contratos para detalles de payload y respuesta.
