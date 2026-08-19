# Plantilla: Plugin de Entidad

## Objetivo

Definir el esqueleto minimo para crear una entidad base reusable.

## Estructura

```text
plugins/<slug>/
  manifest.json
  schema.json
  Hooks.php
  Lifecycle.php
  plugin.js
```

Notas:
- `manifest.json` es obligatorio.
- `schema.json` es obligatorio para plugins `entity`.
- `Hooks.php`, `Lifecycle.php` y `plugin.js` son opcionales segun necesidad.

## manifest.json base

```json
{
  "name": "persons",
  "label": "Personas",
  "label_singular": "Persona",
  "version": "1.0.0",
  "type": "entity",
  "core_version": "1.0.0",
  "description": "Entidad base de personas: nombre, apellidos y datos de contacto."
}
```

`name` es la identidad tecnica fija del plugin (= nombre de carpeta, igual al
namespace PHP) y **nunca** se muestra ni se edita desde `PluginConfig` — se guarda
tal cual en `manifest_json.name` y nunca cambia tras la instalacion. `label` es el nombre de negocio editable por instancia (p. ej. "Clientes"
en vez de "Personas"): se instala con el valor de este manifest por defecto, pero el
admin puede cambiarlo desde `PluginConfig` sin que un `syncAll()`/`update()`
posterior lo sobrescriba. `label_singular` (solo en plugins `entity`) es fijo y
siempre refleja este manifest — se usa para textos como "Crear cliente".
`description` tambien es editable por instancia, igual que `label`.

## schema.json base
El `schema.json` define el contrato fijo del plugin:
- `identities`: campos tecnicos de identidad del sistema (autogenerados, no editables).
- `fields`: campos funcionales del dominio definidos por el plugin.
- `custom_fields`: catalogo de sugerencias opcionales que el frontend ofrece durante la configuracion.
- `relations`: metadatos de relaciones entre entidades.

### Contrato base

```json
{
  "identities": {
    "id": {
      "type": "uuid",
      "label": "ID",
      "auto_generated": true,
      "editable": false
    }
  },
  "fields": {
    "name": {
      "type": "string",
      "required": true,
      "label": "Nombre"
    }
  },
  "custom_fields": [],
  "relations": []
}
```

### Ejemplo: plugin persons

```json
{
  "identities": {
    "id": {
      "type": "uuid",
      "label": "ID",
      "auto_generated": true,
      "editable": false
    }
  },
  "fields": {
    "name": {
      "type": "string",
      "required": true,
      "label": "Nombre"
    },
    "surnames": {
      "type": "string",
      "required": true,
      "label": "Apellidos"
    }
  },
  "custom_fields": [
    {
      "key": "mail",
      "type": "mail",
      "required": true,
      "label": "Email"
    },
    {
      "key": "phone",
      "type": "phone",
      "required": false,
      "label": "Telefono"
    },
    {
      "key": "address",
      "type": "string",
      "required": false,
      "label": "Direccion"
    }
  ],
  "relations": []
}
```

Nota: `mail` y `phone` son los tipos dedicados con validacion de formato propia
(`InputMail`/`InputPhone` en frontend, `MailFieldValidator`/`PhoneFieldValidator`
en backend) — ver `plugins/persons/schema.json` para el contrato completo real,
que incluye ademas DNI, ciudad, provincia, codigo postal y notas.

Comportamiento esperado:
- El admin ve `id` como identidad fija de sistema (no editable).
- `name` y `email` son obligatorios y no se pueden eliminar.
- `custom_fields` se presenta como sugerencias opcionales en frontend.
- El admin puede seleccionar sugerencias o crear campos manuales adicionales.

### Ejemplo con relacion `belongs_to`

Las relaciones definidas en `relations` pueden ser opcionales (`required: false`).
No es necesario declarar una `custom_field` para la FK: la relacion se define en `relations`
y el tipo/propiedades se infieren de la entidad destino mediante `target_field`.

Ejemplo: un pedido puede estar enlazado a un cliente, pero tambien puede ser anonimo.

```json
{
  "identities": {
    "id": {
      "type": "uuid",
      "label": "ID",
      "auto_generated": true,
      "editable": false
    }
  },
  "fields": {
    "total": {
      "type": "number",
      "required": true,
      "label": "Total"
    }
  },
  "custom_fields": [],
  "relations": [
    {
      "key": "id_person",
      "type": "belongs_to",
      "target_entity": "persons",
      "target_field": "id",
      "required": false,
      "label": "Cliente del pedido"
    }
  ]
}
```

Interpretacion de este ejemplo:
- Si `id_person` viene informado, el pedido queda relacionado con ese cliente.
- Si `id_person` viene vacio o `null`, el pedido es valido y se considera anonimo.
- `target_field: "id"` apunta al campo de identidad de `persons`, por lo que el tipo se infiere de esa definicion.

## Checklist

- Slug unico
- Version semantica
- `identities` definido
- Campos obligatorios definidos en `fields`
- Sugerencias opcionales en `custom_fields`
- Relaciones declaradas en `relations` (si aplica)
- Hooks declarados
- Migraciones incluidas
- Pruebas minimas del CRUD

## Registro en base de datos

Al instalarse, el plugin escribe en la tabla `plugins` (unica fuente de verdad del catalogo).
Esto lo hace siempre `PluginSyncService::installFromManifest()` (invocado por
"Sincronizar" o por el alta manual de plugin) — nunca lo escribas a mano ni desde
`Lifecycle::onInstall()`. Registra la fila en `plugins` y siembra `schema_json` desde el
`schema.json` del plugin antes de que se ejecute `onInstall()`. El `Lifecycle.php` de
un plugin de entidad tipico no necesita hacer nada en `onInstall()` — ver
`plugins/persons/Lifecycle.php` como ejemplo de no-op.

`plugin_name` (= `manifest_json.name`) es
la identidad tecnica fija del plugin (= nombre de carpeta, igual al namespace PHP) y
nunca cambia tras la instalacion; `slug` es editable desde `PluginConfig`
y solo sirve para navegacion/URL. `plugin_name` no es unico: pueden
coexistir varias filas con el mismo `plugin_name` y distinto `slug` (varias
instancias del mismo plugin). En el alta inicial de una instancia, `slug` coincide
con `plugin_name` salvo que se indique uno distinto explicitamente.

El tipo del plugin vive en `manifest_json->>'type'`.
Toda consulta al catalogo de entidades usa:
`SELECT * FROM plugins WHERE manifest_json->>'type' = 'entity' AND status = 'active'`.

## Unicidad de un campo en Hooks.php

Si el plugin necesita impedir valores duplicados en un campo (p. ej. `email` en
`persons`, `sku` en `products`), `Hooks.php` puede extender `AbstractUniqueFieldHook`
(`backend/src/plugins/contracts/AbstractUniqueFieldHook.php`) en vez de reimplementar
la comprobación: declara `pluginName()`, `fieldName()` y
`duplicateMessage(string $value)`, y `register($dispatcher)` engancha automáticamente
un hook `beforeSave` (prioridad 5) que rechaza duplicados entre registros activos de
esa entidad.

`pluginName()` debe devolver el `plugin_name` fijo del plugin (= nombre de carpeta),
**nunca** el slug: `EntityService` ya enriquece el contexto de `beforeSave` con
`plugin_name` (resuelto desde la fila real de `plugins`), así que la comprobación de
unicidad sigue funcionando aunque el admin renombre el `slug` de la entidad desde
`PluginConfig`, y se aplica de forma independiente a cada instancia si el mismo
`plugin_name` llega a tener varias.
