/**
 * EntityList.js — Page controller for listing and browsing entities.
 *
 * Responsibilities:
 *   - Load available entity list from GET /entities
 *   - Render entity buttons for selection
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

  /** @type {DynamicTable|null} */
  #table = null;

  /**
   * @param {string|HTMLElement} container
   * @param {{api?: Api, onCreateNew?: Function}} options
   */
  constructor(container, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#api = (options.api !== null && options.api !== undefined && typeof options.api.get === 'function')
      ? options.api
      : new Api(BASE_URL);
    this.#onCreateNew = typeof options.onCreateNew === 'function'
      ? options.onCreateNew
      : null;
  }

  /**
   * Load entities and render the entity selector.
   *
   * @returns {Promise<void>}
   */
  async init() {
    this.#setLoading(true);
    this.#clearError();

    try {
      const { data } = await this.#api.get('/entities');
      const entities = Array.isArray(data) ? data : [];
      AppState.setEntities(entities);
      this.#renderEntitySelector(entities);
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
      const records = Array.isArray(data) ? data : [];
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
   * @param {Array<object>} entities
   */
  #renderEntitySelector(entities) {
    this.#container.innerHTML = '';

    const nav = document.createElement('nav');
    nav.className = 'xt-entity-nav';

    const title = document.createElement('h2');
    title.className = 'xt-entity-nav__title';
    title.textContent = 'Entidades disponibles';
    nav.appendChild(title);

    if (entities.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'xt-entity-nav__empty';
      empty.textContent = 'No hay entidades disponibles.';
      nav.appendChild(empty);
      this.#container.appendChild(nav);
      return;
    }

    const list = document.createElement('ul');
    list.className = 'xt-entity-nav__list';

    for (const entity of entities) {
      const item = document.createElement('li');
      item.className = 'xt-entity-nav__item';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'xt-entity-nav__btn';
      btn.textContent = entity.label ?? entity.slug ?? 'Sin nombre';
      btn.dataset.slug = entity.slug;

      btn.addEventListener('click', () => {
        this.loadEntity(entity.slug);
      });

      item.appendChild(btn);
      list.appendChild(item);
    }

    nav.appendChild(list);
    this.#container.appendChild(nav);
  }

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

    recordsSection.innerHTML = '';

    const header = document.createElement('div');
    header.className = 'xt-records-section__header';

    const heading = document.createElement('h3');
    heading.className = 'xt-records-section__title';
    heading.textContent = `Registros: ${slug}`;
    header.appendChild(heading);

    const createBtn = document.createElement('button');
    createBtn.type = 'button';
    createBtn.className = 'xt-btn xt-btn--primary';
    createBtn.textContent = 'Crear nuevo registro';
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
