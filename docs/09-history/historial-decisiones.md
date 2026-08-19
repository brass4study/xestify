# Historial de Decisiones - MVP Xestify

## Proposito

Documento que guarda el FULL CONTEXT de cada decisión técnica tomada, incluyendo:
- Opciones consideradas.
- Ventajas y desventajas de cada una.
- Preguntas exploratorias que llevaron a la decisión.
- Implicaciones comprendidas.

Sirve como referencia futura si en beta emerge necesidad de cambiar alguna decisión.

---

## DECISION 1: Framework Backend (PHP)

**Fecha resuelta:** Mayo 1, 2026

### Opciones consideradas

#### Opcion A: Laravel
**Pros:**
- ORM robusto (Eloquent) para queries dinámicas.
- Migraciones y seeders bien documentados.
- Broadcasting/eventos para hooks fácil.
- Comunidad grande, muchos packages.
- Productividad alta.

**Contras:**
- Overhead innecesario para CRUD ultra-dinámico (metadata-driven).
- Puede ser overkill para un Core minimalista.
- Más recursos en RPi5 (aunque sostenible).

#### Opcion B: Symfony
**Pros:**
- Arquitectura muy limpia y desacoplada.
- DependencyInjection container potente para plugins.
- Eventos nativos para hooks.
- Muy testeable.
- Escalable a complejidad.

**Contras:**
- Curva de aprendizaje más pronunciada.
- Boilerplate inicial mayor.
- Puede ser pesado para MVP rápido.

#### Opcion C: PHP nativo
**Pros:**
- Mínimo footprint, ideal RPi5.
- Máximo control sobre lógica.
- Fácil inyectar patrones custom.

**Contras:**
- Más código que escribir.
- Menos convenciones = más decisiones.
- Comunidad fragmentada para problemas.

### Seleccion: PHP nativo

**Razon principal:** Máximo control, cero overhead, ideal para MVP transparente.

**Criterio de cambio futuro:** Si en beta emerge complejidad no prevista (ej. transacciones distribuidas, ORMs avanzados), migración a Laravel es viable.

---

## DECISION 2: Inyección de Dependencias

**Fecha resuelta:** Mayo 1, 2026

### Contexto
Una vez seleccionado PHP nativo, necesitábamos gestionar dependencias y plugins dinámicamente.

### Opciones consideradas

#### Opcion A: Pimple
**Pros:**
- Minimalista, 1 archivo.
- Fácil de entender.
- Perfecto para plugins dinámicos.

**Contras:**
- Funciona pero poco más.

#### Opcion B: PHP-DI
**Pros:**
- Autowiring automático.
- Atributos para inyección.
- Mejor para escala sin perder simpleza.

**Contras:**
- Abstracción intermedia (no es minimalista, no es custom).

#### Opcion C: Contenedor casero
**Pros:**
- Controlamos exactamente cómo se inyectan plugins en runtime.
- Podemos customizar exactamente cómo los plugins se inyectan.
- Cero magia, máximo control.
- Ideal para debugging.

**Contras:**
- Más esfuerzo inicial (~200-300 líneas).

### Seleccion: Contenedor casero

**Razon principal:** Máximo control para inyectar plugins en runtime, cero overhead.

**Criterio de cambio futuro:** Si autowiring emerge como pain point, migración a PHP-DI es directa (compatible).

---

## DECISION 3: Framework Frontend

**Fecha resuelta:** Mayo 1, 2026

### Contexto
Necesitábamos renderizar formularios, tablas y tabs dinámicos basados en metadata sin hardcodear UI.

### Pregunta exploratoria
**¿Vanilla significa puro (cero dependencias) o vanilla + librerías mínimas (Alpine, htmx)?**

Respuesta: Se pidió aclaración porque dos enfoques muy distintos:

**Vanilla PURO:** Todo manualmente, DOM manipulation puro.
- Más líneas de código.
- Máxima transparencia.
- Ideal para entender cada píxel.

**Vanilla + Alpine/htmx:** Reactivity minima + helpers.
- Menos código.
- Aún sin build step.
- Balance práctico.

### Opciones consideradas

#### Opcion A: Vue 3 Composition API
**Pros:**
- Componentes reactivos.
- Excelente para formularios dinámicos.
- Carga `v-for` genera componentes en runtime.
- Comunidad hispanohablante fuerte.

**Contras:**
- Requiere build step (Vite, Webpack).
- Transpilación para compatibilidad.

#### Opcion B: React + JSX
**Pros:**
- Ecosistema amplio.
- Virtual DOM eficiente.
- Comunidad global.

**Contras:**
- Más verbose para dinámicos.
- Más dependencias.

#### Opcion C: Vanilla PURO
**Pros:**
- Cero dependencias externas = máxima transparencia.
- Cada componente es clase reutilizable.
- Debugging trivial.
- RPi5 respira.

**Contras:**
- Más líneas de código.
- Menos convenciones.
- Componentización manual.

#### Opcion D: Vanilla + Alpine + htmx
**Pros:**
- Sin build step.
- Reactivity declarativa minima.
- Footprint ultra bajo.

**Contras:**
- Ainda "otra" convención.

### Seleccion: Vanilla PURO

**Razon principal:** Máximo control, transparencia total, ideal para componentes ultra-dinámicos.

**Criterio de cambio futuro:** Si UX crece explosivamente (>50 vistas), transición a Vue 3 es factible sin reescribir lógica.

---

## DECISION 4: Autenticación

**Fecha resuelta:** Mayo 1, 2026

### Contexto
Necesitábamos autenticar usuarios en RPi5 local, con posible comunicación futura con marketplace remoto.

### Opciones consideradas

#### Opcion A: JWT
**Pros:**
- Stateless en servidor.
- Token enviado en cada request.
- Funciona bien con CORS.
- Compatible con múltiples clientes.

**Contras:**
- Revocación = problema (necesita blacklist).
- Poco más overhead en validación.

**Caso de uso:** RPi5 comunica con múltiples clientes o externa.

#### Opcion B: Sesion local (Session + Cookie HTTP-only)
**Pros:**
- Revocación inmediata.
- Más simple para entorno LAN.
- Seguridad contra XSS mejor (cookie HTTP-only).
- Almacenamiento en sesión del servidor.

**Contras:**
- Requiere estado en servidor.
- CORS puede complicarse.

**Caso de uso:** Red local controlada, única RPi5 por negocio.

### Seleccion: JWT

**Razon principal:** Escalabilidad futura (marketplace remoto), stateless, compatible con múltiples clientes.

**Criterio de cambio futuro:** Transición a sesión local es reversible (~300 líneas en backend/frontend).

**Consideraciones de seguridad:**
- Almacenar en localStorage = riesgo XSS (mitigar con CSP headers).
- Blacklist para revocación.
- Access token: 1-2 horas.
- Refresh token: 7 días.

---

## DECISION 5: Schema de Entidades

**Fecha resuelta:** Mayo 1, 2026

### Contexto
Necesitábamos definir estructura de campos de entidades de forma declarativa y validar contra esa definición.

### Pregunta exploratoria
**¿Por qué se sugirieron Vanilla + Alpine/htmx en opción C de frontend?**

Respuesta: Se aclaró porque Vanilla PURO implica escribir validación UX manualmente, manejador de estado, componentes dinámicos sin helpers. Alpine/htmx son librerías (<30KB) que ayudan sin comprometer la filosofía "sin build".

### Opciones consideradas

#### Opcion A: JSON Schema estándar completo
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "properties": {...},
  "required": [...]
}
```

**Pros:**
- Estándar internacional.
- Muchas librerías validadoras.
- Extensible sin límite.

**Contras:**
- Complejo para MVP (80% de features no se usan).
- Documentación densa.
- Overkill para primera iteración.

#### Opcion B: Schema custom minimalista
```json
{
  "entity": "client",
  "version": "1.0.0",
  "fields": [
    {"name": "nombre", "type": "string", "required": true, "label": "..."}
  ]
}
```

**Pros:**
- Ultra simple, fácil parsear.
- Validador casero = ~100 líneas PHP.
- Controlamos exactamente qué soportamos.
- Documentación propia: 1 página.

**Contras:**
- No es estándar (riesgo futuro si integración externa).
- Si crece, reinventamos rueda.

#### Opcion C: Schema custom + soporte futuro a JSON Schema
- Comenzar con B.
- Cuando crezca, migrar a A sin romper.
- Transición gradual.

### Seleccion: Schema custom minimalista

**Razon principal:** MVP rápido, transparencia total, validador trivial.

**Tipos soportados (MVP):**
- string, email, phone
- number, integer
- boolean
- date, datetime
- select (con opciones)

**Tipos futuros:**
- array, object (para extensiones complejas)

**Criterio de cambio futuro:** Migración a JSON Schema es viable sin romper (1 semana de refactor puro).

---

## Matriz de riesgos por decision

| Decision | Risk Level | Mitigacion | Revertibilidad |
|----------|-----------|-----------|----------------|
| PHP nativo | 🟡 Medio | Testing riguroso, separación clear de layers | Alta (1-2 semanas) |
| Container casero | 🟡 Medio | Documentación del patrón, tests de DI | Alta (reescritura) |
| Vanilla PURO | 🔴 Alto | MVP en sprint 1, prototipo con usuarios | Media (2-3 semanas) |
| JWT | 🟢 Bajo | Blacklist, refresh token strategy | Alta (reversible) |
| Schema custom | 🟢 Bajo | Parser genérico, tests de schema | Alta (parser es puro) |

---

## Criterios globales para cambiar decision

Si en beta emerge necesidad de cambiar una decisión, evaluar:

1. **Impact:** ¿Cuántas líneas de código tocar?
2. **Timeline:** ¿Cuánto tiempo tomaría el cambio?
3. **Risk:** ¿Qué podría romper?
4. **Benefit:** ¿Qué se gana?

Cambio es viable si:
```
(Benefit >> Cost) AND (Timeline < 1 semana) AND (Risk < 20%)
```

---

## Proximo paso

Ver [decisiones-tecnicas.md](decisiones-tecnicas.md) para resumen ejecutivo.

Ver [consideraciones-iniciales.md](consideraciones-iniciales.md) para guía de implementación rápida.

---

## DECISION 6: Catalogo de entidades — unificacion en tabla `plugins`

**Fecha resuelta:** Mayo 2, 2026

### Contexto

Durante EPIC 6, al revisar el codigo, se detecto que `system_entities` y `plugins`
contenian exactamente la misma informacion para los plugins de tipo `entity`:


Toda insercion/actualizacion requeria escribir en ambas tablas. Todo acceso de lectura
requeria decidir cual de las dos era la "verdad".

### Opciones consideradas

---

**Rollout histórico:**
En la migración 009 se aplicó la consolidación del catálogo de entidades en la tabla `plugins` (`plugin_type = 'entity'`).
Se eliminó toda referencia a `system_entities` en código y tests, y se validó la idempotencia y la suite completa tras la limpieza. El cambio es definitivo y no reversible.

#### Opcion A: Mantener ambas tablas con sincronizacion automatica
**Pros:**
- Sin cambio de interfaz para consumidores actuales.
**Contras:**
- Duplicacion permanente de datos.
- Riesgo de desincronizacion.
- Dos puntos de escritura = dos fuentes de bugs.

#### Opcion B: Hacer `system_entities` la fuente de verdad, desacoplar de plugins
**Pros:**
- Mantiene separacion conceptual.
**Contras:**
- Aumenta la complejidad relacional.
- Crea dependencia ciclos: plugins dependen de system_entities y viceversa.

#### Opcion C: Usar `plugins` como unica fuente, eliminar `system_entities`
**Pros:**
- Cero duplicacion.
- Un solo punto de escritura (PluginLoader + Installer).
- Modelo mental claro: "toda entidad es un plugin de tipo entity".
- Menos tablas = menos migraciones = menos tests de esquema.
**Contras:**
- Requiere añadir columna `name` a `plugins` (migracion Release A).
- Requiere actualizar ~5 archivos PHP y ~4 test files.

### Seleccion: Opcion C

**Razon principal:** La duplicacion era un defecto de diseno, no una feature.
El invariante "toda entidad es un plugin" es mas limpio y facil de razonar.

**Plan de dos releases:**
- Release A (compatible): añadir `name` a `plugins`, actualizar codigo de escritura/lectura,
  mantener `system_entities` existente para no romper tests.
- Release B (limpieza): `DROP TABLE IF EXISTS system_entities`, actualizar tests finales.

**Resultado:** 7 archivos PHP modificados, 10 migraciones idempotentes, 0 tests en rojo.

---

## DECISION 7: Identidad de `plugins` — `manifest_json` viva en vez de columnas separadas

**Fecha resuelta:** Agosto 16, 2026 (STORY 10.3 §2bis)

### Contexto

STORY 10.3 arrancó con un diseño de columnas separadas: `plugin_name` (fijo,
nuevo) + `slug` (editable, ya existente) + `name`/`description` (editables,
`name` ya existente). Al implementarlo se detectó que esas columnas — junto con
`plugin_type` y `version`, ya existentes — eran en realidad datos que ya vivían
en el `manifest.json` real del plugin en disco, y que `schema_version` (también
ya existente en `plugins` y en `plugin_update_history`) era residual: ningún
sitio del frontend en producción la leía, y su único rol real era un contador
interno de auditoría sin consumidores.

### Opciones consideradas

#### Opcion A: Mantener las columnas separadas (diseño de arranque de la story)
**Pros:** cambio incremental, no toca lo ya construido en el resto de la story.
**Contras:** mantiene 6 columnas que duplican datos que ya existen en el
`manifest.json` en disco; cada actualización de plugin debe sincronizar
columnas y disco por separado; `schema_version` queda sin resolver como deuda
conocida en vez de eliminarse.

#### Opcion B: Añadir `manifest_json` solo para los campos nuevos, dejar el resto de columnas igual
**Pros:** cambio mínimo, menor superficie de riesgo inmediato.
**Contras:** dos fuentes de verdad coexistiendo a la vez (columnas propias Y
`manifest_json`), inconsistente y confuso para el resto del código, no resuelve
el problema de fondo — solo lo traslada.

#### Opcion C: Consolidar todo en `manifest_json` JSONB vivo, eliminar columnas y `schema_version`
**Pros:** una sola fuente de verdad; `manifest_json` refleja literalmente el
`manifest.json` real en disco; elimina `schema_version` sin reemplazo (sin
consumidores reales, confirmado por exploración); simplifica métodos existentes
(`withLabelSingular()`/`withTargetEntity()` dejan de hacer falta).
**Contras:** reescritura de ~15 archivos con SQL literal repetido sobre las
columnas viejas; requiere una fusión explícita cuidadosa al aplicar
actualizaciones de plugin, para no perder ediciones del admin (`label`/
`description`/`target_entity`) al refrescar los campos que sí deben reflejar
el disco.

### Seleccion: Opcion C

**Razon principal:** el mismo criterio que en DECISION 6 (unificación del
catálogo de entidades en `plugins`) — la duplicación de datos que ya existen en
disco dentro de columnas propias de BD era un defecto de diseño heredado del
arranque de la story, no una feature a preservar, y corregirlo de raíz salía
más barato que seguir arrastrándolo.

**Resultado:** ~15 archivos backend reescritos (repositorios, servicios de
ciclo de vida, controllers), `PluginRepository` dividido en `PluginRepository`
(lectura) + `PluginWriteRepository` (escritura) por límite de método de
SonarQube, 60/60 archivos de test en verde tras el cierre completo de
STORY 10.3 (incluidas las 4 ampliaciones de alcance §6-§9 decididas
posteriormente en la misma sesión), contrato JSON público de la API sin
cambios.

---

## DECISION 8: Prioridad de hooks de plugin — autoconsulta de `sort_order`, no tabla `plugin_hooks` dedicada

### Contexto

Para que un plugin `extension` pudiera hacer configurable su prioridad de
registro de hooks desde `PluginManager` (acciones Subir/Bajar), se evaluó
primero una tabla `plugin_hooks` dedicada (`slug`, `hook_name`, `priority`,
`enabled`) como registro explícito de prioridades.

### Opciones consideradas

#### Opcion A: Tabla `plugin_hooks` dedicada
**Contras:** `PluginHookRegistrar` nunca llegó a leerla en la implementación
real — quedó como infraestructura muerta, una tabla más que mantener y
migrar sin ningún consumidor.

#### Opcion B: Autoconsulta de `plugins.sort_order` vía el PDO opcional que `PluginClassLoader` ya inyecta por constructor
**Pros:** no añade tablas nuevas ni cambia el contrato de `Hooks::register()`;
reutiliza una columna que ya existe y ya se usa para el orden manual en
`PluginManager`.

### Seleccion: Opcion B

**Resultado:** `plugins/comments/Hooks.php` (`resolvePriority()`) es la
referencia de este patrón — usa el mínimo `sort_order` entre sus propias
instancias activas, ya que `plugin_name` no es único y una sola llamada a
`register()` cubre todas las instancias activas de ese plugin técnico. Ver
`docs/01-architecture/hooks.md` para el contrato vigente.
