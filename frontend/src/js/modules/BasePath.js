function normalizeBasePath(pathname) {
  if (pathname === '' || pathname === '/') {
    return '';
  }

  return pathname.endsWith('/') ? pathname.slice(0, -1) : pathname;
}

function detectBasePath(moduleUrl) {
  const baseUrl = globalThis.location?.href ?? 'http://localhost/';
  const url = new URL(moduleUrl, baseUrl);
  const pathname = url.pathname;
  const markers = ['/src/js/', '/js/', '/plugins/'];

  for (const marker of markers) {
    const index = pathname.indexOf(marker);
    if (index !== -1) {
      return normalizeBasePath(pathname.slice(0, index));
    }
  }

  const lastSlash = pathname.lastIndexOf('/');
  if (lastSlash <= 0) {
    return '';
  }

  return normalizeBasePath(pathname.slice(0, lastSlash));
}

export const BASE_PATH = detectBasePath(import.meta.url);

export function buildAppUrl(path = '') {
  if (path === '') {
    return BASE_PATH === '' ? '/' : BASE_PATH;
  }

  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${BASE_PATH}${normalizedPath}`;
}

export function buildPluginModuleUrl(pluginSlug) {
  return buildAppUrl(`/plugins/${pluginSlug}/plugin.js`);
}
