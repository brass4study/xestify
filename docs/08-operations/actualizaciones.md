# Sistema de Actualizaciones

## Objetivo

Actualizar plugins ya instalados de forma controlada y trazable, siempre por
acción explícita de un administrador — sin automatismos en cada request.

## Flujo

1. **Sincronizar** — `POST /api/v1/plugins/sync`. Recorre `plugins/` en
   disco, registra los plugins nuevos que encuentra y detecta si algún
   plugin instalado tiene una versión más reciente en disco, pero preserva
   la versión y el schema en ejecución de los ya instalados.
2. **Consultar versiones disponibles** — `GET /api/v1/plugins/updates`.
   Devuelve, para cada plugin instalado, si hay una versión más nueva
   disponible en disco.
3. **Actualizar** — `POST /api/v1/plugins/{slug}/update`. Antes de aplicar,
   guarda un snapshot del estado previo en `plugin_update_history`. Aplica
   la nueva versión con una fusión **aditiva** de schema sobre
   `manifest_json`/`schema_json` (solo añade campos/relaciones nuevos, nunca
   elimina ni reescribe los existentes). Si el plugin implementa
   `onUpdate(array $context)`, se invoca tras la actualización.
4. **Rollback** — `POST /api/v1/plugins/{slug}/rollback`. Restaura versión y
   schema desde el snapshot más reciente en `plugin_update_history`
   compatible con la versión objetivo. Devuelve `409` si no existe un
   snapshot aplicable.

Todo el flujo se controla desde la página `PluginManager` del frontend:
badge de versión disponible, botones de sincronizar/actualizar/revertir y
confirmación antes de aplicar cambios.

## Snapshots

Cada actualización aplicada queda registrada en `plugin_update_history` con
la versión de origen, la versión de destino y el estado previo del plugin
(versión y schema), lo que permite el rollback posterior.

## Logging

Cada operación de sync/update/rollback deja rastro en la respuesta de la
API con al menos: `slug`, versión anterior, versión nueva y resultado. Los
detalles de payload de cada endpoint están en
[docs/03-api/contratos/plugins.md](../03-api/contratos/plugins.md).
