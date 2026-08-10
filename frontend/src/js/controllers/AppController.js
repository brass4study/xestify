import { createApi, createApiWithToken, isUnauthorizedError } from '../models/ApiClientModel.js';
import { PluginRouteController } from './PluginRouteController.js';
import {
  entityCreatePage,
  entityPage,
  entityRecordPage,
  entityTabPage,
  getPageFromHash,
  hashFromPage,
  parseEntityTabPage,
  userDetailPage,
} from './RouteMapController.js';
import { SessionModel } from '../models/SessionModel.js';
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
import { component } from '../views/modules/ComponentFactory.js';
import { RouteController } from './RouteController.js';

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

    this.router = new RouteController({
      onNavigate: async (page) => {
        if (this.activateCurrentEntityRoute(page)) {
          return;
        }
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
    this.shellLayout = null;
    this.contentContainer = null;

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
    const navbarContainer = this.shellLayout.getTarget('shell-menu-nav');
    const userMenuContainer = this.shellLayout.getTarget('shell-menu-config-user');

    this.dashboardApi = createApiWithToken(SessionModel.getToken());

    const entitiesForNav = await this.loadEntitiesForNav(this.dashboardApi);
    if (entitiesForNav === null) {
      return;
    }

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

    this.unsubscribeNavbar();

    this.currentNavbar = new Navbar(navbarContainer, {
      userEmail: SessionModel.getUserEmail(),
      userName,
      avatar,
      roles: userRoles,
      userContainer: userMenuContainer,
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
    const recordRoute = tabRoute === null ? parseEntityRecordPageToken(page) : null;
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
    const parsed = parseEntityRecordPageToken(page);
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
      text: message,
    }).setData('role', 'placeholder');
    PageLayout.create(this.contentContainer, { shell: this.shellLayout })
      .setContent(placeholder);
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
      : parseEntityRecordPageToken(page);
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
      const parsed = parseEntityRecordPageToken(page);
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
