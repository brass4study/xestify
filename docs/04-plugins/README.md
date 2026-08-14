# Plugins y extensiones

Esta carpeta contiene plantillas, ejemplos y documentación sobre el desarrollo de plugins y extensiones para Xestify.

---

## Tipos de plugin

- **Plugin de entidad** (`type: "entity"`): define una entidad base reusable (ejemplo: `persons`). Gestiona su propio schema y lógica de validación. Se registra en la tabla `plugins` y expone su metadata vía manifest y schema.
- **Plugin de extensión** (`type: "extension"`): amplía el comportamiento de una entidad existente mediante hooks y UI adicional (ejemplo: `comments`). Puede inyectar tabs, acciones o paneles personalizados.

---

## Ciclo de vida de un plugin

1. **Instalación:** El método `onInstall()` registra la entidad o extensión y su schema en la tabla `plugins`.
2. **Activación/Desactivación:** Cambia el estado del plugin y habilita/deshabilita hooks y UI asociada.
3. **Hooks:** Los plugins pueden registrar hooks como `beforeSave`, `registerTabs`, `registerActions`, etc., para integrarse con el core y otras extensiones.

---

## Ejemplo real: plugin de entidad `persons`

- [plugins/persons/manifest.json](../../plugins/persons/manifest.json): metadatos y compatibilidad
- [plugins/persons/schema.json](../../plugins/persons/schema.json): definición de campos, custom_fields y relaciones
- [plugins/persons/Hooks.php](../../plugins/persons/Hooks.php): validación de unicidad de email
- [plugins/persons/Lifecycle.php](../../plugins/persons/Lifecycle.php): registro y activación
- [plugins/persons/Installer.php](../../plugins/persons/Installer.php): alta idempotente en la tabla `plugins`

## Ejemplo real: plugin de extensión `comments`

- [plugins/comments/manifest.json](../../plugins/comments/manifest.json): metadatos y target_entity
- [plugins/comments/schema.json](../../plugins/comments/schema.json): campos de comentario y autor
- [plugins/comments/Hooks.php](../../plugins/comments/Hooks.php): inyección de tab "Comentarios" en todas las entidades
- [plugins/comments/Lifecycle.php](../../plugins/comments/Lifecycle.php): registro de hook y activación
- [plugins/comments/plugin.js](../../plugins/comments/plugin.js): panel frontend para gestión de comentarios

---

## Mejores prácticas

- Mantener la estructura plana por plugin (sin carpetas backend/frontend internas)
- Declarar siempre `manifest.json` y `schema.json` (al menos para plugins de entidad)
- Usar hooks para desacoplar lógica y UI del core
- Documentar dependencias y target_entity en manifest
- Proveer paneles frontend desacoplados usando PluginPanelRegistry

---

## Plantillas y referencia

- [plantilla-plugin-entidad.md](plantilla-plugin-entidad.md): Plantilla para plugins de entidad
- [plantilla-plugin-extension.md](plantilla-plugin-extension.md): Plantilla para plugins de extensión