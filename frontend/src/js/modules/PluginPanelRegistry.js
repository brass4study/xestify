/**
 * PluginPanelRegistry — maps plugin slugs to their frontend panel constructors.
 *
 * Each plugin's frontend entry file (e.g. /plugins/comments/frontend/comments.js)
 * calls PluginPanelRegistry.register(slug, PanelClass) as a side effect when
 * imported. EntityEdit uses PluginPanelRegistry.build(slug, options) to obtain
 * a panel instance without knowing anything about the plugin internals.
 *
 * Panel contract — every registered class must expose:
 *   - get element(): HTMLElement   The rendered DOM node to mount.
 *   - flush(resolvedId: string): Promise<void>  Persist pending changes.
 */

/** @type {Map<string, new (options: object) => {element: HTMLElement, flush: (id: string) => Promise<void>}>} */
const registry = new Map();

export const PluginPanelRegistry = {
  /**
   * @param {string} slug
   * @param {new (options: object) => {element: HTMLElement, flush: (id: string) => Promise<void>}} PanelClass
   */
  register(slug, PanelClass) {
    registry.set(slug, PanelClass);
  },

  /**
   * @param {string} slug
   * @param {object} options
   * @returns {{element: HTMLElement, flush: (id: string) => Promise<void>}|null}
   */
  build(slug, options) {
    const PanelClass = registry.get(slug);
    if (PanelClass === undefined) {
      return null;
    }
    return new PanelClass(options);
  },
};
