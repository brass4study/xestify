export const HASH_ROUTE_MAP = Object.freeze({
  login: '#/login',
  home: '#/home',
  profile: '#/profile',
  users: '#/users',
  userDetail: '#/users/:id',
  plugins: '#/plugins',
  pluginConfig: '#/plugins/:slug',
  entityList: '#/entity/:slug',
  entityCreate: '#/entity/:slug/#new',
  entityDetail: '#/entity/:slug/:id',
  entityTab: '#/entity/:slug/:id/:tab',
  entityPluginItemCreate: '#/entity/:slug/:id/:tab/#new',
  entityPluginItem: '#/entity/:slug/:id/:tab/:itemId',
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

/**
 * Standalone page for creating a new item of an extension plugin (e.g. a new
 * optometries ficha) — a real, navigable, bookmarkable route rather than an
 * inline form (STORY 10.5), mirroring entityCreatePage()'s '#new' sentinel.
 */
export function entityPluginItemCreatePage(slug, recordId, tabId) {
  const normalizedSlug = normalizeSegment(slug);
  const normalizedRecordId = typeof recordId === 'string' ? recordId.trim() : '';
  const normalizedTabId = normalizeSegment(tabId);
  if (normalizedSlug === '' || normalizedRecordId === '' || normalizedTabId === '') {
    return entityTabPage(slug, recordId, tabId);
  }

  return `entity-plugin-item-create:${normalizedSlug}:${normalizedRecordId}:${normalizedTabId}`;
}

/**
 * Standalone page for viewing/editing an existing item of an extension
 * plugin (e.g. one optometries ficha).
 */
export function entityPluginItemPage(slug, recordId, tabId, itemId) {
  const normalizedSlug = normalizeSegment(slug);
  const normalizedRecordId = typeof recordId === 'string' ? recordId.trim() : '';
  const normalizedTabId = normalizeSegment(tabId);
  const normalizedItemId = typeof itemId === 'string' ? itemId.trim() : '';
  if (normalizedSlug === '' || normalizedRecordId === '' || normalizedTabId === '' || normalizedItemId === '') {
    return entityTabPage(slug, recordId, tabId);
  }

  return `entity-plugin-item:${normalizedSlug}:${normalizedRecordId}:${normalizedTabId}:${normalizedItemId}`;
}

export function parseEntityPluginItemCreatePage(page) {
  const prefix = 'entity-plugin-item-create:';
  if (typeof page !== 'string' || !page.startsWith(prefix)) {
    return null;
  }

  const parts = page.slice(prefix.length).split(':');
  if (parts.length < 3) {
    return null;
  }

  const [slug, recordId] = parts;
  const tabId = parts.slice(2).join(':');
  if (slug === '' || recordId === '' || tabId === '') {
    return null;
  }

  return { slug, recordId, tabId };
}

export function parseEntityPluginItemPage(page) {
  const prefix = 'entity-plugin-item:';
  if (typeof page !== 'string' || !page.startsWith(prefix)) {
    return null;
  }

  const parts = page.slice(prefix.length).split(':');
  if (parts.length < 4) {
    return null;
  }

  const [slug, recordId] = parts;
  const itemId = parts[parts.length - 1];
  const tabId = parts.slice(2, -1).join(':');
  if (slug === '' || recordId === '' || tabId === '' || itemId === '') {
    return null;
  }

  return { slug, recordId, tabId, itemId };
}

export function pluginConfigPage(slug) {
  const normalizedSlug = normalizeSegment(slug);
  return normalizedSlug === '' ? 'plugins' : `plugins:${normalizedSlug}`;
}

export function parsePluginConfigPage(page) {
  const prefix = 'plugins:';
  if (typeof page !== 'string' || !page.startsWith(prefix)) {
    return null;
  }

  const slug = page.slice(prefix.length);
  return slug === '' ? null : { slug };
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
  if (parsed === null) {
    return null;
  }

  // '#new' is the reserved insert-mode sentinel (never a real plugin slug —
  // it fails PluginIdentityService's slug format check), substituted here
  // without encodeURIComponent so it stays literal in the URL, matching how
  // the entity create route ('#/entity/:slug/#new') displays it unescaped
  // rather than percent-encoded as '%23new'.
  if (parsed.slug === '#new') {
    return HASH_ROUTE_MAP.pluginConfig.replace(':slug', '#new');
  }

  return fillRouteTemplate(HASH_ROUTE_MAP.pluginConfig, parsed.slug);
}

function resolveEntityHash(page) {
  if (typeof page !== 'string') {
    return null;
  }

  if (page.startsWith('entity-plugin-item-create:')) {
    const parsed = parseEntityPluginItemCreatePage(page);
    if (parsed === null) {
      return HASH_ROUTE_MAP.home;
    }

    return fillRouteTemplate(HASH_ROUTE_MAP.entityPluginItemCreate, parsed.slug, parsed.recordId, parsed.tabId);
  }

  if (page.startsWith('entity-plugin-item:')) {
    const parsed = parseEntityPluginItemPage(page);
    if (parsed === null) {
      return HASH_ROUTE_MAP.home;
    }

    return fillRouteTemplate(HASH_ROUTE_MAP.entityPluginItem, parsed.slug, parsed.recordId, parsed.tabId, parsed.itemId);
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

  if (parts.length === 3 && parts[2] === '#new') {
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

  if (parts.length === 5) {
    const tabId = decodeSegment(parts[3]);
    if (tabId === null) {
      return null;
    }

    if (parts[4] === '#new') {
      return `entity-plugin-item-create:${slug}:${recordId}:${tabId}`;
    }

    const itemId = decodeSegment(parts[4]);
    return itemId === null ? null : `entity-plugin-item:${slug}:${recordId}:${tabId}:${itemId}`;
  }

  return null;
}

export function parseEntityTabPage(page) {
  const prefix = 'entity-tab:';
  if (typeof page !== 'string' || !page.startsWith(prefix)) {
    return null;
  }

  const parts = page.slice(prefix.length).split(':');
  if (parts.length < 3) {
    return null;
  }

  const [slug, recordId] = parts;
  const tabId = parts.slice(2).join(':');
  if (slug === '' || recordId === '' || tabId === '') {
    return null;
  }

  return { slug, recordId, tabId };
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
