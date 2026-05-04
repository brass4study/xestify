/**
 * EntityEdit.js — Page controller for creating or editing an entity record.
 *
 * Responsibilities:
 *   - Render a DynamicForm from the entity schema
 *   - Pre-fill the form when editing an existing record (PUT)
 *   - Submit via POST (create) or PUT (edit) using Api.js
 *   - Display per-field validation errors returned by the API
 *   - Invoke an optional onSaved(record) callback on success
 *   - Invoke an optional onCancel() callback when cancel is clicked
 */

import { Api, ApiError } from '../modules/Api.js';
import { DynamicForm } from '../modules/DynamicForm.js';
import { DynamicTabs } from '../modules/DynamicTabs.js';
import { PluginPanelRegistry } from '../modules/PluginPanelRegistry.js';

const BASE_URL = '/api/v1';

export class EntityEdit {
  /** @type {Api} */
  #api;

  /** @type {HTMLElement} */
  #container;

  /** @type {string} */
  #slug;

  /** @type {object} */
  #schema;

  /** @type {string|null} */
  #recordId;

  /** @type {DynamicForm|null} */
  #form = null;

  /** @type {Function|null} */
  #onSaved;

  /** @type {Function|null} */
  #onCancel;

  /**
   * Plugin panels mounted for the current record.
   * Each panel exposes flush(resolvedId) to persist pending changes.
   * @type {Array<{element: HTMLElement, flush: (id: string) => Promise<void>}>}
   */
  #pluginPanels = [];

  /**
   * @param {string|HTMLElement} container
   * @param {string} slug            Entity slug, e.g. 'client'
   * @param {object} schema          Entity schema with fields array
   * @param {{
   *   recordId?: string|null,
   *   initialData?: object,
   *   api?: object,
   *   onSaved?: Function,
   *   onCancel?: Function
   * }} options
   */
  constructor(container, slug, schema, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#slug = slug;
    this.#schema = schema && typeof schema === 'object' ? schema : { fields: [] };
    this.#recordId = options.recordId ?? null;
    this.#api = (options.api !== null && options.api !== undefined && typeof options.api.post === 'function')
      ? options.api
      : new Api(BASE_URL);
    this.#onSaved = typeof options.onSaved === 'function' ? options.onSaved : null;
    this.#onCancel = typeof options.onCancel === 'function' ? options.onCancel : null;

    this.#render(options.initialData ?? {});
  }

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------

  /**
   * Submit the form programmatically (also called on button click).
   *
   * @returns {Promise<void>}
   */
  async submit() {
    if (this.#form === null) {
      return;
    }

    this.#clearErrors();

    if (!this.#validateFormBeforeSubmit()) {
      return;
    }

    this.#setLoading(true);

    try {
      const saved = await this.#persistFormData();
      await this.#flushPendingPlugins(saved);
      this.#notifySaved(saved);
    } catch (err) {
      this.#handleSubmitError(err);
    } finally {
      this.#setLoading(false);
    }
  }

  // ---------------------------------------------------------------------------
  // Render helpers
  // ---------------------------------------------------------------------------

  /**
   * @param {object} initialData
   */
  #render(initialData) {
    this.#container.replaceChildren();

    // Title outside the form wrapper.
    const title = document.createElement('h3');
    title.className = 'xt-edit-wrapper__title';
    title.textContent = this.#recordId === null
      ? `Nuevo registro: ${this.#slug}`
      : `Editar registro: ${this.#slug}`;
    this.#container.appendChild(title);

    const wrapper = document.createElement('div');
    wrapper.className = 'xt-edit-wrapper';

    const errorBanner = document.createElement('p');
    errorBanner.className = 'xt-edit-error-banner';
    errorBanner.hidden = true;
    wrapper.appendChild(errorBanner);

    const formContainer = document.createElement('div');
    formContainer.className = 'xt-edit-form';
    wrapper.appendChild(formContainer);

    const schemaWithDefaults = this.#applyInitialData(this.#schema, initialData);
    this.#form = new DynamicForm(schemaWithDefaults, formContainer);
    this.#form.render();

    this.#container.appendChild(wrapper);

    // Actions stay outside the wrapper so they remain below tabs.
    const actions = document.createElement('div');
    actions.className = 'xt-edit-actions';

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'xt-btn xt-btn--primary';
    saveBtn.textContent = 'Guardar';
    saveBtn.addEventListener('click', () => {
      this.submit();
    });
    actions.appendChild(saveBtn);

    if (this.#onCancel !== null) {
      const cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'xt-btn xt-btn--secondary';
      cancelBtn.textContent = 'Cancelar';
      cancelBtn.addEventListener('click', () => {
        this.#onCancel();
      });
      actions.appendChild(cancelBtn);
    }

    this.#container.appendChild(actions);

    this.#loadAndRenderTabs(wrapper, actions);
  }

  /**
   * Fetch plugin tabs for this entity.
   * When tabs exist, the form fields are moved into a "Datos" tab and plugin
   * tabs are added alongside it, all inside a single DynamicTabs component.
   *
   * @param {HTMLElement} wrapper   The .xt-edit-wrapper that holds the form
   * @param {HTMLElement} actionsEl The .xt-edit-actions bar (outside wrapper)
   * @returns {Promise<void>}
   */
  async #loadAndRenderTabs(wrapper, actionsEl) {
    try {
      const { data } = await this.#api.get(`/entities/${this.#slug}/tabs`);
      const rawTabs = Array.isArray(data?.tabs) ? data.tabs : [];

      if (rawTabs.length === 0) {
        return;
      }

      const recordId = this.#recordId;
      const api = this.#api;

      // Reset plugin panels for this render.
      this.#pluginPanels = [];

      // Dynamically load each plugin's frontend module so it self-registers
      // in PluginPanelRegistry before we build the tab panels.
      await this.#loadPluginModules(rawTabs);

      // Move the existing form content (errorBanner + formContainer) into a
      // detached element so it can be used as the "Datos" tab content.
      const dataPanelContent = document.createDocumentFragment();
      while (wrapper.firstChild !== null) {
        dataPanelContent.appendChild(wrapper.firstChild);
      }
      const dataPanelEl = document.createElement('div');
      dataPanelEl.appendChild(dataPanelContent);

      // Build tab definitions: "Datos" first, then plugin tabs.
      const tabDefs = [
        {
          id: 'datos',
          label: 'Datos',
          content: () => dataPanelEl,
        },
        ...rawTabs.map((tab) => {
          // tab.id equals the plugin slug by convention (e.g. 'comments')
          const panel = PluginPanelRegistry.build(tab.id, {
            endpoint: tab.endpoint ?? '',
            recordId,
            api,
          });
          if (panel !== null) {
            this.#pluginPanels.push(panel);
          }
          return {
            id: tab.id,
            label: tab.label,
            content: () => (panel === null ? this.#buildFallbackPanel(tab.label) : panel.element),
          };
        }),
      ];

      // Replace the wrapper contents with the DynamicTabs component.
      const dynamicTabs = new DynamicTabs(wrapper, { tabs: tabDefs });
      dynamicTabs.render();

      // Move the actions bar to be the last child of #container so it stays
      // below the tabs (it was appended before this async call resolved).
      this.#container.appendChild(actionsEl);
    } catch {
      this.#showGlobalError('No se pudieron cargar las extensiones.');
    }
  }

  /**
   * Dynamically import each plugin's frontend module so it self-registers
   * in PluginPanelRegistry before we try to build tab panels.
   *
   * @param {Array<{id: string}>} tabs
   * @returns {Promise<void>}
   */
  async #loadPluginModules(tabs) {
    await Promise.allSettled(
      tabs.map((tab) => import(`/plugins/${tab.id}/plugin.js`))
    );
  }

  /**
   * Build a minimal fallback panel when no plugin is registered for a tab.
   *
   * @param {string} label
   * @returns {HTMLElement}
   */
  #buildFallbackPanel(label) {
    const el = document.createElement('div');
    el.className = 'xt-tab-panel';
    const placeholder = document.createElement('p');
    placeholder.className = 'xt-placeholder';
    placeholder.textContent = `Plugin "${label}" no disponible.`;
    el.appendChild(placeholder);
    return el;
  }

  /**
   * Inject initialData values as field defaults so DynamicForm pre-fills.
   *
   * @param {object} schema
   * @param {object} initialData
   * @returns {object}
   */
  #applyInitialData(schema, initialData) {
    if (Object.keys(initialData).length === 0) {
      return schema;
    }

    if (Array.isArray(schema.fields)) {
      return {
        ...schema,
        fields: schema.fields.map((field) => {
          if (Object.hasOwn(initialData, field.name)) {
            return { ...field, default: initialData[field.name] };
          }
          return field;
        }),
      };
    }

    if (schema.fields !== null && typeof schema.fields === 'object') {
      const fieldsWithDefaults = {};
      for (const [name, config] of Object.entries(schema.fields)) {
        const fieldConfig = config !== null && typeof config === 'object' ? config : {};
        fieldsWithDefaults[name] = Object.hasOwn(initialData, name)
          ? { ...fieldConfig, default: initialData[name] }
          : fieldConfig;
      }

      return {
        ...schema,
        fields: fieldsWithDefaults,
      };
    }

    return { ...schema };
  }

  // ---------------------------------------------------------------------------
  // Error display
  // ---------------------------------------------------------------------------

  /**
   * Show per-field validation errors below each input.
   *
   * @param {Record<string, string|string[]>} errors
   */
  #showErrors(errors) {
    const formEl = this.#container.querySelector('.xt-edit-form form');

    for (const [fieldName, messages] of Object.entries(errors)) {
      let input = null;
      if (formEl !== null) {
        input = formEl.querySelector(`[name="${fieldName}"]`);
      }

      const msgList = Array.isArray(messages) ? messages : [String(messages)];
      const errorEl = document.createElement('ul');
      errorEl.className = 'xt-field-errors';
      errorEl.dataset.field = fieldName;

      for (const msg of msgList) {
        const li = document.createElement('li');
        li.textContent = msg;
        errorEl.appendChild(li);
      }

      if (input !== null && input.parentElement !== null) {
        input.parentElement.appendChild(errorEl);
      } else if (formEl !== null) {
        formEl.appendChild(errorEl);
      }
    }
  }

  /**
   * @param {string} message
   */
  #showGlobalError(message) {
    const banner = this.#container.querySelector('.xt-edit-error-banner');
    if (banner !== null) {
      banner.textContent = message;
      banner.hidden = false;
    }
  }

  #clearErrors() {
    const banner = this.#container.querySelector('.xt-edit-error-banner');
    if (banner !== null) {
      banner.textContent = '';
      banner.hidden = true;
    }

    const errorLists = this.#container.querySelectorAll('.xt-field-errors');
    for (const el of errorLists) {
      el.remove();
    }
  }

  /**
   * @param {boolean} loading
   */
  #setLoading(loading) {
    const saveBtn = this.#container.querySelector('.xt-edit-actions .xt-btn--primary');
    if (saveBtn !== null) {
      saveBtn.disabled = loading;
      saveBtn.textContent = loading ? 'Guardando…' : 'Guardar';
    }
  }

  #validateFormBeforeSubmit() {
    const { isValid, errors } = this.#form.validate();
    if (!isValid) {
      this.#showErrors(errors);
      return false;
    }
    return true;
  }

  async #persistFormData() {
    const data = this.#form.getData();
    if (this.#recordId === null) {
      const { data: saved } = await this.#api.post(`/entities/${this.#slug}/records`, data);
      return saved;
    }
    const { data: saved } = await this.#api.put(`/entities/${this.#slug}/records/${this.#recordId}`, data);
    return saved;
  }

  async #flushPendingPlugins(saved) {
    const savedId = (saved !== null && typeof saved === 'object')
      ? String(saved.id ?? this.#recordId ?? '')
      : String(this.#recordId ?? '');

    if (savedId === '' || this.#pluginPanels.length === 0) {
      return;
    }

    await Promise.all(this.#pluginPanels.map((p) => p.flush(savedId)));
  }

  #notifySaved(saved) {
    if (this.#onSaved !== null) {
      this.#onSaved(saved);
    }
  }

  #handleSubmitError(err) {
    if (err instanceof ApiError && Object.keys(err.details).length > 0) {
      this.#showErrors(err.details);
      return;
    }
    this.#showGlobalError(err instanceof ApiError ? err.message : 'Error desconocido');
  }

  /**
   * @param {string|HTMLElement} container
   * @returns {HTMLElement}
   */
  #resolveContainer(container) {
    if (container instanceof HTMLElement) {
      return container;
    }

    if (typeof container === 'string') {
      const el = document.querySelector(container);
      if (el instanceof HTMLElement) {
        return el;
      }
    }

    throw new TypeError(`EntityEdit: container "${String(container)}" not found`);
  }
}
