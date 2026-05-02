import { Api } from './modules/Api.js';
import { AppState } from './modules/State.js';
import { EntityEdit } from './pages/EntityEdit.js';
import { EntityList } from './pages/EntityList.js';
import { Login } from './pages/Login.js';
import { Navbar } from './modules/Navbar.js';

const STORAGE_TOKEN_KEY = 'xestify_access_token';
const API_BASE = '/api/v1';

const app = document.getElementById('app');

if (app instanceof HTMLElement) {
  bootstrap(app);
}

function bootstrap(container) {
  const token = localStorage.getItem(STORAGE_TOKEN_KEY);

  if (token !== null && token !== '') {
    setAuthToken(token);
    renderDashboard(container);
    return;
  }

  renderLogin(container);
}

function renderLogin(container) {
  const loginApi = new Api(API_BASE);

  const loginPage = new Login(container, {
    api: loginApi,
    onSuccess: ({ accessToken, email }) => {
      localStorage.setItem(STORAGE_TOKEN_KEY, accessToken);
      setAuthToken(accessToken);
      if (typeof email === 'string') {
        AppState.setUser({ email });
      }
      renderDashboard(container);
    },
  });

  return loginPage;
}

async function renderDashboard(container) {
  container.innerHTML = '';

  const shell = document.createElement('section');
  shell.className = 'xt-shell';

  const navbarEl = document.createElement('div');
  navbarEl.className = 'xt-shell__navbar';
  shell.appendChild(navbarEl);

  const content = document.createElement('main');
  content.className = 'xt-shell__content';
  shell.appendChild(content);

  container.appendChild(shell);

  const dashboardApi = new Api(API_BASE);
  dashboardApi.setToken(AppState.getToken());

  const userEmail = AppState.getUserEmail();

  const navbar = new Navbar(navbarEl, {
    userEmail,
    onLogout: () => {
      clearAuth();
      renderLogin(container);
    },
    onNavigate: (page) => {
      navigateTo(page, content, dashboardApi);
    },
  });

  // Suppress unused-variable warning — navbar manages its own DOM
  void navbar;

  await navigateTo('entities', content, dashboardApi);
}

async function navigateTo(page, content, api) {
  content.innerHTML = '';

  if (page === 'entities') {
    await showEntityList(content, api, null);
    return;
  }

  if (page === 'plugins') {
    const msg = document.createElement('p');
    msg.className = 'xt-placeholder';
    msg.textContent = 'Plugin Manager — próximamente.';
    content.appendChild(msg);
    return;
  }

  content.innerHTML = '<p>Página no encontrada.</p>';
}

/**
 * Render EntityList in the content area, optionally pre-loading a specific entity.
 *
 * @param {HTMLElement} content
 * @param {Api} api
 * @param {string|null} preloadSlug  If set, loadEntity(preloadSlug) is called after init
 */
async function showEntityList(content, api, preloadSlug) {
  content.innerHTML = '';

  let entityListPage;

  entityListPage = new EntityList(content, {
    api,
    onCreateNew: (slug) => {
      showEntityEdit(content, api, slug, entityListPage, null, {});
    },
    onEdit: (slug, recordId, record) => {
      showEntityEdit(content, api, slug, entityListPage, recordId, record);
    },
  });

  try {
    await entityListPage.init();
    if (preloadSlug !== null) {
      await entityListPage.loadEntity(preloadSlug);
    }
  } catch {
    content.innerHTML = '<p>No se pudo cargar la lista de entidades.</p>';
  }
}

/**
 * Render EntityEdit in the content area. On save, returns to EntityList and
 * reloads the entity records. On cancel, returns to EntityList.
 *
 * @param {HTMLElement} content
 * @param {Api} api
 * @param {string} slug
 * @param {EntityList} entityListPage  Current list instance (not used directly)
 * @param {string|null} recordId       null = create, string = edit existing
 * @param {object} initialData         Pre-fill values for edit mode
 */
function showEntityEdit(content, api, slug, entityListPage, recordId, initialData) {
  const entities = AppState.getEntities();
  const schema = entities.find((e) => e.slug === slug) ?? { slug, fields: [] };

  content.innerHTML = '';

  void new EntityEdit(content, slug, schema, {
    api,
    recordId: recordId ?? null,
    initialData: initialData ?? {},
    onSaved: async () => {
      await showEntityList(content, api, slug);
    },
    onCancel: async () => {
      await showEntityList(content, api, slug);
    },
  });

  // Suppress unused reference to entityListPage; kept for API consistency
  void entityListPage;
}

function setAuthToken(token) {
  AppState.setToken(token);
}

function clearAuth() {
  AppState.reset();
  localStorage.removeItem(STORAGE_TOKEN_KEY);
}
