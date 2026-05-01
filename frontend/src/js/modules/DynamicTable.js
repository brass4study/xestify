/**
 * DynamicTable.js — Schema-driven table renderer for Xestify frontend.
 *
 * Features:
 *   - Dynamic columns from schema
 *   - Row rendering from records array
 *   - Basic pagination (prev/next)
 */

export class DynamicTable {
  /** @type {Array<object>} */
  #records = [];

  /** @type {Array<object>} */
  #columns = [];

  /** @type {HTMLElement} */
  #container;

  /** @type {number} */
  #pageSize = 10;

  /** @type {number} */
  #currentPage = 1;

  /**
   * @param {Array<object>} records
   * @param {object} schema
   * @param {string|HTMLElement} container
   * @param {{pageSize?: number}} options
   */
  constructor(records, schema, container, options = {}) {
    this.#records = Array.isArray(records) ? [...records] : [];
    this.#columns = this.#normalizeColumns(schema);
    this.#container = this.#resolveContainer(container);
    this.#pageSize = this.#normalizePageSize(options.pageSize);
  }

  /**
   * Render table and pagination controls in container.
   */
  render() {
    this.#container.innerHTML = '';

    const wrapper = document.createElement('div');
    wrapper.className = 'xt-table-wrapper';

    const table = document.createElement('table');
    table.className = 'xt-table';

    table.appendChild(this.#buildHeader());
    table.appendChild(this.#buildBody());

    wrapper.appendChild(table);
    wrapper.appendChild(this.#buildPagination());
    this.#container.appendChild(wrapper);
  }

  /**
   * Replace current records and reset to first page.
   *
   * @param {Array<object>} records
   */
  setRecords(records) {
    this.#records = Array.isArray(records) ? [...records] : [];
    this.#currentPage = 1;
  }

  /**
   * Replace current schema columns.
   *
   * @param {object} schema
   */
  setSchema(schema) {
    this.#columns = this.#normalizeColumns(schema);
  }

  /**
   * Move to next page if possible.
   */
  nextPage() {
    if (this.#currentPage < this.getTotalPages()) {
      this.#currentPage += 1;
    }
  }

  /**
   * Move to previous page if possible.
   */
  prevPage() {
    if (this.#currentPage > 1) {
      this.#currentPage -= 1;
    }
  }

  /**
   * @returns {number}
   */
  getCurrentPage() {
    return this.#currentPage;
  }

  /**
   * @returns {number}
   */
  getTotalPages() {
    if (this.#records.length === 0) {
      return 1;
    }

    return Math.max(1, Math.ceil(this.#records.length / this.#pageSize));
  }

  /**
   * @returns {Array<object>}
   */
  getCurrentPageRecords() {
    const start = (this.#currentPage - 1) * this.#pageSize;
    return this.#records.slice(start, start + this.#pageSize);
  }

  #buildHeader() {
    const thead = document.createElement('thead');
    const row = document.createElement('tr');

    for (const column of this.#columns) {
      const th = document.createElement('th');
      th.textContent = column.label;
      row.appendChild(th);
    }

    thead.appendChild(row);
    return thead;
  }

  #buildBody() {
    const tbody = document.createElement('tbody');
    const pageRecords = this.getCurrentPageRecords();

    for (const record of pageRecords) {
      const row = document.createElement('tr');

      for (const column of this.#columns) {
        const td = document.createElement('td');
        td.textContent = this.#toDisplayValue(record[column.name]);
        row.appendChild(td);
      }

      tbody.appendChild(row);
    }

    if (pageRecords.length === 0) {
      const row = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = this.#columns.length || 1;
      td.textContent = 'No records';
      row.appendChild(td);
      tbody.appendChild(row);
    }

    return tbody;
  }

  #buildPagination() {
    const nav = document.createElement('div');
    nav.className = 'xt-pagination';

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.textContent = 'Prev';
    prevBtn.disabled = this.#currentPage <= 1;

    const info = document.createElement('span');
    info.textContent = `Page ${this.#currentPage} / ${this.getTotalPages()}`;

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.textContent = 'Next';
    nextBtn.disabled = this.#currentPage >= this.getTotalPages();

    prevBtn.addEventListener('click', () => {
      this.prevPage();
      this.render();
    });

    nextBtn.addEventListener('click', () => {
      this.nextPage();
      this.render();
    });

    nav.appendChild(prevBtn);
    nav.appendChild(info);
    nav.appendChild(nextBtn);

    return nav;
  }

  #resolveContainer(container) {
    if (typeof container === 'string') {
      const element = document.querySelector(container);
      if (!(element instanceof HTMLElement)) {
        throw new TypeError('DynamicTable container not found');
      }
      return element;
    }

    if (container instanceof HTMLElement) {
      return container;
    }

    throw new TypeError('DynamicTable container must be a selector or HTMLElement');
  }

  #normalizeColumns(schema) {
    if (!schema || typeof schema !== 'object') {
      return [];
    }

    const fields = schema.fields;

    if (Array.isArray(fields)) {
      return fields
        .filter((field) => field && typeof field === 'object' && typeof field.name === 'string')
        .map((field) => ({
          name: field.name,
          label: field.label ?? field.name,
        }));
    }

    if (fields && typeof fields === 'object') {
      return Object.keys(fields).map((name) => {
        const cfg = fields[name];
        const label = cfg && typeof cfg === 'object' ? (cfg.label ?? name) : name;
        return { name, label };
      });
    }

    return [];
  }

  #normalizePageSize(pageSize) {
    if (typeof pageSize !== 'number' || !Number.isInteger(pageSize) || pageSize <= 0) {
      return 10;
    }

    return pageSize;
  }

  #toDisplayValue(value) {
    if (value === null || value === undefined) {
      return '';
    }

    if (typeof value === 'object') {
      try {
        return JSON.stringify(value);
      } catch {
        return '[object]';
      }
    }

    return String(value);
  }
}
