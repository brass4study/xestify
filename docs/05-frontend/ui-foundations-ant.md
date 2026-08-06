# STORY 9.1 - Fundamentos de diseño (concepto Ant Design)

## Objetivo

Definir una base visual enterprise, consistente y reutilizable para la SPA de Xestify,
inspirada en el sistema de diseño de Ant Design (valores, patrones y taxonomía de componentes),
sin depender de React ni build step.

## Decisiones aplicadas

- **Base de estilos:** Tailwind CSS generado a CSS estático local y cargado desde `frontend/src/css/tailwind.generated.css`.
- **Sin build step:** el frontend sigue siendo Vanilla JS + HTML servido por Apache/PHP.
- **Tipografía base:** IBM Plex Sans (UI) + IBM Plex Mono (datos técnicos).
- **Fuente de componentes objetivo:** catálogo conceptual de Ant Design (General, Layout,
  Navigation, Data Entry, Data Display, Feedback).

## Valores de diseño adoptados

Tomamos como guía los valores de Ant Design para producto enterprise:

- **Natural:** formularios y navegación con flujo predecible.
- **Certain:** feedback de estado explícito (loading, éxito, error, vacío).
- **Meaningful:** jerarquía visual clara entre navegación, contenido y acciones.
- **Growing:** estructura preparada para extenderse por plugins sin romper el shell.

## Tokens base del sistema UI

Definidos en `frontend/tailwind.config.cjs`:

- `colors.brand.*`: paleta principal para acciones primarias y estado activo.
- `colors.slateui.950`: tono de texto de alta jerarquía.
- `fontFamily.sans` y `fontFamily.mono`: familias tipográficas corporativas.
- `boxShadow.panel` y `boxShadow.float`: elevaciones para cards y overlays.

## Mapeo inicial a componentes del MVP

La Story 9.1 establece las bases para la librería de 9.3 con equivalencias conceptuales:

- `Button`: botón semántico (primario/secundario/peligro) implementado con utilidades Tailwind y atributos `data-role` cuando se necesita anclaje estable.
- `Typography`: títulos/subtítulos con jerarquía estable.
- `Layout`: shell principal con navbar sticky + área de contenido.
- `Form` / `Input` / `Select`: estilos unificados para formularios dinámicos.
- `Table`: tabla base con cabeceras, densidad y acciones.
- `Tabs`: estructura para pestañas core + tabs de plugins.
- `Modal` / `Alert`: feedback y confirmaciones consistentes.

## Reglas de implementación vigentes

- Los componentes nuevos deben usar clases Tailwind como capa principal.
- Los anclajes de comportamiento y tests deben usar `data-role`/`data-*`; evitar prefijos de clase acoplados a implementación.
- `frontend/src/css/main.css` queda restringido a **overrides mínimos** justificados.

## Siguientes pasos (EPIC 9)

- STORY 9.2: anatomía de páginas y navegación SPA.
- STORY 9.3: formalizar API de componentes base reutilizables.
- STORY 9.4+: modularización, shell completo, resiliencia y UX transversal.
