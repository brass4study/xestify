# Auditoría — Módulos dinámicos y de navegación de negocio

**Subsistema:** `views/modules/` (DynamicTable, DynamicForm, DynamicTabs, Modal, ComponentFactory, Navbar, UserMenu, ThemeSettingsPanel, RelatedRecordsPanel)
**EPIC cubiertas:** EPIC 3, 5, 6, 9 (fixes de EPIC 10)
**Severidades:** 0 crítico · 4 mayor · 9 menor · 5 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de los 9 ficheros de `frontend/src/js/views/modules/`, sus consumidores (AppController, páginas, plugins), contraste con `docs/05-frontend/` y los runners del ámbito. Verificación específica de los fixes recientes 6ade511 (fechas dd/mm/yyyy) y 5ed22cb (summaryView).

---

## Resumen

Los 9 módulos están en un estado razonablemente sano: no hay ni un solo `innerHTML`/`insertAdjacentHTML` en todo `frontend/src` — todos los datos de negocio llegan al DOM vía `textContent` o atributos, por lo que **no se encontró ningún vector XSS real**. Los dos fixes recientes están bien integrados y testeados, aunque dejan flecos menores (tipo `timestamp` sin formatear, botón "Refrescar" inerte en el panel de relacionados). Los problemas de fondo son de ciclo de vida y mantenibilidad: un bug real de duplicación del menú en navegación `mixed`, una fuga acumulativa de listeners de `document` en `UserMenu`, un runner de DynamicForm con asserts que no pueden pasar con el código actual, y un `DynamicTable` de 801 líneas con al menos siete responsabilidades.

## Hallazgos por severidad

### MAYOR

**1. `Navbar.render()` no limpia el `linksContainer` externo: links duplicados en modo mixed**
- `frontend/src/js/views/modules/Navbar.js:210`
- `render()` limpia `#container` (163) y `#userContainer` (165), pero el `<ul data-role="navbar-links">` se ancla con `links.setParent(this.#linksContainer ?? nav)` (210) sin vaciar nunca `#linksContainer`. Solo `setLayoutTargets()` (81-83) lo limpia, y únicamente cuando el contenedor *cambia*. En navegación `mixed`, `AppController` construye el Navbar con `linksContainer = shell-menu-nav` (AppController.js:329) y `refreshNavEntities()` (909-914, invocado tras instalar/desinstalar/configurar un plugin en 796, 1095, 1107 y 1119) llama a `setEntities()` → `render()`: cada llamada **añade una lista de links completa nueva junto a la anterior** en el menú lateral. Lo mismo con `setShowBrand()` y `setOrientation()`. En top/side no muerde porque `linksContainer` es `null` y los links viven dentro del `nav` que sí se limpia.
- Sugerencia: al inicio de `render()`, si `this.#linksContainer instanceof HTMLElement`, hacer `replaceChildren()` (como con `#container`/`#userContainer`). Test en NavbarTest con `linksContainer` externo + doble `setEntities()` afirmando que solo existe un `[data-role="navbar-links"]`.

**2. Fuga acumulativa de listeners de `document` en UserMenu (sin `destroy()`), amplificada por Navbar**
- `frontend/src/js/views/modules/UserMenu.js:145-146`
- Cada instancia registra en `render()` un listener de click en `document` (146); el `removeEventListener` (145) solo retira el de *esa misma instancia*. `UserMenu` no tiene `destroy()`, y `Navbar.renderUserMenu()` (135-154) crea una **instancia nueva** en cada llamada descartando la anterior sin desregistrar. El multiplicador está en `AppController.syncNavbarFromState()` (1167-1170): cada notificación de `SessionModel.subscribe` encadena `setUserEmail` + `setUserName` + `setAvatar` + `setRoles`, y **cada una** llama a `renderUserMenu()` → 4 UserMenu nuevos y 4 listeners huérfanos por sync, que retienen el árbol DOM antiguo y ejecutan `setOpen(false)` sobre nodos desconectados en cada click durante toda la sesión. Efecto UX: el dropdown se cierra si estaba abierto cuando llega un sync.
- Sugerencia: (1) `destroy()` en `UserMenu` que desregistre el listener, invocado por `Navbar` antes de crear la instancia nueva. (2) Mejor: que `Navbar` reutilice una única instancia con setters, o que `syncNavbarFromState` pase los 4 valores en una llamada (`setIdentity({...})`).

**3. Asserts imposibles de pasar en DynamicFormTest: usan `type: 'email'` pero el código implementa `'mail'`**
- `frontend/tests/integration/DynamicFormTest.html:47,96,162,171`
- El tipo real del schema es `mail` (plugins/persons/schema.json:62, docs/03-api/contratos/entities.md:15), y `DynamicForm.createInput()` (DynamicForm.js:256-262) solo tiene rama para `'mail'`; `'email'` cae al `inputText` por defecto. En el runner, `buildFullSchema()` declara `{ name: 'email', type: 'email' }` (47), con lo que: el assert `input[name="email"][type="email"]` (96) devuelve `null` → test rojo; y en "reports type and range errors", `isStringLikeType('email')` es `false` (386) → no se valida el formato → `errors.email.length` (171) lanza TypeError → segundo test rojo. La suite del módulo está en rojo permanente (o pasa solo si nadie la ejecuta), camuflando regresiones futuras. La misma deriva `email`↔`mail` existe en `docs/05-frontend/renderizado-dinamico.md:45` y en el criterio de STORY 3.8 (backlog.md:471).
- Sugerencia: cambiar el runner a `type: 'mail'` (el assert `[type="email"]` sigue valiendo: `InputMailComponent` pone `type="email"`). Decidir si `'email'` debe aceptarse como alias en `createInput`/`isStringLikeType` (barato y defensivo) y alinear renderizado-dinamico.md y backlog.

**4. DynamicTable como god class: al menos siete responsabilidades mezcladas**
- `frontend/src/js/views/modules/DynamicTable.js:3-801`
- En 801 líneas conviven: (1) normalización schema→columnas con `ui_field_order` (611-705), duplicada casi línea a línea con `DynamicForm.normalizeFields` (110-143); (2) formateo de valores date/select (737-800); (3) ordenación local (270-286); (4) paginación dual local/remota con dos fuentes de verdad (`#records.length` vs `#totalRecords`); (5) persistencia de preferencias en cookies con API estática (715-735); (6) toolbar y tres menús desplegables (288-402); (7) paginador completo con jump-buttons (428-593); más la fábrica estática `buildActionButton` (34-65) que usan páginas y plugins. El patrón "cada interacción → `this.render()` completo" hace que abrir un menú y elegir una opción destruya y reconstruya toda la tabla.
- Sugerencia: extraer sin cambiar contrato público: `SchemaColumns.js` (normalización compartida con DynamicForm), `CellFormatters.js`, `TablePreferences.js` (cookies), y `TablePagination.js`/`TableToolbar.js` como sub-builders con callbacks. `buildActionButton` encaja en `ComponentFactory`.

### MENOR

**5. Fleco del fix 6ade511: columnas `timestamp` (y `time`) sin formatear**
- `frontend/src/js/views/modules/DynamicTable.js:776-781`
- `formatCellValue` solo trata `date` y `select`; un campo `type: 'timestamp'` (existe: plugins/comments/schema.json:17) se muestra como string ISO crudo. `renderizado-dinamico.md:30` promete "timestamp -> date time picker" como mapeo vigente, pero ni `DynamicForm.createInput` (cae a `inputText`; DynamicFormTest.html:208-211 incluso cementa "should render as text") ni la tabla lo implementan.
- Sugerencia: rama `timestamp` en `formatCellValue` (reutilizando `formatDateValue` + hora) y para el form: o `inputDateTime`, o mover `timestamp` a "Roadmap, no implementado" del doc.

**6. Los menús de toolbar/paginación no se cierran al hacer click fuera**
- `frontend/src/js/views/modules/DynamicTable.js:397-402`
- `#toggleMenu` solo alterna `hidden`; a diferencia de `UserMenu` (document click), `ThemeSettingsPanel` (pointerdown + Escape) e `InputSelect`, los menús de densidad, columnas visibles y tamaño de página quedan abiertos hasta re-pulsar el trigger o un re-render. Pueden quedar abiertos dos a la vez.
- Sugerencia: listener de `document` al abrir (retirado al cerrar), cerrando también el menú hermano; el patrón exacto ya existe en UserMenu.js:24-31.

**7. Las columnas `select` se ordenan por el value crudo, no por la etiqueta mostrada**
- `frontend/src/js/views/modules/DynamicTable.js:270-286`
- Tras 5ed22cb/6ade511 la celda muestra la *label* (`formatCellValue`, 790-799), pero `sortBy`/`getSortedRecords` ordenan por `record[columnName]` (el value interno). Con labels cuyo orden alfabético no coincida con el de los values, el usuario ve una columna "ordenada" en orden aparentemente aleatorio (solo modo local; en remoto decide el servidor).
- Sugerencia: para columnas `select`, resolver la label vía las mismas `column.options` que usa `formatCellValue` antes de comparar.

**8. API pública muerta o inconsistente: `setSchema()` sin consumidores (y con bug latente), `nextPage()/prevPage()` no renderizan ni consultan**
- `frontend/src/js/views/modules/DynamicTable.js:163-166, 191-205`
- `setSchema()` no tiene ningún consumidor en `frontend/src` ni `plugins/` y además resetea `#visibleColumns` solo con las columnas base: quien lo use perderá la visibilidad de las `extraColumns` (la columna "Acciones" de EntityList desaparecería). `nextPage()/prevPage()` (usados solo por DynamicTableTest) mutan `#currentPage` sin `render()` ni `#requestRemoteData` — en modo remoto dejarían estado y datos desincronizados.
- Sugerencia: eliminar `setSchema` (o re-añadir las claves de `extraColumns`), y que `nextPage/prevPage` deleguen en `#goToPage` (o eliminarlos).

**9. La cookie de tamaño de página pisa el `pageSize` explícito del caller (asimetría con `density`)**
- `frontend/src/js/views/modules/DynamicTable.js:72`
- `this.#pageSize = DynamicTable.getPreferredPageSize(this.normalizePageSize(options.pageSize))`: la cookie siempre gana sobre la opción del constructor. Para `density` se arregló exactamente esto (73-78, con comentario "An explicit density option pins the table"), pero `pageSize` no. Hoy no muerde (EntityList pre-resuelve la cookie; optometries usa `showPagination: false`), pero cualquier tabla embebida futura con `pageSize` fijo recibirá el de la cookie global.
- Sugerencia: replicar el patrón de `density`; ajustar EntityList para no llamar a `getPreferredPageSize` por su cuenta (EntityList.js:133, 221).

**10. Id de contenido duplicado entre instancias de Modal (`xestify-modal-content`)**
- `frontend/src/js/views/modules/Modal.js:46-51`
- El título usa el id único de `ModalComponent` (`ui-modal-title-N`), pero el contenido recibe el literal `'xestify-modal-content'` en todas las instancias. Con dos modales montadas (ModalTest monta dos a la vez, 130-138, aunque solo asserta títulos) hay ids duplicados y `aria-describedby` ambiguo.
- Sugerencia: generar el id del contenido con el mismo contador (`ui-modal-content-${n}`).

**11. RelatedRecordsPanel sin paginación ni límite, y con un botón "Refrescar" que no recarga del servidor**
- `frontend/src/js/views/modules/RelatedRecordsPanel.js:63-70, 95-101`
- Fleco de STORY 10.3 §9 / 5ed22cb: (1) `#load` pide *todos* los registros relacionados (`?field=&value=` sin `page/page_size`) y la tabla se crea con `showPagination: false`: un cliente con cientos de ventas renderiza cientos de filas de golpe; (2) la tabla no recibe `showToolbar: false` ni `onRefresh`, por lo que muestra el botón "Refrescar" de la toolbar (DynamicTable.js:299), cuyo `refresh()` sin `onRefresh` ni modo remoto (417-424) solo re-renderiza los datos en memoria: parece que refresca y no lo hace.
- Sugerencia: pasar `onRefresh: () => this.#load(lastResolvedId)` o `showToolbar: false`. Para el volumen, `onQueryChange` contra el endpoint paginado que EntityList ya consume.

**12. `userContainer` de Navbar es obligatorio incluso con `showUserMenu: false`**
- `frontend/src/js/views/modules/Navbar.js:25`
- El constructor hace `this.#userContainer = this.resolveContainer(options.userContainer)` incondicionalmente (lanza si falta), aunque `#showUserMenu` sea `false` y el contenedor jamás se use. Un Navbar "solo links" obliga a fabricar un div sacrificial.
- Sugerencia: resolverlo opcionalmente cuando `showUserMenu === false`.

**13. Triple implementación del mismo algoritmo de normalización de schema (y `resolveContainer` clonado en 7 clases)**
- `frontend/src/js/views/modules/DynamicForm.js:110-143` + `DynamicTable.js:611-643` + `views/pages/EntityEdit.js:426-435`
- El pipeline "array u objeto → nombre desde `name|key(|slug)` → orden por `ui_field_order` → resto en orden original" está copiado en `DynamicForm.normalizeFields`, `DynamicTable.normalizeColumns` y (parcialmente) re-explicado en `optometries/Hooks.php`; `fieldName()` (DynamicForm.js:200-209) y `#fieldName()` (EntityEdit.js:426-435) son idénticos; y `resolveContainer` está clonado en 7 clases (DynamicTable.js:595, DynamicForm.js:211, DynamicTabs.js:14, Navbar.js:265, UserMenu.js:208, Modal.js:181, más páginas). Ya han divergido: `columnsFromSection` acepta `name|key` pero no `slug`, mientras el form sí.
- Sugerencia: un `models/SchemaFieldsModel.js` (sin DOM) con `normalizeFields(schema)` y `fieldName(field)` compartidos, y un `resolveContainer` en BaseComponent o util de views.

### NIT

**14. `content()` se invoca dos veces en el primer render de DynamicTabs**
- `frontend/src/js/views/modules/DynamicTabs.js:92-105`
- `renderTabs()` crea el `TabsComponent` con `activeId` (cuyo `initialize` ya llama a `setActiveTab` → `content()`) y acto seguido repite `this.#root.setActiveTab(activeId, false)` (104), invocando `content()` de nuevo. Con contenidos cacheados es inocuo; con contenidos que construyen DOM nuevo, duplica el primer pintado.
- Sugerencia: eliminar la llamada (103-105) o condicionar a `activeId !== this.#root._activeId`.

**15. Selectores y URLs interpolados sin escapar/codificar en EntityEdit y Tabs**
- `frontend/src/js/views/pages/EntityEdit.js:455, 569-572, 609` + `views/components/Tabs.js:60`
- `formEl.querySelector(`[name="${fieldName}"]`)` interpola nombres de campo que vienen de la respuesta de error de la API; un nombre con `"` rompe el selector (throw). Las URLs de persistencia no usan `encodeURIComponent`, mientras EntityList sí (EntityList.js:209, 224). `TabsComponent.setActiveTab` interpola `id` en `[data-tab-id="${id}"]` con ids que llegan de la API.
- Sugerencia: `CSS.escape(...)` en los selectores y `encodeURIComponent` en las URLs de EntityEdit.

**16. Inconsistencias internas de ThemeSettingsPanel**
- `frontend/src/js/views/modules/ThemeSettingsPanel.js:201-209, 133, 147, 299-301`
- La opción activa de "Modo de navegación" no recibe la clase `is-active` (207) mientras pageStyle (231-233) y themeColor (257-259) sí. El backdrop y el drawer se posicionan con `setAttribute('style', 'position:fixed;...')` en vez de clases, y las previews hardcodean `#1e79ff`/`#334155`: la preview del modo de navegación no refleja el themeColor elegido.
- Sugerencia: `is-active` también en `#buildNavigationModeSection`, estilos fijos a las clases `theme-settings-*` existentes, y `var(--x-brand-500)` en las previews.

**17. Pequeñas fricciones ARIA/semánticas en Navbar, Modal y paginación**
- `frontend/src/js/views/modules/Navbar.js:260` + `Modal.js:31-32` + `DynamicTable.js:429`
- (1) `setActive` escribe `aria-current="false"` en los links inactivos; lo correcto es eliminar el atributo. (2) Modal re-aplica `role="dialog"`/`aria-modal` que `ModalComponent` ya puso. (3) `buildPagination` crea el contenedor como `div` para lo que es una barra de navegación (`nav` + `aria-label` sería lo esperable). (4) Con dos modales abiertas, el Escape de cada instancia cierra ambas.
- Sugerencia: `removeAttribute('aria-current')`; retirar la re-aplicación; `nav` en el paginador; en `#onKeyDown` ignorar Escape si la instancia no es la última abierta.

**18. Derivas puntuales documentación↔código**
- `docs/05-frontend/README.md:114`, `testing-ui.md:8`, `docs/11-backlog/backlog.md:1462`
- (1) "19 runners HTML" cuando hay 22 en `frontend/tests/integration/`. (2) STORY A1.7 afirma que la acción "Eliminar" en EntityList es "hoy inexistente", pero ya está implementada (EntityList.js:136-150 + confirmación + tests en EntityListTest.html:256-335). (3) La deriva `email`/`mail` y el `timestamp` prometido, recogidas en los hallazgos 3 y 5.
- Sugerencia: actualizar el conteo y marcar en A1.7 el criterio de EntityList como cubierto para no re-implementarlo.

## Cobertura de tests

Amplia en lo funcional, con huecos exactamente donde están los bugs. **DynamicTableTest** (26 tests) es el más completo: columnas dinámicas, `summaryView` en array y objeto, labels de select, fechas dd/mm/yyyy (el fix 6ade511 asertado), paginación local y remota, sort, toolbar, cookies, `extraColumns`, `showToolbar:false` y densidad fijada; no cubre el tercer click de sort, el cierre de menús, `setSchema` ni el refresh en modo remoto. **DynamicFormTest** cubre tipos, `getData`, validación, `custom_fields` y el ciclo de relaciones `belongs_to`, pero tiene dos asserts imposibles de pasar por `type: 'email'` (hallazgo 3): el runner está en rojo hoy. **DynamicTabsTest** es excelente (render, activación, ink bar, teclado, `registerTab`, duplicados, destroy y tablist externa — el escenario del fix e1df7d0). **ModalTest** cubre show/close/setContent/backdrop/destroy y unicidad del id de título, no el focus-trap, Escape, retorno de foco ni la colisión del id de contenido. **NavbarTest** cubre brand, links por entidad + Plugins, iniciales/avatar y navegación del dropdown, pero nada de `linksContainer`/modo mixed (el hallazgo 1), `setEntities` tras render, `aria-current` ni la fuga de UserMenu. **ThemeSettingsPanelTest** cubre apertura/cierre, secciones y cambios de preferencia, no Escape/pointerdown fuera ni `destroy()`. `RelatedRecordsPanel` no tiene runner propio (camino feliz cubierto indirectamente en EntityEditTest; estados de error y `flush()` no). `ComponentFactory` se cubre en ComponentsTest.

## Observaciones transversales

1. **Seguridad sólida por construcción.** Cero `innerHTML` en `frontend/src`; `TableComponent` solo acepta `Node` o `textContent` por celda, `Modal.setContent` usa `textContent`. El formateo de fechas (6ade511) es puramente textual (regex sobre "YYYY-MM-DD"), sin `new Date()`: tampoco hay bug de zona horaria. Los únicos residuos son de segundo orden: interpolación de datos de API en *selectores CSS* y el `src` del avatar sin validar esquema (UserMenu.js:52-54) — ninguno explotable como XSS.
2. **Ciclo de vida asimétrico: los módulos saben nacer pero no morir.** `ThemeSettingsPanel`, `Modal` y `DynamicTabs` tienen `destroy()` correcto; `UserMenu`, `Navbar`, `DynamicTable` y `RelatedRecordsPanel` no, y los dos hallazgos MAYOR de runtime nacen de ahí. Convendría un contrato mínimo `destroy()` para todo módulo que registre listeners fuera de su subárbol o escriba en contenedores externos.
3. **"Re-render total" como única estrategia de actualización.** `DynamicTable.render()`, `Navbar.render()` y `UserMenu.render()` reconstruyen todo ante cualquier cambio (elegir densidad cierra el menú, un sync de sesión reconstruye el menú de usuario 4 veces). Coherente con la filosofía vanilla, pero los módulos con menús abiertos necesitan preservar ese estado efímero.
4. **La convención "ComponentFactory como entrada única" convive con tres excepciones**: `RelatedRecordsPanel` (100% `document.createElement`), `InputSelect` (interno, justificable) y fragmentos de tests. La primera es post-EPIC 9 y debería migrar; si no, matizar la regla del doc.
5. **El patrón cookie-preferencia se aplicó a `density` con cuidado pero no a `pageSize`**, y las preferencias de tabla son globales por diseño — documentarlo en `renderizado-dinamico.md`, que sigue describiendo DynamicTable como "paginación básica" y no menciona modo remoto, toolbar, cookies ni `summaryView` (el concepto que dirige 5ed22cb no aparece en ningún doc de 05-frontend).
