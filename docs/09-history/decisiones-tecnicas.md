# Decisiones Tecnicas - MVP Xestify

## Resumen ejecutivo


---

### Nota histÃƒÂ³rica sobre el catÃƒÂ¡logo de entidades

A partir de la migraciÃƒÂ³n 009, el catÃƒÂ¡logo funcional de entidades se consolidÃƒÂ³ exclusivamente sobre la tabla `plugins` (`plugin_type = 'entity'`). Se eliminÃƒÂ³ la dependencia de la tabla legacy `system_entities` en cÃƒÂ³digo y tests, y todos los procesos de alta, consulta y validaciÃƒÂ³n de entidades pasan por el registro de plugins.

Esta decisiÃƒÂ³n implicÃƒÂ³:
- Refactor de controladores, seeders y modelos para operar solo sobre `plugins`.
- Limpieza de cÃƒÂ³digo y tests para eliminar referencias a `system_entities`.
- ValidaciÃƒÂ³n de idempotencia y migraciÃƒÂ³n de datos.

El cambio es definitivo y no reversible: el catÃƒÂ¡logo de entidades siempre se obtiene de los plugins activos.

**Fecha de resolucion:** Mayo 1, 2026
**Responsable de decisiones:** [Usuario]
**Estado:** Aprobadas y listas para implementacion

---

## DECISION 1: Backend - PHP Nativo

**Seleccionado:** PHP nativo
**Alternativas consideradas:** Laravel, Symfony
**Fecha:** Mayo 1, 2026

### Justificacion
- MÃƒÂ¡ximo control y visibilidad del flujo de entidades dinÃƒÂ¡micas.
- NingÃƒÂºn overhead innecesario para un Core minimalista metadata-driven.
- RPi5 vuela sin problemas.
- Ideal para entender cada lÃƒÂ­nea de lÃƒÂ³gica de plugins.

### Implicaciones
- Responsabilidad de implementar: routing manual, DI container, migraciones, eventos/hooks.
- Estructura esperada: `backend/src/Core/`, `backend/src/Services/`, `backend/src/Controllers/`.
- No hay convenciones automÃƒÂ¡ticas: cada componente requiere decisiÃƒÂ³n explÃƒÂ­cita.

### Riesgos mitigados
- AbstracciÃƒÂ³n innecesaria.
- Lock-in a framework.

### Cambio futuro
Si en beta emerge complejidad no prevista, migraciÃƒÂ³n a Laravel es viable sin romper lÃƒÂ³gica de negocio (1-2 semanas).

---

## DECISION 2: InyecciÃƒÂ³n de Dependencias - Contenedor Casero

**Seleccionado:** Contenedor casero
**Alternativas consideradas:** Pimple, PHP-DI
**Fecha:** Mayo 1, 2026

### Justificacion
- MÃƒÂ¡ximo control sobre cÃƒÂ³mo se inyectan plugins en runtime.
- Cero overhead, cero magia.
- Permite registrar hooks directamente al construir servicios.
- Ideal para debugging.

### Estructura esperada
```php
class Container {
    private $services = [];      // Singletons
    private $factories = [];     // Factories

    public function register($name, callable $factory) { ... }
    public function singleton($name, callable $factory) { ... }
    public function get($name) { ... }
}
```

### Implicaciones
- ~200-300 lÃƒÂ­neas de cÃƒÂ³digo inicial.
- ResoluciÃƒÂ³n manual de dependencias entre servicios.
- GestiÃƒÂ³n de ciclo de vida (init/boot/shutdown).

### Cambio futuro
Si necesidad de autowiring emerge, upgrade a PHP-DI es directo.

---

## DECISION 3: Frontend - Vanilla PURO

**Seleccionado:** Vanilla JavaScript puro
**Alternativas consideradas:** Vue 3, React, Vanilla + Alpine/htmx
**Fecha:** Mayo 1, 2026

### Justificacion
- Cero dependencias externas = mÃƒÂ¡xima transparencia.
- Cada componente es una clase reutilizable.
- Debugging trivial.
- RPi5 respira (zero overhead).
- Ideal para sistemas altamente dinÃƒÂ¡micos.

### Estructura esperada
```
frontend/src/
  js/
    modules/
      DynamicForm.js      (clase)
      DynamicTable.js     (clase)
      DynamicTabs.js      (clase)
      Api.js              (cliente HTTP)
      State.js            (estado global)
    pages/
      EntityList.js
      EntityEdit.js
  html/
    index.html
  css/
    style.css
```

### Implicaciones
- Responsabilidad de: validaciÃƒÂ³n UX, estado global, manejo de componentes dinÃƒÂ¡micos.
- MÃƒÂ¡s lÃƒÂ­neas de cÃƒÂ³digo que Vue/React, pero 100% transparente.
- ComponentizaciÃƒÂ³n por clases reutilizables.

### Riesgos mitigados
- Build step innecesario.
- Complejidad de bundler.

### Cambio futuro
Si UX crece explosivamente, transiciÃƒÂ³n a Vue 3 es factible sin reescribir lÃƒÂ³gica (componentes dinÃƒÂ¡micos aplican igual).

---

## DECISION 4: AutenticaciÃƒÂ³n - JWT

**Seleccionado:** JWT (JSON Web Token)
**Alternativas consideradas:** SesiÃƒÂ³n local (Session + Cookie HTTP-only)
**Fecha:** Mayo 1, 2026

### Justificacion
- Stateless en servidor = escalabilidad.
- Funciona bien con marketplace remoto (futuro).
- Token enviado en cada request en header `Authorization: Bearer <token>`.
- Compatible con mÃƒÂºltiples clientes (desktop, mobile, etc.).

### Estructura esperada
```json
{
  "sub": "user_id_uuid",
  "email": "admin@xestify.local",
  "roles": ["admin"],
  "iat": 1234567890,
  "exp": 1234571490
}
```

### Flujo esperado
1. Login Ã¢â€ â€™ Backend valida credenciales Ã¢â€ â€™ Emite JWT.
2. Cliente almacena en localStorage.
3. Cada request incluye header JWT.
4. Backend valida firma del token.

### Implicaciones
- Necesidad de blacklist para revocaciÃƒÂ³n (tabla en BD).
- Tokens refresh: access_token (1-2h) + refresh_token (7d).
- Cliente debe manejar renovaciÃƒÂ³n automÃƒÂ¡tica.

### Riesgos mitigados
- XSS puede leer localStorage (mitigar con CSP headers).

### Cambio futuro
TransiciÃƒÂ³n a sesiÃƒÂ³n local es reversible (cambio de ~300 lÃƒÂ­neas en backend y frontend).

---

## DECISION 5: Schema de Entidades Ã¢â‚¬â€ Contrato del plugin + Schema vivo del admin

**Seleccionado:** Dos capas de schema: contrato del plugin + schema vivo (admin)
**Alternativas consideradas:** Schema fijo por plugin, JSON Schema estÃƒÂ¡ndar
**Fecha:** Mayo 1, 2026
**Actualizado:** Mayo 2, 2026 Ã¢â‚¬â€ modelo `identities` + `fields` + `custom_fields` + `relations`

### Modelo conceptual

El contrato del plugin se define con cuatro bloques:

| Bloque | Origen | Ã‚Â¿Puede modificarlo el admin? | Uso |
|------|--------|-----------------------------|-----|
| **identities** | Sistema/plugin | No Ã¢â‚¬â€ fijo | Identidad tÃƒÂ©cnica (`id` autogenerado) |
| **fields** | Plugin | Parcial: puede extender, no romper obligatorios | Campos funcionales de negocio |
| **custom_fields** | Plugin (catÃƒÂ¡logo) | SÃƒÂ­ | Sugerencias opcionales para frontend |
| **relations** | Plugin | SÃƒÂ­ (activar/desactivar por configuraciÃƒÂ³n) | Metadatos de relaciones |

**Regla fundamental:** cada entidad define su identidad tÃƒÂ©cnica en `identities` y sus campos obligatorios de negocio en `fields` (`required: true`).

### Flujo de configuraciÃƒÂ³n de una entidad

```
Plugin define contrato schema.json        Admin configura la entidad
(identities, fields, custom_fields,       (schema vivo en entity_metadata)
 relations)                                        Ã¢â€â€š
         Ã¢â€â€š                                   Mantiene obligatorios del dominio
 identities fijas                  Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€ â€™      Selecciona sugerencias opcionales
 fields requeridos                 Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€ â€™      AÃƒÂ±ade campos manuales
 relations opcionales              Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€ â€™      Define comportamiento final en runtime
```

### Estructura de schema.json del plugin (contrato)

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
            "label": "TelÃƒÂ©fono"
        }
    ],
    "relations": []
}
```

### Estructura de schema vivo en `plugins.schema_json` (tras configuraciÃƒÂ³n del admin)

El schema vivo refleja la configuraciÃƒÂ³n final del admin para validaciÃƒÂ³n y persistencia de negocio.
Las identidades tÃƒÂ©cnicas se mantienen como contrato de sistema y la CHECK actual sigue validando `fields`.

```json
{
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
        },
        "phone": {
            "type": "string",
            "required": false,
            "label": "TelÃƒÂ©fono"
        }
    }
}
```

### Plantillas de campos (futuro)

`custom_fields` podrÃƒÂ¡ versionarse en plantillas de negocio por sector (`retail`, `b2b`, etc.)
sin romper el contrato base del plugin.

### Tipos de campo soportados (MVP)
- `string`, `email`, `phone`
- `number`, `integer`
- `boolean`
- `date`, `datetime`
- `select` (con array de opciones)

### Futuro
- `array`, `object` (para extensiones complejas)
- Plantillas de campos mÃƒÂºltiples por plugin

### Implicaciones
- `schema.json` del plugin define el contrato inicial de entidad y configuraciÃƒÂ³n.
- En el repo actual, el schema vivo que usa `ValidationService` viene de `plugins.schema_json`.
- `entity_metadata` queda como referencia historica del diseÃƒÂ±o anterior; el catalogo runtime es `plugins`.
- El panel de administraciÃƒÂ³n debe combinar `fields` obligatorios + `custom_fields` opcionales.
- Las relaciones se configuran por metadatos en `relations`, no por definiciÃƒÂ³n duplicada de campo.

### Cambio futuro
MigraciÃƒÂ³n a JSON Schema es viable sin romper (1 semana de refactor puro).

---

## Matriz de decisiones

| Componente | Decision | MVP Ready | Risk Level |
|-----------|----------|-----------|-----------|
| Backend | PHP nativo | Ã¢Å“â€¦ Si | Ã°Å¸Å¸Â¡ Medio |
| DI Container | Casero | Ã¢Å“â€¦ Si | Ã°Å¸Å¸Â¡ Medio |
| Frontend | Vanilla JS | Ã¢Å“â€¦ Si | Ã°Å¸â€Â´ Alto |
| AutenticaciÃƒÂ³n | JWT | Ã¢Å“â€¦ Si | Ã°Å¸Å¸Â¢ Bajo |
| Schema | Custom | Ã¢Å“â€¦ Si | Ã°Å¸Å¸Â¢ Bajo |
| Relaciones | FK en JSONB | Ã¢Å“â€¦ Si | Ã°Å¸Å¸Â¡ Medio |

---

## DECISION 6: Relaciones entre entidades Ã¢â‚¬â€ Metadatos en `relations`, opcionales y tipadas por destino

**Seleccionado:** RelaciÃƒÂ³n declarada en `relations`; la clave vive en `entity_data.content`
**Alternativas consideradas:** Tabla `entity_relations` separada, usar FK real de PostgreSQL
**Fecha:** Mayo 1, 2026
**Actualizado:** Mayo 2, 2026 Ã¢â‚¬â€ relaciÃƒÂ³n opcional sin `custom_field` de FK obligatoria

### Justificacion
- Las entidades son dinÃƒÂ¡micas: no se puede crear una FK real de PostgreSQL en tiempo de ejecuciÃƒÂ³n sin DDL dinÃƒÂ¡mico (peligroso y complejo).
- La relaciÃƒÂ³n se declara en `relations` con `key`, `target_entity` y `target_field`.
- El tipo/semÃƒÂ¡ntica de la referencia se infiere de la entidad destino y su campo objetivo (`target_field`).
- Retrocompatible: no requiere nueva tabla ni migraciÃƒÂ³n.

### CÃƒÂ³mo encajan las relaciones con el modelo de campos

La FK no requiere definirse como `custom_field` separada. El contrato vive en `relations`.
Cada relaciÃƒÂ³n puede ser opcional (`required: false`).

Ejemplo de negocio: un pedido puede tener cliente enlazado o ser anÃƒÂ³nimo en caja.
Si la clave de relaciÃƒÂ³n no viene informada, el registro sigue siendo vÃƒÂ¡lido.

### Contrato de una relaciÃƒÂ³n (en schema.json del plugin)
```json
{
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

### Tipos de relaciÃƒÂ³n soportados (MVP)
| Tipo | SemÃƒÂ¡ntica | FK vive en |
|------|-----------|-----------|
| `belongs_to` | Este registro apunta a otro (N:1) | `content` del registro actual |
| `has_many` | Otros registros apuntan a este (1:N) | `content` de los otros registros |
| `has_one` | Un ÃƒÂºnico otro registro apunta a este (1:1) | `content` del otro registro |

### CÃƒÂ³mo se resuelve una relaciÃƒÂ³n
Para `belongs_to`: el valor de `key` (ej. `id_cliente`) en `content` apunta al registro destino. Para resolver:
```sql
SELECT content FROM entity_data
WHERE entity_slug = 'clients'
  AND id = :id_cliente_value
  AND deleted_at IS NULL
```
No hay JOIN automÃƒÂ¡tico Ã¢â‚¬â€ la resoluciÃƒÂ³n es explÃƒÂ­cita, bajo demanda (lazy).

### Implicaciones
- `ValidationService` no valida existencia del registro relacionado Ã¢â‚¬â€ eso es responsabilidad del Hook `beforeSave` del plugin.
- No hay integridad referencial en BD Ã¢â‚¬â€ es responsabilidad de la capa de aplicaciÃƒÂ³n / hooks.
- Si una relaciÃƒÂ³n opcional no trae valor, se procesa como relaciÃƒÂ³n ausente (caso vÃƒÂ¡lido).

### Riesgos
- Sin FK real Ã¢â€ â€™ posibles registros huÃƒÂ©rfanos si se elimina el registro referenciado.
- **MitigaciÃƒÂ³n:** Hook `beforeDelete` en el plugin que tenga `has_many` puede bloquear el borrado si existen registros dependientes.

### Futuro
Si la complejidad de relaciones crece, se puede aÃƒÂ±adir una tabla `entity_relations` materializada para joins rÃƒÂ¡pidos sin romper el contrato de schema (cambio aditivo).

## Proximo paso

Ver [historial-decisiones.md](historial-decisiones.md) para contexto completo de opciones consideradas.

Ver [consideraciones-iniciales.md](consideraciones-iniciales.md) para guÃƒÂ­a de implementaciÃƒÂ³n rÃƒÂ¡pida.

---

## DECISION 6: Catalogo de entidades Ã¢â‚¬â€ `plugins` como unica fuente de verdad

**Seleccionado:** Tabla `plugins` con `plugin_type = 'entity'` como catalogo unico
**Alternativa descartada:** Tabla `system_entities` separada (existia en EPIC 2, eliminada en EPIC 6)
**Fecha:** Mayo 2, 2026

### Problema detectado

La tabla `system_entities` era un duplicado parcial de `plugins`:
cada entidad instalada requeria una fila en `plugins` (para ciclo de vida y schema) Y
una fila en `system_entities` (para el catalogo). Dos tablas, mismos datos.

### Decision

Eliminar `system_entities` completamente. El filtro `WHERE plugin_type = 'entity' AND status = 'active'`
sobre `plugins` proporciona exactamente el mismo catalogo sin redundancia.

### Migraciones

- **Release A** (`009_unify_entities_into_plugins.sql`): AÃƒÂ±ade columna `name` a `plugins`, backfill desde `system_entities`, crea indice `idx_plugins_type_status`. Codigo actualizado para leer de `plugins` sin romper compatibilidad.
- **Release B** (`010_drop_system_entities.sql`): `DROP TABLE IF EXISTS system_entities`. Codigo y tests finalmente limpios.

### Implicaciones

- `PluginLoader::registerPlugin()` persiste `name` desde el manifest.
- En esa fase se usÃƒÂ³ `EntitySeeder` solo como apoyo transitorio sobre `plugins`; el producto final no depende de seeders de entidad.
- `Installer.php` de cada plugin de entidad escribe solo en `plugins`.
- `SystemEntity.php` consulta `plugins WHERE plugin_type='entity'` (sin cambio de interfaz publica).
- `EntityController::listEntities()` consulta `plugins` directamente.

### Invariante arquitectonico

> Todo tipo de entidad es un plugin. Todo plugin de tipo `entity` es una entidad.
> No existen entidades fuera de `plugins`.
