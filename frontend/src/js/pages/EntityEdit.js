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

    const { isValid, errors } = this.#form.validate();

    if (!isValid) {
      this.#showErrors(errors);
      return;
    }

    const data = this.#form.getData();
    this.#setLoading(true);

    try {
      const isEdit = this.#recordId !== null;
      const path = isEdit
        ? `/entities/${this.#slug}/records/${this.#recordId}`
        : `/entities/${this.#slug}/records`;

      const { data: saved } = isEdit
        ? await this.#api.put(path, data)
        : await this.#api.post(path, data);

      if (this.#onSaved !== null) {
        this.#onSaved(saved);
      }
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.details).length > 0) {
        this.#showErrors(err.details);
      } else {
        this.#showGlobalError(err instanceof ApiError ? err.message : 'Error desconocido');
      }
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
    this.#container.innerHTML = '';

    const wrapper = document.createElement('div');
    wrapper.className = 'xt-edit-wrapper';

    const title = document.createElement('h3');
    title.className = 'xt-edit-wrapper__title';
    title.textContent = this.#recordId !== null
      ? `Editar registro: ${this.#slug}`
      : `Nuevo registro: ${this.#slug}`;
    wrapper.appendChild(title);

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

    const actions = document.createElement('div');
    actions.className = 'xt-edit-actions';

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'xt-btn xt-btn--primary';
    saveBtn.textContent = 'Guardar';
    saveBtn.addEventListener('click', () => { this.submit(); });
    actions.appendChild(saveBtn);

    if (this.#onCancel !== null) {
      const cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.className = 'xt-btn xt-btn--secondary';
      cancelBtn.textContent = 'Cancelar';
      cancelBtn.addEventListener('click', () => { this.#onCancel(); });
      actions.appendChild(cancelBtn);
    }

    wrapper.appendChild(actions);
    this.#container.appendChild(wrapper);
  }

  /**
   * Inject initialData values as field defaults so DynamicForm pre-fills.
   *
   * @param {object} schema
   * @param {object} initialData
   * @returns {object}
   */
  #applyInitialData(schema, initialData) {
    if (!Array.isArray(schema.fields) || Object.keys(initialData).length === 0) {
      return schema;
    }

    return {
      ...schema,
      fields: schema.fields.map((field) => {
        if (Object.prototype.hasOwnProperty.call(initialData, field.name)) {
          return { ...field, default: initialData[field.name] };
        }
        return field;
      }),
    };
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
      const input = formEl !== null
        ? formEl.querySelector(`[name="${fieldName}"]`)
        : null;

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
    const saveBtn = this.#container.querySelector('.xt-btn--primary');
    if (saveBtn !== null) {
      saveBtn.disabled = loading;
      saveBtn.textContent = loading ? 'Guardando…' : 'Guardar';
    }
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
