import { Api, ApiError } from './modules/Api.js';
import { buildAppUrl } from './modules/BasePath.js';
import { AppState } from './modules/State.js';
import { EntityEdit } from './pages/EntityEdit.js';
import { EntityList } from './pages/EntityList.js';
import { Login } from './pages/Login.js';
import { PluginConfig } from './pages/PluginConfig.js';
import { PluginManager } from './pages/PluginManager.js';
import { Navbar } from './modules/Navbar.js';
import { UserProfile } from './pages/UserProfile.js';
import { UserManagement } from './pages/UserManagement.js';

const STORAGE_TOKEN_KEY = 'xestify_access_token';
const STORAGE_USER_EMAIL_KEY = 'xestify_user_email';
const STORAGE_USER_NAME_KEY = 'xestify_user_name';
const STORAGE_USER_AVATAR_KEY = 'xestify_user_avatar';
const API_BASE = buildAppUrl('/api/v1');

const app = document.getElementById('app');
let currentNavbar = null;
let navbarSubscription = null;

if (app instanceof HTMLElement) {
  bootstrap(app);
}

function bootstrap(container) {
  return (async () => {
    const token = localStorage.getItem(STORAGE_TOKEN_KEY);
    const storedEmail = localStorage.getItem(STORAGE_USER_EMAIL_KEY);
    const storedName = localStorage.getItem(STORAGE_USER_NAME_KEY);
    const storedAvatar = localStorage.getItem(STORAGE_USER_AVATAR_KEY);

    if (token !== null && token !== '') {
      setAuthToken(token);
      const payload = decodeJwtPayload(token);
      const fallbackUser = {
        email: storedEmail ?? (typeof payload?.email === 'string' ? payload.email : null),
        name: storedName ?? null,
        avatar: storedAvatar ?? null,
        roles: Array.isArray(payload?.roles) ? payload.roles : [],
      };

      AppState.setUser(fallbackUser);
      await loadCurrentUserProfile(container, token, fallbackUser);
      renderDashboard(container);
      return;
    }

    if (storedEmail !== null && storedEmail !== '') {
      AppState.setUser({ email: storedEmail, name: storedName ?? null, avatar: storedAvatar ?? null, roles: [] });
    }

    renderLogin(container);
  })();
}

function renderLogin(container) {
  const loginApi = new Api(API_BASE);

  const loginPage = new Login(container, {
    api: loginApi,
    onSuccess: async ({ accessToken, email }) => {
      localStorage.setItem(STORAGE_TOKEN_KEY, accessToken);
      setAuthToken(accessToken);
      const payload = decodeJwtPayload(accessToken);
      const fallbackUser = {
        email: typeof email === 'string' ? email : null,
        roles: Array.isArray(payload?.roles) ? payload.roles : [],
      };
      if (typeof email === 'string') {
        AppState.setUser(fallbackUser);
        localStorage.setItem(STORAGE_USER_EMAIL_KEY, email);
      } else {
        localStorage.removeItem(STORAGE_USER_EMAIL_KEY);
      }
      await loadCurrentUserProfile(container, accessToken, fallbackUser);
      renderDashboard(container);
    },
  });

  return loginPage;
}

async function renderDashboard(container) {
  container.replaceChildren();

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
  const entitiesForNav = await loadEntitiesForNav(dashboardApi, container);
  if (entitiesForNav === null) {
    return;
  }
  const isAdmin = currentUserIsAdmin();
  const firstEntitySlug = entitiesForNav.length > 0 ? entitiesForNav[0].slug : '';
  let initialPage = '';
  if (firstEntitySlug !== '') {
    initialPage = `entity:${firstEntitySlug}`;
  } else if (isAdmin) {
    initialPage = 'plugins';
  }

  const userEmail = AppState.getUserEmail();
  const currentUser = AppState.getUser();
  const userName = currentUser && typeof currentUser === 'object' && typeof currentUser.name === 'string' ? currentUser.name : null;
  const avatar = currentUser && typeof currentUser === 'object' && typeof currentUser.avatar === 'string' ? currentUser.avatar : null;
  const userRoles = currentUser && typeof currentUser === 'object' && Array.isArray(currentUser.roles) ? currentUser.roles : [];

  if (navbarSubscription !== null) {
    navbarSubscription();
    navbarSubscription = null;
  }

  currentNavbar = new Navbar(navbarEl, {
    userEmail,
    userName,
    avatar,
    roles: userRoles,
    entities: entitiesForNav,
    currentPage: initialPage,
    canManagePlugins: isAdmin,
    onLogout: () => {
      clearAuth();
      renderLogin(container);
    },
    onNavigate: (page) => {
      navigateTo(page, content, dashboardApi);
    },
  });
  syncNavbarFromState(currentNavbar);
  navbarSubscription = AppState.subscribe(() => {
    syncNavbarFromState(currentNavbar);
  });

  await navigateTo(initialPage, content, dashboardApi);
}

async function navigateTo(page, content, api) {
  content.replaceChildren();

  if (typeof page === 'string' && page.startsWith('entity:')) {
    await showEntityPage(page, content, api);
    return;
  }

  if (typeof page === 'string' && page.startsWith('/plugins/') && page.endsWith('/config')) {
    await showPluginConfigPage(page, content, api);
    return;
  }

  if (page === 'plugins') {
    await showPluginsPage(content, api);
    return;
  }

  if (page === 'profile') {
    showProfilePage(content, api);
    return;
  }

  if (page === 'users') {
    showUsersPage(content, api);
    return;
  }

  showPlaceholder(content, 'Pagina no encontrada.');
}

async function showEntityPage(page, content, api) {
  const slug = page.slice('entity:'.length);
  await showEntityList(content, api, slug === '' ? null : slug);
}

async function showPluginConfigPage(page, content, api) {
  const parts = page.split('/');
  const slug = parts.length >= 4 ? parts[2] : '';
  if (slug !== '') {
    await showPluginConfig(content, api, slug);
  }
}

async function showPluginsPage(content, api) {
  if (!currentUserIsAdmin()) {
    showPlaceholder(content, 'Acceso denegado: solo administradores.');
    return;
  }

  const pluginManager = new PluginManager(content, api, {
    onConfigure: (plugin) => {
      navigateTo(`/plugins/${plugin.slug}/config`, content, api);
    },
  });
  await pluginManager.init();
}

function showProfilePage(content, api) {
  const currentUser = AppState.getUser();
  const displayUser = currentUser && typeof currentUser === 'object'
    ? {
        email: typeof currentUser.email === 'string' ? currentUser.email : AppState.getUserEmail(),
        name: typeof currentUser.name === 'string' ? currentUser.name : null,
        avatar: typeof currentUser.avatar === 'string' ? currentUser.avatar : null,
        roles: Array.isArray(currentUser.roles) ? currentUser.roles : [],
      }
    : { email: AppState.getUserEmail(), roles: [] };
  const profilePage = new UserProfile(content, displayUser, api);
  return profilePage;
}

function showUsersPage(content, api) {
  if (!currentUserIsAdmin()) {
    showPlaceholder(content, 'Acceso denegado: solo administradores.');
    return;
  }

  const demoUsers = [
    { name: 'Ana García', email: 'ana.garcia@xestify.local', roles: ['admin'] },
    { name: 'Luis Pérez', email: 'luis.perez@xestify.local', roles: ['editor'] },
  ];
  const userManagementPage = new UserManagement(content, demoUsers);
  return userManagementPage;
}

async function loadEntitiesForNav(api, container) {
  try {
    const { data } = await api.get('/entities');
    const entities = Array.isArray(data) ? data.filter((entity) => typeof entity?.slug === 'string') : [];
    AppState.setEntities(entities);
    return entities;
  } catch (err) {
    if (err instanceof ApiError && err.code === 401) {
      clearAuth();
      renderLogin(container);
      return null;
    }
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
  content.replaceChildren();

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
    showPlaceholder(content, 'No se pudo cargar la lista de entidades.');
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
async function showEntityEdit(content, api, slug, recordId, initialData) {
  content.replaceChildren();
  showPlaceholder(content, 'Cargando formulario...');

  const schema = await loadEntitySchema(api, slug);
  if (schema === null) {
    showPlaceholder(content, 'No se pudo cargar el formulario de la entidad.');
    return null;
  }

  content.replaceChildren();

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

async function loadEntitySchema(api, slug) {
  try {
    const { data } = await api.get(`/entities/${slug}/schema`);
    if (data?.schema !== null && typeof data?.schema === 'object') {
      return data.schema;
    }
  } catch {
    return null;
  }

  return null;
}

async function showPluginConfig(content, api, slug) {
  content.replaceChildren();
  showPlaceholder(content, 'Cargando configuracion del plugin...');

  const page = new PluginConfig(content, {
    slug,
    api,
    onBack: () => {
      navigateTo('plugins', content, api);
    },
  });

  await page.init();
}

function setAuthToken(token) {
  AppState.setToken(token);
}

function clearAuth() {
  AppState.reset();
  localStorage.removeItem(STORAGE_TOKEN_KEY);
  localStorage.removeItem(STORAGE_USER_EMAIL_KEY);
  localStorage.removeItem(STORAGE_USER_NAME_KEY);
  localStorage.removeItem(STORAGE_USER_AVATAR_KEY);
}

function syncNavbarFromState(navbar) {
  if (!(navbar instanceof Navbar)) {
    return;
  }

  const currentUser = AppState.getUser();
  const userEmail = AppState.getUserEmail();
  const userName = currentUser && typeof currentUser === 'object' && typeof currentUser.name === 'string' ? currentUser.name : null;
  const avatar = currentUser && typeof currentUser === 'object' && typeof currentUser.avatar === 'string' ? currentUser.avatar : null;
  const userRoles = currentUser && typeof currentUser === 'object' && Array.isArray(currentUser.roles) ? currentUser.roles : [];

  navbar.setUserEmail(userEmail);
  navbar.setUserName(userName);
  navbar.setAvatar(avatar);
  navbar.setRoles(userRoles);
}

async function loadCurrentUserProfile(container, token, fallbackUser = null) {
  const api = new Api(API_BASE);
  api.setToken(token);

  try {
    const { data } = await api.get('/users/me');
    const profile = normalizeUserProfile(data ?? {}, fallbackUser);
    AppState.setUser(profile);
    return profile;
  } catch (error) {
    if (error instanceof ApiError && error.code === 401) {
      clearAuth();
      renderLogin(container);
      return null;
    }

    const fallbackProfile = normalizeUserProfile(null, fallbackUser);
    AppState.setUser(fallbackProfile);
    return fallbackProfile;
  }
}

function normalizeUserProfile(profile, fallbackUser = null) {
  const baseUser = getUserObject(profile);
  const fallback = getUserObject(fallbackUser);
  const normalizedUser = {
    id: null,
    email: null,
    name: null,
    avatar: null,
    roles: [],
  };

  normalizedUser.id = resolveUserString(baseUser.id, fallback.id);
  normalizedUser.email = resolveUserString(baseUser.email, fallback.email);
  normalizedUser.name = resolveUserString(baseUser.name, fallback.name);
  normalizedUser.avatar = resolveUserString(baseUser.avatar, fallback.avatar);
  normalizedUser.roles = resolveUserRoles(baseUser.roles, fallback.roles);

  return normalizedUser;
}

function getUserObject(value) {
  return value && typeof value === 'object' ? value : {};
}

function resolveUserString(value, fallbackValue) {
  if (typeof value === 'string') {
    return value;
  }

  if (typeof fallbackValue === 'string') {
    return fallbackValue;
  }

  return null;
}

function resolveUserRoles(value, fallbackValue) {
  if (Array.isArray(value)) {
    return value;
  }

  if (Array.isArray(fallbackValue)) {
    return fallbackValue;
  }

  return [];
}

function showPlaceholder(container, message) {
  const msg = document.createElement('p');
  msg.className = 'xt-placeholder';
  msg.textContent = message;
  container.replaceChildren(msg);
}

function decodeJwtPayload(token) {
  if (typeof token !== 'string' || token === '') {
    return null;
  }

  const parts = token.split('.');
  if (parts.length < 2) {
    return null;
  }

  try {
    const base64 = parts[1].replaceAll('-', '+').replaceAll('_', '/');
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
    const decoded = atob(padded);
    return JSON.parse(decoded);
  } catch {
    return null;
  }
}

function currentUserIsAdmin() {
  const user = AppState.getUser();
  if (user === null || typeof user !== 'object') {
    return false;
  }

  const roles = user.roles;
  return Array.isArray(roles) && roles.includes('admin');
}
