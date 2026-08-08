import { pluginConfigPage } from './RouteMapController.js';

export const PluginRouteController = {
  isPluginConfigPage(page) {
    return typeof page === 'string' && page.startsWith('/plugins/') && page.endsWith('/config');
  },

  getPluginSlugFromPage(page) {
    if (!this.isPluginConfigPage(page)) {
      return '';
    }

    const parts = page.split('/');
    return parts.length >= 4 ? parts[2] : '';
  },

  toPluginConfigPage(slug) {
    return pluginConfigPage(slug);
  },
};
