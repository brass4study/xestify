/**
 * PluginManager — UI for listing and managing plugin activation/deactivation.
 *
 * Features:
 *   - List all installed plugins with status, type, version
 *   - Toggle plugin status (activate/deactivate)
 *   - Badge for plugin type (entity/extension)
 *   - Responsive table/card layout
 */

import { Api } from '../modules/Api.js';

export class PluginManager {
  /** @type {HTMLElement} */
  #container;

  /** @type {Api} */
  #api;

  /** @type {Array<Object>} */
  #plugins = [];

  /**
   * @param {HTMLElement|string} container
   * @param {Api|undefined} api
   */
  constructor(container, api = undefined) {
    const resolved = this.#resolveContainer(container);
    this.#container = resolved;
    this.#api = api ?? new Api();
  }

  /**
   * Initialize: load plugins from API and render UI.
   * @returns {Promise<void>}
   */
  async init() {
    try {
      const response = await this.#api.get('/plugins');
      if (response?.ok === false) {
        throw new Error(response.error?.message ?? 'unknown error');
      }
      const payload = response?.data ?? response;
      this.#plugins = Array.isArray(payload?.plugins) ? payload.plugins : [];
      this.#render();
    } catch (error) {
      this.#renderError(`Error loading plugins: ${error.message}`);
    }
  }

  /**
   * Render the plugin manager UI.
   * @private
   */
  #render() {
    const wrapper = document.createElement('div');
    wrapper.className = 'xt-plugin-manager';
    wrapper.innerHTML = `
      <div class="xt-plugin-manager__header">
        <h2>Plugin Manager</h2>
        <p class="xt-plugin-manager__subtitle">Manage installed plugins</p>
      </div>
      <div class="xt-plugin-manager__content"></div>
    `;

    const content = wrapper.querySelector('.xt-plugin-manager__content');

    if (this.#plugins.length === 0) {
      content.innerHTML = '<p class="xt-plugin-manager__empty">No plugins installed.</p>';
      this.#container.innerHTML = '';
      this.#container.appendChild(wrapper);
      return;
    }

    const table = document.createElement('table');
    table.className = 'xt-plugin-manager__table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Name</th>
          <th>Type</th>
          <th>Version</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    `;

    const tbody = table.querySelector('tbody');

    this.#plugins.forEach((plugin) => {
      const row = document.createElement('tr');
      row.className = `xt-plugin-manager__row xt-plugin-manager__row--${plugin.status}`;
      row.dataset.slug = plugin.slug;

      const statusBadgeClass = plugin.status === 'active'
        ? 'xt-plugin-manager__status-badge--active'
        : 'xt-plugin-manager__status-badge--inactive';

      const typeBadgeClass = plugin.plugin_type === 'entity'
        ? 'xt-plugin-manager__type-badge--entity'
        : 'xt-plugin-manager__type-badge--extension';

      row.innerHTML = `
        <td class="xt-plugin-manager__cell-name">${this.#escapeHtml(plugin.name || plugin.slug)}</td>
        <td class="xt-plugin-manager__cell-type">
          <span class="xt-plugin-manager__type-badge ${typeBadgeClass}">
            ${plugin.plugin_type}
          </span>
        </td>
        <td class="xt-plugin-manager__cell-version">${this.#escapeHtml(plugin.version)}</td>
        <td class="xt-plugin-manager__cell-status">
          <span class="xt-plugin-manager__status-badge ${statusBadgeClass}">
            ${plugin.status}
          </span>
        </td>
        <td class="xt-plugin-manager__cell-actions">
          <button class="xt-plugin-manager__action-btn ${plugin.status === 'active' ? 'xt-plugin-manager__action-btn--deactivate' : 'xt-plugin-manager__action-btn--activate'}"
                  data-action="${plugin.status === 'active' ? 'deactivate' : 'activate'}">
            ${plugin.status === 'active' ? 'Deactivate' : 'Activate'}
          </button>
        </td>
      `;

      const actionBtn = row.querySelector('.xt-plugin-manager__action-btn');
      actionBtn.addEventListener('click', () => {
        this.#handleActionClick(plugin, actionBtn);
      });

      tbody.appendChild(row);
    });

    content.appendChild(table);
    this.#container.innerHTML = '';
    this.#container.appendChild(wrapper);
  }

  /**
   * Handle activate/deactivate button click.
   * @private
   * @param {Object} plugin
   * @param {HTMLElement} button
   */
  #handleActionClick(plugin, button) {
    const newStatus = button.dataset.action === 'activate' ? 'active' : 'inactive';
    button.disabled = true;
    button.textContent = newStatus === 'active' ? 'Activating...' : 'Deactivating...';

    this.#api.put(`/plugins/${plugin.slug}/status`, { status: newStatus })
      .then((response) => {
        if (response?.ok === false) {
          throw new Error(response.error?.message ?? 'Update failed');
        }
        const updated = response?.data ?? response;

        // Update plugin in local list
        const index = this.#plugins.findIndex((p) => p.slug === plugin.slug);
        if (index !== -1) {
          this.#plugins[index] = updated;
        }
        this.#render();
      })
      .catch((error) => {
        button.disabled = false;
        button.textContent = newStatus === 'active' ? 'Activate' : 'Deactivate';
        this.#renderError(`Failed to update plugin: ${error.message}`);
      });
  }

  /**
   * Render error message in a banner.
   * @private
   * @param {string} message
   */
  #renderError(message) {
    const banner = document.createElement('div');
    banner.className = 'xt-plugin-manager__error-banner';
    banner.textContent = message;
    this.#container.innerHTML = '';
    this.#container.appendChild(banner);
  }

  /**
   * Resolve container element.
   * @private
   * @param {HTMLElement|string} container
   * @returns {HTMLElement}
   */
  #resolveContainer(container) {
    if (container instanceof HTMLElement) {
      return container;
    }
    const found = document.querySelector(container);
    if (found instanceof HTMLElement) {
      return found;
    }
    throw new TypeError(`PluginManager container "${String(container)}" not found`);
  }

  /**
   * Escape HTML to prevent XSS.
   * @private
   * @param {string} text
   * @returns {string}
   */
  #escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    };
    return String(text ?? '').replaceAll(/[&<>"']/g, (char) => map[char]);
  }
}
