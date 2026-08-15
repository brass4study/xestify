# Plugins y extensiones

Esta carpeta contiene plantillas, ejemplos y documentación sobre el desarrollo de plugins y extensiones para Xestify.

---

## Tipos de plugin

- **Plugin de entidad** (`type: "entity"`): define una entidad base reusable (ejemplo: `persons`). Gestiona su propio schema y lógica de validación. Se registra en la tabla `plugins`, cuya columna `manifest_json` refleja en vivo el `manifest.json` del plugin (STORY 10.3 §2bis) y `schema_json` el `schema.json`.
- **Plugin de extensión** (`type: "extension"`): amplía el comportamiento de una entidad existente mediante hooks y UI adicional (ejemplo: `comments`). Puede inyectar tabs, acciones o paneles personalizados.

---

## Ciclo de vida de un plugin

1. **Instalación:** dos caminos, ambos vía `PluginSyncService::installFromManifest()`
   (siembra `manifest_json`/`schema_json` en `plugins` a partir de `manifest.json`/
   `schema.json`, sin que el propio plugin tenga que escribir SQL): "Sincronizar"
   desde `PluginManager` (alta masiva, deja los plugins nuevos `inactive`), o el
   alta manual plugin a plugin desde `PluginManager`/`PluginConfig`
   (`POST /api/v1/plugins`, `PluginAdministrationService::registerNew()`) —
   **esta segunda vía activa el plugin automáticamente** justo después de
   insertarlo (dispara `onActivate()`), a diferencia de la sincronización masiva.
   `onInstall()` se ejecuta despues del registro, solo para efectos adicionales
   propios del plugin (normalmente ninguno: ver `plugins/persons/Lifecycle.php`
   como ejemplo de no-op).
2. **Activación/Desactivación:** Cambia el estado del plugin y habilita/deshabilita hooks y UI asociada.
3. **Hooks:** Los plugins pueden registrar hooks como `beforeSave`, `registerTabs`, `registerActions`, etc., para integrarse con el core y otras extensiones.

---

## Ejemplo real: plugin de entidad `persons`

- [plugins/persons/manifest.json](../../plugins/persons/manifest.json): metadatos y compatibilidad
- [plugins/persons/schema.json](../../plugins/persons/schema.json): definición de campos, custom_fields e identidades (el bloque `relations` es funcional desde STORY 10.3 §8 — editable desde la sección "Relaciones" de `PluginConfig`, validado al guardar)
- [plugins/persons/Hooks.php](../../plugins/persons/Hooks.php): validación de unicidad de email
- [plugins/persons/Lifecycle.php](../../plugins/persons/Lifecycle.php): registro y activación

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