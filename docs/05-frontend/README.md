# Frontend y UI dinámica

Esta carpeta reúne la documentación sobre el frontend, el renderizado dinámico y los componentes de la interfaz de usuario de Xestify.

---

## Descripción general

El frontend de Xestify está diseñado para ser completamente dinámico y extensible mediante plugins. La UI se construye a partir de metadatos (schemas) y se adapta automáticamente a las entidades y extensiones instaladas, sin necesidad de modificar el core.

Ademas, el runtime detecta automaticamente su `base path`, por lo que la misma
aplicacion puede servirse tanto desde la raiz del host como desde una subruta
Apache, por ejemplo `http://localhost/xestify/`, sin hardcodear `/api` ni
`/plugins` contra la raiz del dominio.

---

## Componentes principales

- **DynamicForm**: Renderiza formularios a partir de un schema declarativo. Soporta tipos string, number, email, date, select, boolean, etc. Permite validación básica y recolección de datos para POST/PUT.
- **DynamicTable**: Renderiza tablas dinámicas según el schema y los registros obtenidos vía API. Incluye paginación básica.
- **DynamicTabs**: Permite la composición de pestañas, incluyendo tabs inyectados por plugins de extensión.
- **Modal**: Componente reutilizable para diálogos y confirmaciones.
- **Navbar**: Barra de navegación superior, muestra usuario, entidades y acceso a PluginManager.
- **PluginPanelRegistry**: Mapea slugs de plugins a paneles frontend, permitiendo que cada extensión registre su UI.
- **State**: Contenedor global de estado (usuario, entidad activa, registros, token, etc.).
- **Api**: Cliente HTTP genérico con manejo de autenticación y errores.

---

## Flujo de UI dinámica

1. El usuario inicia sesión y selecciona una entidad.
2. El frontend solicita el schema de la entidad al backend (`GET /entities/{slug}/schema`).
3. DynamicForm y DynamicTable renderizan la UI según el schema recibido.
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

- El frontend incluye pruebas E2E en `frontend/tests/` para flujos principales (login, listado, edición, plugins).
- Bajo Apache en desarrollo, las pruebas HTML se exponen en `/tests/*`. Los
  módulos de la aplicación que consumen se cargan mediante `/js/*`, igual que
  en el runtime normal. Esa exposición no forma parte del runtime de producción.
- Esa exposicion se activa con `SetEnvIf ... ENABLE_TEST=1` y tambien funciona
  cuando la app cuelga de un alias/subruta, por ejemplo `/xestify/tests/*`.
- Los tests ya no dependen de una ruta separada `/src/*`; consumen el mismo
  árbol `/js/*` que usa la aplicacion real.
- Se recomienda mantener la cobertura de pruebas al añadir nuevos componentes o plugins.

---

## Referencias

- [renderizado-dinamico.md](renderizado-dinamico.md): Guía detallada de renderizado y mapeo de tipos
- [ui-foundations-ant.md](ui-foundations-ant.md): Fundamentos de diseño de STORY 9.1 inspirados en Ant Design
- [navegacion-anatomia.md](navegacion-anatomia.md): Contrato de navegación hash, anatomía de páginas y convenciones de copy de STORY 9.2
- [../backend/](../../backend/): Contratos de API y ejemplos de payload
- [../04-plugins/](../04-plugins/): Plantillas y ejemplos de plugins
