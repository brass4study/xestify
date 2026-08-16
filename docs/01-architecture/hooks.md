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
- beforeValidate (no implementado)
- afterValidate (no implementado)
- beforeSave — implementado en `EntityService::createRecord()`/`updateRecord()`
- afterSave — implementado en `EntityService::createRecord()`/`updateRecord()`
- beforeDelete — implementado en `EntityService::deleteRecord()`; un plugin puede
  bloquear el borrado de sus propios registros lanzando `HookException`, igual que en
  `beforeSave`. El bloqueo por registros dependientes de OTRA entidad (via
  `schema.relations[]`) no pasa por este hook — es un guard núcleo
  (`EntityService::guardNoDependentRecords()`, reutiliza `ReverseRelationTabResolver`)
  porque requiere introspección cruzada de entidades que un hook de plugin no tiene.
- afterDelete — implementado en `EntityService::deleteRecord()`, no bloqueante

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

No existe ninguna interfaz formal para `Hooks::register()` (es pura convención), y
`registerActiveHooks()` invoca a `register($dispatcher)` de forma polimórfica sobre
cualquier plugin activo — cambiar esa firma afectaría a todos los plugins, no solo a los
que usan `registerTabs`/`registerActions`. Por eso, un plugin `extension` que quiera que
su prioridad sea configurable desde `PluginManager` (acciones Subir/Bajar sobre
`plugins.sort_order`) no cambia el contrato: se autoconsulta su propio `sort_order` a
través del `PDO` opcional que `PluginClassLoader` ya le inyecta por constructor, y lo
pasa como `priority` al registrar. `plugins/comments/Hooks.php` (`resolvePriority()`) es
la referencia de este patrón — usa el mínimo `sort_order` entre sus propias instancias
activas, ya que `plugin_name` no es único y una sola llamada a `register()` cubre todas
las instancias activas de ese plugin técnico.

(Nota histórica: hubo un intento anterior de resolver esto con una tabla `plugin_hooks`
dedicada — `slug`, `hook_name`, `priority`, `enabled` — pero `PluginHookRegistrar` nunca
llegó a leerla y se eliminó como infraestructura muerta. El patrón de autoconsulta vía
`sort_order` evita esa complejidad: no añade tablas ni cambia el contrato de
`register()`.)

## Buenas practicas

- Hooks idempotentes cuando sea posible
- Sin side effects ocultos
- Sin acceso directo a recursos no autorizados
- Timeouts para hooks costosos
