# Arquitectura frontend

## Alcance

Este documento consolida en un solo sitio la estructura de carpetas de
`frontend/src/js`, las convenciones de construcción de componentes, el flujo
de arranque de la SPA y las decisiones de routing vigentes. No repite el
contenido detallado que ya vive en otros documentos de esta carpeta — los
referencia — sino que explica cómo encajan entre sí para orientar a quien
llega al proyecto por primera vez o va a añadir código nuevo.

Xestify frontend es Vanilla JS ES2020+, ES Modules puros, sin build step ni
framework. Los contratos de tipos se expresan con JSDoc, no TypeScript.

## Estructura de carpetas

```text
frontend/src/js/
├── app.js                    Bootstrap técnico mínimo (ver "Flujo de arranque")
├── controllers/               Arranque, routing y orquestación de páginas
│   ├── AppController.js       Orquestador raíz: boot, sesión, shell, errores globales
│   ├── RouteController.js     Motor de navegación hash (navigate, back/forward)
│   ├── RouteMapController.js  Mapa bidireccional hash <-> identificador interno de página
│   └── PluginRouteController.js  Parser de rutas de configuración de plugin
├── models/                    Estado y acceso a datos, sin DOM
│   ├── ApiClientModel.js       Cliente HTTP genérico (Api) con auth y manejo de errores
│   ├── SessionModel.js         Estado de sesión (usuario, token, entidades) y su persistencia en localStorage
│   ├── ThemeModel.js           Estado de preferencias UI/tema (subscribe/notify), normalización y aplicación WYSIWYG al documento
│   ├── NotificationModel.js    Estado de la notificación global activa (subscribe/notify)
│   ├── AppConfigurationModel.js  Persistencia remota de preferencias UI (`/configurations/*`)
│   ├── I18nModel.js            Claves de traducción y resolución de locale
│   ├── BasePathModel.js        Detección del base path en runtime (raíz vs subruta Apache)
│   ├── PluginPanelModel.js     Registro runtime de paneles de plugin (PluginPanelRegistry)
│   ├── AvatarUpload.js         Validación de tamaño y lectura de avatar (FileReader), sin DOM
│   └── ClipboardUtil.js        copyToClipboard() — única lógica pura del flujo de contraseña temporal
├── services/
│   └── UiResilienceService.js  Estados compartidos de vista (loading/empty/error), notificaciones,
│                                confirmaciones modales y pending buttons — capa única para toda la UX
├── views/
│   ├── layout/                 ShellLayout, PageLayout, ListLayout, FormLayout (ver layouts-guide.md)
│   ├── components/             Primitivas registradas en ComponentFactory (Button, Modal, inputs...)
│   ├── modules/                Piezas compuestas reutilizables entre páginas (Navbar, DynamicTable,
│   │                            DynamicForm, DynamicTabs, ThemeSettingsPanel, ComponentFactory)
│   └── pages/                  Una clase por pantalla (Login, EntityList, EntityEdit, PluginManager,
│                                 PluginConfig, UserManager, UserProfile, UserConfig)
```

Regla de ubicación para código nuevo:

- Una pantalla nueva → `views/pages/`.
- Una pieza visual reutilizable entre páginas (tabla, tabs, panel) → `views/modules/`.
- Una primitiva de UI de bajo nivel que otros componentes componen → `views/components/`,
  registrada en `ComponentFactory`.
- Estado o acceso a datos sin DOM → `models/`.
- Lógica de arranque o navegación → `controllers/`.
- Comportamiento transversal de UX (loading, error, confirmaciones) → `services/UiResilienceService.js`,
  nunca reimplementado por página.

## Convenciones de componentes

`ComponentFactory` (`views/modules/ComponentFactory.js`) es la entrada pública
única del sistema de UI: `component.create(name, options)` y
`component.getCatalog()`. Ningún componente se instancia con `new` fuera de
esta fábrica ni se registra nombre no catalogado.

Los nodos devueltos por `component.create()` exponen el encadenado fluent de
`BaseComponent` (`setClassName`, `addClass`, `setData`, `setParent`, etc.).
Ver `README.md` (sección "Construcción encadenada de componentes") para el
detalle completo de la convención, incluida la distinción entre
`setData('role', value)` (genera `data-role`) y `setRole()` (atributo HTML
`role` de accesibilidad).

## Flujo de arranque de la SPA

```text
frontend/src/index.html
  └─ <script type="module" src="js/app.js">
       └─ app.js: bootstrap mínimo
            └─ new AppController(appRoot).start()
                 ├─ Resuelve sesión (SessionModel) y redirige a Login si no hay token válido
                 ├─ Construye la instancia persistente de ShellLayout (una única shell)
                 ├─ Registra RouteController / RouteMapController y despacha la ruta inicial
                 └─ Cada cambio de hash instancia la página correspondiente dentro de
                    `shell-main-content`, reutilizando siempre la misma ShellLayout
```

`app.js` es intencionalmente un bootstrap técnico de una línea de lógica: todo
el arranque real vive en `AppController`. Desde STORY 9.5, las páginas
autenticadas comparten una única instancia de `ShellLayout`; no se crean
shells paralelas por página. El contrato completo de `ShellLayout` /
`PageLayout` / `ListLayout` / `FormLayout` — árbol de zonas, wiring y reglas
de extensión — está documentado en [layouts-guide.md](layouts-guide.md).

`AppController` también centraliza la resiliencia transversal: intercepta
errores JS/red no capturados, aplica las preferencias de tema al documento en
cada cambio (WYSIWYG) y guarda esas preferencias en el backend con un
debounce corto cuando el usuario activo es admin.

Desde STORY 10.1, también centraliza la detección de sesión caducada: `Api`
(`models/ApiClientModel.js`) invoca un único handler registrado vía
`setSessionExpiredHandler()` ante cualquier 401 con token activo, sin
importar la página que lo origina. `AppController` lo registra una vez en su
constructor (`clearAuth()` + `renderLogin({ sessionExpired: true })`); ninguna
página comprueba 401 por su cuenta. Un 401 sin token (login con credenciales
inválidas) no dispara el handler — esa distinción la da gratis el estado del
token en `Api`, no lógica adicional. `clearAuth()` limpia solo estado de
sesión: `ui-preferences` es config global de la instalación, no de sesión, y
sobrevive al logout.

## Decisiones de routing

Xestify usa **hash routing** (`#/ruta`) como convención oficial, sin History
API de rutas "limpias". `RouteMapController.js` es la fuente de verdad del
mapa bidireccional entre hash público y el identificador interno de página
que consumen las vistas (`profile`, `entity:<slug>`, `users:<id>`, etc.);
`PluginRouteController.js` añade el soporte específico de rutas de
configuración de plugin. `RouteController.js` es el motor que aplica esa
traducción: entrada directa, refresh, back/forward y navegación programática
funcionan todos sobre el mismo mapa, sin rutas hardcodeadas por vista.

El mapa completo de rutas actuales y reservadas, la arquitectura de
información (áreas principales, navegación, breadcrumbs objetivo) y el
contrato de anatomía de página viven en
[navegacion-anatomia.md](navegacion-anatomia.md) — ese documento es la fuente
de verdad para añadir o modificar una ruta, no este.

Regla de cambio de tab dentro de un mismo registro: no se reconstruye la
página ni se repiten las cargas de schema/registro/extensiones; solo cambian
el panel activo y los breadcrumbs, preservando back/forward.

## Referencias

- [README.md](README.md): componentes principales, flujo de UI dinámica y extensión por plugins
- [renderizado-dinamico.md](renderizado-dinamico.md): mapeo de tipos de schema a controles de UI
- [ui-foundations-ant.md](ui-foundations-ant.md): fundamentos visuales (STORY 9.1)
- [navegacion-anatomia.md](navegacion-anatomia.md): mapa de rutas y anatomía de página (STORY 9.2)
- [layouts-guide.md](layouts-guide.md): contrato completo de ShellLayout/PageLayout/ListLayout/FormLayout
- [guia-extension.md](guia-extension.md): cómo añadir páginas nuevas y puntos de integración de plugins en UI
- [testing-ui.md](testing-ui.md): jerarquía de tests de frontend y ejecución de la suite E2E
