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
import { DynamicTable } from '../modules/DynamicTable.js';
import { Modal } from '../modules/Modal.js';
import { component } from '../modules/ComponentFactory.js';

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
    const resolved = this.resolveContainer(container);
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

      if (pluginsResponse?.ok === false) {
        throw new Error(pluginsResponse?.error?.message ?? 'Error loading plugins');
      }

      if (updatesResponse?.ok === false) {
        throw new Error(updatesResponse?.error?.message ?? 'Error loading plugin updates');
      }

      const pluginsPayload = pluginsResponse?.data ?? pluginsResponse;
      const updatesPayload = updatesResponse?.data ?? updatesResponse;

      this.#plugins = Array.isArray(pluginsPayload?.plugins) ? pluginsPayload.plugins : [];
      this.#updatesBySlug = this.#indexUpdates(Array.isArray(updatesPayload?.updates) ? updatesPayload.updates : []);
      this.#render();
    } catch (error) {
      this.renderError(`Error loading plugins: ${error.message}`);
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
    const page = component.create('page', { dataRole: 'plugin-manager-page' });
    const syncButton = component.create('button', {
      label: 'Synchronize',
      variant: 'primary',
      dataRole: 'plugin-sync',
      dataAction: 'sync',
      onClick: () => {
        this.#handleSync(syncButton);
      },
    });
    syncButton.className = 'inline-flex items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-700';

    const header = component.create('pageHeader', {
      title: 'Plugin Manager',
      subtitle: 'Manage installed plugins',
      actions: [syncButton],
    });
    page.appendChild(header);

    if (this.#feedbackMessage !== null) {
      const feedback = component.create('alert', {
        type: this.#feedbackType === 'error' ? 'error' : 'success',
        message: this.#feedbackMessage,
      });
      feedback.dataset.role = 'plugin-feedback';
      page.appendChild(feedback);
    }

    const content = component.create('div');
    content.className = 'mt-4 overflow-x-auto rounded-xl border border-slate-200';
    content.dataset.role = 'plugin-content';

    if (this.#plugins.length === 0) {
      const emptyState = component.create('emptyState', {
        title: 'No plugins installed',
        description: 'No plugins installed.',
      });
      emptyState.dataset.role = 'plugin-empty';
      content.appendChild(emptyState);
      page.appendChild(content);
      this.#container.replaceChildren();
      this.#container.appendChild(page);
      return;
    }

    const tableHost = component.create('div');
    content.appendChild(tableHost);

    const rows = this.#plugins.map((plugin) => ({
      __plugin: plugin,
      __update: this.#updatesBySlug[String(plugin.slug)] ?? null,
      name: String(plugin.name || plugin.slug || ''),
    }));

    const schema = {
      fields: [
        { name: 'name', label: 'Name' },
      ],
    };

    const table = new DynamicTable(rows, schema, tableHost, {
      extraColumns: [
        {
          label: 'Type',
          renderCell: (row) => this.#renderTypeBadge(row.__plugin),
        },
        {
          label: 'Version',
          renderCell: (row) => this.#renderVersionCell(row.__plugin, row.__update),
        },
        {
          label: 'Status',
          renderCell: (row) => this.#renderStatusBadge(row.__plugin),
        },
        {
          label: 'Actions',
          renderCell: (row) => this.#renderActionsCell(row.__plugin, row.__update),
        },
      ],
      rowDecorator: (tr, row) => {
        const plugin = row.__plugin;
        tr.className = String(plugin.status ?? '');
        tr.dataset.role = 'plugin-row';
        tr.dataset.slug = String(plugin.slug ?? '');
        if (plugin.status === 'active') {
          tr.classList.add('bg-emerald-50/40');
        } else if (plugin.status === 'inactive') {
          tr.classList.add('bg-amber-50/30');
        }
      },
    });
    table.render();

    const renderedTable = tableHost.querySelector('table');
    if (renderedTable instanceof HTMLTableElement) {
      renderedTable.dataset.role = 'plugin-table';
    }

    const actionButtons = tableHost.querySelectorAll('[data-role="plugin-action"]');
    actionButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const slug = button.dataset.slug ?? '';
        const plugin = this.#plugins.find((item) => String(item.slug) === slug);
        if (!plugin) {
          return;
        }

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

    page.appendChild(content);
    this.#container.replaceChildren();
    this.#container.appendChild(page);
  }

  /**
   * @param {Object} plugin
   * @returns {HTMLElement}
   */
  #renderTypeBadge(plugin) {
    const badge = component.create('span');
    const isEntity = plugin.plugin_type === 'entity';
    badge.className = `${isEntity ? 'bg-sky-100 text-sky-700' : 'bg-fuchsia-100 text-fuchsia-700'} inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide`;
    badge.dataset.role = 'plugin-type-badge';
    badge.textContent = String(plugin.plugin_type ?? '');
    return badge;
  }

  /**
   * @param {Object} plugin
   * @param {Object|null} updateInfo
   * @returns {HTMLElement}
   */
  #renderVersionCell(plugin, updateInfo) {
    const container = component.create('div');
    container.className = 'flex flex-col gap-1';

    const current = component.create('span');
    current.textContent = String(plugin.version ?? '');
    container.appendChild(current);

    if (updateInfo !== null) {
      const badge = component.create('span');
      badge.className = 'inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide';
      badge.dataset.role = 'plugin-update-badge';
      badge.textContent = `Update available: ${String(updateInfo.available_version ?? '')}`;
      container.appendChild(badge);
    }

    return container;
  }

  /**
   * @param {Object} plugin
   * @returns {HTMLElement}
   */
  #renderStatusBadge(plugin) {
    const badge = component.create('span');
    const isActive = plugin.status === 'active';
    badge.className = `${isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'} inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide`;
    badge.dataset.role = 'plugin-status-badge';
    badge.textContent = String(plugin.status ?? '');
    return badge;
  }

  /**
   * @param {Object} plugin
   * @param {Object|null} updateInfo
   * @returns {HTMLElement}
   */
  #renderActionsCell(plugin, updateInfo) {
    const actions = component.create('div');
    actions.className = 'flex flex-wrap gap-1.5';

    const isActive = plugin.status === 'active';
    actions.appendChild(this.#pluginActionButton(
      isActive ? 'Deactivate' : 'Activate',
      isActive ? 'fa-power-off' : 'fa-play',
      isActive ? 'amber' : 'emerald',
      isActive ? 'deactivate' : 'activate',
      plugin
    ));

    if (updateInfo !== null) {
      actions.appendChild(this.#pluginActionButton('Update', 'fa-rotate', 'amber', 'update', plugin));
    }

    if (this.#canRollback(plugin)) {
      actions.appendChild(this.#pluginActionButton('Rollback', 'fa-clock-rotate-left', 'violet', 'rollback', plugin));
    }

    const canConfigure = plugin.status === 'active' && (plugin.plugin_type === 'entity' || plugin.plugin_type === 'extension');
    if (canConfigure) {
      actions.appendChild(this.#pluginActionButton('Configure', 'fa-sliders', 'brand', 'configure', plugin));
    }

    return actions;
  }

  /**
   * @param {string} label
   * @param {string} icon
   * @param {'sky'|'emerald'|'amber'|'red'|'violet'|'brand'|'slate'} tone
   * @param {string} action
   * @param {Object} plugin
   * @returns {HTMLButtonElement}
   */
  #pluginActionButton(label, icon, tone, action, plugin) {
    const button = DynamicTable.buildActionButton({
      label,
      icon,
      tone,
      dataRole: 'plugin-action',
      dataAction: action,
      onClick: () => {},
      disabled: false,
    });

    button.dataset.slug = String(plugin.slug ?? '');
    return button;
  }

  /**
   * @param {Object} plugin
   * @returns {boolean}
   */
  #canRollback(plugin) {
    return plugin.can_rollback === true
      || plugin.can_rollback === 't'
      || plugin.can_rollback === 1
      || plugin.can_rollback === '1';
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
        this.renderError(`Failed to update plugin: ${error.message}`);
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
      this.renderError(`Failed to synchronize plugins: ${error.message}`);
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
      this.renderError(`Failed to update plugin: ${error.message}`);
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
      this.renderError(`Failed to rollback plugin: ${error.message}`);
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
      const body = component.create('div');
      body.className = 'grid gap-3';

      const text = component.create('p');
      text.className = 'text-sm text-slate-700';
      text.textContent = message;

      const actions = component.create('div');
      actions.className = 'flex justify-end gap-2';

      const cancelButton = component.create('button', { label: 'Cancel' });
      cancelButton.className = 'inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700';
      cancelButton.dataset.action = 'cancel-modal';

      const confirmButton = component.create('button', { label: confirmLabel });
      confirmButton.className = 'inline-flex items-center rounded-md border border-brand-700 bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white';
      confirmButton.dataset.action = 'confirm-modal';

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
  renderError(message) {
    const banner = component.create('div');
    banner.className = 'rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700';
    banner.dataset.role = 'plugin-error';
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
  resolveContainer(container) {
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
