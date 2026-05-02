# Decisiones Tecnicas - MVP Xestify

## Resumen ejecutivo

Documento que consolida las 5 decisiones tecnicas tomadas para el MVP. Cada decision incluye justificacion, implicaciones y referencias al historial completo.

**Fecha de resolucion:** Mayo 1, 2026  
**Responsable de decisiones:** [Usuario]  
**Estado:** Aprobadas y listas para implementacion

---

## DECISION 1: Backend - PHP Nativo

**Seleccionado:** PHP nativo  
**Alternativas consideradas:** Laravel, Symfony  
**Fecha:** Mayo 1, 2026

### Justificacion
- Máximo control y visibilidad del flujo de entidades dinámicas.
- Ningún overhead innecesario para un Core minimalista metadata-driven.
- RPi5 vuela sin problemas.
- Ideal para entender cada línea de lógica de plugins.

### Implicaciones
- Responsabilidad de implementar: routing manual, DI container, migraciones, eventos/hooks.
- Estructura esperada: `backend/src/Core/`, `backend/src/Services/`, `backend/src/Controllers/`.
- No hay convenciones automáticas: cada componente requiere decisión explícita.

### Riesgos mitigados
- Abstracción innecesaria.
- Lock-in a framework.

### Cambio futuro
Si en beta emerge complejidad no prevista, migración a Laravel es viable sin romper lógica de negocio (1-2 semanas).

---

## DECISION 2: Inyección de Dependencias - Contenedor Casero

**Seleccionado:** Contenedor casero  
**Alternativas consideradas:** Pimple, PHP-DI  
**Fecha:** Mayo 1, 2026

### Justificacion
- Máximo control sobre cómo se inyectan plugins en runtime.
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
- ~200-300 líneas de código inicial.
- Resolución manual de dependencias entre servicios.
- Gestión de ciclo de vida (init/boot/shutdown).

### Cambio futuro
Si necesidad de autowiring emerge, upgrade a PHP-DI es directo.

---

## DECISION 3: Frontend - Vanilla PURO

**Seleccionado:** Vanilla JavaScript puro  
**Alternativas consideradas:** Vue 3, React, Vanilla + Alpine/htmx  
**Fecha:** Mayo 1, 2026

### Justificacion
- Cero dependencias externas = máxima transparencia.
- Cada componente es una clase reutilizable.
- Debugging trivial.
- RPi5 respira (zero overhead).
- Ideal para sistemas altamente dinámicos.

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
- Responsabilidad de: validación UX, estado global, manejo de componentes dinámicos.
- Más líneas de código que Vue/React, pero 100% transparente.
- Componentización por clases reutilizables.

### Riesgos mitigados
- Build step innecesario.
- Complejidad de bundler.

### Cambio futuro
Si UX crece explosivamente, transición a Vue 3 es factible sin reescribir lógica (componentes dinámicos aplican igual).

---

## DECISION 4: Autenticación - JWT

**Seleccionado:** JWT (JSON Web Token)  
**Alternativas consideradas:** Sesión local (Session + Cookie HTTP-only)  
**Fecha:** Mayo 1, 2026

### Justificacion
- Stateless en servidor = escalabilidad.
- Funciona bien con marketplace remoto (futuro).
- Token enviado en cada request en header `Authorization: Bearer <token>`.
- Compatible con múltiples clientes (desktop, mobile, etc.).

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
1. Login → Backend valida credenciales → Emite JWT.
2. Cliente almacena en localStorage.
3. Cada request incluye header JWT.
4. Backend valida firma del token.

### Implicaciones
- Necesidad de blacklist para revocación (tabla en BD).
- Tokens refresh: access_token (1-2h) + refresh_token (7d).
- Cliente debe manejar renovación automática.

### Riesgos mitigados
- XSS puede leer localStorage (mitigar con CSP headers).

### Cambio futuro
Transición a sesión local es reversible (cambio de ~300 líneas en backend y frontend).

---

## DECISION 5: Schema de Entidades — Contrato del plugin + Schema vivo del admin

**Seleccionado:** Dos capas de schema: contrato del plugin + schema vivo (admin)  
**Alternativas consideradas:** Schema fijo por plugin, JSON Schema estándar  
**Fecha:** Mayo 1, 2026  
**Actualizado:** Mayo 2, 2026 — modelo `identities` + `fields` + `custom_fields` + `relations`

### Modelo conceptual

El contrato del plugin se define con cuatro bloques:

| Bloque | Origen | ¿Puede modificarlo el admin? | Uso |
|------|--------|-----------------------------|-----|
| **identities** | Sistema/plugin | No — fijo | Identidad técnica (`id` autogenerado) |
| **fields** | Plugin | Parcial: puede extender, no romper obligatorios | Campos funcionales de negocio |
| **custom_fields** | Plugin (catálogo) | Sí | Sugerencias opcionales para frontend |
| **relations** | Plugin | Sí (activar/desactivar por configuración) | Metadatos de relaciones |

**Regla fundamental:** cada entidad define su identidad técnica en `identities` y sus campos obligatorios de negocio en `fields` (`required: true`).

### Flujo de configuración de una entidad

```
Plugin define contrato schema.json        Admin configura la entidad
(identities, fields, custom_fields,       (schema vivo en entity_metadata)
 relations)                                        │
         │                                   Mantiene obligatorios del dominio
 identities fijas                  ──→      Selecciona sugerencias opcionales
 fields requeridos                 ──→      Añade campos manuales
 relations opcionales              ──→      Define comportamiento final en runtime
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
        "nombre": {
            "type": "string",
            "required": true,
            "label": "Nombre"
        },
        "apellidos": {
            "type": "string",
            "required": true,
            "label": "Apellidos"
        }
    },
    "custom_fields": [
        {
            "key": "telefono",
            "type": "string",
            "required": false,
            "label": "Teléfono"
        }
    ],
    "relations": []
}
```

### Estructura de schema vivo en entity_metadata (tras configuración del admin)

El schema vivo refleja la configuración final del admin para validación y persistencia de negocio.
Las identidades técnicas se mantienen como contrato de sistema y la CHECK actual sigue validando `fields`.

```json
{
    "fields": {
        "nombre": {
            "type": "string",
            "required": true,
            "label": "Nombre"
        },
        "apellidos": {
            "type": "string",
            "required": true,
            "label": "Apellidos"
        },
        "telefono": {
            "type": "string",
            "required": false,
            "label": "Teléfono"
        }
    }
}
```

### Plantillas de campos (futuro)

`custom_fields` podrá versionarse en plantillas de negocio por sector (`retail`, `b2b`, etc.)
sin romper el contrato base del plugin.

### Tipos de campo soportados (MVP)
- `string`, `email`, `phone`
- `number`, `integer`
- `boolean`
- `date`, `datetime`
- `select` (con array de opciones)

### Futuro
- `array`, `object` (para extensiones complejas)
- Plantillas de campos múltiples por plugin

### Implicaciones
- `schema.json` del plugin define el contrato inicial de entidad y configuración.
- El schema que usa `ValidationService` siempre viene de `entity_metadata` (schema vivo).
- `entity_metadata.schema_json` CHECK constraint solo valida `fields` → retrocompatible.
- El panel de administración debe combinar `fields` obligatorios + `custom_fields` opcionales.
- Las relaciones se configuran por metadatos en `relations`, no por definición duplicada de campo.

### Cambio futuro
Migración a JSON Schema es viable sin romper (1 semana de refactor puro).

---

## Matriz de decisiones

| Componente | Decision | MVP Ready | Risk Level |
|-----------|----------|-----------|-----------|
| Backend | PHP nativo | ✅ Si | 🟡 Medio |
| DI Container | Casero | ✅ Si | 🟡 Medio |
| Frontend | Vanilla JS | ✅ Si | 🔴 Alto |
| Autenticación | JWT | ✅ Si | 🟢 Bajo |
| Schema | Custom | ✅ Si | 🟢 Bajo |
| Relaciones | FK en JSONB | ✅ Si | 🟡 Medio |

---

## DECISION 6: Relaciones entre entidades — Metadatos en `relations`, opcionales y tipadas por destino

**Seleccionado:** Relación declarada en `relations`; la clave vive en `entity_data.content`  
**Alternativas consideradas:** Tabla `entity_relations` separada, usar FK real de PostgreSQL  
**Fecha:** Mayo 1, 2026  
**Actualizado:** Mayo 2, 2026 — relación opcional sin `custom_field` de FK obligatoria

### Justificacion
- Las entidades son dinámicas: no se puede crear una FK real de PostgreSQL en tiempo de ejecución sin DDL dinámico (peligroso y complejo).
- La relación se declara en `relations` con `key`, `target_entity` y `target_field`.
- El tipo/semántica de la referencia se infiere de la entidad destino y su campo objetivo (`target_field`).
- Retrocompatible: no requiere nueva tabla ni migración.

### Cómo encajan las relaciones con el modelo de campos

La FK no requiere definirse como `custom_field` separada. El contrato vive en `relations`.
Cada relación puede ser opcional (`required: false`).

Ejemplo de negocio: un pedido puede tener cliente enlazado o ser anónimo en caja.
Si la clave de relación no viene informada, el registro sigue siendo válido.

### Contrato de una relación (en schema.json del plugin)
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

### Tipos de relación soportados (MVP)
| Tipo | Semántica | FK vive en |
|------|-----------|-----------|
| `belongs_to` | Este registro apunta a otro (N:1) | `content` del registro actual |
| `has_many` | Otros registros apuntan a este (1:N) | `content` de los otros registros |
| `has_one` | Un único otro registro apunta a este (1:1) | `content` del otro registro |

### Cómo se resuelve una relación
Para `belongs_to`: el valor de `key` (ej. `id_cliente`) en `content` apunta al registro destino. Para resolver:
```sql
SELECT content FROM entity_data
WHERE entity_slug = 'clients'
  AND id = :id_cliente_value
  AND deleted_at IS NULL
```
No hay JOIN automático — la resolución es explícita, bajo demanda (lazy).

### Implicaciones
- `ValidationService` no valida existencia del registro relacionado — eso es responsabilidad del Hook `beforeSave` del plugin.
- No hay integridad referencial en BD — es responsabilidad de la capa de aplicación / hooks.
- Si una relación opcional no trae valor, se procesa como relación ausente (caso válido).

### Riesgos
- Sin FK real → posibles registros huérfanos si se elimina el registro referenciado.
- **Mitigación:** Hook `beforeDelete` en el plugin que tenga `has_many` puede bloquear el borrado si existen registros dependientes.

### Futuro
Si la complejidad de relaciones crece, se puede añadir una tabla `entity_relations` materializada para joins rápidos sin romper el contrato de schema (cambio aditivo).

## Proximo paso

Ver [historial-decisiones.md](historial-decisiones.md) para contexto completo de opciones consideradas.

Ver [consideraciones-iniciales.md](consideraciones-iniciales.md) para guía de implementación rápida.

---

## DECISION 6: Catalogo de entidades — `plugins` como unica fuente de verdad

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

- **Release A** (`009_unify_entities_into_plugins.sql`): Añade columna `name` a `plugins`, backfill desde `system_entities`, crea indice `idx_plugins_type_status`. Codigo actualizado para leer de `plugins` sin romper compatibilidad.
- **Release B** (`010_drop_system_entities.sql`): `DROP TABLE IF EXISTS system_entities`. Codigo y tests finalmente limpios.

### Implicaciones

- `PluginLoader::registerPlugin()` persiste `name` desde el manifest.
- `EntitySeeder` hace UPSERT solo en `plugins`.
- `Installer.php` de cada plugin de entidad escribe solo en `plugins`.
- `SystemEntity.php` consulta `plugins WHERE plugin_type='entity'` (sin cambio de interfaz publica).
- `EntityController::listEntities()` consulta `plugins` directamente.

### Invariante arquitectonico

> Todo tipo de entidad es un plugin. Todo plugin de tipo `entity` es una entidad.
> No existen entidades fuera de `plugins`.
