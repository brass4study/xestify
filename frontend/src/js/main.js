import { Api, ApiError } from './modules/Api.js';
import { buildAppUrl } from './modules/BasePath.js';
import { AppState } from './modules/State.js';
import { EntityEdit } from './pages/EntityEdit.js';
import { EntityList } from './pages/EntityList.js';
import { Login } from './pages/Login.js';
import { PluginConfig } from './pages/PluginConfig.js';
import { PluginManager } from './pages/PluginManager.js';
import { Navbar } from './modules/Navbar.js';
import {
  entityCreatePage,
  entityPage,
  entityRecordPage,
  getPageFromHash,
  hashFromPage,
  pluginConfigPage,
} from './modules/Routes.js';
import { UserConfig } from './pages/UserConfig.js';
import { UserProfile } from './pages/UserProfile.js';
import { UserManager } from './pages/UserManager.js';

const STORAGE_TOKEN_KEY = 'xestify_access_token';
const STORAGE_USER_EMAIL_KEY = 'xestify_user_email';
const STORAGE_USER_NAME_KEY = 'xestify_user_name';
const STORAGE_USER_AVATAR_KEY = 'xestify_user_avatar';
const API_BASE = buildAppUrl('/api/v1');

const app = document.getElementById('app');
let currentNavbar = null;
let navbarSubscription = null;
let hashNavigationHandler = null;
let suppressNextHashNavigation = false;

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
  shell.className = 'mx-auto flex min-h-screen w-full max-w-[1280px] flex-col';
  shell.dataset.role = 'app-shell';

  const navbarEl = document.createElement('div');
  navbarEl.className = 'sticky top-0 z-50';
  navbarEl.dataset.role = 'shell-navbar';
  shell.appendChild(navbarEl);

  const content = document.createElement('main');
  content.className = 'flex-1 px-3 py-4 sm:px-4 sm:py-6';
  content.dataset.role = 'shell-content';
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
  let fallbackPage = '';
  if (firstEntitySlug !== '') {
    fallbackPage = entityPage(firstEntitySlug);
  } else if (isAdmin) {
    fallbackPage = 'plugins';
  }
  const initialPage = getPageFromHash(window.location.hash, fallbackPage);

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
    onNavigate: async (page) => {
      await navigateTo(page, content, dashboardApi, { updateHash: true });
    },
  });
  syncNavbarFromState(currentNavbar);
  navbarSubscription = AppState.subscribe(() => {
    syncNavbarFromState(currentNavbar);
  });

  setupHashRouting(content, dashboardApi, fallbackPage);
  await navigateTo(initialPage, content, dashboardApi, { updateHash: true });
}

async function navigateTo(page, content, api, options = {}) {
  const shouldUpdateHash = options.updateHash === true;
  content.replaceChildren();

  if (shouldUpdateHash) {
    updateHashForPage(page);
  }

  if (typeof page === 'string' && page.startsWith('entity:')) {
    await showEntityPage(page, content, api);
    return;
  }

  if (typeof page === 'string' && page.startsWith('entity-create:')) {
    await showEntityCreatePage(page, content, api);
    return;
  }

  if (typeof page === 'string' && page.startsWith('entity-record:')) {
    await showEntityRecordPage(page, content, api);
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
    await showUsersPage(content, api);
    return;
  }

  if (typeof page === 'string' && page.startsWith('users:')) {
    const userId = page.slice('users:'.length);
    await showUserConfigPage(content, api, userId);
    return;
  }

  showPlaceholder(content, 'Pagina no encontrada.');
}

async function showEntityPage(page, content, api) {
  const slug = page.slice('entity:'.length);
  await showEntityList(content, api, slug === '' ? null : slug);
}

async function showEntityCreatePage(page, content, api) {
  const slug = page.slice('entity-create:'.length);
  if (slug === '') {
    showPlaceholder(content, 'No se pudo abrir el formulario de alta.');
    return;
  }

  await showEntityEdit(content, api, slug, null, null);
}

async function showEntityRecordPage(page, content, api) {
  const parsed = parseEntityRecordPageToken(page);
  if (parsed === null) {
    showPlaceholder(content, 'No se pudo abrir la ficha del registro.');
    return;
  }

  await showEntityEdit(content, api, parsed.slug, parsed.recordId, null);
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
      navigateTo(pluginConfigPage(plugin.slug), content, api);
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

async function showUsersPage(content, api) {
  if (!currentUserIsAdmin()) {
    showPlaceholder(content, 'Acceso denegado: solo administradores.');
    return;
  }

  const userManagementPage = new UserManager(content, {
    api,
  });
  await userManagementPage.init();
  return userManagementPage;
}

async function showUserConfigPage(content, api, userId) {
  if (!currentUserIsAdmin()) {
    showPlaceholder(content, 'Acceso denegado: solo administradores.');
    return;
  }

  if (typeof userId !== 'string' || userId === '') {
    await showUsersPage(content, api);
    return;
  }

  let selectedUser = null;
  try {
    const response = await api.get(`/users/${userId}`);
    selectedUser = response?.data ?? null;
  } catch {
    showPlaceholder(content, 'No se pudo cargar la ficha de usuario.');
    return;
  }

  const currentUser = AppState.getUser();
  const currentUserId = currentUser && typeof currentUser === 'object' && typeof currentUser.id === 'string'
    ? currentUser.id
    : null;

  const userConfigPage = new UserConfig(content, {
    mode: 'admin',
    user: selectedUser,
    api,
    currentUserId,
    title: 'Configuración de usuario',
    subtitle: 'Página de configuración del usuario seleccionado.',
    onBack: () => {
      navigateTo('users', content, api, { updateHash: true });
    },
    onDeleted: () => {
      navigateTo('users', content, api, { updateHash: true });
    },
  });

  return userConfigPage;
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
      navigateTo(entityCreatePage(slug), content, api, { updateHash: true });
    },
    onEdit: (slug, recordId) => {
      navigateTo(entityRecordPage(slug, recordId), content, api, { updateHash: true });
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

  const hasInitialData = initialData !== null
    && typeof initialData === 'object'
    && Object.keys(initialData).length > 0;

  let dataForForm = hasInitialData ? initialData : {};
  if (recordId !== null && !hasInitialData) {
    const recordData = await loadEntityRecord(api, slug, recordId);
    if (recordData === null) {
      showPlaceholder(content, 'No se pudo cargar la ficha del registro.');
      return null;
    }

    dataForForm = recordData;
  }

  content.replaceChildren();

  const entityEdit = new EntityEdit(content, slug, schema, {
    api,
    recordId: recordId ?? null,
    initialData: dataForForm,
    onSaved: async () => {
      await navigateTo(entityPage(slug), content, api, { updateHash: true });
    },
    onCancel: async () => {
      await navigateTo(entityPage(slug), content, api, { updateHash: true });
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

async function loadEntityRecord(api, slug, recordId) {
  try {
    const { data } = await api.get(`/entities/${slug}/records/${recordId}`);
    return normalizeRecordContent(data);
  } catch {
    return null;
  }

  return null;
}

function normalizeRecordContent(row) {
  if (row === null || typeof row !== 'object') {
    return null;
  }

  const source = /** @type {Record<string, unknown>} */ (row);
  const content = extractRecordContentObject(source.content);
  if (Object.keys(content).length > 0) {
    return content;
  }

  return extractRecordFields(source);
}

function extractRecordContentObject(rawContent) {
  if (rawContent !== null && typeof rawContent === 'object' && !Array.isArray(rawContent)) {
    return /** @type {Record<string, unknown>} */ (rawContent);
  }

  if (typeof rawContent === 'string') {
    try {
      const parsed = JSON.parse(rawContent);
      if (parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return /** @type {Record<string, unknown>} */ (parsed);
      }
    } catch {
      return {};
    }
  }

  return {};
}

function extractRecordFields(source) {
  const result = {};
  const candidateKeys = ['name', 'email', 'phone', 'creation_stamp', 'is_active', 'title', 'description'];

  for (const key of candidateKeys) {
    if (Object.hasOwn(source, key)) {
      result[key] = source[key];
    }
  }

  return result;
}

function parseEntityRecordPageToken(page) {
  if (typeof page !== 'string' || !page.startsWith('entity-record:')) {
    return null;
  }

  const raw = page.slice('entity-record:'.length);
  const separatorIndex = raw.indexOf(':');
  if (separatorIndex <= 0 || separatorIndex >= raw.length - 1) {
    return null;
  }

  const slug = raw.slice(0, separatorIndex);
  const recordId = raw.slice(separatorIndex + 1);
  if (slug === '' || recordId === '') {
    return null;
  }

  return { slug, recordId };
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
  if (hashNavigationHandler !== null) {
    window.removeEventListener('hashchange', hashNavigationHandler);
    hashNavigationHandler = null;
  }

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
    created_at: null,
    roles: [],
  };

  normalizedUser.id = resolveUserString(baseUser.id, fallback.id);
  normalizedUser.email = resolveUserString(baseUser.email, fallback.email);
  normalizedUser.name = resolveUserString(baseUser.name, fallback.name);
  normalizedUser.avatar = resolveUserString(baseUser.avatar, fallback.avatar);
  normalizedUser.created_at = resolveDateField(
    baseUser.created_at,
    baseUser.creation_stamp,
    fallback.created_at,
    fallback.creation_stamp
  );
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

function resolveDateField(primaryValue, secondaryValue, fallbackPrimaryValue, fallbackSecondaryValue) {
  const direct = resolveUserString(primaryValue, secondaryValue);
  if (direct !== null) {
    return direct;
  }

  return resolveUserString(fallbackPrimaryValue, fallbackSecondaryValue);
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
  msg.className = 'rounded-xl border border-dashed border-slate-300 bg-white/70 px-4 py-10 text-center text-sm text-slate-500';
  msg.dataset.role = 'placeholder';
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

function setupHashRouting(content, api, fallbackPage) {
  if (hashNavigationHandler !== null) {
    window.removeEventListener('hashchange', hashNavigationHandler);
  }

  hashNavigationHandler = () => {
    if (suppressNextHashNavigation) {
      suppressNextHashNavigation = false;
      return;
    }

    const nextPage = getPageFromHash(window.location.hash, fallbackPage);
    void navigateTo(nextPage, content, api, { updateHash: false });
  };

  window.addEventListener('hashchange', hashNavigationHandler);
}

function updateHashForPage(page) {
  const targetHash = hashFromPage(page);
  if (targetHash === '' || window.location.hash === targetHash) {
    return;
  }

  suppressNextHashNavigation = true;
  window.location.hash = targetHash;
}
