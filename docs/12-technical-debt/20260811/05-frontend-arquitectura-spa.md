# Auditoría — Arquitectura del shell SPA

**Subsistema:** Frontend Architecture (routing, estado global, cliente HTTP, theming, i18n, resiliencia de UI)
**EPIC cubiertas:** EPIC 9 (sistema UI, shell frontend y arquitectura SPA), sobre bases de EPIC 3 y EPIC 5
**Severidades:** 0 crítico · 7 mayor · 6 menor · 3 nit

Índice: [Auditoría consolidada](00-informe-consolidado.md)

Alcance revisado íntegramente: `app.js`, `index.html`, los 4 controllers, los 8 models y `UiResilienceService.js`, más los tests de integración y E2E indicados, y el backlog (`docs/11-backlog/backlog.md`) para contrastar intención vs. implementación real. CSS revisado por encima según lo pedido (confirmado: `tailwind.generated.css` es un artefacto minificado de una sola línea con variables `--tw-*`, no editado a mano; `main.css` es hoy un stub de 5 líneas; `theme.runtime.css` sí es el CSS runtime real que consumen las variables que escribe `ThemeModel.js`).

---

## Hallazgos — MAYOR

**1. `UiResilienceService.handleError()` nunca detecta los errores de red reales que produce el propio cliente HTTP**
`frontend/src/js/services/UiResilienceService.js:164-176` vs `frontend/src/js/models/ApiClientModel.js:64-69`.
`handleError` solo reconoce un error de red si `error.type === 'network'` o `error.code === 'NETWORK_ERROR'`. Pero `ApiClientModel.js` lanza `new ApiError(0, 'Network error — server unreachable')`: `code` es el número `0`, y `ApiError` nunca fija `.type`. El propio test `ApiTest.html:123-130` ("error de red lanza ApiError con code 0") documenta ese contrato — y ningún test conecta ese `ApiError` real con `handleError`, por eso la rama nunca se ejercita. Consecuencia: cualquier fallo real de red en la app cae siempre en el mensaje genérico, nunca en "Revise la conexión...". Además, como la clave `'ui.error.generic'` existe en `I18nModel`, la llamada `t('ui.error.generic', fallbackMessage)` **ignora siempre** el `fallbackMessage` que pasa el llamador (parámetro efectivamente muerto salvo que se borre esa clave del diccionario).
*Arreglo:* que `handleError` compruebe `error instanceof ApiError && error.code === 0`, y que use el `fallbackMessage` recibido como segundo argumento de `t(...)` real (o directamente como valor, sin pasar por la clave fija).

**2. Triple canal redundante para una misma notificación → renders duplicados**
`frontend/src/js/services/UiResilienceService.js:22-40` y `:45-67` (dispatch en `window` **y** `document`) + `frontend/src/js/controllers/AppController.js:92-93` (listener registrado en `window` **y** `document`) + `AppController.js:94-96` (`AppState.subscribeNotification`, un tercer canal).
Una sola llamada a `showNotification()` dispara: (a) `AppState.setNotification` directo → notifica al subscriber del constructor; (b) el mismo `CustomEvent` despachado dos veces (window/document) contra el **mismo handler**, registrado en ambos targets → 2 invocaciones más, cada una volviendo a llamar `AppState.setNotification` y a `scheduleGlobalNotificationsRender(true)` (modo inmediato, sin throttle). Resultado: 2-3 reconstrucciones completas del DOM de notificación por cada aviso, sin que ningún test lo detecte (los tests solo comprueban presencia final, no cuántas veces se renderizó). Además, no hay ningún consumidor externo (plugin, módulo desacoplado) de `xestify:notification-changed` en todo el repo — el `CustomEvent` es puro vestigio, ya que `AppState.subscribeNotification` cubre el mismo caso de uso de forma más simple.
*Arreglo:* eliminar el `CustomEvent` (y sus dos listeners) y quedarse solo con el mecanismo de `AppState.subscribeNotification`, que ya es el único necesario.

**3. Parseo de `entity-record:` duplicado carácter a carácter en dos ficheros**
`frontend/src/js/controllers/RouteMapController.js:299-318` (función privada `parseEntityRecordPage`, **no exportada**) vs `frontend/src/js/controllers/AppController.js:1486-1504` (`parseEntityRecordPageToken`, reimplementada desde cero con idéntica lógica).
`RouteMapController.js` exporta el constructor `entityRecordPage()` y el parser de tabs `parseEntityTabPage()`, pero se olvidó de exportar el parser equivalente para records simples — forzando a `AppController.js` a reinventar la misma función con otro nombre. Es el ejemplo más claro de violación DRY del subsistema: si mañana cambia el separador o el formato del token, hay que recordar tocar dos sitios.
*Arreglo:* exportar `parseEntityRecordPage` desde `RouteMapController.js` y hacer que `AppController.js` la importe, borrando su copia local.

**4. Persistencia de identidad de usuario duplicada entre `StateModel.js` y `SessionModel.js`, con `reset()` que no pasa por el setter**
`frontend/src/js/models/StateModel.js:1-3,44-73,143-147` vs `frontend/src/js/models/SessionModel.js:3-8,24-44,46-51`.
Ambos ficheros definen de forma independiente las mismas claves de `localStorage` (`xestify_user_email`, `xestify_user_name`, `xestify_user_avatar`) y ambos las escriben: `StateModel.setUser()` las persiste automáticamente en cada llamada (efecto secundario oculto dentro de lo que se supone un "estado global simple"), y `AppController` además llama explícitamente a `SessionModel.persistUserSnapshot(...)`. Es persistencia por duplicado. Peor aún: `AppState.reset()` (`StateModel.js:260-273`) hace `this.user = null` **directamente**, sin pasar por `setUser(null)`, así que el efecto de limpieza de `localStorage` de `persistUserIdentity` nunca se dispara en el reset — hoy no se nota porque `AppController.clearAuth()` siempre empareja `SessionModel.reset()` con `SessionModel.clearStoredSession()`, pero es una dependencia frágil entre dos ficheros que no se referencian entre sí (mismas claves como *magic strings* repetidas, no como constante compartida).
*Arreglo:* que `StateModel.js` deje de tocar `localStorage` directamente (esa responsabilidad ya la tiene `SessionModel.js`/`ThemeModel.js`), o al menos centralizar las claves en un único módulo.

**5. `UiResilienceService.confirm()`: doble llamada a `modal.show()` con manejo de foco frágil**
`frontend/src/js/services/UiResilienceService.js:212` y `:218`.
Se llama `modal.show(document.activeElement...)` (para fijar a quién devolver el foco al cerrar) y, tras `setContent(body)`, se vuelve a llamar `modal.show()` **sin argumento** — lo que recalcula `#returnFocusElement` a partir de `document.activeElement` en ese instante. Hoy "funciona" porque entre ambas llamadas no hay ningún `await`/yield que mueva el foco realmente (los `requestAnimationFrame` de `Modal.show()` aún no se han ejecutado), así que el segundo cálculo coincide por casualidad con el primero. Es un resto claro de un parche posterior (se añadió el parámetro de retorno de foco y quedó la llamada original a `show()` sin depurar). Si en el futuro se inserta cualquier operación asíncrona entre esas dos líneas, el foco de retorno tras cerrar el modal de confirmación quedaría corrompido — justo el escenario que testea `UiResilienceTest.html:134-162`, hoy en verde solo por la coincidencia temporal descrita.
*Arreglo:* construir y adjuntar los listeners al `body` antes de la primera (y única) llamada a `modal.show(returnFocusElement)`.

**6. `HASH_ROUTE_MAP` es mayoritariamente muerto — solo 5 de sus 13 entradas se leen desde código**
`frontend/src/js/controllers/RouteMapController.js:1-16` vs `resolveBasicHash` (`:146-168`), única función que lo consulta (y solo para `login`/`home`/`profile`/`users`/`plugins`).
Las entradas `userDetail`, `pluginConfig`, `entityList`, `entityCreate`, `entityDetail`, `entityTab`, `resultEmpty`, `resultError`, `resultForbidden` no las lee ningún resolver: la generación real de esas rutas está hardcodeada por separado en `resolveUsersHash`, `resolvePluginsHash` y `resolveEntityHash`, con plantillas de string manuales que no derivan del mapa. `HASH_ROUTE_MAP` aparenta ser la fuente de verdad del sistema de rutas (así se documenta también en STORY 9.6), pero en la práctica es una tabla decorativa que puede desincronizarse del comportamiento real sin que nada lo detecte. Las tres entradas `result*` corresponden a plantillas "result/empty/error" mencionadas en STORY 9.2 que nunca llegaron a implementarse.
*Arreglo:* o se hace que los resolvers deriven realmente de `HASH_ROUTE_MAP` (sustitución de `:param`), o se elimina el mapa y se documentan las rutas solo en el backlog/README para no mantener dos fuentes de verdad.

**7. Manejo de `401` inconsistente entre llamadas API del mismo controlador**
`frontend/src/js/controllers/AppController.js:455-471` (`hydrateUiPreferences`) vs `:764-779` (`loadEntitiesForNav`) y `:1462-1483` (`loadCurrentUserProfile`).
`hydrateUiPreferences` engulle **cualquier** error de `loadUiPreferences` (incluido un 401) en un `catch {}` mudo, mientras que las otras dos llamadas API del mismo flujo de arranque comprueban explícitamente `isUnauthorizedError(error)` para forzar `clearAuth()` + `renderLogin()`. Hoy el 401 se acaba capturando igualmente en la siguiente llamada (`loadEntitiesForNav`), así que no es observable, pero es una política de error no uniforme dentro del mismo método `renderDashboard()` — el tipo de inconsistencia que delata parches sucesivos sin criterio común.

---

## Hallazgos — MENOR

**8. `renderLogin()` no resincroniza el hash cuando la sesión expira a mitad de uso**
`frontend/src/js/controllers/AppController.js:173-175`. Solo reemplaza el hash por `#/login` si estaba vacío (`'' | '#'`). Si un 401 fuerza `clearAuth()` + `renderLogin()` estando el usuario en, p. ej., `#/entity/clients/42`, la URL se queda apuntando a esa ruta protegida mientras en pantalla se ve el login — desincronización visible de URL/UI (inofensiva funcionalmente, confusa para el usuario).

**9. `RouteController.resolveFromHash()` es código muerto**
`frontend/src/js/controllers/RouteController.js:59-61`. No hay ninguna llamada a este método en todo `frontend/` (ni `src` ni `tests`).

**10. Ruta `'ui'` reconocida pero sin implementar**
`frontend/src/js/controllers/RouteMapController.js:100-102` (`if (section === 'ui') return 'ui';`). No existe handler para `'ui'` en `AppController.handlePageNavigation` (`:556-614`) ni mapeo inverso en `hashFromPage`, así que navegar a `#/ui` produce silenciosamente "Página no encontrada". Vestigio de una sección planificada y no completada.

**11. Formato de token de página inconsistente para plugins**
`frontend/src/js/controllers/RouteMapController.js:52-65`. Todas las demás rutas usan el formato `namespace:param` (`entity:slug`, `entity-record:slug:id`, `users:id`), pero `pluginConfigPage()`/`parsePluginConfigPage()` usan un fragmento de ruta literal (`/plugins/slug`) como token interno de página. Funciona (está bien testeado), pero rompe la convención que sigue el resto del router y obliga a `PluginRouteController` a existir solo para ocultar esa diferencia.

**12. `AppState` es un objeto "dios" — evolución coherente pero no documentada frente a STORY 3.7**
`frontend/src/js/models/StateModel.js` completo. El backlog (STORY 3.7, `docs/11-backlog/backlog.md:460-469`) exige explícitamente *"Sin listeners, sin Proxy (Vanilla puro)"*. La restricción de **Proxy** se respeta (no hay reactividad mágica en ningún fichero del subsistema). La de **listeners** se abandonó deliberadamente en STORY 9.7 (que depende de 3.7 y pide expresamente "gestión de estado ampliada... notificaciones... theming en tiempo real"), y la evolución es *coherente*: los tres mecanismos (`listeners`, `uiListeners`, `notificationListeners`) siguen exactamente el mismo patrón `subscribe/unsubscribe/notify`. El problema no es la evolución en sí, sino que (a) el checkbox de STORY 3.7 sigue marcado como si la restricción original siguiera vigente, y (b) `AppState` acumuló en un único objeto mutable global responsabilidades muy dispares — sesión/usuario, caché de entidades/registros, notificación, navegación y preferencias visuales — sin ninguna segmentación, lo que roza la sobre-concentración de responsabilidades (SRP) típica de un "god object", agravada por el punto 4.

**13. API dual de `RouteController.navigate()` sin uso real en producción**
`frontend/src/js/controllers/RouteController.js:36-48`. Acepta tanto tokens de página (`'entity:slug'`) como hashes crudos (`'#/entity/slug'`). Se confirmó por grep que **ningún** código de `frontend/src` llama a `router.navigate('#...')` — solo lo hacen los tests de `FrontendArchitectureTest.html`. Es complejidad añadida a una clase de producción para servir de atajo de testing.

---

## Nits

- **14.** `ThemeModel.js:293` usa el string mágico `'light'` como fallback de `pageStyle` en lugar de `defaults.pageStyle` (que sí se usa consistentemente en el resto de campos del mismo objeto, líneas 294-315).
- **15.** `AppController.js:1006-1017`: el host flotante de respaldo fija clases Tailwind (`fixed inset-0 z-[9999] flex items-start...`) y **además** fija exactamente las mismas propiedades vía `style.*` inline justo debajo — redundante, probablemente residuo de depuración de z-index.
- **16.** `docs/05-frontend/renderizado-dinamico.md` sigue mencionando componentes (`DynamicTabs`, `EntityDetail`) que no coinciden con los nombres reales usados hoy (`EntityEdit`, tabs vía `onTabChange`), señal de doc no actualizada tras EPIC 9 (fuera del núcleo de shell, pero relevante si se cita en la defensa).

---

## Resumen de salud del subsistema

El shell SPA está bien construido para el alcance de un TFM: separación MVC real y consistente entre `controllers`/`models`/`views`, guardas defensivas (`typeof`/`instanceof`) aplicadas de forma sistemática, y un router hash con mapa de rutas sólido y bien probado (entrada directa, refresh, back/forward, fallback de home, preservación de tabs). No se ha encontrado ningún bug crítico que rompa el camino feliz de login, navegación o persistencia de tema/sesión. Los problemas reales están todos en la "fontanería" transversal añadida en STORY 9.7 (notificaciones, manejo de errores, persistencia de identidad): ahí conviven mecanismos redundantes o parcialmente muertos (triple canal de notificación, parser de rutas duplicado, persistencia de usuario por partida doble) que delatan iteraciones sucesivas sin una pasada final de limpieza/DRY — un patrón típico de código construido incrementalmente con asistencia de IA sin refactor de cierre. `StateModel.js` evolucionó de forma coherente respecto a la restricción original de STORY 3.7 (mantuvo "sin Proxy", abandonó "sin listeners" de forma uniforme en los tres tipos de suscripción), lo cual es defendible técnicamente pero debería reflejarse en el backlog. En conjunto: código mantenible, con buen criterio arquitectónico de base, que se beneficiaría de una ronda de consolidación DRY antes de la defensa más que de correcciones de bugs.

**Cobertura de tests:** notable para ser un runner casero, pero con huecos concretos y significativos: (1) el contrato real de error de red de `ApiClientModel` (`ApiError` con `code: 0`) nunca se prueba contra `UiResilienceService.handleError`, que es justo donde está el bug del hallazgo #1; (2) `AppState.subscribe()` (listener de usuario/sesión) no tiene ningún test dedicado, a diferencia de `subscribeUi`, que sí verifica el conteo de notificaciones (`StateTest.html:96-109`); (3) `SessionModel.js` no tiene fichero de test propio entre los revisados — ni `decodeJwtPayload`, ni `normalizeUserProfile`, ni `currentUserIsAdmin`; (4) `isUnauthorizedError()` nunca se testea explícitamente pese a ser el mecanismo central de expiración de sesión; (5) `AppController.buildTemplateDefinition()` y sus seis resolvers (~250 líneas de lógica de breadcrumbs/plantillas) no tienen test de integración directo, solo cobertura indirecta vía Playwright. Los specs E2E (`shell-navigation`, `theme-wysiwyg`) son un buen complemento de caminos felices reales en navegador, pero no sustituyen esos huecos unitarios.
