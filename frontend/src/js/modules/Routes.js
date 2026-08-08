export const HASH_ROUTE_MAP = Object.freeze({
  login: '#/login',
  dashboard: '#/',
  workbench: '#/workbench',
  profile: '#/profile',
  users: '#/users',
  userDetail: '#/users/:id',
  plugins: '#/plugins',
  pluginConfig: '#/plugins/:slug/config',
  entityList: '#/entity/:slug',
  entityCreate: '#/entity/:slug/new',
  entityDetail: '#/entity/:slug/:id',
  entityTab: '#/entity/:slug/:id/:tab',
  resultEmpty: '#/result/empty',
  resultError: '#/result/error',
  resultForbidden: '#/result/403',
});

export const PAGE_BLUEPRINTS = Object.freeze({
  login: Object.freeze({
    area: 'public',
    template: 'login',
    titleKey: 'auth.login.title',
    descriptionKey: 'auth.login.description',
  }),
  dashboard: Object.freeze({
    area: 'workspace',
    template: 'workbench',
    titleKey: 'app.dashboard.title',
    descriptionKey: 'app.dashboard.description',
  }),
  entityList: Object.freeze({
    area: 'operations',
    template: 'list',
    titleKey: 'entities.list.title',
    descriptionKey: 'entities.list.description',
  }),
  entityDetail: Object.freeze({
    area: 'operations',
    template: 'detail',
    titleKey: 'entities.detail.title',
    descriptionKey: 'entities.detail.description',
  }),
  plugins: Object.freeze({
    area: 'system',
    template: 'plugin-management',
    titleKey: 'plugins.management.title',
    descriptionKey: 'plugins.management.description',
  }),
  profile: Object.freeze({
    area: 'account',
    template: 'detail',
    titleKey: 'users.profile.title',
    descriptionKey: 'users.profile.description',
  }),
  users: Object.freeze({
    area: 'system',
    template: 'list',
    titleKey: 'users.list.title',
    descriptionKey: 'users.list.description',
  }),
  userDetail: Object.freeze({
    area: 'system',
    template: 'detail',
    titleKey: 'users.detail.title',
    descriptionKey: 'users.detail.description',
  }),
  resultEmpty: Object.freeze({
    area: 'feedback',
    template: 'result-empty',
    titleKey: 'results.empty.title',
    descriptionKey: 'results.empty.description',
  }),
  resultError: Object.freeze({
    area: 'feedback',
    template: 'result-error',
    titleKey: 'results.error.title',
    descriptionKey: 'results.error.description',
  }),
});

export function entityPage(slug) {
  const normalizedSlug = normalizeSegment(slug);
  return normalizedSlug === '' ? '' : `entity:${normalizedSlug}`;
}

export function entityCreatePage(slug) {
  const normalizedSlug = normalizeSegment(slug);
  return normalizedSlug === '' ? '' : `entity-create:${normalizedSlug}`;
}

export function entityRecordPage(slug, recordId) {
  const normalizedSlug = normalizeSegment(slug);
  const normalizedRecordId = typeof recordId === 'string' ? recordId.trim() : '';
  if (normalizedSlug === '') {
    return '';
  }

  if (normalizedRecordId === '') {
    return entityPage(normalizedSlug);
  }

  return `entity-record:${normalizedSlug}:${normalizedRecordId}`;
}

export function pluginConfigPage(slug) {
  const normalizedSlug = normalizeSegment(slug);
  return normalizedSlug === '' ? 'plugins' : `/plugins/${normalizedSlug}/config`;
}

export function userDetailPage(userId) {
  const normalizedUserId = typeof userId === 'string' ? userId.trim() : '';
  return normalizedUserId === '' ? 'users' : `users:${normalizedUserId}`;
}

export function getPageFromHash(hashValue, fallbackPage) {
  const hash = typeof hashValue === 'string' ? hashValue : '';
  if (hash === '' || hash === '#') {
    return fallbackPage;
  }

  const rawPath = hash.startsWith('#') ? hash.slice(1) : hash;
  const normalizedPath = rawPath.startsWith('/') ? rawPath : `/${rawPath}`;
  const parts = normalizedPath.split('/').filter(Boolean);

  if (parts.length === 0) {
    return fallbackPage;
  }

  const section = parts[0];
  if (section === 'login' || section === 'workbench') {
    return fallbackPage;
  }

  if (section === 'profile') {
    return 'profile';
  }

  if (section === 'ui') {
    return 'ui';
  }

  const usersPage = resolveUsersPage(parts);
  if (usersPage !== null) {
    return usersPage;
  }

  const pluginsPage = resolvePluginsPage(parts);
  if (pluginsPage !== null) {
    return pluginsPage;
  }

  const entityResolvedPage = resolveEntityPage(parts);
  if (entityResolvedPage !== null) {
    return entityResolvedPage;
  }

  return fallbackPage;
}

export function hashFromPage(page) {
  if (page === 'dashboard') {
    return HASH_ROUTE_MAP.dashboard;
  }

  if (page === 'profile') {
    return HASH_ROUTE_MAP.profile;
  }

  if (page === 'users') {
    return HASH_ROUTE_MAP.users;
  }

  if (typeof page === 'string' && page.startsWith('users:')) {
    const userId = page.slice('users:'.length);
    return userId === '' ? HASH_ROUTE_MAP.users : `#/users/${encodeURIComponent(userId)}`;
  }

  if (page === 'plugins') {
    return HASH_ROUTE_MAP.plugins;
  }

  if (typeof page === 'string' && page.startsWith('/plugins/') && page.endsWith('/config')) {
    return `#${page}`;
  }

  if (typeof page === 'string' && page.startsWith('entity-create:')) {
    const slug = page.slice('entity-create:'.length);
    return slug === '' ? HASH_ROUTE_MAP.dashboard : `#/entity/${encodeURIComponent(slug)}/new`;
  }

  if (typeof page === 'string' && page.startsWith('entity-record:')) {
    const parsed = parseEntityRecordPage(page);
    if (parsed === null) {
      return HASH_ROUTE_MAP.dashboard;
    }

    return `#/entity/${encodeURIComponent(parsed.slug)}/${encodeURIComponent(parsed.recordId)}`;
  }

  if (typeof page === 'string' && page.startsWith('entity:')) {
    const slug = page.slice('entity:'.length);
    return slug === '' ? HASH_ROUTE_MAP.dashboard : `#/entity/${encodeURIComponent(slug)}`;
  }

  return '';
}

function resolveUsersPage(parts) {
  if (parts[0] !== 'users') {
    return null;
  }

  if (parts.length >= 2 && parts[1] !== '') {
    return `users:${decodeURIComponent(parts[1])}`;
  }

  return 'users';
}

function resolvePluginsPage(parts) {
  if (parts[0] !== 'plugins') {
    return null;
  }

  if (parts.length >= 3 && parts[2] === 'config') {
    return `/plugins/${parts[1]}/config`;
  }

  return 'plugins';
}

function resolveEntityPage(parts) {
  if (parts[0] !== 'entity') {
    return null;
  }

  if (parts.length < 2 || parts[1] === '') {
    return null;
  }

  const slug = decodeURIComponent(parts[1]);
  if (parts.length === 2) {
    return `entity:${slug}`;
  }

  if (parts[2] === 'new') {
    return `entity-create:${slug}`;
  }

  if (parts[2] !== '') {
    const recordId = decodeURIComponent(parts[2]);
    return `entity-record:${slug}:${recordId}`;
  }

  return `entity:${slug}`;
}

function parseEntityRecordPage(page) {
  const prefix = 'entity-record:';
  if (typeof page !== 'string' || !page.startsWith(prefix)) {
    return null;
  }

  const raw = page.slice(prefix.length);
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

function normalizeSegment(value) {
  return typeof value === 'string' ? value.trim() : '';
}