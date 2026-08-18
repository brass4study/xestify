# Auditoría — Arquitectura SPA frontend: shell, routing, estado y modelos

**Subsistema:** Shell SPA, routing, sesión, API client, tema y modelos
**EPIC cubiertas:** EPIC 9 (bases EPIC 3/5, refuerzos EPIC 10)
**Severidades:** 1 crítico · 5 mayor · 11 menor · 8 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Lectura íntegra de `frontend/src/js/app.js`, `controllers/` (AppController 1.829 líneas, RouteMapController, RouteController, PluginRouteController), `models/` (los 12), `services/UiResilienceService.js`, `frontend/src/index.html`, `frontend/tailwind.config.cjs`, `tailwind.src.css` y `playwright.config.js`, con contraste contra `docs/05-frontend/`, backlog y los runners/specs del ámbito.

---

## Resumen

El subsistema está en un estado notablemente maduro para un frontend vanilla: el mapa de rutas bidireccional (`RouteMapController`) está centralizado y bien testeado, el cliente API con interceptor de sesión caducada y token deslizante es sólido, y no existe ni un solo uso de `innerHTML` en `frontend/src/js` (el riesgo de XSS por render está estructuralmente contenido vía `ComponentFactory` + `textContent`). La deuda real se concentra en `AppController.js`: es un god object que además de orquestar renderiza DOM de notificaciones y resuelve breadcrumbs/plantillas, y su manejo de errores por página pisa el flujo de sesión caducada que el propio proyecto documenta como resuelto. Hay además una carrera de navegación asíncrona sin cancelación que el propio helper E2E reconoce y esquiva con un wait.

## Hallazgos por severidad

### CRÍTICO

**1. El fallback de error de página pisa el login recién renderizado tras un 401 (sesión caducada a mitad de navegación)**
- `frontend/src/js/controllers/AppController.js:951/963/860/941`
- `Api.#request` invoca `onSessionExpired()` de forma **síncrona antes de lanzar** el `ApiError` (ApiClientModel.js:109-111). Ese handler ejecuta `clearAuth()` + `renderLogin({sessionExpired:true})`, que renderiza el login dentro de `this.contentContainer` (AppController.js:180-207). Acto seguido, la excepción propaga al flujo que hizo la petición, y sus ramas de error llaman a `showPlaceholder(...)`: `showEntityEdit` (951, 963), `showUserConfigPage` (859-861), `showEntityList` (940-942). `showPlaceholder` (1194-1211) usa como target `this.contentContainer` — que tras el interceptor **es el contenedor del login** — y ejecuta `target.replaceChildren(placeholder)` (1210): **borra el formulario de login y deja al usuario ante un placeholder de error sin forma de autenticarse** salvo recargar a mano. Ocurre en el flujo más normal que existe (token caducado + clic en un registro/lista/usuario). Contradice `docs/05-frontend/arquitectura.md:99-108`, que da este flujo por cerrado en STORY 10.1. `hydrateUiPreferences`, `loadEntitiesForNav` y `loadCurrentUserProfile` sí tienen la guarda `isUnauthorizedError` — el patrón existe, pero no se aplicó a los flujos de página.
- Sugerencia: propagar la distinción 401 igual que en los tres métodos blindados: `loadEntitySchema`/`loadEntityRecord` devuelven un marcador de "sesión expirada" (o relanzan y se comprueba `isUnauthorizedError` en el llamador) y los `show*` abortan sin `showPlaceholder`. Alternativa más barata y global: `showPlaceholder` no-op cuando `this.dashboardApi === null` (estado inequívoco de "auth limpiada").

### MAYOR

**2. Carrera de navegaciones asíncronas sin cancelación: la página vieja puede pintarse sobre la nueva**
- `frontend/src/js/controllers/AppController.js:535-565` + `RouteController.js:19`
- `navigateTo` y los `show*` encadenan varios `await` sin token de cancelación. El handler de `hashchange` dispara `void this.navigate(...)` sin serializar. Con dos navegaciones rápidas, la primera —más lenta— termina después y ejecuta su `replaceChildren()` + montaje **sobre el contenido de la segunda**: URL y navbar apuntan a la página B con el contenido de la A, y `currentEntityEdit`/`currentEntityRoute` (1031-1032) quedan apuntando a una instancia obsoleta. No es teórico: el helper E2E lo admite y lo esquiva — `frontend/tests/e2e/tests/_helpers.js:10-13`: *"navigating away too early races its in-flight request against the page that follows and can leave stale content rendered"*.
- Sugerencia: contador de época de navegación (`this.navigationEpoch++` al entrar en `navigateTo`; capturar el valor y comprobarlo tras cada `await`, abortando si cambió). Opcionalmente `AbortController` por navegación pasado a `Api`.

**3. Host de notificaciones globales queda desconectado del DOM en la pantalla de login: los toasts de error se vuelven invisibles**
- `frontend/src/js/controllers/AppController.js:1221`
- `#resolveNotificationHost` crea un host de respaldo colgado de `this.container` (1225-1232) cuando no hay shell. Pero `renderLogin` → `PageLayout.build()` ejecuta `this.#container.replaceChildren()` (PageLayout.js:197), **desconectando ese host**. La comprobación de reutilización (1221) es solo `instanceof HTMLElement` — cierta también para un nodo desconectado — así que todas las notificaciones globales posteriores en login (errores JS/red del `globalErrorHandler`, 71-82) se renderizan en un nodo huérfano y no se ven. La secuencia se da siempre: `renderLogin` llama a `renderGlobalNotifications()` (170) *antes* del `build()` (176-179). El test "creates a fallback floating host" solo cubre el estado previo a la desconexión.
- Sugerencia: exigir `this.globalNotificationHost.isConnected` en 1221; si no lo está, recrearlo/re-adjuntarlo. O crear el host de login como zona propia del `PageLayout` de login.

**4. `confirm()` nunca resuelve si el usuario cierra con ESC o clic en el backdrop; el overlay queda huérfano**
- `frontend/src/js/services/UiResilienceService.js:133-184` + `views/modules/Modal.js:58-69`
- `confirm()` solo resuelve desde los listeners de los dos botones (167-168). `Modal` cierra también por ESC (64-69) y clic en overlay (58-62) vía `this.close()`, que ni notifica ni destruye. La promesa que el llamador está `await`-eando (borrados de usuario/plugin/registro) queda **pendiente para siempre** — el flujo muere en silencio — y el overlay oculto nunca pasa por `modal.destroy()`, acumulando nodos. Ningún test cubre ESC/backdrop.
- Sugerencia: `Modal` ya recibe `onClose` en el `component.create('modal', ...)` (Modal.js:20): `confirm()` debería construir el modal con un callback de cierre que ejecute `closeWith(false)`, de modo que cualquier vía de cierre resuelva `false` y destruya.

**5. Navegar a `#/login` autenticado detiene el router: back/forward muertos y sesión intacta bajo un login aparente**
- `frontend/src/js/controllers/AppController.js:594-597` + `:161`
- El handler `'login'` de `handlePageNavigation` llama a `renderLogin()` (596) sin `clearAuth()`. `renderLogin` ejecuta `this.router.stop()` (161), que **elimina el listener de `hashchange`**. Desde ese momento, back/forward y ediciones del hash cambian la URL sin efecto — la SPA queda congelada en el login hasta re-autenticarse o recargar. Además el token sigue en `SessionModel` y `localStorage`: la pantalla aparenta sesión cerrada sin estarlo (un bookmark a `#/login` produce este estado).
- Sugerencia: en el handler de `'login'`: si hay sesión activa, redirigir al fallback en vez de renderizar login; o, si `#/login` debe forzar logout, llamar a `clearAuth()` antes.

**6. God object confirmado: seis responsabilidades extraíbles, con vista y modelo incrustados en el controlador**
- `frontend/src/js/controllers/AppController.js:43-1829`
- Además de orquestar boot/sesión/navegación, el fichero contiene: (1) **render de vistas** (1213-1388): `renderPageNotification`/`renderGlobalNotifications` construyen DOM con animaciones y tonos — una vista de notificaciones que viola `arquitectura.md:48-57` y duplica la paleta del componente `alert`; (2) **resolución de plantillas/breadcrumbs** (1390-1759): `buildTemplateDefinition` + 7 resolvers + `makeBreadcrumbItems`, ~370 líneas declarativas; (3) **lógica de modelo** (1785-1829): `normalizeRecordContent`/`extractRecordContentObject`/`extractRecordFields` pertenecen a `EntityRecordModel.js`; (4) persistencia de preferencias UI (445-533); (5) registro de handlers + factorías de páginas (588-1125); (6) extracción de identidad de usuario **triplicada** (424-433, 805-811, 1156-1165).
- Sugerencia: extracciones incrementales sin cambiar contratos: `PageTemplateResolver`, `NotificationView` (views/modules), mover `normalizeRecordContent` a `EntityRecordModel.js`, y un `SessionModel.getDisplayIdentity()`. Solo esas cuatro bajan ~700 líneas.

### MENOR

**7. El título del toast de error global usa la clave i18n equivocada**
- `frontend/src/js/controllers/AppController.js:76`
- `title: t('ui.error.generic', 'Error')` — en `es`, `ui.error.generic` es "Ha ocurrido un error inesperado.", así que el diálogo muestra ese texto como título **y** como mensaje, duplicado. La clave correcta `ui.error.title` ('Error') existe en I18nModel.js:9 y está sin usar.
- Sugerencia: `title: t('ui.error.title', 'Error')`.

**8. Back/forward entre tabs pierde el resumen del registro en el breadcrumb**
- `frontend/src/js/controllers/AppController.js:582`
- `activateCurrentEntityRoute` llama a `buildTemplateDefinition(page)` **sin contexto**, así que `resolveEntityRecordTemplate` cae a `Registro: ${entityData.recordId}` (1610). El clic directo de tab (991) sí pasa `{ recordSummary }` y muestra "Registro: Juan Pérez". El mismo recorrido enseña dos breadcrumbs distintos según se llegue por clic o por historial.
- Sugerencia: guardar `recordSummary` en `this.currentEntityRoute` al crearlo (1032) y pasarlo como contexto en 582.

**9. `showPluginItemEdit` no aborta con schema/registro nulos, a diferencia de `showEntityEdit`**
- `frontend/src/js/controllers/AppController.js:721-765`
- Si `loadEntitySchema` o `loadEntityRecord` devuelven `null` (fallo no-401), el método continúa: construye breadcrumbs degradados y monta `PluginItemEdit` igualmente (751-764), en vez del early-return con placeholder de `showEntityEdit` (950-953). En el caso 401 agrava el crítico (instancia la página con `api` nulo).
- Sugerencia: replicar las guardas de `showEntityEdit` tras cada carga (y la guarda 401 del arreglo del crítico).

**10. Deriva docs↔código en el mapa de rutas: `/new` documentado vs `#new` implementado, y rutas "reservadas" ya implementadas**
- `docs/11-backlog/backlog.md:1069` y `docs/05-frontend/navegacion-anatomia.md:69-78`
- (1) Backlog (STORY 9.6) y la tabla "Rutas reservadas" documentan el alta como `#/entity/:slug/new`; el código implementa `#/entity/:slug/#new` (RouteMapController.js:10). Quien siga la doc obtiene "No se pudo cargar la ficha del registro." (2) `#/entity/:slug/:id` y `:id/:tab` siguen en "reservadas" pese a estar implementadas (la tabla "Rutas actuales" omite entityCreate/entityDetail/entityTab pero incluye las de plugin-item que dependen de ellas). (3) Las rutas de resultado mezclan `#/result/empty` y `#/resultado/403`, y FrontendArchitectureTest.html:179-183 consagra que **no** deben existir en el mapa — la doc sigue reservándolas.
- Sugerencia: actualizar ambas tablas con la sintaxis `#new` real y retirar/marcar descartadas las rutas de resultado.

**11. Dependencias CDN en runtime (Google Fonts + Font Awesome) contradicen el runtime canónico same-origin**
- `frontend/src/index.html:8-11`
- El shell carga `fonts.googleapis.com`, `fonts.gstatic.com` y `cdnjs.cloudflare.com/.../font-awesome/6.5.2` en runtime. El backlog (980) y `decisiones-tecnicas.md` presumen de "sin dependencia runtime del Play CDN", pero quedan estas dos: en intranet/offline (escenario natural de un mini-ERP para ópticas) desaparecen iconos y tipografías, y se filtra la IP del usuario a terceros. Sin SRI.
- Sugerencia: auto-hospedar woff2 e iconos (o subset de FA) bajo `frontend/src/`, como ya se hizo con Tailwind.

**12. Build de Tailwind no reproducible desde el repo: globs relativos al CWD raíz y sin script de build versionado**
- `frontend/tailwind.config.cjs:2-6`
- Los `content` empiezan por `./frontend/...`: el CLI **debe** ejecutarse desde la raíz del repo; desde `frontend/` genera una hoja vacía (ya pasó: sesion.md:439-442). No hay `package.json`/script npm que fije el comando; la regeneración es manual y de memoria. Hoy no hay desincronización (generated más nuevo que src; 32 KB), pero cualquier clase nueva sin rebuild falla en silencio. `src/css/main.css` es además un fichero muerto (solo un comentario) que sigue desplegándose.
- Sugerencia: `package.json` mínimo en la raíz con `"build:css": "tailwindcss -c frontend/tailwind.config.cjs -i ... -o ... --minify"`, y eliminar `src/css/main.css`.

**13. Sin timeout ni cancelación en `fetch`**
- `frontend/src/js/models/ApiClientModel.js:75-118`
- Ninguna petición tiene `AbortController`/timeout. Con un backend colgado, los placeholders "Cargando formulario..." quedan eternos sin feedback, y las navegaciones abandonadas siguen consumiendo y resolviéndose tarde.
- Sugerencia: `AbortSignal.timeout(n)` por defecto en `#request` + aceptar `signal` externo para cancelar al navegar.

**14. JWT en `localStorage` sin comprobación de expiración local**
- `frontend/src/js/models/SessionModel.js:55-57` + `AppController.js:128-146`
- El token vive en `localStorage`: cualquier XSS futura lo exfiltra (mitigado hoy por la ausencia total de `innerHTML`, pero conviene documentar la decisión). Además `start()` usa el token almacenado sin mirar `exp`, pese a que `decodeJwtPayload` (185-203) sabe decodificarlo: cada arranque con token caducado paga un round-trip a `/users/me` para descubrirlo vía 401.
- Sugerencia: comprobar `exp` en `readStoredSession`/`start()` y descartar el token vencido localmente; registrar en `decisiones-tecnicas.md` la elección localStorage-vs-cookie con su mitigación.

**15. La expiración de sesión pierde el deep-link: tras re-login se aterriza en el fallback**
- `frontend/src/js/controllers/AppController.js:172-174`
- `renderLogin` hace `replaceState(null, '', '#/login')` destruyendo la ruta previa; tras autenticarse, `renderDashboard` navega al fallback (primera entidad), no a donde estaba. En un ERP con fichas profundas es fricción real.
- Sugerencia: guardar el hash previo (memoria o `sessionStorage`) antes del `replaceState` y usarlo como `initialPage` tras el login si sigue válido.

**16. Infraestructura i18n muerta en producción y textos hardcodeados por todo el controlador**
- `frontend/src/js/models/I18nModel.js:79-87`
- `setLocale` no se invoca desde producción (solo tests) y el catálogo `en` es inalcanzable. Mientras, `AppController` incrusta decenas de literales castellanos (placeholders 555/563/668..., breadcrumbs y títulos 1417-1660) sin `t()`, contradiciendo el contrato "copy preparado para i18n" de navegacion-anatomia.md:123-133. O se avanza EPIC A1 o se asume el monolingüe; el estado intermedio es lo peor de ambos.
- Sugerencia: corto plazo, mover los literales de `AppController` a claves de `I18nModel`; documentar que `en` es aspiracional.

**17. `AppController` no tiene `destroy()`: listeners globales y suscripciones imposibles de liberar**
- `frontend/src/js/controllers/AppController.js:81-85`
- El constructor registra `window.addEventListener('error'/'unhandledrejection')`, `NotificationModel.subscribe` y `setSessionExpiredHandler` sin vía de desmontaje. En la app es singleton y no fuga; pero los runners (UiResilienceTest crea ~10 instancias) dejan todos los handlers vivos compartiendo `NotificationModel`, lo que explica los dobles `requestAnimationFrame` de los tests y hace frágil cualquier futuro re-boot.
- Sugerencia: `destroy()` simétrico al constructor y usarlo en los tests.

### NIT

**18. `setClassName` del diálogo de notificación asignado dos veces; la primera es código muerto**
- `frontend/src/js/controllers/AppController.js:1327-1337`
- El diálogo se crea con una `className` completa (1328) que se sobreescribe íntegra nueve líneas después con la variante de acento (1337).
- Sugerencia: construir la clase una sola vez con el acento resuelto.

**19. Generador de id de notificación con doble `Date.now()`**
- `frontend/src/js/services/UiResilienceService.js:13`
- El fallback repite `Date.now()` — la segunda llamada no añade entropía útil.
- Sugerencia: `notify-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`.

**20. `notificationRenderScheduled` es propiedad implícita**
- `frontend/src/js/controllers/AppController.js:99-121`
- Se lee en 99 y se asigna en 108/111 sin inicializarse en el constructor, a diferencia de las otras 15 propiedades declaradas en 46-61.
- Sugerencia: `this.notificationRenderScheduled = false;` en el constructor.

**21. `setEntities` no notifica a los suscriptores**
- `frontend/src/js/models/SessionModel.js:143-145`
- `setUser`/`reset` disparan `notify()`, pero `setEntities` no: el refresco de navbar depende del acople manual en `refreshNavEntities` (AppController.js:909-914). Estado semi-reactivo inconsistente.
- Sugerencia: notificar también en `setEntities` (o documentar por qué no).

**22. Tokens internos con `:` como separador construidos con segmentos ya decodificados**
- `frontend/src/js/controllers/RouteMapController.js:372-397`
- `resolveEntityPage` inyecta `decodeSegment(...)` directamente en `entity-record:${slug}:${recordId}`: un slug o id con `:` (p. ej. `%3A`) desplaza el parseo de `entity-tab`/`plugin-item` (split posicional en 414-420). El tabId sí está blindado y testeado; el recordId no — el test 208-210 consagra que `recordId` puede contener `:`, creando asimetría entre parsers. Con UUIDs reales no ocurre; fragilidad latente del formato.
- Sugerencia: escapar `:` en los segmentos al construir el token, o usar un separador imposible en URL decodificada.

**23. Runner E2ETest.html con infraestructura duplicada, nombre engañoso y comentario obsoleto**
- `frontend/tests/integration/E2ETest.html:24-93`
- Reimplementa `test()`, `mockFetch` y `separator` locales en vez de `helpers.js` como los otros 21 runners; se llama "E2ETest" siendo de integración (colisiona con la carpeta `e2e/` real); y el comentario "Simulate the flow wired in app.js" (372) apunta a un wiring que desde EPIC 9 vive en `AppController` — resto del refactor 9.4.
- Sugerencia: renombrar (p. ej. `EntityFlowTest.html`), migrar a `helpers.js` y actualizar el comentario.

**24. Preset `cyan` inalcanzable en ThemeModel**
- `frontend/src/js/models/ThemeModel.js:31-42` vs `200-210`
- `PRIMARY_THEME_PRESETS.cyan` está definido pero no aparece en `UI_THEME_SCHEMA.themeColor`, así que `normalizeOption` degrada cualquier `themeColor:'cyan'` a `blue`. Código muerto (o falta la opción en el panel, que renderiza 9 colores — el test fija 9).
- Sugerencia: añadir cyan al schema (y actualizar el assert a 10) o borrar el preset.

**25. `getUiPreferences` ejecuta ~5 normalizaciones completas por llamada**
- `frontend/src/js/models/ThemeModel.js:321-323` + `364-377`
- `getUiPreferences` → `mergeUiPreferences(defaults, current)`, que invoca `normalizeUiPreferences` tres veces más la normalización final. Se llama en cada `notifyUi`, `syncUiPreferences` y render de `applyUiPreferencesToDocument` (que normaliza otra vez). Sin impacto perceptible, pero trabajo redundante en el camino caliente del WYSIWYG.
- Sugerencia: mantener `currentUiPreferences` siempre normalizado y devolver una copia sin re-mergear.

## Cobertura de tests

La cobertura del núcleo de routing es la mejor del subsistema: `FrontendArchitectureTest.html` verifica el mapa bidireccional completo (centinelas `#new`, query strings, tabId con `:`, rutas fuera de mapa) más entrada directa, refresh, back/forward y el fallback de `#/home` con `RouteController` real; también fija por contrato la estructura de `ShellLayout`/`PageLayout` y la ausencia de APIs legacy. `ApiTest.html` cubre el envelope, el interceptor 401 y el token deslizante; `UiResilienceTest.html` cubre confirm (solo vía botones), notificaciones página/global/fallback, `hydrateUiPreferences` ante 401 y que `clearAuth` no toca preferencias; ThemeModel/ThemeSettingsPanel cubren normalización y WYSIWYG. Los 5 specs Playwright cubren los happy paths. **Huecos:** nada cubre la expiración de sesión *a mitad de navegación de página* (el crítico pasaría desapercibido); ninguna prueba de navegaciones rápidas concurrentes (la carrera se esquiva con `waitForSelector` en `_helpers.js:13` en lugar de testearse); `confirm()` con ESC/backdrop sin cubrir; el host de notificaciones en login solo se prueba antes de la desconexión; `handlePageNavigation`/`navigateTo` sin tests de despacho; `SessionModelTest.html` (6 asserts triviales) no toca `persistUserSnapshot`, `readStoredSession`, `buildFallbackUser`, `normalizeUserProfile` ni `decodeJwtPayload`; no hay E2E de las rutas de plugin-item (STORY 10.5) ni del breadcrumb tras back/forward. Además `testing-ui.md:8` dice "19 runners" y hay 22.

## Observaciones transversales

1. **El patrón "guarda 401" existe pero se aplicó a medias.** Tres cargadores lo implementan con comentario idéntico y cinco flujos de página no. Rastro típico de un refactor (STORY 10.1) que blindó el arranque pero no la navegación — convertir la guarda en un helper único (`this.guard401(promise)`) para que no pueda olvidarse.
2. **Controlador que renderiza y modela.** La disciplina MVC que la documentación predica (y las vistas cumplen) se rompe justo en `AppController`: DOM de notificaciones, catálogos de breadcrumbs y normalización de registros conviven con la orquestación. Todos los hallazgos MAYOR/MENOR de ese fichero comparten esta causa raíz.
3. **Doble render estructural en cada navegación.** `navigateTo` → `applyTemplateForPage` monta plantilla/breadcrumbs/título, y acto seguido casi todos los `show*` los reconstruyen con `PageLayout.create(...)` + `setBreadcrumbs`. Invisible para el usuario, pero duplica trabajo y multiplica los puntos de divergencia del breadcrumb.
4. **Los tests conocen los bugs que el código no arregla.** Dos comentarios de la suite E2E documentan defectos de producción como workarounds del test (la carrera de navegación en `_helpers.js`, la dependencia de fixtures ajenos en `plugin-manager.spec.js`). Cuando un helper de test necesita explicar un bug del runtime, ese texto debería ser una entrada de deuda técnica.
5. **Higiene positiva destacable:** cero `innerHTML`/`insertAdjacentHTML` en todo `frontend/src/js`, escapado sistemático vía `textContent`/`component.create`, `encodeURIComponent`/`decodeURIComponent` simétricos en el router, y comentarios de intención de calidad excepcional que explican *por qué*, no *qué*.
