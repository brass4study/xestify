# Sistema de Hooks

## Objetivo

Permitir que plugins amplien comportamiento del Core sin modificar codigo central.

## Tipos de hook

1. Hooks de ciclo de vida de plugin
- onInstall
- onActivate
- onDeactivate

Nota: `onUpdate` y `onUninstall` no forman parte del contrato actual `PluginLifecycleInterface`.

2. Hooks de entidad
- beforeValidate
- afterValidate
- beforeSave
- afterSave
- beforeDelete
- afterDelete

3. Hooks de UI
- registerTabs
- registerActions
- registerWidgets

## Contrato sugerido

```php
interface HookHandler {
    public function handle(array $context): array;
}
```

Contexto minimo recomendado:

- plugin_slug
- entity_slug
- operation
- actor_id
- payload
- timestamp

## Orden de ejecucion

1. Core pre-hooks
2. Hooks de plugins activos por prioridad
3. Core post-hooks

## Manejo de errores

- Hook fallido en before* bloquea operacion
- Hook fallido en after* se registra como warning
- Toda excepcion debe incluir plugin_slug y hook_name

## Registro en base de datos

Tabla `plugin_hooks`:

- id
- slug
- target_entity_slug
- hook_name
- priority
- enabled

## Buenas practicas

- Hooks idempotentes cuando sea posible
- Sin side effects ocultos
- Sin acceso directo a recursos no autorizados
- Timeouts para hooks costosos
