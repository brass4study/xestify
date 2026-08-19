# Testing de UI

## Jerarquía de tests

```text
frontend/tests/
├── css/, js/helpers.js       Assets compartidos por los runners de integración
├── integration/               22 runners HTML: componentes/páginas en DOM real
│                               con fetch mockeado, sin backend ni BD reales
└── e2e/                       Proyecto Playwright: navegador real contra el
                                runtime Apache+PHP real, con backend y BD reales
```

Los dos niveles cubren cosas distintas y **no se sustituyen entre sí**:

- **`frontend/tests/integration/*.html`**: cargan una clase de
  `frontend/src/js/...` directamente en un `<div id="sandbox">`, sin pasar
  por `AppController` ni por routing real, con `globalThis.fetch` mockeado
  (`frontend/tests/js/helpers.js`: `mockFetch`, `mockFetchWithMap`,
  `mockFetchWithRoutes`). Verifican el comportamiento de un componente o una
  página de forma aislada y rápida. **Nota:** a pesar del nombre de la
  carpeta, no son tests de integración en el sentido estricto (no hay
  backend ni BD reales de por medio) — son más bien tests de componente/
  unitarios de frontend con dependencias inyectadas (`api: apiStub`, etc.).
- **`frontend/tests/e2e/*.spec.js`** (Playwright): abren un navegador real,
  navegan la SPA servida por Apache tal cual la vería un usuario, y golpean
  el backend PHP y la base de datos reales. Verifican que las piezas
  encajan de extremo a extremo: login real, routing real, persistencia real.

## Ejecutar los tests de integración

Ver la sección "Tests" de `AGENTS.md` para el flujo canónico: abrir el
runner en el navegador integrado de VS Code, servido por Apache
(`ENABLE_TEST=1`) o un servidor HTTP local equivalente. Esa vía sigue siendo
la prioritaria para validar visualmente un runner HTML.

**URL exacta:** `http://<host>/tests/integration/<Runner>.html` (p. ej.
`http://127.0.0.1/xestify/tests/integration/UserManagementTest.html` con el
alias `/xestify/` de `docs/08-operations/apache-vhost-examples.md`).
**No** `http://<host>/frontend/tests/...`: el `.htaccess` raíz bloquea el
acceso directo a `frontend/` (y a `backend/docs/skills/tools/var`) con
`RewriteRule ^(?:backend|docs|frontend|...)(?:/|$) - [F,END]` y solo expone
el contenido a través de las rutas reescritas `/tests/`, `/js/` y `/css/`
(ver `.htaccess` raíz). Pedir la ruta con el prefijo `frontend/` devuelve
403 aunque Apache y `ENABLE_TEST` estén bien configurados — no es un
problema de permisos ni motivo para montar un servidor alternativo.

### Qué cubre cada runner (22)

| Runner | Módulo probado | Verifica |
|---|---|---|
| `LoginTest.html` | `views/pages/Login.js` | Labels `for`/`id`, feedback oculto por defecto, aviso único de campos requeridos vacíos |
| `UserProfileTest.html` | `views/pages/UserProfile.js` | Campos de contraseña, oculta reset/borrado, medidor de fortaleza oculto sin nueva contraseña |
| `UserManagementTest.html` | `views/pages/UserManager.js`, `UserConfig.js` | Edición de rol/email por admin, reset de contraseña temporal, borrado con modal de confirmación |
| `EntityListTest.html` | `views/pages/EntityList.js` | Carga entidades desde la API, pagina/ordena registros, borra un registro tras confirmar en modal |
| `EntityEditTest.html` | `views/pages/EntityEdit.js` | Validación, POST/PUT según modo, errores de API por campo, campos de relación hidratados vía fetch |
| `PluginManagerTest.html` | `views/pages/PluginManager.js` | Activa/desactiva plugins, sincroniza, reordena, actualiza/revierte versión con confirmación modal |
| `PluginConfigTest.html` | `views/pages/PluginConfig.js` | Edición de campos/relaciones de un plugin, columna Capa condicional, persistencia de cambios no guardados al reordenar |
| `E2ETest.html` | `views/pages/EntityList.js` + `EntityEdit.js` | Flujo combinado: de listar entidad a crear un registro y ver la lista recargada |
| `SessionModelTest.html` | `models/SessionModel.js` | Almacena/limpia usuario, entidades y token; `reset()` restaura el estado inicial |
| `ApiTest.html` | `models/ApiClientModel.js` | Métodos HTTP, cabecera `Authorization`, manejo de 401/422, refresco de token vía `X-Refreshed-Token` |
| `EntityRecordModelTest.html` | `models/EntityRecordModel.js` (`recordSummaryLabel`) | Concatena campos `summaryView` respetando orden, cae al `fallbackId` si no hay contenido válido |
| `ThemeModelTest.html` | `models/ThemeModel.js` | Fusiona preferencias de UI, notifica suscriptores normalizados, `resetUiPreferences` restaura valores por defecto |
| `AvatarUploadTest.html` | `models/AvatarUpload.js` | Valida tamaño máximo de fichero, convierte imagen a data URL, rechaza si falla `FileReader` |
| `UiResilienceTest.html` | `services/UiResilienceService.js` + `AppController` | Normaliza errores en mensajes amigables, modales de confirmación, notificaciones globales/flotantes |
| `DynamicTabsTest.html` | `views/modules/DynamicTabs.js` | Cambia tab activo por click/teclado, mueve la ink bar, registra tabs dinámicamente sin duplicados |
| `DynamicTableTest.html` | `views/modules/DynamicTable.js` | Columnas dinámicas desde schema, orden asc/desc, paginación remota/local, densidad persistida en cookies |
| `DynamicFormTest.html` | `views/modules/DynamicForm.js` | Genera controles por tipo, valida requeridos/rangos, hidrata selects de relación vía `setFieldOptions()` |
| `ComponentsTest.html` | `views/modules/ComponentFactory.js` | Fábrica central de componentes UI, `inputSelect.setOptions()`, breadcrumb con dropdown, cálculo de `passwordStrength` |
| `NavbarTest.html` | `views/modules/Navbar.js`, `UserMenu.js` | Enlaces por entidad, menú de usuario oculto que abre al hover, acción de gestión solo para admins |
| `ModalTest.html` | `views/modules/Modal.js` | `show()`/`close()`, `setContent()` con texto o nodo, cierre al pulsar backdrop, `destroy()` elimina el overlay del DOM |
| `ThemeSettingsPanelTest.html` | `views/modules/ThemeSettingsPanel.js` | Abre/cierra el panel, cambia `pageStyle` a dark, `navigationMode` a side, `themeColor` actualiza `--x-brand-500` |
| `FrontendArchitectureTest.html` | `controllers/RouteController.js`, `RouteMapController.js` + layouts | Navegación por hash, back/forward, construcción exacta del árbol del shell |

## Ejecutar la suite E2E (Playwright)

### Prerrequisitos

1. Apache+PHP sirviendo el proyecto en el runtime canónico same-origin (ver
   `docs/08-operations/apache-vhost-examples.md`). Por defecto la suite
   apunta a `http://127.0.0.1/xestify/`.
2. Base de datos con el usuario admin seed y los plugins sincronizados al
   menos una vez — el boot normal ya no auto-seedea ni auto-sincroniza:

   ```bash
   php tools/setup/seed-admin-user.php
   php tools/setup/sync-plugins.php
   ```

### Setup (una sola vez)

```bash
cd frontend/tests/e2e
npm install
npx playwright install chromium
```

### Ejecución

```bash
cd frontend/tests/e2e
npx playwright test              # headless, reporter list + html local
npx playwright test --headed     # con navegador visible
npx playwright test login.spec.js   # un solo archivo
```

Para apuntar a una URL base distinta (otro puerto, alias o host):

```bash
XESTIFY_E2E_BASE_URL=http://127.0.0.1/otra-ruta/ npx playwright test
```

### Qué cubre cada spec (8 ficheros, 21 tests)

| Spec | Tests | Cobertura |
|---|---|---|
| `login.spec.js` | 8 | Login válido/inválido, campo requerido vacío, formato de email inválido, doble submit, accesos rápidos admin y usuario normal (condicionados a `APP_DEBUG`), sesión inválida almacenada → aviso de caducidad |
| `entity-crud.spec.js` | 3 | Alta y edición de una persona vía URL directa; borrado con verificación de soft-delete (404 posterior); regresión de foco tras cargar tabs |
| `plugin-manager.spec.js` | 2 | Activar/desactivar un plugin desde la lista; desinstalar un plugin desde la UI (desactivar → borrar → confirmar) |
| `shell-navigation.spec.js` | 4 | Shell persistente entre entidades; back/forward; 2 tests de regresión de una condición de carrera de navegación (ver más abajo) |
| `theme-wysiwyg.spec.js` | 1 | Cambiar el tema, aplicación inmediata (WYSIWYG) y persistencia tras recargar |
| `orders-invoices.spec.js` | 1 | Crear un pedido y una factura ligada a él vía `id_order`, verificando la relación en la respuesta real de la API |
| `optometries-contact-lenses.spec.js` | 1 | Añadir una ficha de optometría y una de lentillas de contacto a una persona, de extremo a extremo |
| `input-select-viewport.spec.js` | 1 | Regresión: el panel de un selector de relación con muchas opciones se abre dentro del viewport, no por debajo |

**Los 2 tests de regresión de `shell-navigation.spec.js`** documentan dos
variantes de la misma condición de carrera de navegación
(`AppController`/`EntityList`/`EntityEdit`): navegar a una
entidad mientras una llamada anterior (una redirección tras guardar, o una
navegación previa) sigue en curso podía dejar la pantalla equivocada en
pantalla. Ambos fuerzan el orden de las respuestas con `page.route()` en
vez de depender de timing real.

### Convenciones de la suite

- `frontend/tests/e2e/tests/_helpers.js` centraliza el login (`loginAsAdmin`),
  las credenciales del seed y helpers para el selector de relación a medida
  (`selectCustomOption`, `selectCustomOptionByValue`, `selectFirstCustomOption`,
  `useLargeTablePageSize`). No repetir el flujo de login en cada spec.
- **No todos los specs limpian los datos que crean** — depende de qué tipo
  de dato sea:
  - `theme-wysiwyg.spec.js` cambia una preferencia y la restaura al final
    (lee el valor original antes de tocar nada).
  - `plugin-manager.spec.js` registra una instancia de plugin *fixture*
    (`demoinventory` bajo un slug único) solo para el test, y la borra en
    un `finally` — es un dato desechable, no de negocio.
  - `entity-crud.spec.js`, `orders-invoices.spec.js`,
    `optometries-contact-lenses.spec.js` y los tests de regresión de
    `shell-navigation.spec.js` crean **registros de negocio reales**
    (personas, pedidos, facturas, fichas) y los dejan — el dataset local se
    trata como una demo que crece con cada ejecución, no como un fixture
    aislado que haya que revertir. `entity-crud.spec.js` explica el motivo
    en su propio comentario de cabecera.
- `playwright.config.js` fija `workers: 1` / `fullyParallel: false`: los
  specs comparten el mismo dataset seedeado y no deben correr en paralelo.
- `frontend/tests/e2e/` no se sirve por Apache (`.htaccess` con
  `Require all denied`): Playwright dirige su propio navegador contra la
  `baseURL`, no necesita que sus ficheros (`node_modules`, reportes) sean
  accesibles por HTTP.

## Referencias

- [arquitectura.md](arquitectura.md): estructura de carpetas y flujo SPA
- [guia-extension.md](guia-extension.md): checklist de tests al añadir una página o un plugin de extensión
- `AGENTS.md`, sección "Tests": política de verificación en navegador integrado
- [Testing de backend](../06-backend/testing.md): equivalente para backend (72 tests `unit`/`integration-db`/`integration-plugins`)
