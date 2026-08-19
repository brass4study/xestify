# Contrato: Plugins

Todas las rutas de este contrato requieren `AuthMiddleware` (JWT) y rol
`admin` (`PluginManagerController`). Las respuestas que incluyen un objeto
"plugin" aplanado (`slug`, `name`, `description`, `plugin_type`, `status`,
`version`) lo derivan de la columna `manifest_json` de `plugins`.

## GET /api/v1/plugins
- Lista todos los plugins instalados (cualquier estado).
- Respuesta:
```json
{
  "ok": true,
  "data": {
    "plugins": [
      {
        "slug": "persons",
        "name": "Personas",
        "description": "Entidad base de personas...",
        "plugin_type": "entity",
        "version": "1.0.0",
        "status": "active",
        "installed_at": "2026-01-01T00:00:00+00:00",
        "updated_at": "2026-01-01T00:00:00+00:00",
        "can_rollback": false
      }
    ]
  }
}
```

## GET /api/v1/plugins/available
- Carpetas de disco (`plugins/<name>/`) que aún no tienen fila en `plugins` —
  candidatas para el alta manual. Incluye una previsualización de `config`
  (campos/relaciones/target_entity) leída directamente del `manifest.json`/
  `schema.json` en disco, con la misma forma que `GET /plugins/{slug}/config`.
- Respuesta:
```json
{
  "ok": true,
  "data": {
    "available": [
      {
        "plugin_name": "orders",
        "label": "Pedidos",
        "suggested_slug": "orders",
        "description": "...",
        "plugin_type": "entity",
        "config": { "fields": [ /* ... */ ], "relations": [ /* ... */ ] }
      }
    ]
  }
}
```

## POST /api/v1/plugins
- Alta manual de una instancia nueva de un plugin descubierto en disco. **El
  plugin se registra y se activa automáticamente** (a diferencia del alta masiva
  vía `POST /plugins/sync`, que deja los plugins nuevos en `inactive`).
- Request:
```json
{
  "plugin_name": "orders",
  "slug": "pedidos",
  "name": "Pedidos",
  "description": "...",
  "fields": [ /* opcional, mismo formato que PUT .../config */ ],
  "relations": [ /* opcional, solo entity */ ],
  "target_entity": "persons"
}
```
  Solo `plugin_name` es obligatorio; el resto son overrides opcionales aplicados
  sobre los valores por defecto del manifest.
- Respuesta: `{ "ok": true, "data": { "plugin": { /* forma aplanada, status: "active" */ } } }`
- Errores: 422 si `plugin_name` falta o no está entre los disponibles, o si el
  `slug` indicado ya está en uso.

## PUT /api/v1/plugins/{slug}/status
- Activa o desactiva un plugin ya instalado (dispara `onActivate()`/`onDeactivate()`).
- Request: `{ "status": "active" }` (o `"inactive"`)
- Respuesta: `{ "ok": true, "data": { /* plugin, forma aplanada */ } }`
- Errores: 422 si falta/es inválido `status`; 404 si el slug no existe.

## POST /api/v1/plugins/sync
- Sincroniza plugins nuevos encontrados en disco (registra los que faltan, deja
  `inactive`) y detecta actualizaciones/plugins sin cambios, sin aplicarlas.
- Respuesta:
```json
{
  "ok": true,
  "data": {
    "summary": { "discovered": 3, "registered": 1, "unchanged": 1, "outdated": 1, "errors": 0 },
    "plugins": {
      "orders": [
        { "slug": "orders", "name": "Pedidos", "plugin_type": "entity", "result": "registered", "installed_version": "1.0.0", "available_version": "1.0.0", "message": "..." }
      ]
    }
  }
}
```

## GET /api/v1/plugins/updates
- Lista instancias con una versión más nueva disponible en disco.
- Respuesta: `{ "ok": true, "data": { "updates": [ { "slug", "name", "plugin_type", "installed_version", "available_version" } ] } }`

## POST /api/v1/plugins/{slug}/update
- Aplica la versión de disco disponible (fusión aditiva de schema — no borra
  campos existentes; guarda una foto en `plugin_update_history` antes de aplicar,
  usada por el rollback).
- Respuesta: `{ "ok": true, "data": { "plugin": { /* fila cruda, sin aplanar: incluye manifest_json anidado */ }, "update": { "from_version", "to_version", "schema_changed", "diff" } } }`
- Errores: 404 si no existe; 409 si no hay versión nueva o el tipo cambió.

## POST /api/v1/plugins/{slug}/rollback
- Restaura la última foto guardada en `plugin_update_history` para ese slug.
- Respuesta: `{ "ok": true, "data": { "plugin": { /* fila cruda, sin aplanar */ }, "rollback": { "from_version", "to_version", "snapshot_id" } } }`
- Errores: 404 si no existe; 409 si no hay snapshot disponible.

## POST /api/v1/plugins/{slug}/move-up
- Sube un puesto el `sort_order` manual del plugin (intercambia posición con el
  vecino anterior). Afecta al orden en que `PluginManager` lista los plugins.
- Respuesta: `{ "ok": true, "data": { /* plugin, forma aplanada */ } }`
- Errores: 404 si el slug no existe.

## POST /api/v1/plugins/{slug}/move-down
- Baja un puesto el `sort_order` manual del plugin (intercambia posición con
  el vecino siguiente).
- Respuesta: `{ "ok": true, "data": { /* plugin, forma aplanada */ } }`
- Errores: 404 si el slug no existe.

## GET /api/v1/plugins/{slug}/config
- Solo plugins `entity`/`extension` activos.
- Respuesta:
```json
{
  "ok": true,
  "data": {
    "plugin": { "slug", "name", "description", "plugin_type", "status", "version" },
    "config": {
      "fields": [ { "active": true, "key": "name", "type": "string", "label": "Nombre", "required": true, "summaryView": true, "locked": true, "source": "base" } ],
      "relations": [ { "key": "id_person", "type": "belongs_to", "target_entity": "persons", "target_field": "id", "label": "Cliente", "required": false } ],
      "target_entity": "*"
    }
  }
}
```
  `relations` solo aparece para plugins `entity`; `target_entity` solo para `extension`.

## PUT /api/v1/plugins/{slug}/config
- Request: `{ "fields": [...], "relations"?: [...], "slug"?, "name"?, "description"?, "target_entity"? }`
  (`relations` solo se procesa para plugins `entity`; `target_entity` solo para `extension`).
  Si el payload trae `slug`/`name`/`description`, también actualiza la identidad
  del plugin en la misma operación.
- Respuesta: misma forma que `GET .../config`.
- Validación de `relations`: cada fila exige `key` no vacío y sin colisión con un
  campo/relación existente, `target_entity` que sea una entidad activa, y
  `target_field` que sea una identidad declarada de esa entidad destino. `type`
  siempre se persiste como `"belongs_to"`.
- Errores: 422 en validación; 404 si no existe.

## DELETE /api/v1/plugins/{slug}
- Borra el plugin y **todos** sus datos asociados (irreversible). Permitido en
  cualquier estado — si está `active`, ejecuta `onDeactivate()` antes del borrado
  físico, en la misma operación.
- Cascada por tipo: `entity` borra `plugin_entity_data` propio y cualquier
  `plugin_extension_data` que apuntara a esa entidad; `extension` borra solo su
  propio `plugin_extension_data`. En ambos casos se borra también
  `plugin_update_history` y la fila de `plugins`.
- Respuesta: `{ "ok": true, "data": { "deleted": true, "slug": "orders" } }`
- Errores: 404 si el slug no existe.
