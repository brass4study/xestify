# Auditoría — Páginas de la SPA

**Subsistema:** `views/pages/` (Login, EntityList, EntityEdit, PluginManager, PluginConfig, PluginItemEdit, UserConfig, UserManager, UserProfile)
**EPIC cubiertas:** EPIC 5, 6, 7, 8, 10
**Severidades:** 0 crítico · 7 mayor · 10 menor · 5 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de los 9 ficheros de `frontend/src/js/views/pages/`, verificación de componentes implicados (InputSelect para los selects leídos vía DOM), contraste contra `docs/05-frontend/` y backlog, y revisión de los runners de `frontend/tests/integration/` y specs de `frontend/tests/e2e/`.

---

## Resumen

Las 9 páginas están en un estado razonablemente sano: arquitectura MVC consistente, sin `innerHTML` ni `console.log` en todo `frontend/src/js` (el XSS queda cubierto de raíz porque `ComponentFactory`/`BaseComponent` renderizan siempre vía `textContent`), y las páginas núcleo (Login, EntityEdit, EntityList, PluginManager) bien defendidas. La deuda se concentra en tres frentes: **protección contra doble submit ausente en las páginas más recientes** (PluginConfig, UserConfig, PluginItemEdit — mientras Login y EntityEdit sí la tienen), **manejo de errores divergente entre páginas** (desde banner inline hasta borrar la página entera), y **PluginConfig.js con 1.449 líneas** acumulando cinco responsabilidades extraíbles.

## Hallazgos por severidad

### MAYOR

**1. Registro creado pero flujo roto (y duplicable) si falla el flush de un panel de extensión en EntityEdit**
- `frontend/src/js/views/pages/EntityEdit.js:86-88`
- `submit()` hace `#persistFormData()` (POST en modo alta, 566-574) y después `#flushPendingPlugins(saved)` (576-586, `Promise.all` de `panel.flush(savedId)`). Si el POST tiene éxito pero un `flush` rechaza (p. ej. el POST de un comentario pendiente falla), el `catch` de la línea 89 muestra el error y **no** llama a `#notifySaved`: el usuario sigue en el formulario en modo alta (`#recordId` sigue `null`) aunque el registro ya existe en BD. Al pulsar «Guardar» de nuevo se ejecuta otro POST y se crea un **registro duplicado**.
- Sugerencia: tras un POST de alta exitoso, asignar `this.#recordId = saved.id` antes del flush (los reintentos pasan a PUT), y en el error de flush distinguir el mensaje («el registro se guardó, pero falló X extensión»).

**2. Sin guard de doble submit ni estado pending en «Guardar» de PluginConfig (también en UserConfig y PluginItemEdit)**
- `frontend/src/js/views/pages/PluginConfig.js:1183-1185`
- El listener `saveButton.addEventListener('click', async () => { await this.saveFromDom(wrapper); })` no deshabilita el botón ni usa flag. Un doble clic en modo `create` lanza dos `POST /plugins` (`createFromDom`, 1103-1123) y registra **dos instancias del plugin**; en modo edit, dos PUT concurrentes. Contrasta con `deletePlugin()` (1207-1223), que en el mismo fichero sí usa `UiResilienceService.setButtonPending`, y con Login (`#isSubmitting`, Login.js:53-57) y EntityEdit (EntityEdit.js:72). El mismo patrón falta en `UserConfig.js:223-226` (`#submitForm`, dos PUT) y `PluginItemEdit.js:153-159` + `#save` (178-202), donde un doble clic en alta crea **dos fichas** de extensión.
- Sugerencia: aplicar en las tres páginas el patrón ya existente: flag de envío + `setButtonPending/clearButtonPending` alrededor de la llamada API.

**3. Cualquier error de acción destruye la lista completa de plugins en PluginManager**
- `frontend/src/js/views/pages/PluginManager.js:554-570`
- `renderError()` hace `this.#container.replaceChildren()` y reconstruye el layout **solo con el banner de error**. Lo invocan los `catch` de todas las acciones por fila: toggle (398), sync (421), update (454), rollback (487), delete (521) y move (538). Un fallo puntual (p. ej. un 409 al reordenar) borra la tabla entera; el usuario pierde el contexto y debe navegar fuera y volver. Además, los `finally` llaman a `clearButtonPending` sobre botones que ya no están en el DOM.
- Sugerencia: en errores de acción, conservar la lista: fijar `#feedbackMessage/#feedbackType = 'error'` y re-renderizar con `#render()` (o `layout.setNotification`), reservando `renderError()` para el fallo de carga inicial.

**4. Resolución de plugin por `tabId` en PluginItemEdit rompe extensiones multi-instancia (refactor de STORY 10.3 no aplicado aquí)**
- `frontend/src/js/views/pages/PluginItemEdit.js:90 y 108`
- Dos inversiones respecto al patrón corregido en EntityEdit.js:281-292 (que documenta que «tab.id … is not a valid module path» y que debe importarse por `tab.plugin_name`): (1) línea 90: `import(buildPluginModuleUrl(this.#pluginSlug))` usa el **tab id** (slug de instancia) como carpeta del módulo; para una instancia con slug ≠ `plugin_name` el import 404ea y la página muestra «Este plugin no soporta edición en página independiente». (2) Línea 108: `tabs.find((candidate) => candidate.plugin_name === this.#pluginSlug || candidate.id === this.#pluginSlug)` prioriza `plugin_name` sobre `id`: con dos instancias del mismo plugin, navegar a la ficha de la segunda puede casar con la **primera** pestaña (endpoint y campos equivocados). Latente hoy (optometries/contact_lenses tienen slug == plugin_name), pero el multi-instancia ya existe (`sales` como segunda instancia de `orders`).
- Sugerencia: en `#loadTab()` casar primero por `candidate.id`, y hacer el `import` con `tab.plugin_name ?? this.#pluginSlug` una vez cargada la tab, igual que EntityEdit.

**5. «Reset password» de UserConfig sin confirmación previa**
- `frontend/src/js/views/pages/UserConfig.js:571-604`
- El botón (wiring en 476-480) llama directamente a `PUT /users/{id}/password` y sustituye la contraseña por una temporal en el acto. Acción destructiva e irreversible para la sesión de ese usuario, y en el mismo fichero el borrado (`#deleteUser`, 618-627) sí pasa por `UiResilienceService.confirm`. Un clic accidental invalida la contraseña real de un usuario.
- Sugerencia: envolver `#resetPassword` en `UiResilienceService.confirm({...})` con el mismo patrón que `#deleteUser`, y poner el botón en pending durante la llamada.

**6. PluginConfig.js: 1.449 líneas con cinco responsabilidades extraíbles**
- `frontend/src/js/views/pages/PluginConfig.js:12-1449`
- Es el fichero más largo del frontend y mezcla: (1) modo `create` (catálogo `/plugins/available`, `buildCreateState`/`applyTypeSelection`/`createFromDom`); (2) tabla de campos con sus 9 renderizadores de celda (369-394, 618-860); (3) tabla de relaciones con otros 7 (396-586); (4) el editor modal de opciones de `select` (874-998, un sub-componente completo con su propio ciclo render/sync); y (5) la maquinaria de sincronización DOM↔estado (`syncStateFromDom`/`collectRowsFromDom`/`readRowFromDom`/`readRelationRowFromDom`, 1007-1058 y 1330-1403). Literales de clase de cabecera/celda duplicados entre `renderFieldsTable` (376-379) y `renderRelationsTable` (403-406).
- Sugerencia: extraer a `views/modules/`: `FieldOptionsEditorModal`, `PluginFieldsTableSection` y `PluginRelationsTableSection` (cada una con su render + lectura de DOM), dejando en la página estado, guardado y modo create. Solo esto baja el fichero a ~500 líneas.

**7. Feedback de página reimplementado en 4 páginas contra la regla documentada**
- `frontend/src/js/views/pages/PluginManager.js:20-21`
- `docs/05-frontend/arquitectura.md:56-57` establece que el comportamiento transversal de UX vive en `UiResilienceService`, «nunca reimplementado por página». Sin embargo el par mensaje/tipo + banner se reimplementa cuatro veces: `PluginManager` (`#feedbackMessage/#feedbackType` + `#appendFeedback`, 20-21 y 134-145), `PluginConfig` (`#message/#messageType` + `clearNotice`/`noticeType`, 23-24 y 1125-1140), `UserConfig` (`setPageMessage` + `#mountFeedback`, 131-134 y 696-707) y `UserManager` (`#setMessage` + banner inline, 83-90 y 235-238), cada una con matices distintos (PluginConfig soporta `info`, UserConfig no; PluginManager duplica el mensaje en notificación global).
- Sugerencia: un helper compartido (en `UiResilienceService` o en los layouts: `layout.setPageFeedback(type, message)`) y migrar las cuatro páginas.

### MENOR

**8. La ruta de configuración de plugin no comprueba admin (inconsistencia de gating)**
- `frontend/src/js/controllers/AppController.js:767-772`
- `showPluginsPage` (775-778), `showUsersPage` (822-825) y `showUserConfigPage` (845-848) cortan con «Acceso denegado: solo administradores», pero `showPluginConfigPage` monta `PluginConfig` sin comprobar `SessionModel.currentUserIsAdmin()`. Un no-admin que navegue a `#/plugins/persons` ve la página admin montarse y fallar con el error crudo de la API (el backend sí protege el endpoint: no es agujero de seguridad, pero sí una UI admin operativa a medias).
- Sugerencia: añadir el mismo guard al principio de `showPluginConfigPage`.

**9. Comprobaciones `response?.ok === false` inalcanzables (código muerto) en PluginManager**
- `frontend/src/js/views/pages/PluginManager.js:61-67 y 384-386`
- `Api.#request` (ApiClientModel.js:103-117) devuelve `{ data, meta }` cuando `ok === true` y **lanza** `ApiError` en cualquier otro caso; la respuesta nunca llega al llamador con `.ok`. Los bloques `if (pluginsResponse?.ok === false) { throw ... }` de `#refreshData` y `#handleActionClick` son inalcanzables, igual que los fallbacks `response?.data ?? response` (69-70, 409).
- Sugerencia: eliminar esos bloques y confiar en el `catch`, como el resto de páginas.

**10. Doble canal de error en UserConfig donde uno es inoperante**
- `frontend/src/js/views/pages/UserConfig.js:561-568`
- En el `catch` de `#submitForm` se escribe `errorNode.textContent` y `errorNode.hidden = false`, e inmediatamente se llama a `this.#render()`, que reconstruye el formulario y crea un `errorNode` nuevo **oculto** (283-288): la escritura se pierde y solo sobrevive el banner. En cambio, en `#resetPassword` (598-601) y `#deleteUser` (643-646) el mismo `errorNode` sí sobrevive → el error se muestra dos veces (nodo + banner).
- Sugerencia: elegir un único canal (el banner de `#mountFeedback`) y eliminar el `errorNode` inline, o dejar de re-renderizar tras escribirlo.

**11. Los detalles de error del backend se descartan en Login y se sustituyen por «Requerido.»**
- `frontend/src/js/views/pages/Login.js:335-345`
- `applyFieldErrorDetails` solo mira si `details.email`/`details.password` existen y pinta el literal fijo `'Requerido.'`, aunque el backend haya devuelto otro mensaje. La validación cliente (91-118) y la del servidor divergen en el copy, y cualquier detail distinto de «requerido» se muestra mal etiquetado.
- Sugerencia: usar `details.email[0]`/`details.password[0]` como texto con fallback a «Requerido.».

**12. Carga de la ficha de PluginItemEdit descargando la colección completa**
- `frontend/src/js/views/pages/PluginItemEdit.js:115-131`
- `#loadContent` hace GET del endpoint de listado y busca el item con `rows.find(...)`. Funciona hoy porque los endpoints de extensión no paginan, pero descarga N registros para usar 1 y se romperá en silencio («No se encontró la ficha») el día que el endpoint pagine. Además los `catch` de `#loadTab`/`#loadContent` degradan cualquier error (red, 500) al mismo mensaje de «no encontrado».
- Sugerencia: endpoint de detalle (`GET {endpoint}/{itemId}`) o al menos distinguir «error de carga» de «no existe».

**13. Constructor legacy muerto, doble render y mapping de filas duplicado en UserManager**
- `frontend/src/js/views/pages/UserManager.js:37-48`
- (1) La rama `if (Array.isArray(options))` (37-41) no la usa nadie: AppController.js:828 y `UserManagementTest.html` pasan objeto de opciones. (2) El constructor renderiza (47) y `init()` vuelve a cargar y renderizar (50-53): parpadeo de estado vacío en cada navegación. (3) El mapeo usuario→fila está copiado dos veces dentro de `onRefresh` (103-109 y 123-129).
- Sugerencia: eliminar la rama legacy, no renderizar en el constructor cuando hay `api`, y extraer `#toRow(user)`.

**14. Borrar un registro en EntityList devuelve siempre a la página 1 y resetea el orden**
- `frontend/src/js/views/pages/EntityList.js:211`
- `#deleteRecord` recarga con `this.loadEntity(slug)`, que pide `#recordsUrl(slug)` con los valores por defecto (página 1, sort `created_at asc`, 217-225). Si el usuario estaba en la página 5 con otra ordenación, pierde su posición tras cada borrado.
- Sugerencia: conservar el último `query` usado (ya llega por `onQueryChange`, 151-164) y reutilizarlo en la recarga post-borrado.

**15. `loadActiveEntityOptions` de PluginConfig silencia el fallo y deja la extensión sin destino visible**
- `frontend/src/js/views/pages/PluginConfig.js:1418-1435`
- El `catch {}` devuelve `[]`: si `GET /entities` falla, el combo «Relación de extensión» y los selects «Entidad destino» se renderizan vacíos sin aviso, y un guardado posterior puede degradar `target_entity`. Además `init()` en modo edit encadena dos awaits secuenciales (91-92) que podrían ir en `Promise.all` como el modo create (71-74).
- Sugerencia: propagar el fallo como aviso (`#message` tipo warning) y paralelizar las dos cargas.

**16. Deriva docs↔código en rutas, inventarios y recuentos**
- `docs/05-frontend/navegacion-anatomia.md:69-78`
- Cuatro derivas verificadas: (1) navegacion-anatomia.md mantiene `#/entity/:slug/new`, `:id` y `:id/:tab` como «rutas reservadas» cuando están implementadas y despachadas (AppController.js:599-621); (2) `arquitectura.md:44-45` omite `PluginItemEdit.js` en el inventario de pages y su lista de models omite `UserModel.js` y `EntityRecordModel.js`; (3) `README.md:114` y `testing-ui.md:8` dicen «19 runners HTML» pero hay 22; (4) `backlog.md:950` titula STORY 8.5 con la ruta `#/usuarios` cuando la real es `#/users`.
- Sugerencia: pase de sincronización documental: rutas a «actuales», inventarios completos, recuento actualizado y ruta canónica anotada en el backlog.

**17. Credenciales semilla incluidas en el bundle servido a todos los clientes**
- `frontend/src/js/views/pages/Login.js:22-23`
- `QUICK_ACCESS_ADMIN`/`QUICK_ACCESS_USER` (admin123/usuario123) viajan en el JS a cualquier visitante aunque `APP_DEBUG` sea false (el flag solo oculta los botones, 288-291). Documentado con NOSONAR como decisión de STORY 10.1 y son credenciales de seeder de desarrollo, pero si una instalación real conserva los usuarios semilla, el JS público revela credenciales admin válidas.
- Sugerencia: servir las credenciales de quick-access desde `/health` (solo con `debug === true`), o documentar en operaciones que el deploy exige cambiar las contraseñas semilla.

### NIT

**18. Selector `[name="${fieldName}"]` sin escapar en EntityEdit**
- `frontend/src/js/views/pages/EntityEdit.js:455`
- Un `field` con comillas o corchetes en su clave (las claves las define el admin en PluginConfig) haría lanzar a `querySelector` y abortaría el pintado de los errores de campo.
- Sugerencia: `CSS.escape(fieldName)`.

**19. `#canRollback` acepta `'t'`, `1` y `'1'`**
- `frontend/src/js/views/pages/PluginManager.js:368-373`
- Normalización de booleanos de PostgreSQL (`'t'`) filtrada al frontend; el backend debería serializar `can_rollback` como boolean JSON y este código quedar en `=== true`.
- Sugerencia: normalizar en `PluginRepository` y simplificar aquí.

**20. Badge de «Actualización disponible» sin colores**
- `frontend/src/js/views/pages/PluginManager.js:270-274`
- El span solo tiene clases estructurales (`inline-flex rounded-full px-2.5 …`), sin `bg-*`/`text-*`, a diferencia del badge de estado (284). Se renderiza como texto plano con forma de píldora invisible.
- Sugerencia: añadir tono (p. ej. `bg-sky-100 text-sky-700`).

**21. `<img>` de avatar sin clases de tamaño en la tabla de UserManager**
- `frontend/src/js/views/pages/UserManager.js:151-153`
- El avatar se inserta sin `h-full w-full object-cover` (que sí tiene la variante de UserConfig.js:304-307): imágenes no cuadradas deforman la celda.
- Sugerencia: reutilizar un único builder de avatar (tercera duplicación junto a `getInitials` en ambos ficheros).

**22. Indentación anómala y literales i18n ausentes en PluginConfig**
- `frontend/src/js/views/pages/PluginConfig.js:167-172`
- Bloques con indentación desalineada (167-179) y toda la página en literales castellanos sin `t()` (igual que UserConfig y PluginItemEdit parcialmente), mientras PluginManager/EntityList/EntityEdit ya usan `t(clave, fallback)` — incluso dentro de EntityEdit conviven `t('forms.save')` (140) y `'Borrar'` hardcodeado (165).
- Sugerencia: pase de homogeneización de `t()` sobre las tres páginas rezagadas.

## Cobertura de tests

Los runners cubren bien las páginas veteranas: **LoginTest** (16 tests, incluidos doble submit re-entrante, inputs deshabilitados en vuelo, aviso de sesión caducada y quick-access), **EntityEditTest** (~33: create/edit, errores estructurados, retry, tabs de relación e hidratación de selects), **EntityListTest** (14: paginación remota, borrado con modal, descripción de manifest), **PluginManagerTest** (~29: secciones, sync, update/rollback/delete con confirmación, move con límites, supervivencia a re-render de densidad), **PluginConfigTest** (20: campos base bloqueados, reorden, layers, relaciones, preservación de ediciones no guardadas), **UserManagementTest** (9) y **UserProfileTest** (9), más **E2ETest.html** de flujo list→create→reload. La suite Playwright (5 specs, 13 tests) cubre login (7), CRUD de entidad (2), navegación de shell (2), activación de plugin (1) y tema (1). **Huecos:** **PluginItemEdit no tiene ningún runner ni spec** (página completa de STORY 10.5 sin test alguno — precisamente donde están los hallazgos de multi-instancia); no hay tests del **modo create de PluginConfig hasta el POST** (ni de duplicidad); no hay tests de **doble submit** en PluginConfig/UserConfig/PluginItemEdit (sí para Login, incluso en E2E); no se testea el camino **flush-de-plugin-falla tras guardar** de EntityEdit ni que `renderError` de PluginManager destruye la lista (el test «shows error banner on API failure» solo cubre el fallo de carga inicial). Assert desalineado: `UserManagementTest.html:106` pasa `currentUserId: 'u-admin'` a `UserManager`, opción que esa clase no acepta (pertenece a `UserConfig`) — el test pasa pero no prueba lo que sugiere. En E2E no hay specs de gestión de usuarios, PluginConfig ni fichas de extensión. Los recuentos documentados («19 runners», «12/12 specs» en sesion.md) están desfasados frente a los 22 runners y 13 tests E2E actuales.

## Observaciones transversales

1. **`resolveContainer()` copiado 7 veces** (Login 437-450, EntityList 362-375, EntityEdit 642-655, PluginManager 572-581, PluginConfig 1437-1448, UserConfig 805-818, UserManager 244-257) con dos variantes divergentes: PluginConfig/PluginManager omiten el `typeof container === 'string'`. Ya existe `UiResilienceService.resolveHost` (186-199) que hace exactamente esto — debería ser el único punto.
2. **Manejo de errores con cuatro estrategias distintas por página**: banner inline persistente (EntityList), banner + notificación global (PluginManager, EntityEdit), solo banner (PluginConfig — su `renderError` no notifica) y solo `setViewState` (PluginItemEdit). El usuario recibe el mismo tipo de fallo con UX diferente según la pantalla.
3. **Dos generaciones de páginas conviven**: las migradas plenamente al patrón EPIC 9/10 (PluginItemEdit, EntityEdit — render tokens, `UiResilienceService` en todo) y las que retienen mecánica propia (PluginConfig re-renderiza el árbol completo con `syncStateFromDom` manual; UserConfig gestiona drafts a mano). El guard de doble submit marca exactamente esa frontera generacional.
4. **Copys duplicados entre AppController y las páginas**: títulos/subtítulos por defecto definidos dos veces (p. ej. «Sincroniza, activa y configura plugins del sistema.» en AppController.js:1427 y PluginManager.js:42; ídem PluginConfig y UserManager), con riesgo de divergencia silenciosa.
5. **Puntos fuertes a preservar**: el patrón render-token de EntityEdit (437-445) contra renders obsoletos, la hidratación diferida de selects de relación con `Promise.allSettled`, el interceptor único de sesión caducada (ninguna página comprueba 401 por su cuenta), y el escapado sistemático vía `textContent` que elimina la clase entera de XSS.
