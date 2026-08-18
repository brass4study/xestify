# Auditoría — Toolkit de componentes UI base y layouts

**Subsistema:** `views/components/` + `views/layout/`
**EPIC cubiertas:** EPIC 9 (base EPIC 5)
**Severidades:** 0 crítico · 4 mayor · 11 menor · 5 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de los 36 ficheros de `frontend/src/js/views/components/` y los 4 layouts de `frontend/src/js/views/layout/`, con verificación de usos cruzados (Grep de imports/consumos por todo `frontend/` y `plugins/`), contraste contra `docs/05-frontend/` y los runners del ámbito.

---

## Resumen

El toolkit está en un estado notablemente sano para ser vanilla JS: **no hay ningún vector XSS** (todo el render usa `textContent`/`createElement`, nunca `innerHTML` con datos), la sospecha de duplicación Modal/Tabs/Table entre `components/` y `modules/` **no es tal** (los de `modules/` son wrappers de comportamiento que consumen los de `components/` vía factory), y los layouts tienen tests de contrato estrictos. La deuda real se concentra en: la pseudo-herencia de `BaseComponent`, la falta total de ciclo de vida/`destroy()` en componentes con listeners globales (InputSelect), una semántica inconsistente de `setClassName()` que ya provoca pérdidas silenciosas de estilo en páginas reales, y una opción de tema (`fixedHeader`) implementada a medias que hoy es un no-op.

## Hallazgos por severidad

### MAYOR

**1. La herencia de HTMLElement es ficticia: cada `create()` copia todos los métodos ligados al elemento**
- `frontend/src/js/views/components/BaseComponent.js:224-253`
- Ninguna clase del toolkit se registra con `customElements.define`. `createComponentInstance()` crea un elemento plano y `bindPrototypeChain()` copia **cada método del prototipo como función ligada por instancia** (`target[name] = descriptor.value.bind(target)`). Consecuencias: (1) `instanceof ComponentClass` nunca funciona, así que el propio código recurre a duck-typing (`header.getTarget?.('content')` en PageLayout.js:261-264); (2) coste de memoria: `TableComponent._render()` crea cada `th`/`td` con `component.create` (Table.js:55, 107), y cada celda arrastra ~15 closures ligadas — en una tabla de 50×10 son miles de funciones; (3) `bindPrototypeChain` solo liga `descriptor.value`, por lo que un getter/setter definido en una subclase se descartaría en silencio (InputSelect e InputSwitch lo esquivan redefiniendo propiedades con `Object.defineProperties` en `initialize` — un workaround del workaround).
- Sugerencia: decidir el modelo de una vez: custom elements reales, o factorías/mixins explícitos que solo adjunten los métodos necesarios. Paso mínimo: dejar de usar `component.create` para primitivas de alto volumen (tr/th/td dentro de `TableComponent`) y documentar la restricción de getters/setters.

**2. Panel de InputSelect portalado a `<body>` y 5 listeners globales quedan huérfanos si el componente se desmonta con el dropdown abierto**
- `frontend/src/js/views/components/InputSelect.js:366-384`
- `_openDropdown()` mueve el panel a `document.body` con posición fixed (367-373) y registra `document.click`, `document.keydown`, `scroll` capturante, `resize` y `hashchange` (380-384). Solo `_closeDropdown()` lo revierte. Si el subárbol que contiene el select se reemplaza con el dropdown abierto (re-render de un panel tras un refresh de datos — nada de eso dispara `hashchange` ni click), el panel queda flotando a coordenadas fijas sobre la nueva UI y los listeners globales siguen vivos hasta el siguiente click/Escape/scroll. No existe `destroy()` ni `disconnectedCallback` en todo el toolkit.
- Sugerencia: en los handlers globales, comprobar `this.isConnected` y auto-cerrar/limpiar si el select ya no está en el DOM; idealmente exponer `destroy()` y llamarlo desde los flujos que reconstruyen paneles.

**3. La preferencia `fixedHeader` es un no-op: `top-0` sin `sticky`/`fixed`**
- `frontend/src/js/views/layout/ShellLayout.js:447,460`
- `#menuClassName()` y `#mixedBarClassName()` hacen `settings.fixedHeader ? 'top-0' : 'relative'`. `top-0` sin esquema de posicionamiento no tiene efecto: el header nunca queda fijo en modos top/mixed. Tampoco hay CSS que enganche `data-ui-fixed-header` (verificado: cero reglas). Simétricamente, `fixedSidebar` (dataset en 148) tampoco hace nada: el menú lateral lleva `sticky top-0` incondicional (434). `ThemeModel.js:171` define `fixedHeader: true` por defecto y lo normaliza/persiste — una preferencia WYSIWYG que viaja hasta el DOM y se queda en atributo decorativo. Refactor incompleto: la rama condicional demuestra la intención. (Atenúa: `ThemeSettingsPanel` no expone toggles para estas dos claves; la futura STORY A1.3 planifica auditar exactamente esto.)
- Sugerencia: añadir `sticky` junto a `top-0` cuando `fixedHeader` sea true (y condicionar el `sticky` del sidebar a `fixedSidebar`), o retirar ambas claves de `ThemeModel` hasta implementarlas.

**4. Semántica inconsistente de `setClassName()`: machaca el estilo propio del componente y ya causa bugs de consumo**
- `frontend/src/js/views/components/BaseComponent.js:20-23` (+ InputSelect.js:168-174, Typography.js, ListLayout.js:11-13, FormLayout.js:10-12)
- `BaseComponent.setClassName()` reemplaza `className` entero. `InputSelectComponent` lo redefine para proteger su estructura, pero Typography/Alert/Section no: el mismo método significa "reemplaza todo" o "cambia solo el aspecto" según el componente. La trampa ya muerde en producción: `PluginConfig.js:210-216` y 295-297 crean typography con `size:'sm', color:'slate-600'` y encadenan `.setClassName('mt-1')`, que borra `text-sm text-slate-600` en silencio (la convención de docs/05-frontend/README.md:59-60 dice usar `addClass()` — el caller la incumple porque nada lo impide). Y los layouts la explotan al revés: `ListLayout.js:11-13` y `FormLayout.js:10-12` crean un `SectionComponent` solo para aniquilar acto seguido su className y su `data-role` — querían la primitiva `'sectionTag'`.
- Sugerencia: unificar semántica: que los componentes con estilo propio protejan su base como InputSelect (o que `setClassName` sobre ellos avise), reservar el reemplazo total para primitivas, y cambiar los layouts a `sectionTag`.

### MENOR

**5. `setTitle()`/`setMessage()` de Alert destruyen la estructura y son funcionalmente idénticos**
- `frontend/src/js/views/components/Alert.js:62-70`
- Ambos hacen `this.setText(...)`, que sustituye **todo** el contenido del alert (icono, título, mensaje) por texto plano; tras llamar a cualquiera, el otro campo desaparece. Nadie los llama en `src/` (verificado) — API muerta y rota a la vez.
- Sugerencia: guardar referencias a los nodos de título/mensaje en `initialize()` y que cada setter actualice el suyo; o eliminar los tres mutadores (`setType` incluido, que también resetea `className`).

**6. Semántica ARIA de tabs rota: `tablist` en el `nav` pero los `tab` cuelgan de un `div` intermedio; faltan `aria-controls`/`aria-labelledby`**
- `frontend/src/js/views/components/Tabs.js:13-28`
- `role="tablist"` se pone en el `nav` (16), pero los botones `role="tab"` (85) viven dentro de `tabList`, un `div` sin rol (19-22), rompiendo la relación de propiedad ARIA. El `tabpanel` (24-28) no tiene `aria-labelledby` ni los botones `aria-controls`/`id`. El teclado (91-109) y `aria-selected`/`tabindex` sí están bien.
- Sugerencia: mover `role="tablist"` al contenedor directo de los botones, asignar `id` a cada botón, `aria-controls` hacia el panel y refrescar `aria-labelledby` en `setActiveTab`.

**7. `<a>` anidado dentro de `<button>` en items de breadcrumb con menú; el `href` queda muerto y el menú se cierra con `mouseleave`**
- `frontend/src/js/views/components/Breadcrumb.js:59-63,118,199-201`
- Cuando un item tiene `href` y `menu`, `createItemNode` crea un ancla (59-63) y `wrapWithDropdown` la mete dentro del botón trigger (118): HTML inválido y además el click hace `preventDefault()` (195) — ese enlace no navega nunca. El `mouseleave` (199-201) cierra el menú aunque se abriera por teclado, y `aria-expanded` solo existe tras el primer toggle (sin `aria-haspopup`).
- Sugerencia: item-con-menú como botón puro con la primera entrada del menú apuntando al `href`, `aria-haspopup="menu"`/`aria-expanded="false"` iniciales, y no cerrar por `mouseleave` cuando la apertura fue por click/teclado.

**8. Teclado incompleto en InputSelect y desincronización silenciosa con valores inexistentes**
- `frontend/src/js/views/components/InputSelect.js:421-477`
- (1) Sin type-ahead. (2) Tab con el dropdown abierto mueve el foco y deja el panel abierto (no hay `focusout`). (3) `setSelectedValue()` (209-222) con un valor que no existe deja el `<select>` nativo en `selectedIndex -1` (trigger en blanco) sin emitir evento: en relaciones hidratadas (`setOptions`, 252-280), si el id guardado ya no existe, la UI muestra vacío mientras el modelo del caller conserva el id obsoleto y lo re-guardaría en silencio.
- Sugerencia: type-ahead básico y cierre en `focusout`; en `setOptions`, si `desiredValue` no está entre las nuevas opciones, notificar (evento `change` o callback).

**9. Clase Tailwind construida dinámicamente (`text-${color}`) con CSS compilado y sin safelist**
- `frontend/src/js/views/components/Typography.js:31-36`
- `tailwind.config.cjs` no tiene `safelist`; con Tailwind compilado, `text-${color}` solo existe si el literal completo aparece en algún fichero escaneado. Hoy funciona de carambola para slate-600/700/900; cualquier color nuevo quedará sin estilo, sin error. Anti-patrón documentado por Tailwind.
- Sugerencia: mapa `COLOR_CLASSES` explícito, igual que `SIZE_CLASSES`/`WEIGHT_CLASSES`/`ALIGN_CLASSES` en el mismo fichero.

**10. Id fijo `xestify-modal-content` duplicado si conviven dos modales**
- `frontend/src/js/views/modules/Modal.js:46-49`
- El título hereda id único del componente base (`ui-modal-title-N`), pero el contenido recibe el literal `xestify-modal-content` en todas las instancias → ids DOM duplicados y `aria-describedby` ambiguo cuando conviven dos modales (ModalTest cubre unicidad de títulos, no de contenidos). Además, con dos modales abiertos, ambos escuchan Escape en `document` y se cierran a la vez.
- Sugerencia: generar el id de contenido con el mismo contador que el título; cerrar con Escape solo el modal superior.

**11. Código muerto verificado en `src/`: Spinner, Skeleton, InputRadio y varios mutadores**
- `frontend/src/js/views/components/Spinner.js`, `Skeleton.js`, `InputRadio.js` (+ `Alert.setType/setTitle/setMessage`, `Typography.setAlign`, `Table.addColumn/addRow`)
- `component.create('spinner'|'skeleton'|'inputRadio')` solo aparece en ComponentsTest.html; ningún flujo de producción los usa (grep completo de `frontend/src` y `plugins/`). El backlog lo sabe a medias: STORY A1.4 (backlog.md:1414) reconoce Skeleton "sin uso". Spinner además solapa con Loader (dos indicadores de carga; solo Loader vive, en Login.js:432). `Typography.setAlign` y `Table.addColumn/addRow` tampoco tienen callers.
- Sugerencia: mantener Skeleton (tiene story asignada), decidir Spinner vs Loader (uno sobra) y podar los mutadores sin uso o cubrirlos con tests si son API intencional.

**12. Dos mecanismos paralelos de breadcrumbs en PageHeader/PageLayout, uno muerto**
- `frontend/src/js/views/components/PageHeader.js:9-22` vs `frontend/src/js/views/layout/PageLayout.js:272-276`
- `PageHeaderComponent` soporta `options.breadcrumbs` (crea `data-role="page-header-breadcrumb"`, singular), pero su único caller de producción (`PageLayout.#mountHeader()`) no lo usa: crea su propia zona `page-header-breadcrumbs` (plural) con `prepend`. La rama del componente es código muerto y el naming singular/plural invita a confundir selectores.
- Sugerencia: retirar `options.breadcrumbs` de PageHeader (o delegar) y unificar el data-role.

**13. `loadOptions(...).then(...)` sin `.catch`: el select queda en "Cargando…" para siempre**
- `frontend/src/js/views/components/ExtensionLayerFields.js:127-133`
- En `buildRelationField`, si la promesa de opciones rechaza, no hay `.catch`: rechazo no manejado y el select permanece deshabilitado con "Cargando…" sin feedback. El propio ComponentsTest (147-154) demuestra que el contrato de error existe (`setOptions([], { disabled: true, placeholder: 'No se pudieron cargar las opciones' })`) — no se usa aquí.
- Sugerencia: `.catch(() => select.setOptions([], { disabled: true, placeholder: 'No se pudieron cargar las opciones' }))`.

**14. Clase `cursor-inherit` inexistente en el CSS compilado**
- `frontend/src/js/views/components/InputSwitch.js:34`
- `INPUT_CLASSES` incluye `cursor-inherit`, que no es utilidad Tailwind (cero apariciones en `tailwind.generated.css`). El checkbox invisible que cubre el control no hereda el cursor: sobre un switch deshabilitado el usuario no ve `not-allowed` aunque el root lo declare (31).
- Sugerencia: valor arbitrario `cursor-[inherit]` o alternar el cursor del input según estado.

**15. Deriva documentación↔código acumulada en docs/05-frontend y backlog**
- `docs/05-frontend/*` y `docs/11-backlog/backlog.md`
- (1) `layouts-guide.md:15-43`: el árbol del shell omite `shell-mixed-bar*`, `shell-menu-config-theme` y `shell-floating-ui` (creados en ShellLayout.js:40-59) y nombra `list-panel` cuando el código crea `list-section`/`list-content-host`. (2) `arquitectura.md:63-64` afirma "Ningún componente se instancia con `new` fuera de esta fábrica", pero `AxisGauge` se usa con `new AxisGauge(...)` desde ambos plugins (plugins/optometries/plugin.js:298) y no está en el factory; `ExtensionLayerFields.js` exporta funciones sueltas desde `components/`. (3) `ui-foundations-ant.md:54` prohíbe "SVG inline ni glifos Unicode", pero PasswordStrength usa `•`/`✓`/`✕` (122-124, 168) y AxisGauge es SVG inline íntegro (excepción razonable sin registrar). (4) `backlog.md:1022` referencia la ruta antigua `frontend/tests/ComponentsTest.html`; `backlog.md:5-7` dice "9.8 como siguiente foco" cuando 9.8 y 9.9 figuran ✅ más abajo.
- Sugerencia: pase de sincronización: árbol de layouts-guide, registrar las dos excepciones en ui-foundations-ant, refrescar cabecera y rutas del backlog.

### NIT

**16. Estilos inline que duplican clases Tailwind en `shell-floating-ui`**
- `frontend/src/js/views/layout/ShellLayout.js:40-47`
- Se asigna `className 'pointer-events-none flex justify-center px-4 pt-4'` e inmediatamente inline styles que repiten tres de esas propiedades y añaden `fixed/inset/z-index/isolation`, expresables como `fixed inset-0 z-[9999] isolate`. Similar DRY: `build()` (48-49, 54-55) hardcodea las clases de menú/mixed-bar que `#menuClassName`/`#mixedBarClassName` recalculan en `applyUiPreferences`.
- Sugerencia: clases Tailwind en el className y que `build()` delegue en los mismos métodos.

**17. `const showMenuConfig = hasMenuConfig;` — alias literal**
- `frontend/src/js/views/layout/ShellLayout.js:102`
- Alias sin transformación que viaja por `applyUiPreferences` y `#syncModeVisibility` como parámetro separado, sugiriendo una distinción inexistente.
- Sugerencia: eliminar el alias.

**18. Heurística de icono de Button rompe `fa-regular`**
- `frontend/src/js/views/components/Button.js:56-58`
- `icon.includes('fa-') && !icon.includes('fa-solid')` antepone `fa-solid` a cualquier icono sin él, incluido `'fa-regular fa-star'` → `fa-solid fa-regular fa-star`, dos estilos FA en conflicto.
- Sugerencia: comprobar contra la lista de prefijos de estilo (`fa-solid|fa-regular|fa-brands`) antes de anteponer.

**19. `addCell()` no añade nada y los `add*` de Table re-renderizan todo**
- `frontend/src/js/views/components/Table.js:32-35`
- `addCell` es nombre engañoso para "computar el valor de la celda"; `addColumn`/`addRow` llaman a `_render()` completo por elemento (O(n²) si alguien poblara en bucle).
- Sugerencia: renombrar a `resolveCellValue` y documentar que `addColumn/addRow` son para mutación puntual.

**20. Variables sin usar en EmptyState y mutación del input recibido en FormField**
- `frontend/src/js/views/components/EmptyState.js:9,16` y `FormField.js:11-14`
- En EmptyState, `const title` y `const description` se asignan y no se leen. En FormField, se muta el elemento del caller (`options.input.id = options.input.name`) como efecto colateral, y si el input no tiene ni id ni name pero se pasa `options.name`, el `for` del label (23-26) apunta a un id inexistente.
- Sugerencia: quitar las asignaciones muertas; generar el id solo si va a usarse y validar que el `for` referencie un id real.

## Cobertura de tests

Buena en el eje factory/layouts, floja en interacción fina. `ComponentsTest.html` (17 tests) cubre: despacho y catálogo del factory, primitivas de entrada (4 tests dedicados al ciclo `setOptions` de InputSelect — el punto más delicado y mejor cubierto), Breadcrumb con dropdown (apertura, click fuera, Escape), estado/loading de InputSwitch, render de feedback, PasswordStrength, zonas de PageHeader y dos tests de regresión anti-duplicación de classNames. `FrontendArchitectureTest.html` valida el árbol exacto del shell, preferencias UI (side/mixed), la sincronización fluent de PageLayout, ListLayout y FormLayout. `ModalTest.html` cubre show/close/setContent/backdrop/destroy/unicidad de ids de título, y `UiResilienceTest.html` añade focus trap y restauración de foco. `DynamicTabsTest.html` cubre indirectamente Tabs (teclado, ink bar, tablist externa). **Huecos:** cero cobertura de teclado del propio InputSelect (flechas, Enter, Escape, portal a `<body>` y su limpieza — justo el MAYOR 2); nada sobre `applyUiPreferences` con `fixedHeader`/`fixedSidebar` (un assert de posición habría delatado el no-op); AxisGauge y ExtensionLayerFields sin runner; sin tests de Loader, Logo/BrandLogo, mutadores de Typography ni de Alert (que están rotos); la unicidad de ids en ModalTest no cubre el contenido; ordenación/densidad de TableComponent solo indirecta desde DynamicTableTest.

## Observaciones transversales

1. **Sin ciclo de vida:** ningún componente del toolkit tiene `destroy()`/desconexión; los que registran listeners globales (InputSelect, Breadcrumb-dropdown) dependen de heurísticas para limpiarse. El único `destroy()` real vive en los wrappers de `modules/`. Cualquier componente futuro con estado global heredará el mismo agujero.
2. **Seguridad sana y consistente:** todo el ámbito construye DOM con `createElement`/`textContent`; ni un `innerHTML` con datos. Patrón a preservar explícitamente (merecería una regla escrita en docs/05-frontend).
3. **La supuesta duplicación components/ vs modules/ es en realidad una arquitectura en capas correcta** (presentación en `components/`, comportamiento en `modules/`: modules/Modal.js:17, DynamicTabs.js:92 y DynamicTable.js:133 consumen los componentes base). Vale la pena documentarla en arquitectura.md para que nadie la "arregle" fusionándolos.
4. **API heterogénea entre componentes:** nombres desalineados para el mismo concepto (`label` en Spinner vs `title/description` en Loader vs `title/message` en Alert; `checkedChildren/unCheckedChildren` solo en InputSwitch), Breadcrumb acepta array posicional u objeto, AxisGauge es clase `new` fuera del factory, y `dataRole`-opción vs `setData('role')` conviven. Una pasada de normalización (o una tabla de convenciones en docs) evitaría que cada componente nuevo elija dialecto propio.
5. **El patrón `Object.defineProperties` para `value`/`disabled`** se repite a mano en InputSelect e InputSwitch; si aparece un tercer control compuesto, conviene extraerlo a un helper de `BaseComponent`.
