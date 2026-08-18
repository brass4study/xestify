# Decisiones Tecnicas - MVP Xestify

## Resumen ejecutivo


---

### Nota histórica sobre el catálogo de entidades

A partir de la migración 009, el catálogo funcional de entidades se consolidó exclusivamente sobre la tabla `plugins` (`plugin_type = 'entity'`). Se eliminó la dependencia de la tabla legacy `system_entities` en código y tests, y todos los procesos de alta, consulta y validación de entidades pasan por el registro de plugins.

Esta decisión implicó:
- Refactor de controladores, seeders y modelos para operar solo sobre `plugins`.
- Limpieza de código y tests para eliminar referencias a `system_entities`.
- Validación de idempotencia y migración de datos.

El cambio es definitivo y no reversible: el catálogo de entidades siempre se obtiene de los plugins activos.

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

## DECISION 7: Sistema UI base - concepto Ant Design con Tailwind CSS

**Seleccionado:** Tailwind CSS vía CDN Play + implementación propia en Vanilla JS
**Alternativas consideradas:** CSS propio completo, Ant Design React (descartado por stack), Bootstrap
**Fecha:** Agosto 6, 2026

> **Estado:** decisión de integración inicial supersedida durante STORY 9.1. El
> runtime actual usa CSS Tailwind generado localmente y no depende del Play CDN;
> ver la decisión "CSS — Tailwind CSS como framework de estilos" más adelante.

### Justificación

- Se necesita una base visual enterprise y coherente para la EPIC 9.
- El proyecto debe mantenerse sin build step y sin migrar a React.
- Tailwind permite acelerar la construcción de componentes manteniendo control total del DOM generado por nuestras clases JS.
- El sistema de diseño se alinea conceptualmente con Ant Design (valores, patrones, categorías de componentes) sin acoplarse a su implementación React.

### Implicaciones

- `frontend/src/index.html` define `tailwind.config` y tokens base (tipografía, color, sombras).
- `frontend/src/css/main.css` queda reducido a overrides mínimos de compatibilidad.
- Los anclajes semánticos y de test pasan a `data-role`/`data-*`, y el estilo principal pasa a utilidades Tailwind.
- Los componentes nuevos de frontend deben nacer ya sobre esta base.

### Riesgos mitigados

- Evitar divergencia visual entre módulos/páginas.
- Evitar deuda de CSS ad hoc al escalar shell, rutas y librería de componentes.
- Mantener compatibilidad con plugins de UI existentes.

### Cambio futuro

Si en producción se requiere pipeline optimizado (purge/minificado), se podrá migrar de CDN Play a Tailwind CLI standalone sin alterar la arquitectura Vanilla ni los contratos de componentes.

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

> Nota (implementación real): no se construyó blacklist ni un refresh_token separado.
> `JwtService` emite un único `access_token` con TTL vía `JWT_EXPIRY` (por defecto 1h),
> pero la renovación automática sí se implementó como sesión deslizante: `AuthMiddleware`
> reemite el token en la cabecera `X-Refreshed-Token` en cualquier request autenticado
> cuando le queda menos de la mitad de su TTL, y `ApiClientModel.js` lo aplica de forma
> transparente. Un usuario activo nunca ve expirar la sesión; uno inactivo caduca entre
> `JWT_EXPIRY` y `1.5 × JWT_EXPIRY` tras su última actividad y debe volver a hacer login.
> Alcance reducido razonable para el MVP/TFM (sin token de revocación independiente),
> documentado aquí para no inducir a error al contrastar esta decisión con el código.

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
**Actualizado:** Agosto 16, 2026 (STORY 10.3 §2bis) — ver DECISION 10 más abajo:
`plugins.name`/`plugin_type`/`version`/`description` (identidad y metadata del
plugin, no del schema funcional en sí) se consolidaron en `manifest_json`;
`relations` (columna 4 de la tabla de este bloque) pasó de metadato declarado
pero no funcional a bloque validado y editable de verdad desde `PluginConfig`.

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
(identities, fields, custom_fields,       (schema vivo en plugins.schema_json)
 relations)                                        │
         │                                   Mantiene obligatorios del dominio
 identities fijas                  ──→      Selecciona sugerencias opcionales
 fields requeridos                 ──→      Añade campos manuales
 relations opcionales              ──→      Define comportamiento final en runtime
```

### Estructura de schema.json del plugin (contrato)

```json
{
    "entity": "persons",
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
            "label": "Teléfono"
        }
    ],
    "relations": []
}
```

### Estructura de schema vivo en `plugins.schema_json` (tras configuración del admin)

El schema vivo refleja la configuración final del admin para validación y persistencia de negocio.
Las identidades técnicas se mantienen como contrato de sistema y la CHECK actual sigue validando `fields`.

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
- En el repo actual, el schema vivo que usa `ValidationService` viene de `plugins.schema_json`.
- `entity_metadata` queda como referencia historica del diseño anterior; el catalogo runtime es `plugins`.
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

### Tipos de relación soportados (MVP)
| Tipo | Semántica | FK vive en |
|------|-----------|-----------|
| `belongs_to` | Este registro apunta a otro (N:1) | `content` del registro actual |
| `has_many` | Otros registros apuntan a este (1:N) | `content` de los otros registros |
| `has_one` | Un único otro registro apunta a este (1:1) | `content` del otro registro |

### Cómo se resuelve una relación
Para `belongs_to`: el valor de `key` (ej. `id_person`) en `content` apunta al registro destino. Para resolver:
```sql
SELECT content FROM entity_data
WHERE entity_slug = 'persons'
  AND id = :id_person_value
  AND deleted_at IS NULL
```
No hay JOIN automático — la resolución es explícita, bajo demanda (lazy).

### Implicaciones
- `ValidationService` no valida existencia del registro relacionado — eso es responsabilidad del Hook `beforeSave` del plugin.
- No hay integridad referencial en BD — es responsabilidad de la capa de aplicación / hooks.
- Si una relación opcional no trae valor, se procesa como relación ausente (caso válido).

### Riesgos
- Sin FK real → posibles registros huérfanos si se elimina el registro referenciado.
- **Mitigado:** `EntityService::deleteRecord()` bloquea el borrado (`HookException`, HTTP 422)
  cuando otra entidad tiene registros que apuntan al registro vía `schema.relations[]`
  (guard núcleo con `ReverseRelationTabResolver`, no un hook de plugin — ver
  `docs/01-architecture/hooks.md`). Además, borrar un registro limpia físicamente todo
  `plugin_extension_data` que apuntara a él (comentarios, etc.), evitando huérfanos ahí
  también.

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
- En esa fase se usó `EntitySeeder` solo como apoyo transitorio sobre `plugins`; el producto final no depende de seeders de entidad.
- `Installer.php` de cada plugin de entidad escribe solo en `plugins`.
- `SystemEntity.php` consulta `plugins WHERE plugin_type='entity'` (sin cambio de interfaz publica).
- `EntityController::listEntities()` consulta `plugins` directamente.

### Invariante arquitectonico

> Todo tipo de entidad es un plugin. Todo plugin de tipo `entity` es una entidad.
> No existen entidades fuera de `plugins`.

---

## DECISION 7: CSS — Tailwind CSS como framework de estilos

**Seleccionado:** Tailwind CSS (sustituye CSS propio actual)
**Alternativas consideradas:** CSS propio con variables (situación actual), Bootstrap, Bulma
**Fecha:** Agosto 5, 2026

### Justificacion

- El CSS propio (`main.css`) crecio de forma ad hoc en cada story y acumula reglas inconsistentes.
- Tailwind proporciona un sistema de utilidades consistente sin necesidad de nombrar clases propias.
- Compatible con el stack sin bundler: la app carga una hoja CSS Tailwind generada localmente y el frontend sigue siendo Vanilla JS + HTML servido por Apache/PHP.
- La restriccion de no introducir un bundler se mantiene: Tailwind se genera offline y el runtime no depende del Play CDN.

### Estrategia de integracion

| Entorno | Mecanismo | Notas |
|---------|-----------|-------|
| Desarrollo local | CSS generado localmente (`frontend/src/css/tailwind.generated.css`) | Sin warning del navegador ni dependencia runtime del Play CDN |
| Produccion / CI | Tailwind CLI | Regenera la misma hoja desde `frontend/tailwind.config.cjs` y `frontend/src/css/tailwind.src.css` |

### Alcance del cambio

- `frontend/src/css/main.css` se elimina o se reduce a overrides absolutamente minimos (animaciones custom, scrollbars, etc.).
- Todos los componentes nuevos del EPIC 9 se construyen directamente con clases Tailwind.
- La migracion de componentes existentes (DynamicForm, DynamicTable, Modal, PluginManager) se realiza en STORY 9.3.

### Riesgos mitigados

- Lock-in a nombres de clase propios.
- Inconsistencia de spacing y colores entre componentes creados en stories distintas.

### Cambio futuro

Si se adopta un bundler en fases posteriores (EPIC A1+), la configuracion de Tailwind migra de CDN a plugin PostCSS sin cambios en markup.

---

## DECISION 8: Frontend Routing — Hash routing (`#/ruta`)

**Seleccionado:** Hash-based routing (`window.location.hash` + evento `hashchange`)
**Alternativa descartada:** HTML5 History API (`pushState` / `popState`)
**Fecha:** Agosto 5, 2026

### Justificacion

- Hash routing funciona sin configuracion especial en el servidor: Apache no necesita `FallbackResource` ni rewrite rules para deep links.
- Refresh del navegador nunca rompe la vista: el servidor siempre sirve `index.html` y el hash es resuelto por el cliente.
- Mas simple de implementar y depurar en el entorno Apache + PHP nativo del proyecto.
- Compatibilidad total con el servidor de desarrollo `php -S` sin router personalizado.

### Convencion de rutas

```
#/home                          Inicio
#/login                         Pantalla de autenticacion
#/profile                       Perfil del usuario autenticado
#/users                         Gestion administrativa de usuarios
#/users/:id                     Ficha/configuracion de usuario
#/entity/:slug                  Listado de registros de una entidad
#/entity/:slug/#new             Alta de registro
#/entity/:slug/:id              Detalle / edicion de registro
#/entity/:slug/:id/:tab         Tab de un registro (ej: #/.../:id/comentarios)
#/entity/:slug/:id/:tab/#new    Alta de item de plugin extension en un tab (STORY 10.5)
#/entity/:slug/:id/:tab/:itemId Ficha de item de plugin extension en un tab (STORY 10.5)
#/plugins                       PluginManager
#/plugins/:slug                 Configuracion de un plugin
```

**Nota sobre tabs:** se prefiere subruta (`#/.../comentarios`) sobre query param (`?tab=comentarios`) para mantener consistencia. Query param solo se usa si el tab requiere estado adicional en query string.

**Fuente de verdad actual:** `frontend/src/js/controllers/RouteMapController.js` centraliza el mapa de hashes y la traduccion entre URL visible e identificadores internos de pagina, con `frontend/src/js/controllers/PluginRouteController.js` para las rutas de configuracion de plugin.
**Contrato canónico:** el frontend solo acepta el mapa de rutas en inglés; los aliases legacy ya no forman parte del contrato ni de la implementación.

### Implicaciones

- El router cliente escucha `hashchange` y parsea `window.location.hash`.
- Navegacion programatica: `window.location.hash = '#/ruta'` o wrapper equivalente apoyado en `RouteMapController.js`.
- Back/forward del navegador funcionan de forma nativa al cambiar el hash.
- No hay necesidad de `<base href>` ni configuracion de servidor para deep links.
- El contrato de pagina separa URL, plantilla y copy: las vistas futuras deben poder resolver `template`, `titleKey` y `descriptionKey` sin hardcodear idioma en la estructura del componente.

### Cambio futuro

Si el proyecto requiere URLs limpias en produccion (EPIC 9+), la migracion a History API es un cambio contenido en el modulo router sin afectar paginas ni componentes.

---

## DECISION 9: Shell SPA persistente y layouts de pagina

**Seleccionado:** una única instancia de `ShellLayout` para páginas autenticadas,
con `PageLayout`, `ListLayout` y `FormLayout` como contratos de composición.
**Alternativas descartadas:** shell propio por página, búsqueda de zonas mediante
selectores globales y layouts monolíticos dentro de `AppController`.
**Fecha:** Agosto 10, 2026

### Justificacion

- La navegación, la cabecera y las zonas transversales deben persistir al cambiar
    de vista sin reconstruir estructuras paralelas.
- Las páginas necesitan publicar breadcrumbs, toolbars, tabs, notificaciones,
    contenido y acciones mediante un contrato estable y testeable.
- Los plugins deben disponer de puntos de extensión explícitos sin conocer ni
    recorrer el DOM global de la aplicación.
- Login no pertenece a la shell autenticada, pero debe reutilizar la misma base
    de layouts para mantener una única forma de componer páginas.

### Contrato

- `AppController` crea `ShellLayout` una vez al iniciar la sesión autenticada y
    pasa la instancia activa a cada página.
- `ShellLayout` solo posee zonas estructurales y no renderiza contenido de página.
- `PageLayout` compone cabecera, breadcrumbs, toolbar, tabs y contenido; sus
    especializaciones `ListLayout` y `FormLayout` resuelven listados y formularios.
- Login usa `PageLayout` standalone con template `login`, sin navbar autenticada.
- No se permiten shells por vista ni accesos paralelos a las zonas mediante
    `querySelector` global.

### Verificacion

- `FrontendArchitectureTest.html` valida el árbol de shell, los targets, los
    layouts especializados y la plantilla Login.
- El cierre de STORY 9.5 deja 17/17 runners HTML y 166/166 aserciones en verde.
- El contrato detallado vive en `docs/05-frontend/layouts-guide.md`.

---

## DECISION 10: Identidad de `plugins` — columna `manifest_json` viva en vez de columnas separadas

**Seleccionado:** consolidar `plugin_name`, `plugin_type`, `version`, `name` y
`description` de la tabla `plugins` en una única columna `manifest_json JSONB`
que refleja en vivo el `manifest.json` real del plugin en disco; eliminar
`schema_version` sin reemplazo (de `plugins` y de `plugin_update_history`).
**Alternativas descartadas:** mantener las columnas separadas ya existentes
(el diseño con el que arrancó STORY 10.3); introducir una columna `plugin_name`
adicional junto a las demás sin tocar el resto (diseño intermedio, descartado
por dejar la misma información duplicada en dos sitios — la columna y el propio
`manifest.json` en disco — con riesgo de divergencia entre ambas).
**Fecha:** Agosto 16, 2026 (STORY 10.3 §2bis)

### Contexto

STORY 10.3 partía de un diseño con columnas propias (`plugin_name` fijo,
`slug` editable, `name`/`description` editables). Al implementarlo se detectó
que `plugins` había acumulado columnas que en realidad son propiedades directas
del `manifest.json` del plugin (`plugin_name`, `plugin_type`, `version`, `name`)
más una columna (`schema_version`) que resultó ser residual: no la leía el
frontend en ningún sitio (confirmado por exploración exhaustiva de
`PluginConfig.js`, `PluginManager.js`, `AppController.js`, `EntityList.js`), y
su único rol real era un contador interno de auditoría sin consumidores.

### Modelo elegido

`manifest_json` es una columna **viva**, no una foto fija del install:
- `name`, `version`, `type`, `core_version`, `label_singular` siempre reflejan
  el `manifest.json` en disco — se refrescan en cada actualización explícita de
  plugin. No editables por el admin.
- `label`, `description`, `target_entity` (solo `extension`) son editables por
  el admin desde `PluginConfig`; una actualización de plugin los preserva
  (merge sobre el manifest nuevo, nunca overwrite).
- `plugins.name` (editable, ej. "Clientes") mapea a `manifest_json.label`, **no**
  a `manifest_json.name` — son valores distintos (`manifest_json.name` es la
  identidad técnica fija = carpeta = namespace PHP, ej. "persons"). Distinción
  cerrada explícitamente con el usuario tras una ambigüedad de redacción en el
  plan original que los igualaba por error.
- `schema_json` vuelve a ser puramente estructural (`identities`/`fields`/
  `custom_fields`/`relations`/`plugin_suggested_custom_fields`/`ui_field_order`),
  sin campos de identidad inyectados en el momento de codificar.

### Implicaciones

- Sin cambio en el contrato JSON público de la API: los controllers/servicios
  siguen devolviendo las mismas claves planas (`name`, `plugin_type`, `version`,
  `description`) que antes, solo que las computan leyendo `manifest_json` en vez
  de columnas — confirmado sin consumidores de `schema_version` en producción.
- `PluginRepository` se dividió en `PluginRepository` (lectura) +
  `PluginWriteRepository` (escritura) — límite de método de SonarQube alcanzado
  al añadir el helper de decodificación compartido.
- Simplificaciones que este refactor habilitó: `withLabelSingular()`/
  `withTargetEntity()` (inyección puntual en `schema_json` al codificar)
  desaparecieron, ya no hacen falta.

### Verificacion

- `php backend/tests/run.php all` en verde (60/60 archivos tras el cierre de
  STORY 10.3, incluidas las 4 ampliaciones de alcance posteriores §6-§9).
- Migración aplicada contra la BD de dev preservando los valores admin-editados
  reales (`label`/`description`) en vez de los valores por defecto del disco.
- Smoke test manual sobre un plugin real: activar/desactivar/actualizar/
  rollback, confirmando que `label`/`description` editados sobreviven a un
  `syncAll()`/`update()` posterior.

---

## DECISION 11: Instalación CLI, esquema base en `backend/database/schema/` y superficie web de `tools/`

**Seleccionado:** (a) los SQL de definición inicial (`001`-`006`) viven en
`backend/database/schema/` y los aplica `tools/setup/install.php` a través de
PDO (`SchemaInstaller`, transacción por fichero, sin dependencia de `psql`);
`backend/database/migrations/` queda vacía y reservada para migraciones
incrementales futuras, sin tabla de tracking hasta que exista la primera
migración real. (b) Todo `tools/**/*.php` es exclusivamente CLI: guard
`PHP_SAPI !== 'cli'` en `tools/setup/bootstrap.php` (requerido como primera
sentencia por cada script, vigilado por `ToolsCliGuardTest`), más `.htaccess`
`Require all denied` por directorio (`tools/`, `backend/`, `docs/`, `skills/`;
`backend/public/` re-permite; `plugins/` deniega `*.php|*.json`) y `[NC]` en las
reglas de bloqueo del `.htaccess` raíz. (c) El instalador crea un administrador
real (`AdminUserCreator`, `is_seed=false`); los usuarios seed de contraseña
conocida solo se crean con `APP_DEBUG=true` o `--with-seed-users`; ningún script
acepta secretos por flag (prompt o `XESTIFY_*`); `backend/.env` a `0640`.
`tools/dev/` (QA) no se distribuye en el ZIP de release.
**Alternativas descartadas:** dejar los SQL en `migrations/` (nombre engañoso:
no son incrementales); confiar solo en la regla del `.htaccess` raíz (dependía
de Apache + `AllowOverride All` + `mod_rewrite` y era case-sensitive, con bypass
real en Windows vía `/Tools/...`); sacar `tools/setup` del artefacto (complica la
instalación y no protege a quien clona el repo); instalador web con token de un
solo uso (justo la superficie que se quiere evitar); mover el `DocumentRoot` a una
subcarpeta `public/` (solución definitiva, cambio arquitectónico grande —
registrada como deuda, no implementada).
**Fecha:** Agosto 18, 2026 (fuera de story; discutido en 4 rondas con el usuario)

### Contexto

`INSTALL.md` describía una instalación manual (bucle `psql` sobre
`backend/database/migrations/*.sql`, seeds y sync a mano). Al diseñar el
instalador se comprobó que (1) los ficheros de `migrations/` eran en realidad
la definición idempotente del esquema completo; (2) los scripts de
`tools/setup/` viajan en el ZIP dentro del `DocumentRoot` y su única protección
era una `RewriteRule` case-sensitive del `.htaccess` raíz, con
`switch-demoinventory-version.php` (escribe en `plugins/`) ni siquiera pasando
por `bootstrap.php`; (3) con `APP_DEBUG=false` los usuarios seed no pueden
iniciar sesión (`AuthController`) y la aplicación no tiene ninguna vía de alta
de usuarios, así que una instalación de producción quedaba sin acceso.

### Verificacion

- Instalación limpia real no interactiva sobre BD/rol desechables
  (`--create-db`, `production` y `development`), segunda ejecución idempotente,
  `--seed-business-data` abortando en BD limpia como se documenta.
- Guard sin Apache (`php -S`, que ignora `.htaccess`) → 403 en todos los scripts
  de `tools/`; contra el vhost Apache real, 403 en rutas internas (incluidas
  variantes en mayúsculas) y 200 en `/health`, SPA, `plugin.js`, CSS/JS;
  `check-install.php` exit 0; suite E2E Playwright 21/21.
- `php backend/tests/run.php all` 74/74 (`SchemaIdempotenceTest`,
  `AdminUserCreatorTest`, `ToolsCliGuardTest` nuevos/renombrados).

### Deuda derivada

- `DocumentRoot` en subcarpeta pública (`public/`) con el resto del código
  fuera del árbol servible.
- Alta de usuarios desde la UI/API (backlog, STORY A1.8); mientras tanto
  `tools/setup/create-admin-user.php`.
- El mecanismo de migraciones incrementales (tracking, orden, rollback) se
  definirá con la primera migración real en `backend/database/migrations/`.

### Correcciones derivadas (hallazgos de la instalación limpia real)

- `InstalledPluginSchemaValidator::assertContainsCanonical()` (comprobación de
  "schema instalado corrupto" en cada `syncAll()`) exigía igualdad total con el
  disco, incluida una sección `identities` que los plugins `extension` nunca
  tienen y atributos/secciones que `PluginConfig` permite editar por diseño
  (`summaryView` de campos base, catálogo de `custom_fields` editable/borrable,
  `relations` por instalación). Resultado: toda extensión recién registrada y
  todo plugin configurado se reportaban `error` en cada sync. Regla vigente:
  solo `identities` y `fields` base son inmutables y se comparan (ignorando
  `summaryView`/`layer`/`origin`); una sección que el disco no declara no se
  exige; `custom_fields`/`relations` no se comparan. La deriva real (p. ej.
  `label` de un campo base cambiado en disco sin subir versión) se sigue
  reportando. Cobertura: 3 casos nuevos en `PluginSyncServiceTest.php`.
- `tools/setup/sync-plugins.php` imprimía `unknown` por plugin: no leía el
  formato por instancia que `syncAll()` devuelve desde STORY 10.3.
- Datos locales (operación puntual, no versionada): borrado de un usuario
  huérfano de tests (`admin-seedlist-…@xestify.test`) y realineación de 24
  `label` de campos base de `contact_lenses` con `schema.json` (deriva de disco
  sin subir versión). Tras ello, `sync-plugins.php` en la BD de desarrollo:
  14 instancias `unchanged`, 0 errores.
