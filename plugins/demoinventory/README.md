# demoinventory (plugin demo para UI de updates/rollback)

Este plugin se usa para probar en frontend las funcionalidades de STORY 7.5:
- sincronizacion
- deteccion de updates
- update
- rollback

## Flujo rapido de prueba

1. Poner plugin en v1 (disco):

```powershell
php tools/dev/switch-demoinventory-version.php 1
```

2. Sincronizar plugins (API o script):

```powershell
php tools/setup/sync-plugins.php
```

3. Activar `demoinventory` desde PluginManager.

4. Cambiar plugin a v2 (disco):

```powershell
php tools/dev/switch-demoinventory-version.php 2
```

5. En PluginManager, pulsar `Synchronize`.
   Debe aparecer `Update available` en `demoinventory`.

6. Pulsar `Update` y confirmar.
   Tras actualizar, debe aparecer `Rollback` (si hay snapshot compatible).

7. Pulsar `Rollback` y confirmar.
   Debe volver a la version previa.

## Versiones

- v1: `manifest.v1.json` + `schema.v1.json`
- v2: `manifest.v2.json` + `schema.v2.json`

La v2 es aditiva (agrega `custom_field` `stock`) para ser compatible con la politica de updates del sistema.
