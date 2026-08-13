# Frontend y UI dinámica

Esta carpeta reúne la documentación sobre el frontend, el renderizado dinámico y los componentes de la interfaz de usuario de Xestify.

---

## Descripción general

El frontend de Xestify está diseñado para ser completamente dinámico y extensible mediante plugins. La UI se construye a partir de metadatos (schemas) y se adapta automáticamente a las entidades y extensiones instaladas, sin necesidad de modificar el core.

Ademas, el runtime detecta automaticamente su `base path`, por lo que la misma
aplicacion puede servirse tanto desde la raiz del host como desde una subruta
Apache, por ejemplo `http://localhost/xestify/`, sin hardcodear `/api` ni
`/plugins` contra la raiz del dominio.

El frontend sigue una arquitectura MVC estricta: toda la logica de aplicacion de
`frontend/src/js` vive bajo `controllers/`, `views/` y `models/`. El entrypoint
raiz `app.js` es solo un bootstrap tecnico minimo que delega en `AppController`.
`controllers/` concentra arranque, routing y orquestacion; `views/` organiza
`layout/`, `components/`, `modules/` y `pages/`; `models/` concentra estado,
sesion, cliente API, helpers de base path y runtime de plugins.

Desde STORY 9.5, las páginas autenticadas comparten una única instancia de
`ShellLayout`. `PageLayout`, `ListLayout` y `FormLayout` componen cabeceras,
breadcrumbs, toolbars, contenido y acciones sin generar layouts paralelos.
Login usa la plantilla standalone `login` de `PageLayout` y no monta navbar.

---

## Componentes principales

- **ComponentFactory**: Entrada pública única del sistema UI. Expone `component.create(name, options)` y `component.getCatalog()`, mantiene el registro canónico de componentes y rechaza nombres no registrados.
- **DynamicForm**: Renderiza formularios a partir de un schema declarativo. Soporta tipos string, number, email, date, select, boolean, etc. Permite validación básica y recolección de datos para POST/PUT.
- **DynamicTable**: Renderiza tablas dinámicas según el schema y los registros obtenidos vía API. Incluye paginación básica.
- **DynamicTabs**: Permite la composición de pestañas, incluyendo tabs inyectados por plugins de extensión.
- **Modal**: Componente reutilizable para diálogos y confirmaciones.
- **Navbar**: Barra de navegación superior, muestra usuario, entidades y acceso a PluginManager.
- **ShellLayout**: Armazón persistente de las páginas autenticadas y fuente de sus zonas estructurales.
- **PageLayout / ListLayout / FormLayout**: Plantillas reutilizables para anatomía común, listados y formularios.
- **PluginPanelRegistry**: Registro runtime de plugins frontend, ubicado en la capa `models`, para que cada extensión registre su UI sin abrir capas paralelas a MVC.
- **State**: Estado global repartido por dominio — sesión (`models/SessionModel.js`: usuario, token, entidades), tema/preferencias UI (`models/ThemeModel.js`) y notificación global (`models/NotificationModel.js`) —, cada uno con su propio `subscribe`/`notify`.
- **Api**: Cliente HTTP genérico (`models/ApiClientModel.js`) con manejo de autenticación y errores.

---

## Construcción encadenada de componentes

Los elementos creados mediante `component.create()` exponen los métodos
encadenables de `BaseComponent`. Durante la creación de una vista, se deben usar
para agrupar la configuración inicial y el montaje del nodo:

```js
const navbarContainer = component.create('div')
	.setClassName('top-0 z-50')
	.setData('role', 'shell-navbar')
	.setParent(shell);
```

- Usar `setClassName()` para asignar todas las clases iniciales y `addClass()`
	para añadir clases posteriormente.
- Usar `setData('role', value)` para generar `data-role`. `setRole()` se reserva
	para el atributo HTML accesible `role` y no sustituye a `data-role`.
- Pasar el nodo padre a `setParent()` cuando ya esté disponible. La resolución
	mediante string se reserva para contenedores externos que deban localizarse
	por selector, `id`, `data-role` o `data-id`.
- Mantener una constante cuando el nodo se reutilice o forme parte del valor de
	retorno. Si no se reutiliza, la cadena puede terminar directamente en
	`setParent()`.
- Aplicar el encadenado a la configuración inicial. Los cambios de estado
	posteriores pueden seguir usando propiedades DOM cuando expresen mejor la
	mutación.

---

## Flujo de UI dinámica

1. El usuario inicia sesión y selecciona una entidad.
2. El frontend solicita el schema de la entidad al backend (`GET /entities/{slug}/schema`).
3. DynamicForm y DynamicTable solicitan controles registrados mediante `component.create()` y renderizan la UI según el schema recibido.
4. Si existen plugins de extensión activos, DynamicTabs monta los tabs adicionales y PluginPanelRegistry integra los paneles personalizados.
5. Las acciones del usuario (crear, editar, eliminar) se validan primero en frontend y luego en backend.

---

## Ejemplo de uso: renderizado de formulario

```js
import { DynamicForm } from './modules/DynamicForm.js';

const schema = {
	fields: [
		{ name: 'name', type: 'string', required: true },
		{ name: 'email', type: 'email', required: true },
		{ name: 'is_active', type: 'boolean' }
	]
};

const form = new DynamicForm(schema, '#form-container');
form.render();
```

---

## Extensión de la UI mediante plugins

- Los plugins de tipo extensión pueden registrar tabs y paneles personalizados usando DynamicTabs y PluginPanelRegistry.
- El backend expone hooks (`registerTabs`, `registerActions`) y el frontend consulta las extensiones activas para cada entidad.
- Cada panel de plugin debe exponer el contrato `{ element: HTMLElement, flush: (id: string) => Promise<void> }`.

---

## Pruebas y calidad

- `frontend/tests/integration/`: 19 runners HTML de componente/integración
  (DOM real, `fetch` mockeado, sin backend real) para flujos principales
  (login, listado, edición, plugins).
- `frontend/tests/e2e/`: suite Playwright con navegador real contra el
  runtime Apache+PHP real, backend y base de datos reales.
- Bajo Apache en desarrollo, los runners de `frontend/tests/integration/` se
  exponen en `/tests/integration/*`. Los módulos de la aplicación que
  consumen se cargan mediante `/js/*`, igual que en el runtime normal. Esa
  exposición no forma parte del runtime de producción.
- Esa exposicion se activa con `SetEnvIf ... ENABLE_TEST=1` y tambien funciona
  cuando la app cuelga de un alias/subruta, por ejemplo `/xestify/tests/*`.
- Los tests ya no dependen de una ruta separada `/src/*`; consumen el mismo
  árbol `/js/*` que usa la aplicacion real.
- Se recomienda mantener la cobertura de pruebas al añadir nuevos componentes o plugins.
- Detalle completo de ambos niveles, prerrequisitos y comandos de ejecución
  en [testing-ui.md](testing-ui.md).

---

## Referencias

- [arquitectura.md](arquitectura.md): Estructura de carpetas, convenciones de componentes, flujo SPA y decisiones de routing
- [guia-extension.md](guia-extension.md): Cómo añadir páginas nuevas y puntos de integración de plugins en UI
- [testing-ui.md](testing-ui.md): Jerarquía de tests de frontend y ejecución de la suite E2E
- [renderizado-dinamico.md](renderizado-dinamico.md): Guía detallada de renderizado y mapeo de tipos
- [ui-foundations-ant.md](ui-foundations-ant.md): Fundamentos de diseño de STORY 9.1 inspirados en Ant Design
- [navegacion-anatomia.md](navegacion-anatomia.md): Contrato de navegación hash, anatomía de páginas y convenciones de copy de STORY 9.2
- [layouts-guide.md](layouts-guide.md): Wiring directo Shell-Page y contratos fluent de PageLayout, ListLayout y FormLayout
- [../backend/](../../backend/): Contratos de API y ejemplos de payload
- [../04-plugins/](../04-plugins/): Plantillas y ejemplos de plugins
