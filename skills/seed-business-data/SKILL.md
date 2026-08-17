---
name: seed-business-data
description: Siembra datos de negocio de demostracion (clientes, distribuidores, oftalmologos, marcas, fabricantes, pedidos a distribuidor, ventas a cliente, facturas, fichas de optometria/lentillas y comentarios) para entornos de desarrollo/demo de Xestify (STORY 10.6). Usar cuando el usuario pida "sembrar datos de demo", "poblar la base de datos", "cargar datos de negocio", "resetear los datos de demo", o mencione STORY 10.6.
---

# Seed Business Data

Siembra el conjunto de datos de negocio de demostracion definido en STORY
10.6 del backlog (`docs/11-backlog/backlog.md`). Toda la logica real vive en
PHP (`backend/src/database/seeders/BusinessDataSeeder.php` y sus grupos); esta
skill solo documenta cuando y como ejecutarla.

## Que siembra

11 grupos, en este orden de dependencia:

| Grupo | Tabla destino | Cantidad | Depende de |
|---|---|---|---|
| `brands` | `plugin_entity_data` | 30 | — |
| `manufacturers` | `plugin_entity_data` | 15 | — |
| `distributors` | `plugin_entity_data` | 25 | — |
| `ophthalmologists` | `plugin_entity_data` | 100 | — |
| `clients` | `plugin_entity_data` | 200 | — |
| `orders` (Pedidos a distribuidor) | `plugin_entity_data` | 300 | `distributors` |
| `invoices` | `plugin_entity_data` | ~270 (90% de `orders`) | `orders` |
| `sales` (Ventas a cliente) | `plugin_entity_data` | 250 | `clients` |
| `optometries` (ficha) | `plugin_extension_data` | 100% de `clients`, 1-4 fichas c/u | `clients`, `ophthalmologists` |
| `contact_lenses` (ficha) | `plugin_extension_data` | 100% de `clients`, 1-4 fichas c/u | `clients`, `brands`, `manufacturers`, `distributors` |
| `comments` | `plugin_extension_data` (solo sobre `clients`) | 0-3 por cliente | `clients` |

No siembra `products`, `items` (plugin `demoinventory`) ni la plantilla
`basic` inactiva — fuera del alcance de STORY 10.6.

Cada cliente recibe un "tier" de actividad (alto/medio/bajo, 40/30/30)
compartido entre `optometries`, `contact_lenses` y `comments`: los mismos
clientes "VIP" concentran más fichas y más comentarios a la vez.

## Prerrequisitos

1. El usuario admin debe existir: `php tools/setup/seed-admin-user.php`.
2. Los 11 plugins/instancias listados arriba deben estar `status='active'`
   en la tabla `plugins` (sincronizar y activar vía `tools/setup/sync-plugins.php`
   o desde PluginManager si falta alguno).

Si falta el admin o cualquier plugin requerido no está activo, el script
**aborta entero sin insertar nada** e indica qué falta.

## Comando

```powershell
php tools/setup/seed-business-data.php
```

## Comportamiento idempotente

"Todo o nada por grupo": antes de sembrar cada uno de los 11 grupos, se
comprueba si ya tiene registros. Si los tiene, ese grupo se salta entero
(no se completa ni se duplica) y sus ids existentes se reutilizan para los
grupos dependientes. Es seguro volver a ejecutar el comando en cualquier
momento — no duplica datos.

## Verificación rápida

```sql
SELECT entity_slug, COUNT(*) FROM plugin_entity_data GROUP BY entity_slug ORDER BY entity_slug;
SELECT plugin_slug, COUNT(*) FROM plugin_extension_data GROUP BY plugin_slug ORDER BY plugin_slug;
```

## Reset manual (solo desarrollo, no automatizado)

Para poder volver a probar el seeder desde cero:

```sql
DELETE FROM plugin_entity_data WHERE entity_slug IN
  ('clients', 'distributors', 'ophthalmologists', 'brands', 'manufacturers', 'orders', 'sales', 'invoices');
DELETE FROM plugin_extension_data WHERE plugin_slug IN
  ('optometries', 'contact_lenses', 'comments');
```

No ejecutar esto en una base de datos con datos reales de negocio: borra
también cualquier registro real que coincida con esos slugs, no solo el
sembrado por el seeder.
