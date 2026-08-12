export const HASH_ROUTE_MAP = Object.freeze({
  login: '#/login',
  home: '#/home',
  profile: '#/profile',
  users: '#/users',
  userDetail: '#/users/:id',
  plugins: '#/plugins',
  pluginConfig: '#/plugins/:slug',
  entityList: '#/entity/:slug',
  entityCreate: '#/entity/:slug/new',
  entityDetail: '#/entity/:slug/:id',
  entityTab: '#/entity/:slug/:id/:tab',
});

function fillRouteTemplate(template, ...params) {
  let index = 0;
  return template.replace(/:[a-zA-Z]+/g, () => encodeURIComponent(params[index++] ?? ''));
}

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

export function entityTabPage(slug, recordId, tabId) {
  const recordPage = entityRecordPage(slug, recordId);
  const normalizedTabId = normalizeSegment(tabId);
  if (!recordPage.startsWith('entity-record:') || normalizedTabId === '') {
    return recordPage;
  }

  return `entity-tab:${recordPage.slice('entity-record:'.length)}:${normalizedTabId}`;
}

export function pluginConfigPage(slug) {
  const normalizedSlug = normalizeSegment(slug);
  return normalizedSlug === '' || normalizedSlug.includes('/') ? 'plugins' : `/plugins/${normalizedSlug}`;
}

export function parsePluginConfigPage(page) {
  const prefix = '/plugins/';
  if (typeof page !== 'string' || !page.startsWith(prefix)) {
    return null;
  }

  const slug = page.slice(prefix.length);
  return slug === '' || slug.includes('/') ? null : { slug };
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
  const pathWithoutQuery = rawPath.split('?')[0];
  const normalizedPath = pathWithoutQuery.startsWith('/') ? pathWithoutQuery : `/${pathWithoutQuery}`;
  const parts = normalizedPath.split('/').filter(Boolean);

  if (parts.length === 0) {
    return fallbackPage;
  }

  const section = parts[0];
  if (section === 'login' && parts.length === 1) {
    return 'login';
  }

  if (section === 'home' && parts.length === 1) {
    return fallbackPage;
  }

  if (section === 'profile' && parts.length === 1) {
    return 'profile';
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
  const basicHash = resolveBasicHash(page);
  if (basicHash !== null) {
    return basicHash;
  }

  const usersHash = resolveUsersHash(page);
  if (usersHash !== null) {
    return usersHash;
  }

  const pluginsHash = resolvePluginsHash(page);
  if (pluginsHash !== null) {
    return pluginsHash;
  }

  const entitiesHash = resolveEntityHash(page);
  if (entitiesHash !== null) {
    return entitiesHash;
  }

  return '';
}

function resolveBasicHash(page) {
  if (page === 'login') {
    return HASH_ROUTE_MAP.login;
  }

  if (page === 'home') {
    return HASH_ROUTE_MAP.home;
  }

  if (page === 'profile') {
    return HASH_ROUTE_MAP.profile;
  }

  if (page === 'users') {
    return HASH_ROUTE_MAP.users;
  }

  if (page === 'plugins') {
    return HASH_ROUTE_MAP.plugins;
  }

  return null;
}

function resolveUsersHash(page) {
  if (typeof page !== 'string' || !page.startsWith('users:')) {
    return null;
  }

  const userId = page.slice('users:'.length);
  return userId === '' ? HASH_ROUTE_MAP.users : fillRouteTemplate(HASH_ROUTE_MAP.userDetail, userId);
}

function resolvePluginsHash(page) {
  const parsed = parsePluginConfigPage(page);
  return parsed === null ? null : fillRouteTemplate(HASH_ROUTE_MAP.pluginConfig, parsed.slug);
}

function resolveEntityHash(page) {
  if (typeof page !== 'string') {
    return null;
  }

  if (page.startsWith('entity-tab:')) {
    const parsed = parseEntityTabPage(page);
    if (parsed === null) {
      return HASH_ROUTE_MAP.home;
    }

    return fillRouteTemplate(HASH_ROUTE_MAP.entityTab, parsed.slug, parsed.recordId, parsed.tabId);
  }

  if (page.startsWith('entity-create:')) {
    const slug = page.slice('entity-create:'.length);
    return slug === '' ? HASH_ROUTE_MAP.home : fillRouteTemplate(HASH_ROUTE_MAP.entityCreate, slug);
  }

  if (page.startsWith('entity-record:')) {
    const parsed = parseEntityRecordPage(page);
    if (parsed === null) {
      return HASH_ROUTE_MAP.home;
    }

    return fillRouteTemplate(HASH_ROUTE_MAP.entityDetail, parsed.slug, parsed.recordId);
  }

  if (page.startsWith('entity:')) {
    const slug = page.slice('entity:'.length);
    return slug === '' ? HASH_ROUTE_MAP.home : fillRouteTemplate(HASH_ROUTE_MAP.entityList, slug);
  }

  return null;
}

function resolveUsersPage(parts) {
  if (parts[0] !== 'users') {
    return null;
  }

  if (parts.length === 2 && parts[1] !== '') {
    const userId = decodeSegment(parts[1]);
    return userId === null ? null : `users:${userId}`;
  }

  return parts.length === 1 ? 'users' : null;
}

function resolvePluginsPage(parts) {
  if (parts[0] !== 'plugins') {
    return null;
  }

  if (parts.length === 2 && parts[1] !== '') {
    const slug = decodeSegment(parts[1]);
    return slug === null ? null : pluginConfigPage(slug);
  }

  return parts.length === 1 ? 'plugins' : null;
}

function resolveEntityPage(parts) {
  if (parts[0] !== 'entity') {
    return null;
  }

  if (parts.length < 2 || parts[1] === '') {
    return null;
  }

  const slug = decodeSegment(parts[1]);
  if (slug === null) {
    return null;
  }

  if (parts.length === 2) {
    return `entity:${slug}`;
  }

  if (parts.length === 3 && parts[2] === 'new') {
    return `entity-create:${slug}`;
  }

  const recordId = decodeSegment(parts[2]);
  if (recordId === null) {
    return null;
  }

  if (parts.length === 3) {
    return `entity-record:${slug}:${recordId}`;
  }

  if (parts.length === 4) {
    const tabId = decodeSegment(parts[3]);
    return tabId === null ? null : `entity-tab:${slug}:${recordId}:${tabId}`;
  }

  return null;
}

export function parseEntityTabPage(page) {
  const prefix = 'entity-tab:';
  if (typeof page !== 'string' || !page.startsWith(prefix)) {
    return null;
  }

  const parts = page.slice(prefix.length).split(':');
  if (parts.length !== 3 || parts.includes('')) {
    return null;
  }

  return { slug: parts[0], recordId: parts[1], tabId: parts[2] };
}

export function parseEntityRecordPage(page) {
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

function decodeSegment(value) {
  if (typeof value !== 'string' || value === '') {
    return null;
  }

  try {
    return decodeURIComponent(value);
  } catch {
    return null;
  }
}
