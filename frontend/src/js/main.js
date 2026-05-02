import { Api } from './modules/Api.js';
import { AppState } from './modules/State.js';
import { EntityEdit } from './pages/EntityEdit.js';
import { EntityList } from './pages/EntityList.js';
import { Login } from './pages/Login.js';
import { Navbar } from './modules/Navbar.js';

const STORAGE_TOKEN_KEY = 'xestify_access_token';
const STORAGE_USER_EMAIL_KEY = 'xestify_user_email';
const API_BASE = '/api/v1';

const app = document.getElementById('app');

if (app instanceof HTMLElement) {
  bootstrap(app);
}

function bootstrap(container) {
  const token = localStorage.getItem(STORAGE_TOKEN_KEY);
  const storedEmail = localStorage.getItem(STORAGE_USER_EMAIL_KEY);

  if (storedEmail !== null && storedEmail !== '') {
    AppState.setUser({ email: storedEmail });
  }

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
        localStorage.setItem(STORAGE_USER_EMAIL_KEY, email);
      } else {
        localStorage.removeItem(STORAGE_USER_EMAIL_KEY);
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
  const entitiesForNav = await loadEntitiesForNav(dashboardApi);
  const firstEntitySlug = entitiesForNav.length > 0 ? entitiesForNav[0].slug : '';
  const initialPage = firstEntitySlug === '' ? 'plugins' : `entity:${firstEntitySlug}`;

  const userEmail = AppState.getUserEmail();

  const navbar = new Navbar(navbarEl, {
    userEmail,
    entities: entitiesForNav,
    currentPage: initialPage,
    onLogout: () => {
      clearAuth();
      renderLogin(container);
    },
    onNavigate: (page) => {
      navigateTo(page, content, dashboardApi);
    },
  });
  navbar.setUserEmail(userEmail);

  await navigateTo(initialPage, content, dashboardApi);
}

async function navigateTo(page, content, api) {
  content.innerHTML = '';

  if (typeof page === 'string' && page.startsWith('entity:')) {
    const slug = page.slice('entity:'.length);
    await showEntityList(content, api, slug === '' ? null : slug);
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

async function loadEntitiesForNav(api) {
  try {
    const { data } = await api.get('/entities');
    const entities = Array.isArray(data) ? data.filter((entity) => typeof entity?.slug === 'string') : [];
    AppState.setEntities(entities);
    return entities;
  } catch {
    return [];
  }
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
      showEntityEdit(content, api, slug, null, {});
    },
    onEdit: (slug, recordId, record) => {
      showEntityEdit(content, api, slug, recordId, record);
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
 * @param {string|null} recordId       null = create, string = edit existing
 * @param {object} initialData         Pre-fill values for edit mode
 */
function showEntityEdit(content, api, slug, recordId, initialData) {
  const entities = AppState.getEntities();
  const schema = entities.find((e) => e.slug === slug) ?? { slug, fields: [] };

  content.innerHTML = '';

  const entityEdit = new EntityEdit(content, slug, schema, {
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

  return entityEdit;
}

function setAuthToken(token) {
  AppState.setToken(token);
}

function clearAuth() {
  AppState.reset();
  localStorage.removeItem(STORAGE_TOKEN_KEY);
  localStorage.removeItem(STORAGE_USER_EMAIL_KEY);
}
