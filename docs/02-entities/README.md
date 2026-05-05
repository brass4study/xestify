# Entidades y modelo de datos

Esta carpeta reúne la documentación sobre la estructura flexible de entidades, el uso de JSONB y el versionado de esquemas en Xestify.

## Contenido
- [postgresql-jsonb.md](postgresql-jsonb.md): Uso de JSONB para entidades dinámicas
- [versionado-esquemas.md](versionado-esquemas.md): Estrategias de versionado y migración de esquemas

---

Consulta estos documentos para entender cómo se modelan, almacenan y evolucionan las entidades y sus campos en el sistema.
---

**Catálogo de entidades:**
El sistema obtiene el listado de entidades funcionales exclusivamente desde la tabla `plugins` filtrando por `plugin_type = 'entity'` y `status = 'active'`.
No existe ya la tabla `system_entities` ni coexistencia de catálogos. Toda la lógica de alta, consulta y validación de entidades se basa en los plugins instalados y activos.
