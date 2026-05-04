/**
 * EntityList.js — Page controller for listing and browsing entities.
 *
 * Responsibilities:
 *   - Load available entity list from GET /entities
 *   - On entity click, load its records from GET /entities/:slug/records
 *   - Render records using DynamicTable
 *   - Expose a "Crear nuevo registro" button that invokes an optional callback
 */

import { Api, ApiError } from '../modules/Api.js';
import { AppState } from '../modules/State.js';
import { DynamicTable } from '../modules/DynamicTable.js';

const BASE_URL = '/api/v1';

export class EntityList {
  /** @type {Api} */
  #api;

  /** @type {HTMLElement} */
  #container;

  /** @type {Function|null} */
  #onCreateNew;

  /** @type {Function|null} */
  #onEdit;

  /** @type {DynamicTable|null} */
  #table = null;

  /**
   * @param {string|HTMLElement} container
   * @param {{api?: Api, onCreateNew?: Function, onEdit?: Function}} options
   */
  constructor(container, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#api = (options.api !== null && options.api !== undefined && typeof options.api.get === 'function')
      ? options.api
      : new Api(BASE_URL);
    this.#onCreateNew = typeof options.onCreateNew === 'function'
      ? options.onCreateNew
      : null;
    this.#onEdit = typeof options.onEdit === 'function'
      ? options.onEdit
      : null;
  }

  /**
   * Load entities and store them in AppState.
   *
   * @returns {Promise<void>}
   */
  async init() {
    this.#setLoading(true);
    this.#clearError();
    this.#container.replaceChildren();

    try {
      const { data } = await this.#api.get('/entities');
      const entities = Array.isArray(data) ? data : [];
      AppState.setEntities(entities);

      if (entities.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'xt-placeholder';
        empty.textContent = 'No hay entidades disponibles.';
        this.#container.appendChild(empty);
      }
    } catch (err) {
      this.#handleError(err);
    } finally {
      this.#setLoading(false);
    }
  }

  /**
   * Load records for the given entity slug and render the table.
   *
   * @param {string} slug
   * @returns {Promise<void>}
   */
  async loadEntity(slug) {
    this.#setLoading(true);
    this.#clearError();
    AppState.setCurrentEntity(slug);

    try {
      const { data } = await this.#api.get(`/entities/${slug}/records`);
      const records = this.#normalizeRecords(data);
      AppState.setRecords(records);

      const schema = this.#schemaForSlug(slug);
      this.#renderRecords(records, schema, slug);
    } catch (err) {
      this.#handleError(err);
    } finally {
      this.#setLoading(false);
    }
  }

  // ---------------------------------------------------------------------------
  // Render helpers
  // ---------------------------------------------------------------------------

  /**
   * @param {Array<object>} records
   * @param {object} schema
   * @param {string} slug
   */
  #renderRecords(records, schema, slug) {
    let recordsSection = this.#container.querySelector('.xt-records-section');

    if (recordsSection === null) {
      recordsSection = document.createElement('section');
      recordsSection.className = 'xt-records-section';
      this.#container.appendChild(recordsSection);
    }

    recordsSection.replaceChildren();

    const header = document.createElement('div');
    header.className = 'xt-records-section__header';

    const heading = document.createElement('h3');
    heading.className = 'xt-records-section__title';
    const entityLabel = this.#entityLabelForSlug(slug);
    heading.textContent = entityLabel;
    header.appendChild(heading);

    const createBtn = document.createElement('button');
    createBtn.type = 'button';
    createBtn.className = 'xt-btn xt-btn--primary xt-create-btn';
    const createIcon = document.createElement('i');
    createIcon.className = 'fa-solid fa-circle-plus xt-create-btn__icon';
    createIcon.setAttribute('aria-hidden', 'true');
    const createLabel = document.createElement('span');
    createLabel.textContent = this.#createLabelForSlug(slug);
    createBtn.appendChild(createIcon);
    createBtn.appendChild(createLabel);
    createBtn.addEventListener('click', () => {
      if (this.#onCreateNew !== null) {
        this.#onCreateNew(slug);
      }
    });
    header.appendChild(createBtn);
    recordsSection.appendChild(header);

    const tableContainer = document.createElement('div');
    tableContainer.className = 'xt-records-section__table';
    recordsSection.appendChild(tableContainer);

    this.#table = new DynamicTable(records, schema, tableContainer);
    this.#table.render();

    if (this.#onEdit !== null) {
      this.#injectEditColumn(tableContainer, records, slug);
    }
  }

  /**
   * Inject an "Acciones" column into the rendered DynamicTable.
   *
   * @param {HTMLElement} tableContainer
   * @param {Array<object>} records
   * @param {string} slug
   */
  #injectEditColumn(tableContainer, records, slug) {
    const table = tableContainer.querySelector('table');
    if (table === null) {
      return;
    }

    const theadRow = table.querySelector('thead tr');
    if (theadRow !== null) {
      const th = document.createElement('th');
      th.textContent = 'Acciones';
      theadRow.appendChild(th);
    }

    const tbodyRows = table.querySelectorAll('tbody tr');
    const pageRecords = this.#table === null
      ? records
      : this.#table.getCurrentPageRecords();

    tbodyRows.forEach((row, index) => {
      const record = pageRecords[index];
      if (record === undefined) {
        return;
      }
      const td = document.createElement('td');
      const editBtn = document.createElement('button');
      editBtn.type = 'button';
      editBtn.className = 'xt-btn xt-btn--sm xt-row-edit-btn';
      const editIcon = document.createElement('i');
      editIcon.className = 'fa-solid fa-pencil xt-row-edit-btn__icon';
      editIcon.setAttribute('aria-hidden', 'true');
      const editLabel = document.createElement('span');
      editLabel.textContent = 'Editar';
      editBtn.appendChild(editIcon);
      editBtn.appendChild(editLabel);
      editBtn.addEventListener('click', () => {
        if (this.#onEdit !== null) {
          this.#onEdit(slug, record.id ?? null, record);
        }
      });
      td.appendChild(editBtn);
      row.appendChild(td);
    });
  }

  // ---------------------------------------------------------------------------
  // State helpers
  // ---------------------------------------------------------------------------

  /**
   * Derive a minimal schema from AppState entities for the given slug.
   *
   * @param {string} slug
   * @returns {object}
   */
  #schemaForSlug(slug) {
    const entities = AppState.getEntities();
    const found = entities.find((e) => e.slug === slug);
    return found ?? { slug, fields: [] };
  }

  /**
   * @param {string} slug
   * @returns {string}
   */
  #entityLabelForSlug(slug) {
    const entities = AppState.getEntities();
    const found = entities.find((e) => e.slug === slug);

    if (found !== undefined && typeof found.label === 'string' && found.label.trim() !== '') {
      return found.label;
    }

    return slug;
  }

  /**
   * @param {string} slug
   * @returns {string}
   */
  #createLabelForSlug(slug) {
    const entities = AppState.getEntities();
    const found = entities.find((e) => e.slug === slug);
    const singular = found !== undefined && typeof found.label_singular === 'string' && found.label_singular !== ''
      ? found.label_singular
      : slug;

    return `Crear ${singular.toLowerCase()}`;
  }

  /**
   * Convert DB rows to flat records expected by DynamicTable/DynamicForm.
   * backend returns dynamic fields inside `content` (JSONB).
   *
   * @param {unknown} data
   * @returns {Array<object>}
   */
  #normalizeRecords(data) {
    if (!Array.isArray(data)) {
      return [];
    }

    return data.map((row) => this.#normalizeRecord(row));
  }

  /**
   * @param {unknown} row
   * @returns {object}
   */
  #normalizeRecord(row) {
    if (row === null || typeof row !== 'object') {
      return {};
    }

    const source = /** @type {Record<string, unknown>} */ (row);
    const content = this.#extractContentObject(source.content);

    return {
      ...content,
      id: source.id ?? null,
      entity_slug: source.entity_slug ?? null,
      created_at: source.created_at ?? null,
      updated_at: source.updated_at ?? null,
    };
  }

  /**
   * @param {unknown} rawContent
   * @returns {Record<string, unknown>}
   */
  #extractContentObject(rawContent) {
    if (rawContent !== null && typeof rawContent === 'object' && !Array.isArray(rawContent)) {
      return /** @type {Record<string, unknown>} */ (rawContent);
    }

    if (typeof rawContent === 'string') {
      try {
        const parsed = JSON.parse(rawContent);
        if (parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)) {
          return /** @type {Record<string, unknown>} */ (parsed);
        }
      } catch {
        return {};
      }
    }

    return {};
  }

  /**
   * @param {boolean} loading
   */
  #setLoading(loading) {
    AppState.loading = loading;

    let indicator = this.#container.querySelector('.xt-loading');
    if (loading) {
      if (indicator === null) {
        indicator = document.createElement('p');
        indicator.className = 'xt-loading';
        indicator.textContent = 'Cargando…';
        this.#container.prepend(indicator);
      }
    } else if (indicator !== null) {
      indicator.remove();
    }
  }

  #clearError() {
    AppState.error = null;
    const errorEl = this.#container.querySelector('.xt-error');
    if (errorEl !== null) {
      errorEl.remove();
    }
  }

  /**
   * @param {unknown} err
   */
  #handleError(err) {
    const message = err instanceof ApiError ? err.message : 'Error desconocido';
    AppState.error = { message };

    const errorEl = document.createElement('p');
    errorEl.className = 'xt-error';
    errorEl.textContent = message;
    this.#container.appendChild(errorEl);
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

    throw new TypeError(`EntityList: container "${String(container)}" not found`);
  }
}
