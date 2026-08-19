# Fundamentos de diseño (concepto Ant Design)

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
- **Build de Tailwind:** el comando de generación se ejecuta desde la raiz del repo, por lo que los globs de contenido deben apuntar a `./frontend/src/**` y `./plugins/**` para evitar una salida vacía o incompleta.

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

## Mapeo inicial a componentes

Base para la librería de componentes, con equivalencias conceptuales:

- `Button`: botón semántico (primario/secundario/peligro) implementado con utilidades Tailwind y atributos `data-role` cuando se necesita anclaje estable.
- `Typography`: títulos/subtítulos con jerarquía estable.
- `Layout`: shell principal con navbar + área de contenido.
- `Form` / `Input` / `Select`: estilos unificados para formularios dinámicos.
- `Table`: tabla base con cabeceras, densidad y acciones.
- `Tabs`: estructura para pestañas core + tabs de plugins.
- `Modal` / `Alert`: feedback y confirmaciones consistentes.

## Reglas de implementación vigentes

- Los componentes nuevos deben usar clases Tailwind como capa principal.
- Los anclajes de comportamiento y tests deben usar `data-role`/`data-*`; evitar prefijos de clase acoplados a implementación.
- `frontend/src/css/main.css` queda restringido a **overrides mínimos** justificados.
- Los botones de accion de tablas pueden ajustar el tamaño del icono inline cuando el foco es la legibilidad del glyph; no se deben crear clases CSS paralelas para ese caso.
- Los iconos se implementan siempre con Font Awesome (`fa-solid`, `aria-hidden="true"`), cargado vía CDN en `index.html`; no se mezclan SVG inline ni glifos Unicode como alternativa.
- Excepción vigente a "Tailwind como capa principal": `frontend/src/css/brand.css` (Logo/BrandLogo) es CSS a mano, sin utilidades Tailwind, por requerir un layout de marca (chevrons superpuestos, tipografía a medida) que las utilidades no expresan con la misma fidelidad. Sigue reaccionando a los tokens de tema (`--x-brand-*`) igual que el resto de componentes.
