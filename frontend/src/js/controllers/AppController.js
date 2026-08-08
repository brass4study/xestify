import { createApi, createApiWithToken, isUnauthorizedError } from '../models/ApiClientModel.js';
import { PluginRouteController } from './PluginRouteController.js';
import {
  entityCreatePage,
  entityPage,
  entityRecordPage,
  getPageFromHash,
  userDetailPage,
} from './RouteMapController.js';
import { SessionModel } from '../models/SessionModel.js';
import { ShellLayoutView } from '../views/layout/ShellLayoutView.js';
import { EntityEdit } from '../views/pages/EntityEdit.js';
import { EntityList } from '../views/pages/EntityList.js';
import { Login } from '../views/pages/Login.js';
import { PluginConfig } from '../views/pages/PluginConfig.js';
import { PluginManager } from '../views/pages/PluginManager.js';
import { UserConfig } from '../views/pages/UserConfig.js';
import { UserManager } from '../views/pages/UserManager.js';
import { UserProfile } from '../views/pages/UserProfile.js';
import { Navbar } from '../views/modules/Navbar.js';
import { RouteController } from './RouteController.js';

export class AppController {
  /** @param {HTMLElement} container */
  constructor(container) {
    this.container = container;
    this.currentNavbar = null;
    this.navbarSubscription = null;
    this.dashboardApi = null;
    this.contentContainer = null;

    this.router = new RouteController({
      onNavigate: async (page) => {
        await this.navigateTo(page);
      },
    });
  }

  async start() {
    const storedSession = SessionModel.readStoredSession();

    if (typeof storedSession.token === 'string' && storedSession.token !== '') {
      SessionModel.setToken(storedSession.token);
      const fallbackUser = SessionModel.buildFallbackUser(storedSession.token, {
        email: storedSession.email,
        name: storedSession.name,
        avatar: storedSession.avatar,
      });

      SessionModel.setUser(fallbackUser);
      const profile = await this.loadCurrentUserProfile(storedSession.token, fallbackUser);
      if (profile === null) {
        return;
      }

      await this.renderDashboard();
      return;
    }

    if (typeof storedSession.email === 'string' && storedSession.email !== '') {
      SessionModel.setUser({
        email: storedSession.email,
        name: storedSession.name,
        avatar: storedSession.avatar,
        roles: [],
      });
    }

    this.renderLogin();
  }

  renderLogin() {
    this.router.stop();
    this.unsubscribeNavbar();
    this.currentNavbar = null;
    this.dashboardApi = null;
    this.contentContainer = null;

    const loginApi = createApi();

    return new Login(this.container, {
      api: loginApi,
      onSuccess: async ({ accessToken, email }) => {
        SessionModel.persistAccessToken(accessToken);
        SessionModel.setToken(accessToken);

        const fallbackUser = SessionModel.buildFallbackUser(accessToken, {
          email: typeof email === 'string' ? email : null,
        });

        SessionModel.setUser(fallbackUser);
        SessionModel.persistUserSnapshot(fallbackUser);

        const profile = await this.loadCurrentUserProfile(accessToken, fallbackUser);
        if (profile === null) {
          return;
        }

        await this.renderDashboard();
      },
    });
  }

  async renderDashboard() {
    const { navbarContainer, contentContainer } = ShellLayoutView.createDashboardLayout(this.container);
    this.contentContainer = contentContainer;

    this.dashboardApi = createApiWithToken(SessionModel.getToken());

    const entitiesForNav = await this.loadEntitiesForNav(this.dashboardApi);
    if (entitiesForNav === null) {
      return;
    }

    const isAdmin = SessionModel.currentUserIsAdmin();
    const firstEntitySlug = entitiesForNav.length > 0 ? entitiesForNav[0].slug : '';
    let fallbackPage = '';
    if (firstEntitySlug !== '') {
      fallbackPage = entityPage(firstEntitySlug);
    } else if (isAdmin) {
      fallbackPage = 'plugins';
    }

    const initialPage = getPageFromHash(window.location.hash, fallbackPage);
    const currentUser = SessionModel.getUser();
    const userName = currentUser && typeof currentUser === 'object' && typeof currentUser.name === 'string'
      ? currentUser.name
      : null;
    const avatar = currentUser && typeof currentUser === 'object' && typeof currentUser.avatar === 'string'
      ? currentUser.avatar
      : null;
    const userRoles = currentUser && typeof currentUser === 'object' && Array.isArray(currentUser.roles)
      ? currentUser.roles
      : [];

    this.unsubscribeNavbar();

    this.currentNavbar = new Navbar(navbarContainer, {
      userEmail: SessionModel.getUserEmail(),
      userName,
      avatar,
      roles: userRoles,
      entities: entitiesForNav,
      currentPage: initialPage,
      canManagePlugins: isAdmin,
      onLogout: () => {
        this.clearAuth();
        this.renderLogin();
      },
      onNavigate: async (page) => {
        await this.router.navigate(page, { updateHash: true });
      },
    });

    this.syncNavbarFromState();
    this.navbarSubscription = SessionModel.subscribe(() => {
      this.syncNavbarFromState();
    });

    await this.router.start(fallbackPage);
  }

  async navigateTo(page) {
    if (!(this.contentContainer instanceof HTMLElement)) {
      return;
    }

    if (this.dashboardApi === null) {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'No se pudo preparar la navegacion.');
      return;
    }

    this.contentContainer.replaceChildren();

    if (typeof page === 'string' && page.startsWith('entity:')) {
      await this.showEntityPage(page);
      return;
    }

    if (typeof page === 'string' && page.startsWith('entity-create:')) {
      await this.showEntityCreatePage(page);
      return;
    }

    if (typeof page === 'string' && page.startsWith('entity-record:')) {
      await this.showEntityRecordPage(page);
      return;
    }

    if (PluginRouteController.isPluginConfigPage(page)) {
      await this.showPluginConfigPage(page);
      return;
    }

    if (page === 'plugins') {
      await this.showPluginsPage();
      return;
    }

    if (page === 'profile') {
      this.showProfilePage();
      return;
    }

    if (page === 'users') {
      await this.showUsersPage();
      return;
    }

    if (typeof page === 'string' && page.startsWith('users:')) {
      const userId = page.slice('users:'.length);
      await this.showUserConfigPage(userId);
      return;
    }

    ShellLayoutView.showPlaceholder(this.contentContainer, 'Pagina no encontrada.');
  }

  async showEntityPage(page) {
    const slug = page.slice('entity:'.length);
    await this.showEntityList(slug === '' ? null : slug);
  }

  async showEntityCreatePage(page) {
    const slug = page.slice('entity-create:'.length);
    if (slug === '') {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'No se pudo abrir el formulario de alta.');
      return;
    }

    await this.showEntityEdit(slug, null, null);
  }

  async showEntityRecordPage(page) {
    const parsed = parseEntityRecordPageToken(page);
    if (parsed === null) {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'No se pudo abrir la ficha del registro.');
      return;
    }

    await this.showEntityEdit(parsed.slug, parsed.recordId, null);
  }

  async showPluginConfigPage(page) {
    const slug = PluginRouteController.getPluginSlugFromPage(page);
    if (slug !== '') {
      await this.showPluginConfig(slug);
    }
  }

  async showPluginsPage() {
    if (!SessionModel.currentUserIsAdmin()) {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'Acceso denegado: solo administradores.');
      return;
    }

    const pluginManager = new PluginManager(this.contentContainer, this.dashboardApi, {
      onConfigure: (plugin) => {
        void this.router.navigate(PluginRouteController.toPluginConfigPage(plugin.slug), { updateHash: true });
      },
    });
    await pluginManager.init();
  }

  showProfilePage() {
    const currentUser = SessionModel.getUser();
    const displayUser = currentUser && typeof currentUser === 'object'
      ? {
          email: typeof currentUser.email === 'string' ? currentUser.email : SessionModel.getUserEmail(),
          name: typeof currentUser.name === 'string' ? currentUser.name : null,
          avatar: typeof currentUser.avatar === 'string' ? currentUser.avatar : null,
          roles: Array.isArray(currentUser.roles) ? currentUser.roles : [],
        }
      : { email: SessionModel.getUserEmail(), roles: [] };
    return new UserProfile(this.contentContainer, displayUser, this.dashboardApi);
  }

  async showUsersPage() {
    if (!SessionModel.currentUserIsAdmin()) {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'Acceso denegado: solo administradores.');
      return;
    }

    const userManagementPage = new UserManager(this.contentContainer, {
      api: this.dashboardApi,
      onViewUser: (user) => {
        if (user && typeof user.id === 'string') {
          void this.router.navigate(userDetailPage(user.id), { updateHash: true });
        }
      },
    });

    await userManagementPage.init();
    return userManagementPage;
  }

  async showUserConfigPage(userId) {
    if (!SessionModel.currentUserIsAdmin()) {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'Acceso denegado: solo administradores.');
      return;
    }

    if (typeof userId !== 'string' || userId === '') {
      await this.showUsersPage();
      return;
    }

    let selectedUser = null;
    try {
      const response = await this.dashboardApi.get(`/users/${userId}`);
      selectedUser = response?.data ?? null;
    } catch {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'No se pudo cargar la ficha de usuario.');
      return;
    }

    const currentUser = SessionModel.getUser();
    const currentUserId = currentUser && typeof currentUser === 'object' && typeof currentUser.id === 'string'
      ? currentUser.id
      : null;

    return new UserConfig(this.contentContainer, {
      mode: 'admin',
      user: selectedUser,
      api: this.dashboardApi,
      currentUserId,
      title: 'Configuración de usuario',
      subtitle: 'Página de configuración del usuario seleccionado.',
      onBack: () => {
        void this.router.navigate('users', { updateHash: true });
      },
      onDeleted: () => {
        void this.router.navigate('users', { updateHash: true });
      },
    });
  }

  async loadEntitiesForNav(api) {
    try {
      const { data } = await api.get('/entities');
      const entities = Array.isArray(data) ? data.filter((entity) => typeof entity?.slug === 'string') : [];
      SessionModel.setEntities(entities);
      return entities;
    } catch (error) {
      if (isUnauthorizedError(error)) {
        this.clearAuth();
        this.renderLogin();
        return null;
      }

      return [];
    }
  }

  async showEntityList(preloadSlug) {
    this.contentContainer.replaceChildren();

    const entityListPage = new EntityList(this.contentContainer, {
      api: this.dashboardApi,
      onCreateNew: (slug) => {
        void this.router.navigate(entityCreatePage(slug), { updateHash: true });
      },
      onEdit: (slug, recordId) => {
        void this.router.navigate(entityRecordPage(slug, recordId), { updateHash: true });
      },
    });

    try {
      await entityListPage.init();
      if (preloadSlug !== null) {
        await entityListPage.loadEntity(preloadSlug);
      }
    } catch {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'No se pudo cargar la lista de entidades.');
    }
  }

  async showEntityEdit(slug, recordId, initialData) {
    this.contentContainer.replaceChildren();
    ShellLayoutView.showPlaceholder(this.contentContainer, 'Cargando formulario...');

    const schema = await this.loadEntitySchema(slug);
    if (schema === null) {
      ShellLayoutView.showPlaceholder(this.contentContainer, 'No se pudo cargar el formulario de la entidad.');
      return null;
    }

    const hasInitialData = initialData !== null
      && typeof initialData === 'object'
      && Object.keys(initialData).length > 0;

    let dataForForm = hasInitialData ? initialData : {};
    if (recordId !== null && !hasInitialData) {
      const recordData = await this.loadEntityRecord(slug, recordId);
      if (recordData === null) {
        ShellLayoutView.showPlaceholder(this.contentContainer, 'No se pudo cargar la ficha del registro.');
        return null;
      }

      dataForForm = recordData;
    }

    this.contentContainer.replaceChildren();

    return new EntityEdit(this.contentContainer, slug, schema, {
      api: this.dashboardApi,
      recordId: recordId ?? null,
      initialData: dataForForm,
      onSaved: async () => {
        await this.router.navigate(entityPage(slug), { updateHash: true });
      },
      onCancel: async () => {
        await this.router.navigate(entityPage(slug), { updateHash: true });
      },
    });
  }

  async loadEntitySchema(slug) {
    try {
      const { data } = await this.dashboardApi.get(`/entities/${slug}/schema`);
      if (data?.schema !== null && typeof data?.schema === 'object') {
        return data.schema;
      }
    } catch {
      return null;
    }

    return null;
  }

  async loadEntityRecord(slug, recordId) {
    try {
      const { data } = await this.dashboardApi.get(`/entities/${slug}/records/${recordId}`);
      return normalizeRecordContent(data);
    } catch {
      return null;
    }
  }

  async showPluginConfig(slug) {
    this.contentContainer.replaceChildren();
    ShellLayoutView.showPlaceholder(this.contentContainer, 'Cargando configuracion del plugin...');

    const page = new PluginConfig(this.contentContainer, {
      slug,
      api: this.dashboardApi,
      onBack: () => {
        void this.router.navigate('plugins', { updateHash: true });
      },
    });

    await page.init();
  }

  clearAuth() {
    this.router.stop();
    this.unsubscribeNavbar();
    this.currentNavbar = null;
    this.dashboardApi = null;
    this.contentContainer = null;

    SessionModel.reset();
    SessionModel.clearStoredSession();
  }

  unsubscribeNavbar() {
    if (this.navbarSubscription !== null) {
      this.navbarSubscription();
      this.navbarSubscription = null;
    }
  }

  syncNavbarFromState() {
    if (!(this.currentNavbar instanceof Navbar)) {
      return;
    }

    const currentUser = SessionModel.getUser();
    const userName = currentUser && typeof currentUser === 'object' && typeof currentUser.name === 'string'
      ? currentUser.name
      : null;
    const avatar = currentUser && typeof currentUser === 'object' && typeof currentUser.avatar === 'string'
      ? currentUser.avatar
      : null;
    const userRoles = currentUser && typeof currentUser === 'object' && Array.isArray(currentUser.roles)
      ? currentUser.roles
      : [];

    this.currentNavbar.setUserEmail(SessionModel.getUserEmail());
    this.currentNavbar.setUserName(userName);
    this.currentNavbar.setAvatar(avatar);
    this.currentNavbar.setRoles(userRoles);
  }

  async loadCurrentUserProfile(token, fallbackUser = null) {
    const api = createApiWithToken(token);

    try {
      const { data } = await api.get('/users/me');
      const profile = SessionModel.normalizeUserProfile(data ?? {}, fallbackUser);
      SessionModel.setUser(profile);
      SessionModel.persistUserSnapshot(profile);
      return profile;
    } catch (error) {
      if (isUnauthorizedError(error)) {
        this.clearAuth();
        this.renderLogin();
        return null;
      }

      const fallbackProfile = SessionModel.normalizeUserProfile(null, fallbackUser);
      SessionModel.setUser(fallbackProfile);
      SessionModel.persistUserSnapshot(fallbackProfile);
      return fallbackProfile;
    }
  }
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
