# Sistema de Hooks

## Objetivo

Permitir que plugins amplien comportamiento del Core sin modificar codigo central.

## Tipos de hook

1. Hooks de ciclo de vida de plugin
- onInstall
- onActivate
- onDeactivate

Nota: `onUpdate` y `onRollback` son opcionales — un plugin los declara implementando `PluginLifecycleUpdateInterface` (que extiende `PluginLifecycleInterface`); `PluginLifecycleInvoker` comprueba `instanceof` antes de invocarlos. `onUninstall` no forma parte de ningún contrato actual.

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

## Registro de hooks en tiempo de ejecución

En cada request, `PluginHookRegistrar::registerActiveHooks()` recorre los plugins con
`plugins.status = 'active'` (`PluginRepository::listActiveSlugs()`) y llama
`Hooks::register($dispatcher)` de cada uno, sin condiciones adicionales — es el propio
plugin quien decide qué hooks registra y con qué prioridad. Activar/desactivar el plugin
completo (`plugins.status`) es la única palanca real; no existe activación/desactivación
de un hook individual.

## Buenas practicas

- Hooks idempotentes cuando sea posible
- Sin side effects ocultos
- Sin acceso directo a recursos no autorizados
- Timeouts para hooks costosos
