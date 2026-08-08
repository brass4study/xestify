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
import { component } from '../modules/ComponentFactory.js';

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
    this.#container = this.resolveContainer(container);
    this.#api = (options.api !== null && options.api !== undefined && typeof options.api.get === 'function')
      ? options.api
      : new Api();
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
        const empty = component.create('emptyState', {
          title: 'Sin entidades',
          description: 'No hay entidades disponibles.',
        });
        empty.dataset.role = 'entity-empty';
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
    let recordsSection = this.#container.querySelector('[data-role="records-section"]');

    if (recordsSection === null) {
      recordsSection = component.create('section', {
        dataRole: 'records-section',
        children: component.create('div'),
      });
      recordsSection.className = 'mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-panel';
      this.#container.appendChild(recordsSection);
    }

    recordsSection.replaceChildren();

    const createBtn = component.create('button', {
      label: this.#createLabelForSlug(slug),
      variant: 'primary',
      dataRole: 'record-create',
      onClick: () => {
        if (this.#onCreateNew !== null) {
          this.#onCreateNew(slug);
        }
      },
    });
    createBtn.className = 'inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-300';
    const header = component.create('pageHeader', {
      title: this.#entityLabelForSlug(slug),
      subtitle: this.#createLabelForSlug(slug),
      actions: [createBtn],
    });
    header.dataset.role = 'records-header';
    header.className = 'flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between';
    const heading = header.querySelector('[data-role="page-title"]');
    if (heading instanceof HTMLElement) {
      heading.dataset.role = 'records-title';
    }
    recordsSection.appendChild(header);

    const tableContainer = component.create('div', {
      className: 'p-3',
      dataset: { role: 'records-table-wrap' },
    });
    recordsSection.appendChild(tableContainer);

    const extraColumns = this.#onEdit === null
      ? []
      : [
          {
            label: 'Acciones',
            renderCell: (record) => DynamicTable.buildActionButton({
              label: 'Editar',
              icon: 'fa-pen',
              tone: 'sky',
              dataRole: 'record-edit',
              onClick: () => {
                if (this.#onEdit !== null) {
                  this.#onEdit(slug, record.id ?? null, record);
                }
              },
            }),
          },
        ];

    this.#table = new DynamicTable(records, schema, tableContainer, { extraColumns });
    this.#table.render();
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

    let indicator = this.#container.querySelector('[data-role="entity-loading"]');
    if (loading) {
      if (indicator === null) {
        indicator = component.create('p', {
          className: 'mb-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500',
          dataset: { role: 'entity-loading' },
          text: 'Cargando…',
        });
        this.#container.prepend(indicator);
      }
    } else if (indicator !== null) {
      indicator.remove();
    }
  }

  #clearError() {
    AppState.error = null;
    const errorEl = this.#container.querySelector('[data-role="entity-error"]');
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

    const errorEl = component.create('p', {
      className: 'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700',
      dataset: { role: 'entity-error' },
      text: message,
    });
    this.#container.appendChild(errorEl);
  }

  /**
   * @param {string|HTMLElement} container
   * @returns {HTMLElement}
   */
  resolveContainer(container) {
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
