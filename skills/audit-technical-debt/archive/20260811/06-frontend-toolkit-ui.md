# Auditoría — Toolkit de Componentes UI y Layouts

**Subsistema:** Frontend UI Toolkit (componentes reutilizables y layouts)
**EPIC cubiertas:** base EPIC 5 (frontend dinámico base), ampliado en EPIC 9
**Severidades:** 0 crítico · 4 mayor · 5 menor · 3 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Alcance revisado íntegramente (lectura completa, no solo grep): los 28 componentes en `frontend/src/js/views/components/`, los 4 layouts en `frontend/src/js/views/layout/`, `ComponentFactory.js`, `ComponentsTest.html`, `ModalTest.html`, y como evidencia de uso real: `Login.js`, `DynamicForm.js`, `UiResilienceService.js`, `AppController.js`, y el CSS Tailwind compilado.

---

## Hallazgos — MAYOR

### 1. Fuga de nodos DOM + `id` duplicados por cada modal reutilizable creado
**Ficheros:** `frontend/src/js/views/modules/Modal.js:100-112` (método `close()`), `frontend/src/js/views/components/Modal.js:21-30` (id de título fijo), `frontend/src/js/services/UiResilienceService.js:178-230` (método `confirm()`)
**Categoría:** correctitud / gestión de recursos / accesibilidad

`UiResilienceService.confirm()` (usado típicamente en confirmaciones de borrado en toda la app) crea una instancia nueva con `new Modal(container, {title})` en cada llamada (línea 186) y la monta en `document.body` al hacer `show()`. Pero `Modal.close()` (modules/Modal.js:100-112) solo oculta el overlay (`hidden=true`, clases `hidden`/`flex`) — **nunca lo elimina del DOM**. Como además `components/Modal.js:21-30` fija el `id` del `<h3>` de título como una cadena **estática** (`'ui-modal-title'`) en lugar de generarlo por instancia, tras dos o más confirmaciones en la misma sesión el documento acumula varios elementos con `id="ui-modal-title"` (HTML inválido) y `document.getElementById`/`aria-labelledby` referenciará siempre el primero, anunciando a lectores de pantalla el título de un modal antiguo. Es una fuga de memoria/DOM progresiva y real, no solo teórica, dado el patrón de uso de `confirm()`.

Adicionalmente, dentro de `confirm()` se llama `modal.show()` dos veces seguidas (líneas 212 y 218): la primera antes de `setContent(body)`, por lo que el primer `requestAnimationFrame` de auto-foco actúa sobre contenido vacío; el código compensa manualmente enfocando el botón de confirmación a mano (líneas 219-229), señal de parche sobre un síntoma en vez de arreglo de raíz.

**Sugerencia:** dar a `Modal` un método `destroy()` que desmonte el overlay del DOM y se invoque tras `close()` en usos de un solo disparo como `confirm()`; generar el `id` del título de forma única por instancia en `components/Modal.js` en lugar de un literal fijo; eliminar la llamada duplicada a `show()`.

### 2. Asociación `label`/`for` rota en el formulario de login
**Ficheros:** `frontend/src/js/views/components/FormField.js:15-17`, `frontend/src/js/views/components/BaseComponent.js:137-157` (`InputComponent.initialize`), `frontend/src/js/views/pages/Login.js:117-133`
**Categoría:** bug de accesibilidad básica

`FormFieldComponent` pone `label.setAttribute('for', options.name)`. `Login.js` pasa `name: 'email'` / `name: 'password'` tanto al `formField` como al input interno, pero `InputComponent.initialize` (BaseComponent.js) solo asigna el atributo HTML `name`, nunca `id`. Resultado: `label[for="email"]` no apunta a ningún `id` existente en el DOM — clicar la etiqueta no enfoca el campo y la asociación programática etiqueta↔campo (fundamental para lectores de pantalla) falla en la página de login, la más usada de la aplicación.

`DynamicForm.js:24-31` sí lo resuelve correctamente: usa `fieldId(field.name)` tanto para el `id` del input (`.setId(...)`) como para el `name` pasado a `formField`. Esto confirma que es una inconsistencia entre llamadas, no una limitación del componente — y que `FormFieldComponent` debería garantizar el contrato en vez de dejarlo en manos del consumidor.

**Sugerencia:** que `FormFieldComponent` derive el `id` del input automáticamente (o lo genere si falta) en vez de depender de que cada llamada sincronice manualmente `name`/`id`; mientras tanto, añadir `.setId('email')`/`.setId('password')` en `Login.js`.

### 3. Clase Tailwind base duplicada literalmente en 9 ficheros `Input*.js`
**Ficheros:** `InputDate.js:7`, `InputEmail.js:7`, `InputPassword.js:7`, `InputSelect.js:7`, `InputText.js:7`, `InputTextArea.js:6`, `InputTime.js:7` (idénticos byte a byte: `'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500'`); `InputCheck.js:8` e `InputRadio.js:7` repiten igualmente `'h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500'`.
**Categoría:** redundancia / DRY / mantenibilidad

**Cada `Input*` reimplementa el estilo base en vez de heredarlo** de `InputComponent`/`BaseComponent`. Cambiar el radio del borde o el color de foco exige editar 9 ficheros a mano y arriesga inconsistencias (de hecho el patrón lógico de `initialize()` — `super.initialize()` → `setType()` → `className` — sí es consistente entre ellos, lo cual hace más evidente que falta un único punto para el string de clases).

**Sugerencia:** mover ambas constantes a `InputComponent` (p. ej. `INPUT_BASE_CLASSNAME`, `CHECKABLE_BASE_CLASSNAME`) y que cada subclase solo llame a `this.className = InputComponent.BASE_CLASSNAME` o a un método `applyBaseInputStyle()`.

### 4. El estado visual de error puede quedar enmascarado por la clase base (no eliminada)
**Fichero:** `frontend/src/js/views/components/BaseComponent.js:171-182` (`InputComponent.setError`)
**Categoría:** bug de correctitud visual

`setError()` hace `this.addClass('border-red-300', 'focus:border-red-500')` pero **no quita** `border-slate-300` (la clase base que todo `Input*` ya lleva). Comprobado en el CSS compilado (`frontend/src/css/tailwind.generated.css`): `.border-red-300` aparece en el offset 11314 y `.border-slate-300` en el 11886 — con igual especificidad, la declaración posterior gana, así que **en reposo el borde se queda gris, no rojo**. Solo en estado `:focus` funciona como se espera, porque ahí `.focus\:border-red-500` sí aparece después de `.focus\:border-brand-500` en el CSS generado. `aria-invalid` y `dataset.error` sí quedan correctos, así que no es catastrófico, pero el indicador visual más básico de error falla en reposo, y depende de un orden de generación de Tailwind que nadie controla explícitamente.

**Sugerencia:** en `setError`, quitar explícitamente `border-slate-300`/`focus:border-brand-500` al entrar en error (y restaurarlas al limpiar), en vez de confiar en el orden del cascade compilado.

---

## Hallazgos — MENOR

### 5. Parámetro `variant: 'ghost'` inerte pasado a `Button`
**Ficheros:** `Tabs.js:78-86`, `Modal.js:33` (components), y fuera de alcance pero mismo síntoma en `AppController.js:1142`
`Button.js`'s `variantClassName()` (líneas 71-85) solo reconoce `'primary'`/`'danger'`/`'success'` (con fallback por defecto); no existe rama `'ghost'`. En los tres sitios, justo después se llama `.setClassName(...)`, que **sobrescribe entera** la className calculada por `ButtonComponent`, así que el valor de `variant: 'ghost'` es completamente inerte en la práctica. Es señal de un refactor incompleto (o de una variante planeada y nunca añadida) que confunde a cualquiera que lea el código.
**Sugerencia:** añadir la variante `'ghost'` a `Button.js`, o eliminar el parámetro de las llamadas que igualmente sobrescriben la clase.

### 6. Código muerto/redundante al fijar el `id` del título del modal
**Fichero:** `frontend/src/js/views/components/Modal.js:21-30`
Se llama `.setId('ui-modal-title')` (línea 25) y acto seguido (líneas 27-30) se vuelve a buscar el mismo nodo por selector y se le reasigna el mismo `id` manualmente — resto evidente de una versión anterior del código.
**Sugerencia:** eliminar las líneas 27-30.

### 7. Rama muerta en `Breadcrumb.resolveItems`
**Fichero:** `frontend/src/js/views/components/Breadcrumb.js:202-226`
`resolveConfig()` ya gestiona `Array.isArray(options)` antes de invocar `resolveItems()` (línea 202-208), por lo que el `if (Array.isArray(options))` dentro de `resolveItems` (línea ~221) nunca se ejecuta.
**Sugerencia:** eliminarla.

### 8. Componentes registrados en el catálogo pero sin ningún uso en páginas/módulos reales
**Fichero:** `frontend/src/js/views/modules/ComponentFactory.js` (líneas 73, 83, 103, 105, 116)
`inputRadio`, `inputTime`, `spinner`, `skeleton` y el alias `inputCheckbox` solo aparecen usados en `ComponentsTest.html` — ninguna página de negocio (`views/pages`) ni módulo (`views/modules`) los invoca. No son código muerto en sentido estricto (forman parte del catálogo del toolkit y están probados a nivel smoke-test), pero conviene poder señalarlo en la defensa: son infraestructura preparada, no funcionalidad usada hoy.

### 9. Acoplamiento fuerte de `PageLayout` con la estructura DOM interna de `PageHeaderComponent`
**Fichero:** `frontend/src/js/views/layout/PageLayout.js:249-297` (`#mountHeader`)
Accede a partes internas de `PageHeaderComponent` vía `querySelector('[data-role="..."]')` en vez de una API pública expuesta por el propio componente, y lanza `TypeError` si el DOM interno no coincide con lo esperado. Funciona hoy, pero es una fuga de abstracción entre capas (layout conoce implementación interna de un componente de presentación).

---

## Hallazgos — NIT

- **10.** **`setHtml()`/`options.html`** (`BaseComponent.js:20-26` y `196-198`): API pública nunca usada en toda la aplicación (verificado por grep). Código muerto y, si algún día se usa con contenido no confiable, vector de XSS vía `insertAdjacentHTML`/`innerHTML` sin sanitizar.
- **11.** **`Button.js:22-24` y `34-36`**: con `disabled:true` se fija a la vez `aria-disabled="true"` y el atributo nativo `disabled`; con `disabled` nativo el elemento ya no es focuseable, así que `aria-disabled` es redundante y puede confundir sobre cuál es la fuente de verdad.
- **12.** **`Breadcrumb.js:182-184`**: el dropdown de breadcrumb solo se cierra con `mouseleave`; no hay cierre por click-fuera ni por `Escape`, lo que en pantallas táctiles obliga a repulsar el trigger.

---

## Punto específico verificado: `components/Modal.js` vs `modules/Modal.js`

**No es duplicación real.** `components/Modal.js` es un primitivo de presentación (construye el DOM estático: overlay, `<section>` diálogo, cabecera con título/botón cerrar, contenedor de contenido) y se registra en `ComponentFactory.js` bajo el nombre `'modal'`. `modules/Modal.js` es un controlador de comportamiento: internamente hace `component.create('modal', {...})` (instancia el primitivo anterior) y añade el comportamiento real — `show()`/`close()`, focus-trap, cierre con Escape y con clic fuera, gestión de `aria-*`, devolución de foco al elemento que abrió el modal. Es el mismo patrón que `DynamicTable`/`TableComponent` o `DynamicForm`/`FormComponent`: un módulo con estado envuelve un componente "tonto" de presentación — coherente con el resto del proyecto. Único consumidor real: `UiResilienceService.js` (usado por `PluginManager` y `UserConfig` para confirmaciones). El único reproche real es el nombre idéntico en dos carpetas, que sí puede confundir en una primera lectura y quizá merezca renombrarse (p. ej. `ModalComponent` vs `ModalController`).

---

## Resumen de salud del subsistema

El toolkit de componentes tiene una arquitectura de base sólida y bien pensada: `BaseComponent`/`InputComponent` con métodos encadenables, un `ComponentFactory` centralizado con catálogo introspectable, y un patrón muy consistente entre los `Input*` triviales (10 ficheros casi idénticos en estructura). El caso "`Modal.js` duplicado" que se pidió verificar explícitamente **no es duplicación real** (ver arriba): es una separación de capas legítima, aunque el nombre idéntico en dos carpetas es una trampa real de lectura que merece renombrarse o documentarse. El problema más serio no es la duplicación en sí, sino que esa separación no incluyó nunca un ciclo de vida de destrucción, lo que produce una fuga de DOM/`id` duplicados con el uso normal de `UiResilienceService.confirm()`. El defecto más transversal es el clásico de "cada componente reimplementa en vez de heredar": una cadena de clases Tailwind repetida literalmente en 9 ficheros. No se encontraron bugs que rompan la aplicación o corrompan datos; los defectos identificados son de tipo "se ve mal" o "se degrada con el uso", propios de un proyecto construido incrementalmente con asistencia de IA y sin una pasada final de consolidación entre EPIC 5 y EPIC 9.

**Cobertura de tests:** `ComponentsTest.html` cubre razonablemente la mayoría de componentes con smoke tests (tipo de nodo devuelto, atributos clave, comportamiento del switch, cálculo de `passwordStrength`, dropdown de breadcrumb). `ModalTest.html` cubre bien el ciclo show/close/backdrop/setContent del wrapper `modules/Modal.js`. Faltan: tests para `inputDate`, `inputTime`, `inputHidden` e `inputFile` pese a usarse en producción (fecha de alta, avatar de usuario); tests de navegación por teclado en `Tabs` (`_handleKeydown` con flechas/Home/End no se ejercita en ningún test); ningún test detecta la fuga de DOM/`id` duplicados al crear múltiples `Modal`; ningún test verifica la asociación `label`/`for` de `FormField` (habría cazado el bug de `Login.js`); y ningún test verifica que `setError()` produzca realmente un borde rojo visible (solo se comprueba la presencia de la clase, no el resultado del cascade). Los layouts (`PageLayout`, `ShellLayout`, `FormLayout`, `ListLayout`) no tienen test dedicado dentro del alcance de ficheros indicado.
