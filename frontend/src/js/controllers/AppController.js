import { createApi, createApiWithToken, isUnauthorizedError } from '../models/ApiClientModel.js';
import { loadUiPreferences, saveUiPreferences } from '../models/AppConfigurationModel.js';
import { PluginRouteController } from './PluginRouteController.js';
import {
  entityCreatePage,
  entityPage,
  entityRecordPage,
  entityTabPage,
  getPageFromHash,
  hashFromPage,
  parseEntityRecordPage,
  parseEntityTabPage,
  userDetailPage,
} from './RouteMapController.js';
import { SessionModel } from '../models/SessionModel.js';
import { AppState } from '../models/StateModel.js';
import { applyUiPreferencesToDocument } from '../models/ThemeModel.js';
import { t } from '../models/I18nModel.js';
import { UiResilienceService } from '../services/UiResilienceService.js';
import { PageLayout } from '../views/layout/PageLayout.js';
import { ShellLayout } from '../views/layout/ShellLayout.js';
import { EntityEdit } from '../views/pages/EntityEdit.js';
import { EntityList } from '../views/pages/EntityList.js';
import { Login } from '../views/pages/Login.js';
import { PluginConfig } from '../views/pages/PluginConfig.js';
import { PluginManager } from '../views/pages/PluginManager.js';
import { UserConfig } from '../views/pages/UserConfig.js';
import { UserManager } from '../views/pages/UserManager.js';
import { UserProfile } from '../views/pages/UserProfile.js';
import { Navbar } from '../views/modules/Navbar.js';
import { ThemeSettingsPanel } from '../views/modules/ThemeSettingsPanel.js';
import { RouteController } from './RouteController.js';
import { component } from '../views/modules/ComponentFactory.js';

export class AppController {
  /** @param {HTMLElement} container */
  constructor(container) {
    this.container = container;
    this.currentNavbar = null;
    this.navbarSubscription = null;
    this.dashboardApi = null;
    this.shellLayout = null;
    this.contentContainer = null;
    this.currentEntityEdit = null;
    this.currentEntityRoute = null;
    this.themeSettingsPanel = null;
    this.currentThemeSettingsNavigationMode = null;
    this.uiSubscription = null;
    this.notificationSubscription = null;
    this.uiHydrating = false;
    this.uiSaveTimer = null;
    this.currentNavbarNavigationMode = null;

    this.router = new RouteController({
      onNavigate: async (page) => {
        if (this.activateCurrentEntityRoute(page)) {
          return;
        }
        await this.navigateTo(page);
      },
    });
    this.globalErrorHandler = (event) => {
      const error = event?.error ?? event?.reason ?? null;
      const message = UiResilienceService.handleError(error, t('ui.error.generic', 'Ha ocurrido un error inesperado.'));
      UiResilienceService.showNotification({
        type: 'error',
        title: t('ui.error.generic', 'Error'),
        message,
      });
      this.scheduleGlobalNotificationsRender(true);
    };
    window.addEventListener('error', this.globalErrorHandler);
    window.addEventListener('unhandledrejection', this.globalErrorHandler);
    this.notificationSubscription = AppState.subscribeNotification(() => {
      this.scheduleGlobalNotificationsRender();
    });
  }

  scheduleGlobalNotificationsRender(immediate = false) {
    if (this.notificationRenderScheduled && !immediate) {
      return;
    }

    if (typeof window === 'undefined') {
      this.renderGlobalNotifications();
      return;
    }

    this.notificationRenderScheduled = true;

    const runRender = () => {
      this.notificationRenderScheduled = false;
      this.renderGlobalNotifications();
    };

    if (immediate || typeof window.requestAnimationFrame !== 'function') {
      runRender();
      return;
    }

    window.requestAnimationFrame(runRender);
  }

  async start() {
    this.subscribeUiPreferences();
    this.syncUiPreferences();
    this.scheduleGlobalNotificationsRender();

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
    this.destroyThemeSettingsPanel();
    this.clearUiSaveTimer();
    this.currentNavbar = null;
    this.currentNavbarNavigationMode = null;
    this.dashboardApi = null;
    this.shellLayout = null;
    this.contentContainer = null;
    this.renderGlobalNotifications();

    if (window.location.hash === '' || window.location.hash === '#') {
      window.history.replaceState(null, '', '#/login');
    }

    const loginLayout = PageLayout.create(this.container)
      .setTemplate('login')
      .setFooter('Xestify MVP · Acceso seguro')
      .build();
    this.contentContainer = loginLayout.getContentTarget();

    const loginApi = createApi();

    return new Login(this.contentContainer, {
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
    this.shellLayout = ShellLayout.create(this.container).build();
    this.contentContainer = this.shellLayout.getTarget('shell-main-content');
    const uiPreferences = AppState.getUiPreferences();
    const isMixedNavigation = uiPreferences.navigationMode === 'mixed';
    let navbarContainer;
    let themeSettingsContainer;
    let userMenuContainer;
    let linksContainer;
    if (isMixedNavigation) {
      ({
        navbarContainer,
        themeSettingsContainer,
        userMenuContainer,
        linksContainer,
      } = this.#resolveMixedNavbarTargets());
    } else {
      ({
        navbarContainer,
        themeSettingsContainer,
        userMenuContainer,
        linksContainer,
      } = this.#resolveDefaultNavbarTargets());
    }
    let navbarOrientation = 'side';
    if (uiPreferences.navigationMode === 'top' || isMixedNavigation) {
      navbarOrientation = 'top';
    }

    this.dashboardApi = createApiWithToken(SessionModel.getToken());
    const hydrated = await this.hydrateUiPreferences();
    if (hydrated === null) {
      return;
    }
    this.syncUiPreferences();
    this.renderGlobalNotifications();

    const entitiesForNav = await this.loadEntitiesForNav(this.dashboardApi);
    if (entitiesForNav === null) {
      return;
    }

    const {
      isAdmin,
      fallbackPage,
      initialPage,
      userName,
      avatar,
      userRoles,
    } = this.#resolveDashboardIdentity(entitiesForNav);

    this.destroyThemeSettingsPanel();
    if (isAdmin && themeSettingsContainer instanceof HTMLElement) {
      this.themeSettingsPanel = new ThemeSettingsPanel(themeSettingsContainer);
      this.currentThemeSettingsNavigationMode = uiPreferences.navigationMode;
    }

    this.unsubscribeNavbar();

    const navbarOptions = {
      orientation: navbarOrientation,
      userEmail: SessionModel.getUserEmail(),
      userName,
      avatar,
      roles: userRoles,
      userContainer: userMenuContainer,
      linksContainer,
      linksOrientation: isMixedNavigation ? 'side' : 'top',
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
    };

    this.currentNavbar = new Navbar(navbarContainer, navbarOptions);
    this.currentNavbarNavigationMode = uiPreferences.navigationMode;

    this.syncNavbarFromState();
    this.navbarSubscription = SessionModel.subscribe(() => {
      this.syncNavbarFromState();
    });

    await this.router.start(fallbackPage);
  }

  /**
   * @returns {{ navbarContainer: HTMLElement|null, themeSettingsContainer: HTMLElement|null, userMenuContainer: HTMLElement|null, linksContainer: HTMLElement|null }}
   */
  #resolveMixedNavbarTargets() {
    return {
      navbarContainer: this.shellLayout.getTarget('shell-mixed-bar-nav'),
      themeSettingsContainer: this.shellLayout.getTarget('shell-mixed-bar-config-theme'),
      userMenuContainer: this.shellLayout.getTarget('shell-mixed-bar-config-user'),
      linksContainer: this.shellLayout.getTarget('shell-menu-nav'),
    };
  }

  /**
   * @returns {{ navbarContainer: HTMLElement|null, themeSettingsContainer: HTMLElement|null, userMenuContainer: HTMLElement|null, linksContainer: HTMLElement|null }}
   */
  #resolveDefaultNavbarTargets() {
    return {
      navbarContainer: this.shellLayout.getTarget('shell-menu-nav'),
      themeSettingsContainer: this.shellLayout.getTarget('shell-menu-config-theme'),
      userMenuContainer: this.shellLayout.getTarget('shell-menu-config-user'),
      linksContainer: null,
    };
  }

  /**
   * @param {string} navigationMode
   * @returns {{ navbarContainer: HTMLElement|null, themeSettingsContainer: HTMLElement|null, userMenuContainer: HTMLElement|null, linksContainer: HTMLElement|null }|null}
   */
  #resolveNavigationTargets(navigationMode) {
    if (!(this.shellLayout instanceof ShellLayout)) {
      return null;
    }

    if (navigationMode === 'mixed') {
      return this.#resolveMixedNavbarTargets();
    }

    return this.#resolveDefaultNavbarTargets();
  }

  /**
   * @param {string} navigationMode
   * @param {{ navbarContainer: HTMLElement|null, themeSettingsContainer: HTMLElement|null, userMenuContainer: HTMLElement|null, linksContainer: HTMLElement|null }|null} targets
   */
  #syncThemeSettingsPanelTargets(navigationMode, targets) {
    if (!(targets && targets.themeSettingsContainer instanceof HTMLElement)) {
      return;
    }

    if (!(this.themeSettingsPanel instanceof ThemeSettingsPanel)) {
      return;
    }

    if (this.currentThemeSettingsNavigationMode === navigationMode) {
      return;
    }

    this.themeSettingsPanel.setContainer(targets.themeSettingsContainer);
    this.currentThemeSettingsNavigationMode = navigationMode;
  }

  /**
   * @param {string} navigationMode
   * @param {{ navbarContainer: HTMLElement|null, themeSettingsContainer: HTMLElement|null, userMenuContainer: HTMLElement|null, linksContainer: HTMLElement|null }|null} targets
   */
  #syncNavbarTargets(navigationMode, targets) {
    if (!(targets && this.currentNavbar instanceof Navbar)) {
      return;
    }

    if (this.currentNavbarNavigationMode !== navigationMode) {
      this.currentNavbar.setLayoutTargets({
        container: targets.navbarContainer,
        userContainer: targets.userMenuContainer,
        linksContainer: targets.linksContainer,
        linksOrientation: navigationMode === 'mixed' ? 'side' : 'top',
        orientation: navigationMode === 'side' ? 'side' : 'top',
      });
      this.currentNavbarNavigationMode = navigationMode;
      return;
    }

    this.currentNavbar.setOrientation(navigationMode === 'side' ? 'side' : 'top');
  }

  /**
   * @param {Array<{slug?: string}>} entitiesForNav
   * @returns {{ isAdmin: boolean, fallbackPage: string, initialPage: string, userName: string|null, avatar: string|null, userRoles: string[] }}
   */
  #resolveDashboardIdentity(entitiesForNav) {
    const isAdmin = SessionModel.currentUserIsAdmin();
    const firstEntitySlug = entitiesForNav[0]?.slug ?? '';
    let fallbackPage = isAdmin ? 'plugins' : 'home';
    if (firstEntitySlug !== '') {
      fallbackPage = entityPage(firstEntitySlug);
    }

    let initialPage = getPageFromHash(window.location.hash, fallbackPage);
    if (initialPage === 'login') {
      initialPage = fallbackPage;
      window.history.replaceState(null, '', hashFromPage(fallbackPage));
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

    return {
      isAdmin,
      fallbackPage,
      initialPage,
      userName,
      avatar,
      userRoles,
    };
  }

  subscribeUiPreferences() {
    if (this.uiSubscription !== null) {
      return;
    }

    this.uiSubscription = AppState.subscribeUi(() => {
      this.syncUiPreferences();
      this.scheduleUiPreferencesSave();
    });
  }

  syncUiPreferences() {
    const uiPreferences = AppState.getUiPreferences();
    applyUiPreferencesToDocument(uiPreferences, {
      app: this.container,
    });

    if (this.shellLayout instanceof ShellLayout) {
      this.shellLayout.applyUiPreferences(uiPreferences);
    }

    const targets = this.#resolveNavigationTargets(uiPreferences.navigationMode);
    this.#syncThemeSettingsPanelTargets(uiPreferences.navigationMode, targets);
    this.#syncNavbarTargets(uiPreferences.navigationMode, targets);
    this.renderGlobalNotifications();
  }

  destroyThemeSettingsPanel() {
    if (this.themeSettingsPanel instanceof ThemeSettingsPanel) {
      this.themeSettingsPanel.destroy();
      this.themeSettingsPanel = null;
      this.currentThemeSettingsNavigationMode = null;
    }
  }

  async hydrateUiPreferences() {
    if (this.dashboardApi === null) {
      return;
    }

    this.uiHydrating = true;
    try {
      const settings = await loadUiPreferences(this.dashboardApi);
      if (settings !== null) {
        AppState.setUiPreferences(settings);
      }
    } catch (error) {
      if (isUnauthorizedError(error)) {
        this.clearAuth();
        this.renderLogin();
        return null;
      }
      // La UI ya dispone de fallback local/default; no bloquear el arranque.
    } finally {
      this.uiHydrating = false;
    }
  }

  scheduleUiPreferencesSave() {
    if (this.uiHydrating || this.dashboardApi === null || !SessionModel.currentUserIsAdmin()) {
      return;
    }

    this.clearUiSaveTimer();
    this.uiSaveTimer = window.setTimeout(() => {
      void this.persistUiPreferences();
    }, 180);
  }

  clearUiSaveTimer() {
    if (this.uiSaveTimer !== null) {
      window.clearTimeout(this.uiSaveTimer);
      this.uiSaveTimer = null;
    }
  }

  async persistUiPreferences() {
    if (this.dashboardApi === null || !SessionModel.currentUserIsAdmin()) {
      return;
    }

    try {
      await saveUiPreferences(this.dashboardApi, AppState.getUiPreferences());
    } catch {
      // Mantener la UX local aunque falle el guardado remoto.
    }
  }

  async navigateTo(page) {
    if (!(this.contentContainer instanceof HTMLElement)) {
      return;
    }

    this.renderGlobalNotifications();

    this.currentEntityEdit = null;
    this.currentEntityRoute = null;

    if (this.currentNavbar instanceof Navbar) {
      const navbarPage = this.resolveNavbarPage(page);
      if (navbarPage !== '') {
        this.currentNavbar.setActive(navbarPage);
      }
    }

    this.applyTemplateForPage(page);

    if (this.dashboardApi === null) {
      this.showPlaceholder('No se pudo preparar la navegacion.');
      return;
    }

    this.contentContainer.replaceChildren();

    const wasHandled = await this.handlePageNavigation(page);
    if (!wasHandled) {
      this.showPlaceholder('Pagina no encontrada.');
    }
  }

  activateCurrentEntityRoute(page) {
    if (!(this.currentEntityEdit instanceof EntityEdit) || this.currentEntityRoute === null) {
      return false;
    }

    const tabRoute = parseEntityTabPage(page);
    const recordRoute = tabRoute === null ? parseEntityRecordPage(page) : null;
    const route = tabRoute ?? (recordRoute === null ? null : { ...recordRoute, tabId: 'data' });
    if (route === null
      || route.slug !== this.currentEntityRoute.slug
      || route.recordId !== this.currentEntityRoute.recordId
      || !this.currentEntityEdit.setActiveTab(route.tabId)) {
      return false;
    }

    const template = this.buildTemplateDefinition(page);
    PageLayout.create(this.contentContainer, { shell: this.shellLayout })
      .setBreadcrumbs(template.breadcrumbs ?? []);
    return true;
  }

  async handlePageNavigation(page) {
    const handlers = [
      {
        matches: (nextPage) => nextPage === 'home',
        run: () => this.showHomePage(),
      },
      {
        matches: (nextPage) => nextPage === 'login',
        run: () => this.renderLogin(),
      },
      {
        matches: (nextPage) => typeof nextPage === 'string' && nextPage.startsWith('entity:'),
        run: (nextPage) => this.showEntityPage(nextPage),
      },
      {
        matches: (nextPage) => typeof nextPage === 'string' && nextPage.startsWith('entity-create:'),
        run: (nextPage) => this.showEntityCreatePage(nextPage),
      },
      {
        matches: (nextPage) => typeof nextPage === 'string' && nextPage.startsWith('entity-tab:'),
        run: (nextPage) => this.showEntityTabPage(nextPage),
      },
      {
        matches: (nextPage) => typeof nextPage === 'string' && nextPage.startsWith('entity-record:'),
        run: (nextPage) => this.showEntityRecordPage(nextPage),
      },
      {
        matches: (nextPage) => PluginRouteController.isPluginConfigPage(nextPage),
        run: (nextPage) => this.showPluginConfigPage(nextPage),
      },
      {
        matches: (nextPage) => nextPage === 'plugins',
        run: () => this.showPluginsPage(),
      },
      {
        matches: (nextPage) => nextPage === 'profile',
        run: () => this.showProfilePage(),
      },
      {
        matches: (nextPage) => nextPage === 'users',
        run: () => this.showUsersPage(),
      },
      {
        matches: (nextPage) => typeof nextPage === 'string' && nextPage.startsWith('users:'),
        run: (nextPage) => this.showUserConfigPage(nextPage.slice('users:'.length)),
      },
    ];

    for (const handler of handlers) {
      if (!handler.matches(page)) {
        continue;
      }

      await handler.run(page);
      return true;
    }

    return false;
  }

  showHomePage() {
    this.showPlaceholder('Selecciona una seccion para empezar.');
  }

  async showEntityPage(page) {
    const slug = page.slice('entity:'.length);
    await this.showEntityList(slug === '' ? null : slug);
  }

  async showEntityCreatePage(page) {
    const slug = page.slice('entity-create:'.length);
    if (slug === '') {
      this.showPlaceholder('No se pudo abrir el formulario de alta.');
      return;
    }

    await this.showEntityEdit(slug, null, null);
  }

  async showEntityRecordPage(page) {
    const parsed = parseEntityRecordPage(page);
    if (parsed === null) {
      this.showPlaceholder('No se pudo abrir la ficha del registro.');
      return;
    }

    await this.showEntityEdit(parsed.slug, parsed.recordId, null);
  }

  async showEntityTabPage(page) {
    const parsed = parseEntityTabPage(page);
    if (parsed === null) {
      this.showPlaceholder('No se pudo abrir la pestaña del registro.');
      return;
    }

    await this.showEntityEdit(parsed.slug, parsed.recordId, null, parsed.tabId);
  }

  async showPluginConfigPage(page) {
    const slug = PluginRouteController.getPluginSlugFromPage(page);
    if (slug !== '') {
      await this.showPluginConfig(slug);
    }
  }

  async showPluginsPage() {
    if (!SessionModel.currentUserIsAdmin()) {
      this.showPlaceholder('Acceso denegado: solo administradores.');
      return;
    }

    const pageHeader = this.buildTemplateDefinition('plugins').pageHeader ?? {};
    const pluginManager = new PluginManager(this.contentContainer, this.dashboardApi, {
      shellLayout: this.shellLayout,
      title: pageHeader.title,
      description: pageHeader.subtitle,
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
    const pageHeader = this.buildTemplateDefinition('profile').pageHeader ?? {};
    return new UserProfile(this.contentContainer, displayUser, this.dashboardApi, {
      shellLayout: this.shellLayout,
      title: pageHeader.title,
      subtitle: pageHeader.subtitle,
    });
  }

  async showUsersPage() {
    if (!SessionModel.currentUserIsAdmin()) {
      this.showPlaceholder('Acceso denegado: solo administradores.');
      return;
    }

    const pageHeader = this.buildTemplateDefinition('users').pageHeader ?? {};
    const userManagementPage = new UserManager(this.contentContainer, {
      api: this.dashboardApi,
      shellLayout: this.shellLayout,
      title: pageHeader.title,
      description: pageHeader.subtitle,
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
      this.showPlaceholder('Acceso denegado: solo administradores.');
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
      this.showPlaceholder('No se pudo cargar la ficha de usuario.');
      return;
    }

    const currentUser = SessionModel.getUser();
    const currentUserId = currentUser && typeof currentUser === 'object' && typeof currentUser.id === 'string'
      ? currentUser.id
      : null;

    const pageHeader = this.buildTemplateDefinition(userDetailPage(userId)).pageHeader ?? {};
    return new UserConfig(this.contentContainer, {
      mode: 'admin',
      user: selectedUser,
      api: this.dashboardApi,
      shellLayout: this.shellLayout,
      currentUserId,
      title: pageHeader.title,
      subtitle: pageHeader.subtitle,
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
    const pageHeader = preloadSlug === null
      ? {}
      : this.buildTemplateDefinition(entityPage(preloadSlug)).pageHeader ?? {};

    const entityListPage = new EntityList(this.contentContainer, {
      api: this.dashboardApi,
      shellLayout: this.shellLayout,
      title: pageHeader.title,
      description: pageHeader.subtitle,
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
      this.showPlaceholder('No se pudo cargar la lista de entidades.');
    }
  }

  async showEntityEdit(slug, recordId, initialData, initialTab = null) {
    this.contentContainer.replaceChildren();
    this.showPlaceholder('Cargando formulario...');

    const schema = await this.loadEntitySchema(slug);
    if (schema === null) {
      this.showPlaceholder('No se pudo cargar el formulario de la entidad.');
      return null;
    }

    const hasInitialData = initialData !== null
      && typeof initialData === 'object'
      && Object.keys(initialData).length > 0;

    let dataForForm = hasInitialData ? initialData : {};
    if (recordId !== null && !hasInitialData) {
      const recordData = await this.loadEntityRecord(slug, recordId);
      if (recordData === null) {
        this.showPlaceholder('No se pudo cargar la ficha del registro.');
        return null;
      }

      dataForForm = recordData;
    }

    this.contentContainer.replaceChildren();
    const pageToken = recordId === null
      ? entityCreatePage(slug)
      : entityRecordPage(slug, recordId);
    const pageHeader = this.buildTemplateDefinition(pageToken).pageHeader ?? {};

    const entityEdit = new EntityEdit(this.contentContainer, slug, schema, {
      api: this.dashboardApi,
      recordId: recordId ?? null,
      initialData: dataForForm,
      initialTab,
      onTabChange: recordId === null
        ? null
        : async (tabId) => {
          const page = entityTabPage(slug, recordId, tabId);
          await this.router.navigate(page, { updateHash: true, notify: false });
          const template = this.buildTemplateDefinition(page);
          PageLayout.create(this.contentContainer, { shell: this.shellLayout })
            .setBreadcrumbs(template.breadcrumbs ?? []);
        },
      shellLayout: this.shellLayout,
      title: pageHeader.title,
      description: pageHeader.subtitle,
      onSaved: async () => {
        await this.router.navigate(entityPage(slug), { updateHash: true });
      },
      onCancel: async () => {
        await this.router.navigate(entityPage(slug), { updateHash: true });
      },
    });
    this.currentEntityEdit = entityEdit;
    this.currentEntityRoute = { slug, recordId };
    return entityEdit;
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
    this.showPlaceholder('Cargando configuracion del plugin...');

    const pageHeader = this.buildTemplateDefinition(
      PluginRouteController.toPluginConfigPage(slug)
    ).pageHeader ?? {};
    const page = new PluginConfig(this.contentContainer, {
      slug,
      api: this.dashboardApi,
      shellLayout: this.shellLayout,
      title: pageHeader.title,
      description: pageHeader.subtitle,
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
    this.currentNavbarNavigationMode = null;
    this.dashboardApi = null;
    this.shellLayout = null;
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

  applyTemplateForPage(page) {
    if (this.shellLayout === null) {
      return;
    }

    const template = this.buildTemplateDefinition(page);
    const pageHeader = template.pageHeader ?? {};
    PageLayout.create(this.contentContainer, { shell: this.shellLayout })
      .setTemplate(template.template ?? 'home')
      .setBreadcrumbs(template.breadcrumbs ?? [])
      .setTitle(pageHeader.title ?? '')
      .setDescription(pageHeader.subtitle ?? '')
      .setHeaderToolbar(null)
      .setHeaderBottom(null)
      .setActions(null)
      .setFooter(template.footerText ?? '');
    this.shellLayout.clearZone('shell-main-notifications');
    this.contentContainer = this.shellLayout.getTarget('shell-main-content');
  }

  showPlaceholder(message) {
    const placeholder = component.create('p', {
      className: 'rounded-xl border border-dashed border-slate-300 bg-white/70 px-4 py-10 text-center text-sm text-slate-500',
      text: typeof message === 'string' && message !== '' ? message : t('ui.error.generic', 'Ha ocurrido un error inesperado.'),
    }).setData('role', 'placeholder');

    const target = this.contentContainer instanceof HTMLElement ? this.contentContainer : this.container;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    if (this.shellLayout instanceof ShellLayout) {
      PageLayout.create(target, { shell: this.shellLayout }).setContent(placeholder);
      return;
    }

    target.replaceChildren(placeholder);
  }

  #resolveNotificationHost() {
    if (this.shellLayout instanceof ShellLayout) {
      const shellTarget = this.shellLayout.getTarget('shell-floating-ui');
      if (shellTarget instanceof HTMLElement) {
        return shellTarget;
      }
    }

    if (this.globalNotificationHost instanceof HTMLElement) {
      return this.globalNotificationHost;
    }

    const fallbackHost = component.create('div');
    fallbackHost.setData('role', 'shell-floating-ui');
    fallbackHost.setClassName('pointer-events-none fixed inset-0 isolate z-[9999] flex items-start justify-center px-4 pt-4');

    const appRoot = this.container instanceof HTMLElement ? this.container : document.body;
    if (appRoot instanceof HTMLElement) {
      appRoot.appendChild(fallbackHost);
    }

    this.globalNotificationHost = fallbackHost;
    return fallbackHost;
  }

  renderPageNotification(notification = AppState.getNotification()) {
    if (!(this.shellLayout instanceof ShellLayout)) {
      return;
    }

    if (!notification || typeof notification !== 'object' || notification.global === true) {
      this.shellLayout.clearZone('shell-main-notifications');
      return;
    }

    const type = typeof notification.type === 'string' && notification.type !== '' ? notification.type : 'info';
    const title = typeof notification.title === 'string' && notification.title !== '' ? notification.title : '';
    const message = typeof notification.message === 'string' ? notification.message : '';
    const toneClasses = this.#resolvePageNotificationTone(type);

    const banner = component.create('div')
      .setData('role', 'page-notification')
      .setData('type', type)
      .setClassName(`flex items-start justify-between gap-3 rounded-xl px-4 py-3 text-sm ${toneClasses}`);
    banner.setAttribute('role', 'status');
    banner.setAttribute('aria-live', 'polite');

    const content = component.create('div');
    if (title !== '') {
      component.create('div', {
        className: 'font-semibold',
        text: title,
      }).setParent(content);
    }

    if (message !== '') {
      const messageClassName = title !== '' ? 'mt-1' : '';
      component.create('div', {
        className: messageClassName,
        text: message,
      }).setParent(content);
    }

    content.setParent(banner);
    this.shellLayout.setZone('shell-main-notifications', banner);
  }

  #resolvePageNotificationTone(type) {
    if (type === 'error') {
      return 'bg-red-50 text-red-700';
    }

    if (type === 'warning') {
      return 'bg-amber-50 text-amber-700';
    }

    if (type === 'success') {
      return 'bg-emerald-50 text-emerald-700';
    }

    return 'bg-slate-50 text-slate-700';
  }

  renderGlobalNotifications() {
    const target = this.#resolveNotificationHost();
    if (!(target instanceof HTMLElement)) {
      return;
    }

    const notification = AppState.getNotification();
    target.replaceChildren();

    if (!notification || typeof notification !== 'object') {
      target.hidden = true;
      this.renderPageNotification(null);
      return;
    }

    if (notification.global !== true) {
      target.hidden = true;
      this.renderPageNotification(notification);
      return;
    }

    const type = typeof notification.type === 'string' && notification.type !== '' ? notification.type : 'info';
    const title = typeof notification.title === 'string' && notification.title !== '' ? notification.title : 'Aviso';
    const message = typeof notification.message === 'string' ? notification.message : '';

    const backdrop = component.create('div');
    backdrop.setData('role', 'modal-backdrop');
    backdrop.setClassName('modal-backdrop');
    backdrop.style.zIndex = '10000';
    backdrop.style.pointerEvents = 'auto';

    const dialog = component.create('div', {
      className: 'pointer-events-auto relative z-[10001] mt-2 w-full max-w-lg rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-2xl shadow-slate-900/20 backdrop-blur transition-all duration-200',
    });
    dialog.setData('role', 'global-notification');
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('tabindex', '-1');

    const accentClassName = this.#resolveGlobalNotificationAccent(type);

    dialog.setClassName(`pointer-events-auto w-full max-w-lg rounded-2xl border p-4 shadow-2xl shadow-slate-900/20 backdrop-blur transition-all duration-200 ${accentClassName}`);
    dialog.style.animation = 'toast-slide-in 220ms ease-out';

    const header = component.create('div', { className: 'flex items-start justify-between gap-3' });
    const content = component.create('div');
    component.create('div', {
      className: 'text-sm font-semibold',
      text: title,
    }).setParent(content);
    component.create('div', {
      className: 'mt-1 text-sm opacity-90',
      text: message,
    }).setParent(content);

    const closeButton = component.create('button', {
      label: '×',
      ariaLabel: 'Cerrar notificación',
      dataAction: 'dismiss-global-notification',
    });
    closeButton.setClassName('inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 text-lg text-slate-600 transition hover:bg-slate-100');
    closeButton.addEventListener('click', () => {
      dialog.style.animation = 'toast-slide-out 220ms ease-in forwards';
      backdrop.style.animation = 'theme-backdrop-exit 220ms ease-in forwards';
      window.setTimeout(() => {
        AppState.clearNotification();
        this.renderGlobalNotifications();
      }, 220);
    });

    content.setParent(header);
    closeButton.setParent(header);
    header.setParent(dialog);
    target.appendChild(backdrop);
    target.appendChild(dialog);
    target.hidden = false;

    window.requestAnimationFrame(() => {
      dialog.focus();
    });
  }

  #resolveGlobalNotificationAccent(type) {
    if (type === 'error') {
      return 'border-red-200 bg-red-50 text-red-800';
    }

    if (type === 'warning') {
      return 'border-amber-200 bg-amber-50 text-amber-800';
    }

    return 'border-slate-200 bg-white text-slate-800';
  }

  buildTemplateDefinition(page) {
    if (typeof page !== 'string') {
      return {
        template: 'home',
      };
    }

    const resolvers = [
      this.resolvePluginTemplate.bind(this),
      this.resolveUsersTemplate.bind(this),
      this.resolveProfileTemplate.bind(this),
      this.resolveEntityCreateTemplate.bind(this),
      this.resolveEntityRecordTemplate.bind(this),
      this.resolveEntityListTemplate.bind(this),
    ];

    for (const resolver of resolvers) {
      const resolved = resolver(page);
      if (resolved !== null) {
        return resolved;
      }
    }

    return this.defaultHomeTemplate();
  }

  resolvePluginTemplate(page) {
    if (page === 'plugins') {
      return {
        template: 'plugin-management',
        breadcrumbs: this.makeBreadcrumbItems([
          { label: 'Sistema' },
          { label: 'Plugins', active: true },
        ]),
        pageHeader: {
          title: 'Gestión de plugins',
          subtitle: 'Sincroniza, activa y configura plugins del sistema.',
        },
        footerText: 'Zona de extensiones preparada para sync, update y rollback.',
      };
    }

    if (!PluginRouteController.isPluginConfigPage(page)) {
      return null;
    }

    const slug = PluginRouteController.getPluginSlugFromPage(page);
    return {
      template: 'plugin-management',
      breadcrumbs: this.makeBreadcrumbItems([
        { label: 'Sistema' },
        { label: 'Plugins', href: '#/plugins' },
        { label: slug === '' ? 'Configuración' : `Configuración: ${slug}`, active: true },
      ]),
      pageHeader: {
        title: 'Configuración de plugin',
        subtitle: 'Ajusta opciones específicas del plugin activo.',
      },
      footerText: slug === '' ? '' : `Plugin objetivo: ${slug}`,
    };
  }

  resolveUsersTemplate(page) {
    if (page === 'users') {
      return {
        template: 'list',
        breadcrumbs: this.makeBreadcrumbItems([
          { label: 'Sistema' },
          { label: 'Usuarios', active: true },
        ]),
        pageHeader: {
          title: 'Gestión de usuarios',
          subtitle: 'Administra cuentas, roles y accesos.',
        },
      };
    }

    if (!page.startsWith('users:')) {
      return null;
    }

    const userId = page.slice('users:'.length);
    return {
      template: 'detail',
      breadcrumbs: this.makeBreadcrumbItems([
        { label: 'Sistema' },
        { label: 'Usuarios', href: '#/users' },
        { label: userId === '' ? 'Detalle' : `Usuario ${userId}`, active: true },
      ]),
      pageHeader: {
        title: 'Detalle de usuario',
        subtitle: 'Consulta o edita la ficha del usuario seleccionado.',
      },
    };
  }

  resolveProfileTemplate(page) {
    if (page !== 'profile') {
      return null;
    }

    return {
      template: 'detail',
      breadcrumbs: this.makeBreadcrumbItems([
        { label: 'Cuenta' },
        { label: 'Mi perfil', active: true },
      ]),
      pageHeader: {
        title: 'Mi perfil',
        subtitle: 'Actualiza tus datos personales y tu contraseña.',
      },
    };
  }

  resolveEntityCreateTemplate(page) {
    if (!page.startsWith('entity-create:')) {
      return null;
    }

    const slug = page.slice('entity-create:'.length);
    const label = this.resolveEntityLabel(slug);
    return {
      template: 'detail',
      breadcrumbs: this.makeBreadcrumbItems([
        { label: 'Operaciones' },
        { label, href: `#/entity/${encodeURIComponent(slug)}` },
        { label: 'Nuevo registro', active: true },
      ]),
      pageHeader: {
        title: `Nuevo registro: ${label}`,
        subtitle: 'Completa los campos requeridos y guarda para continuar.',
      }
    };
  }

  resolveEntityRecordTemplate(page) {
    const isRecordPage = page.startsWith('entity-record:');
    const isTabPage = page.startsWith('entity-tab:');
    if (!isRecordPage && !isTabPage) {
      return null;
    }

    const entityData = isTabPage
      ? parseEntityTabPage(page)
      : parseEntityRecordPage(page);
    if (entityData === null) {
      return null;
    }

    const label = this.resolveEntityLabel(entityData.slug);
    const breadcrumbs = [
      { label: 'Operaciones' },
      { label, href: `#/entity/${encodeURIComponent(entityData.slug)}` },
      { label: `Registro ${entityData.recordId}`, active: !isTabPage },
    ];
    if (isTabPage) {
      breadcrumbs.push({ label: entityData.tabId, active: true });
    }

    return {
      template: 'detail',
      breadcrumbs: this.makeBreadcrumbItems(breadcrumbs),
      pageHeader: {
        title: `Detalle de ${label}`,
        subtitle: 'Edita datos y extensiones del registro seleccionado.',
      }
    };
  }

  resolveEntityListTemplate(page) {
    if (!page.startsWith('entity:')) {
      return null;
    }

    const slug = page.slice('entity:'.length);
    const label = this.resolveEntityLabel(slug);
    return {
      template: 'list',
      breadcrumbs: this.makeBreadcrumbItems([
        { label: 'Operaciones' },
        { label, active: true },
      ]),
      pageHeader: {
        title: label,
        subtitle: 'Explora registros y accede a acciones contextuales.',
      },
    };
  }

  defaultHomeTemplate() {
    return {
      template: 'home',
      breadcrumbs: this.makeBreadcrumbItems([
        { label: 'Workspace', active: true },
      ]),
      pageHeader: {
        title: 'Panel de trabajo',
        subtitle: 'Selecciona una sección para empezar.',
      },
    };
  }

  resolveNavbarPage(page) {
    if (typeof page !== 'string') {
      return '';
    }

    if (page.startsWith('entity:') || page.startsWith('entity-create:')
      || page.startsWith('entity-record:') || page.startsWith('entity-tab:')) {
      const slug = this.resolveEntitySlugFromPage(page);
      return slug === '' ? '' : `entity:${slug}`;
    }

    if (page === 'plugins' || PluginRouteController.isPluginConfigPage(page)) {
      return 'plugins';
    }

    if (page === 'users' || page.startsWith('users:') || page === 'profile') {
      return '';
    }

    return page;
  }

  resolveEntitySlugFromPage(page) {
    if (page.startsWith('entity:')) {
      return page.slice('entity:'.length);
    }

    if (page.startsWith('entity-create:')) {
      return page.slice('entity-create:'.length);
    }

    if (page.startsWith('entity-record:')) {
      const parsed = parseEntityRecordPage(page);
      return parsed === null ? '' : parsed.slug;
    }

    if (page.startsWith('entity-tab:')) {
      const parsed = parseEntityTabPage(page);
      return parsed === null ? '' : parsed.slug;
    }

    return '';
  }

  resolveEntityLabel(slug) {
    const normalizedSlug = typeof slug === 'string' ? slug.trim() : '';
    if (normalizedSlug === '') {
      return 'Entidad';
    }

    const entities = SessionModel.getEntities();
    const match = Array.isArray(entities)
      ? entities.find((entity) => entity?.slug === normalizedSlug)
      : null;
    const label = typeof match?.label === 'string' ? match.label.trim() : '';
    return label === '' ? normalizedSlug : label;
  }

  makeBreadcrumbItems(items) {
    return items.map((item) => {
      if (item.active === true) {
        return {
          label: item.label,
          active: true,
        };
      }

      if (typeof item.href === 'string' && item.href !== '') {
        return {
          label: item.label,
          href: item.href,
        };
      }

      return {
        label: item.label,
      };
    });
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
