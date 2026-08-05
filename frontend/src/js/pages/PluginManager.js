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
import { Modal } from '../modules/Modal.js';

export class PluginManager {
  /** @type {HTMLElement} */
  #container;

  /** @type {Api} */
  #api;

  /** @type {(plugin: Object) => void} */
  #onConfigure;

  /** @type {Array<Object>} */
  #plugins = [];

  /** @type {Object<string, Object>} */
  #updatesBySlug = {};

  /** @type {string|null} */
  #feedbackMessage = null;

  /** @type {'success'|'error'|null} */
  #feedbackType = null;

  /**
   * @param {HTMLElement|string} container
   * @param {Api|undefined} api
   * @param {{ onConfigure?: (plugin: Object) => void }} options
   */
  constructor(container, api = undefined, options = {}) {
    const resolved = this.#resolveContainer(container);
    this.#container = resolved;
    this.#api = api ?? new Api();
    this.#onConfigure = typeof options.onConfigure === 'function' ? options.onConfigure : () => {};
  }

  /**
   * Initialize: load plugins from API and render UI.
   * @returns {Promise<void>}
   */
  async init() {
    await this.#refreshData();
  }

  /**
   * @returns {Promise<void>}
   */
  async #refreshData() {
    try {
      const [pluginsResponse, updatesResponse] = await Promise.all([
        this.#api.get('/plugins'),
        this.#api.get('/plugins/updates'),
      ]);
      const pluginsPayload = pluginsResponse?.data ?? pluginsResponse;
      const updatesPayload = updatesResponse?.data ?? updatesResponse;

      this.#plugins = Array.isArray(pluginsPayload?.plugins) ? pluginsPayload.plugins : [];
      this.#updatesBySlug = this.#indexUpdates(Array.isArray(updatesPayload?.updates) ? updatesPayload.updates : []);
      this.#render();
    } catch (error) {
      this.#renderError(`Error loading plugins: ${error.message}`);
    }
  }

  /**
   * @param {Array<Object>} updates
   * @returns {Object<string, Object>}
   */
  #indexUpdates(updates) {
    return updates.reduce((acc, update) => {
      const slug = String(update?.slug ?? '');
      if (slug !== '') {
        acc[slug] = update;
      }
      return acc;
    }, {});
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
        <div>
          <h2>Plugin Manager</h2>
          <p class="xt-plugin-manager__subtitle">Manage installed plugins</p>
        </div>
        <button class="xt-plugin-manager__sync-btn" data-action="sync">Synchronize</button>
      </div>
      ${this.#feedbackMessage === null ? '' : `<div class="xt-plugin-manager__feedback xt-plugin-manager__feedback--${this.#feedbackType}">${this.#escapeHtml(this.#feedbackMessage)}</div>`}
      <div class="xt-plugin-manager__content"></div>
    `;

    const syncButton = wrapper.querySelector('.xt-plugin-manager__sync-btn');
    if (syncButton instanceof HTMLButtonElement) {
      syncButton.addEventListener('click', () => {
        this.#handleSync(syncButton);
      });
    }

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
      const updateInfo = this.#updatesBySlug[String(plugin.slug)] ?? null;

      const statusBadgeClass = plugin.status === 'active'
        ? 'xt-plugin-manager__status-badge--active'
        : 'xt-plugin-manager__status-badge--inactive';

      const typeBadgeClass = plugin.plugin_type === 'entity'
        ? 'xt-plugin-manager__type-badge--entity'
        : 'xt-plugin-manager__type-badge--extension';

      const canConfigure = plugin.status === 'active' && (plugin.plugin_type === 'entity' || plugin.plugin_type === 'extension');
      row.innerHTML = `
        <td class="xt-plugin-manager__cell-name">${this.#escapeHtml(plugin.name || plugin.slug)}</td>
        <td class="xt-plugin-manager__cell-type">
          <span class="xt-plugin-manager__type-badge ${typeBadgeClass}">
            ${plugin.plugin_type}
          </span>
        </td>
        <td class="xt-plugin-manager__cell-version">
          <div class="xt-plugin-manager__version-stack">
            <span>${this.#escapeHtml(plugin.version)}</span>
            ${updateInfo === null ? '' : `<span class="xt-plugin-manager__update-badge">Update available: ${this.#escapeHtml(updateInfo.available_version)}</span>`}
          </div>
        </td>
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
          ${updateInfo === null ? '' : '<button class="xt-plugin-manager__action-btn xt-plugin-manager__action-btn--update" data-action="update">Update</button>'}
          ${plugin.can_rollback === true || plugin.can_rollback === 't' || plugin.can_rollback === 1 || plugin.can_rollback === '1'
    ? '<button class="xt-plugin-manager__action-btn xt-plugin-manager__action-btn--rollback" data-action="rollback">Rollback</button>'
    : ''}
          ${canConfigure ? '<button class="xt-plugin-manager__action-btn xt-plugin-manager__action-btn--configure" data-action="configure">Configure</button>' : ''}
        </td>
      `;

      const actionButtons = row.querySelectorAll('.xt-plugin-manager__action-btn');
      actionButtons.forEach((button) => {
        button.addEventListener('click', () => {
          if (button.dataset.action === 'configure') {
            this.#onConfigure(plugin);
            return;
          }

          if (button.dataset.action === 'update') {
            this.#handleUpdate(plugin, button);
            return;
          }

          if (button.dataset.action === 'rollback') {
            this.#handleRollback(plugin, button);
            return;
          }

          this.#handleActionClick(plugin, button);
        });
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
    this.#feedbackMessage = null;
    this.#feedbackType = null;

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
   * @param {HTMLButtonElement} button
   * @returns {Promise<void>}
   */
  async #handleSync(button) {
    this.#feedbackMessage = null;
    this.#feedbackType = null;
    button.disabled = true;
    button.textContent = 'Synchronizing...';

    try {
      const response = await this.#api.post('/plugins/sync', {});
      const payload = response?.data ?? response;
      const outdated = Number(payload?.summary?.outdated ?? 0);
      this.#feedbackType = 'success';
      this.#feedbackMessage = `Synchronization complete. ${outdated} plugin(s) with updates available.`;
      await this.#refreshData();
    } catch (error) {
      this.#renderError(`Failed to synchronize plugins: ${error.message}`);
    } finally {
      button.disabled = false;
      button.textContent = 'Synchronize';
    }
  }

  /**
   * @param {Object} plugin
   * @param {HTMLButtonElement} button
   * @returns {Promise<void>}
   */
  async #handleUpdate(plugin, button) {
    const accepted = await this.#confirmAction(
      'Confirm plugin update',
      `Update plugin "${plugin.name || plugin.slug}" to the latest available version?`,
      'Update'
    );

    if (!accepted) {
      return;
    }

    this.#feedbackMessage = null;
    this.#feedbackType = null;
    button.disabled = true;
    button.textContent = 'Updating...';

    try {
      await this.#api.post(`/plugins/${plugin.slug}/update`, {});
      this.#feedbackType = 'success';
      this.#feedbackMessage = `Plugin "${plugin.name || plugin.slug}" updated successfully.`;
      await this.#refreshData();
    } catch (error) {
      this.#renderError(`Failed to update plugin: ${error.message}`);
    } finally {
      button.disabled = false;
      button.textContent = 'Update';
    }
  }

  /**
   * @param {Object} plugin
   * @param {HTMLButtonElement} button
   * @returns {Promise<void>}
   */
  async #handleRollback(plugin, button) {
    const accepted = await this.#confirmAction(
      'Confirm plugin rollback',
      `Rollback plugin "${plugin.name || plugin.slug}" to the previous version snapshot?`,
      'Rollback'
    );

    if (!accepted) {
      return;
    }

    this.#feedbackMessage = null;
    this.#feedbackType = null;
    button.disabled = true;
    button.textContent = 'Rolling back...';

    try {
      await this.#api.post(`/plugins/${plugin.slug}/rollback`, {});
      this.#feedbackType = 'success';
      this.#feedbackMessage = `Plugin "${plugin.name || plugin.slug}" rolled back successfully.`;
      await this.#refreshData();
    } catch (error) {
      this.#renderError(`Failed to rollback plugin: ${error.message}`);
    } finally {
      button.disabled = false;
      button.textContent = 'Rollback';
    }
  }

  /**
   * @param {string} title
   * @param {string} message
   * @param {string} confirmLabel
   * @returns {Promise<boolean>}
   */
  #confirmAction(title, message, confirmLabel) {
    return new Promise((resolve) => {
      const modal = new Modal(this.#container, { title });
      const body = document.createElement('div');
      body.className = 'xt-plugin-manager__confirm';

      const text = document.createElement('p');
      text.className = 'xt-plugin-manager__confirm-text';
      text.textContent = message;

      const actions = document.createElement('div');
      actions.className = 'xt-plugin-manager__confirm-actions';

      const cancelButton = document.createElement('button');
      cancelButton.type = 'button';
      cancelButton.className = 'xt-plugin-manager__action-btn';
      cancelButton.textContent = 'Cancel';

      const confirmButton = document.createElement('button');
      confirmButton.type = 'button';
      confirmButton.className = 'xt-plugin-manager__action-btn xt-plugin-manager__action-btn--confirm';
      confirmButton.textContent = confirmLabel;

      const closeWith = (result) => {
        modal.close();
        resolve(result);
      };

      cancelButton.addEventListener('click', () => closeWith(false));
      confirmButton.addEventListener('click', () => closeWith(true));

      actions.appendChild(cancelButton);
      actions.appendChild(confirmButton);
      body.appendChild(text);
      body.appendChild(actions);

      modal.setContent(body);
      modal.show();
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
