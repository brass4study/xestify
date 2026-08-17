# Auditoría — Módulos dinámicos y páginas de negocio

**Subsistema:** Frontend Pages & Modules (formularios/tablas/tabs dinámicos, navbar, páginas de negocio)
**EPIC cubiertas:** EPIC 3, 5, 6, 7, 8 (en su vertiente frontend)
**Severidades:** 2 crítico · 3 mayor · 2 menor · 0 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Revisión completa (ficheros leídos íntegros) de `ComponentFactory.js`, `DynamicForm.js`, `DynamicTable.js`, `DynamicTabs.js`, `Modal.js` (modules), `Navbar.js`, `ThemeSettingsPanel.js`, `UserMenu.js`, `EntityList.js`, `EntityEdit.js`, `Login.js`, `PluginConfig.js`, `PluginManager.js`, `UserConfig.js`, `UserManager.js`, `UserProfile.js`, más `views/components/Modal.js`, `views/components/Table.js`, `services/UiResilienceService.js` (parcial, para verificar hallazgos), `plugins/comments/plugin.js` y los docs de referencia. Tests: lectura completa de `PluginManagerTest.html`, `PluginConfigTest.html`, los 3 specs Playwright, y grep dirigido sobre `EntityEditTest.html`.

---

## Hallazgos por severidad

### CRÍTICO

**1. `EntityEdit.js:62-86` — El formulario de alta/edición de registros queda bloqueado para siempre tras el primer error de validación**

```js
async submit() {
    if (this.#form === null || this.#isSubmitting) return;
    this.#isSubmitting = true;
    this.#clearErrors();
    if (!this.#validateFormBeforeSubmit()) {
        return;                 // <-- sale del método SIN resetear #isSubmitting
    }
    this.#setLoading(true);
    try { ... } finally { this.#isSubmitting = false; this.#setLoading(false); }
}
```
El `return` de la rama de validación fallida está **fuera** del `try/finally` que resetea `#isSubmitting`. Como `#setLoading(true)` (que deshabilita visualmente el botón vía `UiResilienceService.setButtonPending`) solo se llama *después* de pasar la validación, el botón "Guardar" queda visualmente normal pero `submit()` hace un no-op silencioso en todos los clics siguientes (`if (... || this.#isSubmitting) return;`), sin volver a mostrar errores ni feedback alguno. Cualquier usuario que deje un campo obligatorio vacío en el primer intento pierde la capacidad de guardar ese formulario hasta recargar la página. Es el flujo CRUD más usado de la aplicación (alta/edición de cualquier entidad) y no está cubierto por ningún test (`EntityEditTest.html` no tiene ningún caso de "reintentar tras error de validación").
**Arreglo:** mover el `return` dentro del `try`, o envolver todo el cuerpo (incluida la validación) en `try/finally`.

**2. `PluginManager.js` y `PluginConfig.js` — los botones de acción de la tabla dinámica dejan de funcionar tras cualquier re-render interno de `DynamicTable`**

- `PluginManager.js:294-307` (`#pluginActionButton`) construye los botones Activar/Desactivar/Configurar/Actualizar/Revertir con `onClick: () => {}` (no-op) y depende de `#renderContent` (líneas 195-203) para hacer un `querySelectorAll('[data-role="plugin-action"]')` **una sola vez**, justo después del primer `layout.createTable(...)`, y engancharles el listener real vía `#bindActionButton` (205-230).
- `PluginConfig.js:345-355` (`buildRowActionButton`) hace exactamente lo mismo para Subir/Bajar/Eliminar fila, con el binding real hecho una sola vez en `bindTableEvents` (357-380), invocado dentro del `render()` de la página.

El problema: `DynamicTable.render()` (`DynamicTable.js:89-137`) reemplaza el DOM entero (`this.#container.replaceChildren()`) y reconstruye la tabla vía `component.create('table', ...)`, y esto se dispara internamente — sin avisar a la página — en `sortBy()` (156-177), `#goToPage()` (228-235), `#setPageSize()` (237-249), el menú de densidad (309-321) y el menú de columnas visibles (350-360); todos accesibles desde la barra de herramientas que `buildToolbar()` monta **siempre**, incluso con `showPagination: false` (caso de `PluginConfig`). Cada uno de esos re-renders vuelve a invocar los `renderCell` originales, que vuelven a crear los botones con `onClick: () => {}`, y el binding delegado nunca se repite. Basta con un clic en el icono de engranaje (⚙) para desactivar una columna, o en el icono de densidad, para que Activar/Desactivar/Actualizar/Revertir (o Subir/Bajar/Eliminar) dejen de responder, sin ningún error visible.

Confirmación de que es evitable: `EntityList.js:137-146` y `UserManager.js:159-172` pasan el handler real directamente al `onClick` de `DynamicTable.buildActionButton`/`renderCell` — el patrón correcto — lo que indica que esto es una inconsistencia/parche a medias específico de las dos páginas de plugins, no una limitación del componente.
**Arreglo:** pasar el handler real directamente en `renderCell` (como hace `EntityList`), eliminando el paso intermedio de "crear con no-op + bindeo posterior por querySelector".

---

### MAYOR

**3. `UserManager.js:288-321` y `UserConfig.js:875-908` — `normalizeRoleList()` duplicada carácter por carácter**

Ambos ficheros definen la misma función top-level (incluida la indentación extra de una tab, señal de copy-paste literal), y además replican lógica casi idéntica de `#displayName`/`#displayEmail`/`#getInitials`. Es exactamente el tipo de redundancia que aparece entre `EntityList/EntityEdit` y `UserManager/UserConfig`.
**Arreglo:** extraer a un módulo compartido (p.ej. `models/UserModel.js` o `utils/roles.js`).

**4. Cobertura de tipos incompleta en la generación dinámica de formularios — `DynamicForm.js:195-249`**

`createInput()` no tiene rama para `type: 'number'` ni `type: 'time'`; ambos caen al `inputText` genérico (texto plano), pese a que `extractValue()` (67-108) y `validateField()`/`validateNumber()` (260-347) sí tratan `number` como caso especial. Consecuencia: un campo numérico se edita como texto libre, sin teclado numérico ni controles nativos, y solo se valida al perder el foco/enviar. Además, `ComponentFactory.js:73` registra el factory `inputTime` (respaldado por `InputTimeComponent`) que **no se usa en ningún punto del código fuente** (verificado por grep global) — es una pieza registrada pero nunca conectada al flujo real, indicio de refactor/feature a medio terminar. Los docs (`renderizado-dinamico.md`) prometen además mapeos `object -> subform` y `array -> repeater` que no existen en absoluto en `DynamicForm`/`DynamicTable` — es razonable como roadmap, pero conviene que la memoria del TFM lo marque explícitamente como alcance no cubierto en vez de dejarlo implícito.

**5. Dos soluciones distintas al mismo problema de "página CRUD con formulario"**

`EntityEdit` no usa un `<form>` con evento `submit` real: el botón "Guardar" llama `this.submit()` directamente (`EntityEdit.js:126-134`), por lo que pulsar Enter dentro de un campo de texto no envía el formulario. `UserConfig` (base de `UserProfile` y de la ficha admin de usuario) sí envuelve todo en un `<form>` con `addEventListener('submit', ...)` y usa `form.requestSubmit()` desde el botón (`UserConfig.js:219-223`, `406-414`). Ambos patrones son válidos aisladamente, pero al ser dos páginas de "editar una entidad con validación y guardado" resueltas de forma distinta (una es clase plana, la otra usa herencia por template method), sugiere una convención que no llegó a unificarse tras EPIC 5 vs EPIC 6/7 — vale la pena mencionarlo en la defensa como decisión consciente o como deuda pendiente, según corresponda.

---

### MENOR

**6. `Login.js:25-72`** no tiene guarda de re-entrada (`#isSubmitting`) como sí tiene `EntityEdit`; un doble clic muy rápido en "Entrar" antes de que arranque la petición podría disparar dos `POST /auth/login` concurrentes. Impacto bajo (el backend debería ser idempotente), pero rompe la consistencia de patrón entre páginas equivalentes.

**7. `UserConfig.js:756`** mezcla espacios con tabs en una única línea (`value: temporaryPassword,`), y en general el fichero (908 líneas) concentra mucha lógica de UI + validación + IO en una sola clase — no es ilegible, pero es el candidato más claro del subsistema para dividir (p.ej. extraer el manejo de avatar/`FileReader` y el flujo de contraseña temporal a colaboradores separados).

---

### Punto específico solicitado: `views/modules/Modal.js` vs `views/components/Modal.js`

**No es duplicación — es una arquitectura de dos capas legítima**, coherente con el resto de `views/modules/*.js` (que siempre envuelven un componente "tonto" de `views/components/` con estado y comportamiento):

- `views/components/Modal.js` (54 líneas) exporta `ModalComponent extends BaseComponent`: construye únicamente el DOM estático (overlay, `<section>` diálogo, cabecera con título/botón cerrar, contenedor de contenido) a partir de `options`. Se registra en `ComponentFactory.js:20,101-102` bajo el nombre `'modal'`.
- `views/modules/Modal.js` (190 líneas) exporta `Modal`: internamente hace `component.create('modal', {...})` (es decir, instancia el `ModalComponent` anterior) y añade el comportamiento real — `show()`/`close()`, focus-trap (`#handleTabKey`, `#getFocusableElements`, líneas 114-147), cierre con Escape y con clic fuera, gestión de `aria-*`, devolución de foco al elemento que abrió el modal.
- Único consumidor en todo el repo: `services/UiResilienceService.js` importa `Modal` de `modules/Modal.js` para su `confirm()` (usado por `PluginManager` en actualizar/revertir y por `UserConfig` en borrar usuario — confirmado por las aserciones sobre `[data-action="confirm-modal"]` en `PluginManagerTest.html`). Ninguna página del ámbito revisado importa Modal directamente (grep sobre `views/pages` sin resultados); todo pasa por `UiResilienceService.confirm()`.
- `ComponentFactory.js` importa `ModalComponent` únicamente para registrarlo como factory `'modal'`, que es justo lo que `modules/Modal.js` consume.

**Conclusión:** son cosas distintas con nombres iguales, no una duplicación de código. El único reproche real es de nomenclatura (dos clases llamadas "Modal" en directorios distintos puede confundir a primera vista, como de hecho motivó esta pregunta), pero es coherente con el resto del proyecto (`DynamicTable` envuelve `TableComponent`, `DynamicForm` envuelve `FormComponent`/`InputXComponent`, etc.), así que lo calificaría como **nit**, no como hallazgo de diseño a corregir.

---

## Resumen de salud del subsistema

El subsistema de renderizado dinámico (`ComponentFactory`/`DynamicForm`/`DynamicTable`/`DynamicTabs`) está bien diseñado: separación clara entre normalización de schema, construcción de inputs y validación, con métodos cortos y responsabilidad única — es la parte más sólida del código revisado y demuestra buen entendimiento de clean code. Las páginas de negocio, en cambio, muestran señales claras de haberse construido en oleadas distintas (EPIC 5/6 vs EPIC 7/8): `EntityList`/`EntityEdit` y `UserManager` conectan sus botones de acción de tabla de forma correcta y directa, mientras que `PluginManager`/`PluginConfig` introdujeron un patrón de "bind posterior por querySelector" que rompe de forma silenciosa y fácilmente reproducible en cuanto el usuario interactúa con el propio toolbar de `DynamicTable` (density/columnas/orden) — es el hallazgo más serio de la auditoría porque afecta justamente al flujo de activar/desactivar/actualizar/revertir plugins. El segundo hallazgo crítico, el bloqueo permanente del guardado en `EntityEdit` tras un error de validación, es igual de grave por tratarse del formulario CRUD más usado de la aplicación. Fuera de eso, la duplicación entre `UserManager`/`UserConfig` es evidencia concreta (no especulación) de una abstracción compartida que no llegó a extraerse, y la cobertura de tipos de campo (`number`/`time`) muestra una pieza (`inputTime`) registrada pero nunca conectada. La supuesta duplicación de `Modal.js` no es tal: es una arquitectura de dos capas correcta y consistente con el resto del código. En conjunto, el subsistema es funcionalmente sólido en el 90% de los casos de uso felices, pero tiene dos bugs de correctitud con impacto de uso normal (no casos límite exóticos) que conviene arreglar y poder explicar en la defensa.

## Nota sobre cobertura de tests

El runner HTML propio de `PluginManagerTest.html` y `PluginConfigTest.html` es razonablemente completo en cuanto a *comportamiento de negocio* (badges de tipo/estado/actualización, confirmación antes de actualizar/revertir, sincronización, campos bloqueados vs editables, reordenar filas), pero en ningún caso combina una interacción con el toolbar de la tabla (ordenar, densidad, columnas, tamaño de página) con un clic posterior en un botón de acción — por eso el bug crítico #2 pasa desapercibido con la suite en verde. De forma similar, `EntityEditTest.html` no tiene ningún caso de "enviar con datos inválidos y luego corregir y reenviar", por lo que el bug crítico #1 tampoco está cubierto. Los specs de Playwright (`entity-crud`, `login`, `plugin-manager`) son finos y de tipo "camino feliz" (un escenario por fichero), sin cubrir rutas de error de validación, doble envío, ni interacción con densidad/orden de tabla antes de una acción de plugin — es decir, apuntan exactamente a los mismos puntos ciegos que la suite de integración. No se detectó ningún test que compare explícitamente el comportamiento compartido entre `UserManager` y `UserConfig` (lo que habría podido exponer la duplicación de `normalizeRoleList` como riesgo de divergencia futura).
