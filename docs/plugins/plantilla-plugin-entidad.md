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
  Installer.php
  plugin.js
```

Notas:
- `manifest.json` es obligatorio.
- `schema.json` es obligatorio para plugins `entity`.
- `Hooks.php`, `Lifecycle.php`, `Installer.php` y `plugin.js` son opcionales segun necesidad.

## manifest.json base

```json
{
  "slug": "clients",
  "name": "Clientes",
  "version": "1.0.0",
  "type": "entity",
  "core_version": "1.0.0"
}
```

## schema.json base
El `schema.json` define el contrato fijo del plugin:
- `identities`: campos tecnicos de identidad del sistema (autogenerados, no editables).
- `fields`: campos funcionales del dominio definidos por el plugin.
- `custom_fields`: catalogo de sugerencias opcionales que el frontend ofrece durante la configuracion.
- `relations`: metadatos de relaciones entre entidades.

### Contrato base

```json
{
  "entity": "<slug>",
  "version": "1.0.0",
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

### Ejemplo: plugin clients

```json
{
  "entity": "clients",
  "version": "1.0.0",
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
    "email": {
      "type": "email",
      "required": true,
      "label": "Email"
    }
  },
  "custom_fields": [
    {
      "key": "phone",
      "type": "string",
      "required": false,
      "label": "Telefono"
    },
    {
      "key": "creation_stamp",
      "type": "timestamp",
      "required": false,
      "default": "now",
      "label": "Fecha de alta"
    },
    {
      "key": "is_active",
      "type": "boolean",
      "required": false,
      "default": true,
      "label": "Activo"
    }
  ],
  "relations": []
}
```

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
  "entity": "order",
  "version": "1.0.0",
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
      "key": "id_cliente",
      "type": "belongs_to",
      "target_entity": "clients",
      "target_field": "id",
      "required": false,
      "label": "Cliente del pedido"
    }
  ]
}
```

Interpretacion de este ejemplo:
- Si `id_cliente` viene informado, el pedido queda relacionado con ese cliente.
- Si `id_cliente` viene vacio o `null`, el pedido es valido y se considera anonimo.
- `target_field: "id"` apunta al campo de identidad de `clients`, por lo que el tipo se infiere de esa definicion.

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

Al instalarse, el plugin escribe en la tabla `plugins` (unica fuente de verdad del catalogo):

```php
// En Installer.php del plugin
$pdo->prepare(
    'INSERT INTO plugins (slug, name, plugin_type, version, status)
     VALUES (:slug, :name, \'entity\', :version, \'active\')
     ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name, status = \'active\''
)->execute([':slug' => $slug, ':name' => $name, ':version' => $version]);
```

**No escribir en `system_entities`** - esa tabla fue eliminada en Release B.
Toda consulta al catalogo de entidades usa: `SELECT * FROM plugins WHERE plugin_type = 'entity' AND status = 'active'`.
