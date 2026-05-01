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

## DECISION 5: Schema de Entidades — Plantilla del plugin + Schema vivo del admin

**Seleccionado:** Dos capas de schema: plantilla (plugin) + schema vivo (admin)  
**Alternativas consideradas:** Schema fijo por plugin, JSON Schema estándar  
**Fecha:** Mayo 1, 2026  
**Actualizado:** Mayo 2, 2026 — modelo de campos de identidad + campos sugeridos + campos custom

### Modelo conceptual

Una entidad tiene **tres tipos de campos**, con diferente origen y mutabilidad:

| Tipo | Origen | ¿Puede modificarlo el admin? | Marcador en schema |
|------|--------|-----------------------------|--------------------|
| **Identidad** | Sistema (auto) | No — nunca | `identity: true` |
| **Sugerido** | Plantilla del plugin | Sí — puede modificar o eliminar | `suggested: true` |
| **Personalizado** | Creado por el admin | Sí — control total | (sin marcador) |

**Regla fundamental:** el único campo obligatorio de cualquier entidad es su identificador (`id` UUID, generado automáticamente por el sistema). Todo lo demás es opcional o sugerido.

### Flujo de configuración de una entidad

```
Plugin define schema.json           Admin configura la entidad
(plantilla / sugerencia)            (schema vivo en entity_metadata)
        │                                      │
  fields con suggested:true   ──→   Admin acepta, modifica o elimina
  identity fields              ──→   Siempre presentes, no editables
                               ──→   Admin añade campos propios (custom)
                               ──→   Schema vivo guardado en entity_metadata
```

### Estructura de schema.json del plugin (plantilla)

```json
{
    "entity": "client",
    "version": "1.0.0",
    "fields": {
        "nombre": {
            "type": "string",
            "required": false,
            "label": "Nombre",
            "suggested": true
        },
        "email": {
            "type": "email",
            "required": false,
            "label": "Email",
            "suggested": true
        },
        "telefono": {
            "type": "string",
            "required": false,
            "label": "Teléfono",
            "suggested": true
        },
        "activo": {
            "type": "boolean",
            "required": false,
            "default": true,
            "label": "Activo",
            "suggested": true
        }
    },
    "relations": []
}
```

### Estructura de schema vivo en entity_metadata (tras configuración del admin)

El admin puede haber eliminado `telefono`, renombrado etiquetas, añadido un campo propio y marcado `email` como requerido:

```json
{
    "fields": {
        "nombre": {
            "type": "string",
            "required": true,
            "label": "Nombre del cliente"
        },
        "email": {
            "type": "email",
            "required": true,
            "label": "Email de contacto"
        },
        "activo": {
            "type": "boolean",
            "required": false,
            "default": true,
            "label": "Activo"
        },
        "notas_internas": {
            "type": "string",
            "required": false,
            "label": "Notas internas"
        }
    },
    "relations": []
}
```

Los marcadores `suggested` e `identity` **no se guardan** en el schema vivo — son solo información de la plantilla del plugin para guiar la UI del panel.

### Plantillas de campos (futuro)

En una versión posterior, al configurar una entidad el admin podrá elegir entre diferentes colecciones de campos sugeridos según el tipo de negocio:

```
Entidad "Cliente":
  → Plantilla: Servicios B2B  (empresa, CIF, contacto, sector)
  → Plantilla: Comercio       (nombre, email, teléfono, dirección)
  → Plantilla: Básico         (nombre, email)
  → Personalizado             (el admin parte desde cero)
```

Cada plantilla sería una variante del `schema.json` del plugin o un archivo adicional en el directorio del plugin (`templates/b2b.json`, `templates/retail.json`, etc.).

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
- `schema.json` del plugin es **solo referencia / sugerencia** — no se almacena directamente.
- El schema que usa `ValidationService` siempre viene de `entity_metadata` (schema vivo).
- `entity_metadata.schema_json` CHECK constraint solo valida `fields` → retrocompatible.
- El panel de administración necesita una pantalla de configuración de entidad que lea la plantilla del plugin y la combine con el schema vivo actual.

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

## DECISION 6: Relaciones entre entidades — FK en JSONB, sin tablas relacionales

**Seleccionado:** FK almacenada como campo en `entity_data.content` (JSONB), declarada en `relations` del schema  
**Alternativas consideradas:** Tabla `entity_relations` separada, usar FK real de PostgreSQL  
**Fecha:** Mayo 1, 2026  
**Actualizado:** Mayo 2, 2026 — ajuste: campos FK son sugeridos por el plugin, no fijos

### Justificacion
- Las entidades son dinámicas: no se puede crear una FK real de PostgreSQL en tiempo de ejecución sin DDL dinámico (peligroso y complejo).
- Almacenar la FK como un campo más en el JSONB `content` es coherente con el modelo existente.
- La relación se declara en `schema.json` del plugin → el sistema sabe cómo resolver la referencia, sin necesidad de join de BD.
- Retrocompatible: no requiere nueva tabla ni migración.

### Cómo encajan las relaciones con el modelo de campos

Un campo FK (por ejemplo `id_cliente`) es un campo más del schema. En la plantilla del plugin se declara como `suggested: true`, igual que cualquier otro campo. El admin puede:
- **Mantenerlo**: la relación funciona y el panel puede ofrecer un selector del registro relacionado.
- **Eliminarlo**: la relación declarada en `relations` queda inactiva (no se resuelve), sin error.

Los campos FK no son fijos del plugin — son sugerencias necesarias para que la relación funcione, pero el admin tiene la última palabra.

### Contrato de una relación (en schema.json del plugin)
```json
{
    "fields": {
        "id_cliente": {
            "type": "string",
            "required": false,
            "label": "Cliente",
            "suggested": true
        }
    },
    "relations": [
        {
            "name": "cliente",
            "type": "belongs_to",
            "target_entity": "client",
            "foreign_key": "id_cliente",
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
Para `belongs_to`: el campo `id_cliente` en `content` contiene el UUID del registro `client`. Para resolver:
```sql
SELECT content FROM entity_data
WHERE entity_slug = 'client'
  AND id = :id_cliente_value
  AND deleted_at IS NULL
```
No hay JOIN automático — la resolución es explícita, bajo demanda (lazy).

### Implicaciones
- `ValidationService` no valida existencia del registro relacionado — eso es responsabilidad del Hook `beforeSave` del plugin.
- No hay integridad referencial en BD — es responsabilidad de la capa de aplicación / hooks.
- Si el admin elimina el campo FK del schema vivo, la relación en `relations` se ignora silenciosamente.

### Riesgos
- Sin FK real → posibles registros huérfanos si se elimina el registro referenciado.
- **Mitigación:** Hook `beforeDelete` en el plugin que tenga `has_many` puede bloquear el borrado si existen registros dependientes.

### Futuro
Si la complejidad de relaciones crece, se puede añadir una tabla `entity_relations` materializada para joins rápidos sin romper el contrato de schema (cambio aditivo).

## Proximo paso

Ver [historial-decisiones.md](historial-decisiones.md) para contexto completo de opciones consideradas.

Ver [consideraciones-iniciales.md](consideraciones-iniciales.md) para guía de implementación rápida.
