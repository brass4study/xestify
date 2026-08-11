# Testing de UI

## Jerarquía de tests

```text
frontend/tests/
├── css/, js/helpers.js       Assets compartidos por los runners de integración
├── integration/               19 runners HTML: componentes/páginas en DOM real
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
  página de forma aislada y rápida.
- **`frontend/tests/e2e/*.spec.js`** (Playwright): abren un navegador real,
  navegan la SPA servida por Apache tal cual la vería un usuario, y golpean
  el backend PHP y la base de datos reales. Verifican que las piezas
  encajan de extremo a extremo: login real, routing real, persistencia real.

## Ejecutar los tests de integración

Ver la sección "Tests" de `AGENTS.md` para el flujo canónico: abrir el
runner en el navegador integrado de VS Code, servido por Apache
(`ENABLE_TEST=1`) o un servidor HTTP local equivalente. Esa vía sigue siendo
la prioritaria para validar visualmente un runner HTML.

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

### Qué cubre cada spec

| Spec | Cobertura |
|------|-----------|
| `login.spec.js` | Login con credenciales válidas e inválidas |
| `shell-navigation.spec.js` | Navegación entre entidades, shell persistente, back/forward |
| `entity-crud.spec.js` | Alta y edición de un registro real de la entidad `clients` |
| `plugin-manager.spec.js` | Activar/desactivar un plugin desde `PluginManager` |
| `theme-wysiwyg.spec.js` | Cambiar el tema, verificar aplicación inmediata (WYSIWYG) y persistencia tras recargar |

### Convenciones de la suite

- `frontend/tests/e2e/tests/_helpers.js` centraliza el login (`loginAsAdmin`)
  y las credenciales del seed. No repetir el flujo de login en cada spec.
- Los specs que crean o mutan datos (`entity-crud`, `plugin-manager`,
  `theme-wysiwyg`) leen el estado inicial cuando hace falta y lo restauran al
  terminar, para no dejar el dataset local en un estado distinto entre
  ejecuciones.
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
