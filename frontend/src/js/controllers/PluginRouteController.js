import { parsePluginConfigPage, pluginConfigPage } from './RouteMapController.js';

export const PluginRouteController = {
  isPluginConfigPage(page) {
    return parsePluginConfigPage(page) !== null;
  },

  getPluginSlugFromPage(page) {
    if (!this.isPluginConfigPage(page)) {
      return '';
    }

    return parsePluginConfigPage(page)?.slug ?? '';
  },

  toPluginConfigPage(slug) {
    return pluginConfigPage(slug);
  },
};
