# Guía de extensión del frontend

## Alcance

Esta guía cubre dos caminos de extensión distintos: añadir una página nueva
al core del frontend, y registrar la UI de un plugin de tipo `extension`
(tabs y paneles). Para la estructura general de carpetas y convenciones
transversales, ver [arquitectura.md](arquitectura.md).

## Añadir una página nueva del core

1. **Crear la clase de página** en `frontend/src/js/views/pages/MiPagina.js`.
   Sigue el patrón de las páginas existentes (`EntityList`, `UserProfile`,
   `PluginManager`): constructor `(container, ...opciones)`, método `init()`
   o render inmediato, y opciones `{ shellLayout, onSaved, onCancel, ... }`
   inyectadas por quien la instancia — nunca resuelve la shell buscándola en
   el DOM.
2. **Elegir el layout correcto**: `ListLayout` para tablas/listados,
   `FormLayout` para formularios de edición o configuración. Ver el contrato
   completo, ejemplos funcionales y las 9 reglas de extensión en
   [layouts-guide.md](layouts-guide.md). No construyas markup de cabecera,
   breadcrumbs o acciones a mano; eso es responsabilidad del layout.
3. **Registrar la ruta** en `RouteMapController.js` (mapa hash público <->
   identificador interno) y, si aplica, en `PluginRouteController.js` para
   rutas de configuración de plugin. El mapa completo de rutas actuales y
   reservadas vive en [navegacion-anatomia.md](navegacion-anatomia.md);
   añade tu ruta ahí también para que quede documentada.
4. **Conectar el despacho** en `AppController.js`: qué identificador interno
   instancia tu página nueva dentro de `shell-main-content`.
5. **Reusar la infraestructura transversal**: estados de carga/vacío/error y
   notificaciones van siempre por `UiResilienceService`
   (`setViewState`/`clearViewState`, `showNotification`, `confirm`,
   `setButtonPending`). No repliques estos estados a mano por página — es
   precisamente lo que consolidó STORY 9.7/9.8.
6. **Añadir tests**:
   - Un runner HTML en `frontend/tests/integration/MiPaginaTest.html`
     (componente/integración con `fetch` mockeado, patrón de
     `frontend/tests/js/helpers.js`).
   - Si la página participa en un flujo de usuario relevante (login,
     navegación, CRUD, plugins, tema), añade o extiende un spec en
     `frontend/tests/e2e/tests/`. Ver [testing-ui.md](testing-ui.md).

## Patrones de datos

- **Estado global**: repartido por dominio, cada uno con su propio
  `subscribe`/`notify` — sesión (`models/SessionModel.js`: usuario, token,
  entidades), tema/preferencias UI (`models/ThemeModel.js`) y notificación
  global (`models/NotificationModel.js`). No crear stores paralelos.
- **Acceso a API**: `Api` (`models/ApiClientModel.js`), cliente HTTP genérico
  con auth y errores tipados (`ApiError`). Una página nueva que consume el
  schema de una entidad hace `GET /entities/{slug}/schema` y pasa el
  resultado a `DynamicForm`/`DynamicTable` en vez de construir formularios a
  mano — ver el mapeo completo de tipos en
  [renderizado-dinamico.md](renderizado-dinamico.md).
- **Persistencia de sesión y preferencias**: `SessionModel.js` persiste
  token y snapshot de usuario en `localStorage`; `AppConfigurationModel.js`
  persiste las preferencias de UI en el backend por cliente, vía
  `ThemeModel.subscribeUi` + guardado con debounce en `AppController`.

## Puntos de integración de plugins en UI

Los plugins de tipo `extension` (a diferencia de los de tipo `entity`, que
aportan un catálogo completo) añaden una pestaña y un panel propio dentro de
`EntityEdit` para una entidad destino (`target_entity` en su manifest).

Contrato de panel obligatorio, expuesto por la clase que registra el plugin:

```js
{
  element: HTMLElement,                          // nodo a montar en la pestaña
  flush: (resolvedId: string) => Promise<void>,   // persiste cambios pendientes
}
```

Flujo completo, usando `plugins/comments/plugin.js` como referencia real:

1. **Backend**: el plugin registra el hook `registerTabs` (ver
   `docs/04-plugins/`) devolviendo `{ id, label, endpoint }` por cada tab que
   aporta para la entidad activa.
2. **Frontend — carga**: `EntityEdit` pide `GET /entities/{slug}/tabs`,
   importa dinámicamente `plugins/{id}/plugin.js` para cada tab recibido
   (`#loadPluginModules`) y construye el panel con
   `PluginPanelRegistry.build(tab.id, { endpoint, recordId, api })`.
3. **Frontend — autorregistro**: `plugin.js` importa
   `PluginPanelRegistry` desde `models/PluginPanelModel.js` y se registra a
   sí mismo al cargarse:

   ```js
   import { PluginPanelRegistry } from '../../js/models/PluginPanelModel.js';

   export class MiExtensionPanel {
     constructor({ endpoint, recordId, api }) { /* ... */ }
     get element() { /* ... */ }
     async flush(resolvedId) { /* ... */ }
   }

   PluginPanelRegistry.register('mi-extension-slug', MiExtensionPanel);
   ```

4. **Frontend — montaje**: `DynamicTabs` recibe la lista de tabs (la pestaña
   `data` del core más una por plugin) y monta `panel.element` como
   contenido. Al guardar el registro, `EntityEdit` invoca `flush(recordId)`
   en cada panel de plugin para persistir sus cambios pendientes contra el
   `endpoint` propio del plugin — nunca contra el endpoint de la entidad
   base.
5. Si el módulo del plugin falla al cargar o no se autorregistra, `EntityEdit`
   muestra un panel de fallback (`#buildFallbackPanel`) en vez de romper el
   resto de la vista.

## Checklist rápida

**Voy a añadir una página nueva del core:**
- [ ] Clase en `views/pages/`, layout correcto (`ListLayout`/`FormLayout`)
- [ ] Ruta registrada en `RouteMapController` + documentada en `navegacion-anatomia.md`
- [ ] Despacho conectado en `AppController`
- [ ] Estados de carga/vacío/error vía `UiResilienceService`, sin duplicarlos
- [ ] Runner de integración en `frontend/tests/integration/`
- [ ] Spec E2E si la página participa en un flujo de usuario relevante

**Voy a añadir un plugin de extensión con UI propia:**
- [ ] Backend: hook `registerTabs` devuelve `{ id, label, endpoint }`
- [ ] `plugins/{slug}/plugin.js` exporta una clase con el contrato `{ element, flush }`
- [ ] Autorregistro con `PluginPanelRegistry.register(slug, Clase)` al final del módulo
- [ ] `flush(resolvedId)` persiste contra el endpoint propio del plugin, no contra `/records`
- [ ] Probado con un registro nuevo (sin `recordId`) y uno existente

## Referencias

- [arquitectura.md](arquitectura.md): estructura de carpetas, flujo SPA y routing
- [layouts-guide.md](layouts-guide.md): contrato de ShellLayout/PageLayout/ListLayout/FormLayout
- [navegacion-anatomia.md](navegacion-anatomia.md): mapa de rutas y anatomía de página
- [renderizado-dinamico.md](renderizado-dinamico.md): mapeo de tipos de schema a controles de UI
- [testing-ui.md](testing-ui.md): jerarquía de tests y ejecución de la suite E2E
- [../04-plugins/](../04-plugins/): contrato de hooks backend (`registerTabs`, `registerActions`) y manifest
