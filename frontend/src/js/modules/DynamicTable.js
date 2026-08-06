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

  /** @type {Array<object>} */
  #extraColumnsStart = [];

  /** @type {Array<object>} */
  #extraColumnsEnd = [];

  /** @type {((row: HTMLTableRowElement, record: object, rowIndex: number) => void)|null} */
  #rowDecorator = null;

  /** @type {boolean} */
  #showPagination = true;

  /** @type {string|null} */
  #wrapperClassName = null;

  /** @type {string|null} */
  #tableClassName = null;

  /** @type {string|null} */
  #tableDataRole = null;

  /** @type {HTMLElement} */
  #container;

  /** @type {number} */
  #pageSize = 10;

  /** @type {number} */
  #currentPage = 1;

  /**
   * Build a semantic action button used by table action columns.
   *
   * @param {{
   *   label: string,
   *   icon: string,
   *   tone?: 'sky'|'emerald'|'amber'|'red'|'violet'|'brand'|'slate',
   *   onClick?: () => void,
   *   disabled?: boolean,
   *   dataRole?: string,
   *   dataAction?: string
   * }} options
   * @returns {HTMLButtonElement}
   */
  static buildActionButton(options) {
    const tone = typeof options?.tone === 'string' ? options.tone : 'slate';
    const palette = {
      sky: 'text-sky-700 hover:text-sky-800 focus:ring-sky-200',
      emerald: 'text-emerald-700 hover:text-emerald-800 focus:ring-emerald-200',
      amber: 'text-amber-700 hover:text-amber-800 focus:ring-amber-200',
      red: 'text-red-700 hover:text-red-800 focus:ring-red-200',
      violet: 'text-violet-700 hover:text-violet-800 focus:ring-violet-200',
      brand: 'text-brand-700 hover:text-brand-800 focus:ring-brand-200',
      slate: 'text-slate-700 hover:text-slate-900 focus:ring-slate-200',
    };

    const colorClasses = palette[tone] ?? palette.slate;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = `inline-flex items-center gap-1.5 border-0 bg-transparent px-1 py-1 text-xs font-semibold transition duration-150 focus:outline-none focus:ring-2 ${colorClasses}`;

    if (typeof options?.dataRole === 'string' && options.dataRole !== '') {
      button.dataset.role = options.dataRole;
    }
    if (typeof options?.dataAction === 'string' && options.dataAction !== '') {
      button.dataset.action = options.dataAction;
    }

    if (options?.disabled === true) {
      button.disabled = true;
    }

    const icon = document.createElement('i');
    icon.className = `fa-solid ${options.icon} leading-none`;
    icon.style.fontSize = '18px';
    icon.setAttribute('aria-hidden', 'true');

    const label = document.createElement('span');
    label.textContent = options.label;

    button.appendChild(icon);
    button.appendChild(label);

    if (typeof options?.onClick === 'function') {
      button.addEventListener('click', options.onClick);
    }

    return button;
  }

  /**
   * @param {Array<object>} records
   * @param {object} schema
   * @param {string|HTMLElement} container
   * @param {{
   *   pageSize?: number,
   *   extraColumns?: Array<{
   *     label: string,
   *     position?: 'start'|'end',
   *     headerClassName?: string,
   *     cellClassName?: string,
   *     renderCell: (record: object, rowIndex: number) => Node|string|number|null|undefined
   *   }>,
  *   rowDecorator?: (row: HTMLTableRowElement, record: object, rowIndex: number) => void,
  *   showPagination?: boolean,
  *   wrapperClassName?: string,
  *   tableClassName?: string,
  *   tableDataRole?: string
   * }} options
   */
  constructor(records, schema, container, options = {}) {
    this.#records = Array.isArray(records) ? [...records] : [];
    this.#columns = this.#normalizeColumns(schema);
    this.#container = this.#resolveContainer(container);
    this.#pageSize = this.#normalizePageSize(options.pageSize);
    const extraColumns = this.#normalizeExtraColumns(options.extraColumns);
    this.#extraColumnsStart = extraColumns.filter((column) => column.position === 'start');
    this.#extraColumnsEnd = extraColumns.filter((column) => column.position === 'end');
    this.#rowDecorator = typeof options.rowDecorator === 'function' ? options.rowDecorator : null;
    this.#showPagination = options.showPagination !== false;
    this.#wrapperClassName = typeof options.wrapperClassName === 'string' && options.wrapperClassName !== '' ? options.wrapperClassName : null;
    this.#tableClassName = typeof options.tableClassName === 'string' && options.tableClassName !== '' ? options.tableClassName : null;
    this.#tableDataRole = typeof options.tableDataRole === 'string' && options.tableDataRole !== '' ? options.tableDataRole : null;
  }

  /**
   * Render table and pagination controls in container.
   */
  render() {
    this.#container.replaceChildren();

    const wrapper = document.createElement('div');
    wrapper.className = this.#wrapperClassName ?? 'overflow-x-auto rounded-xl border border-slate-200 bg-white';
    wrapper.dataset.role = 'table-wrapper';

    const table = document.createElement('table');
    table.className = this.#tableClassName ?? 'min-w-[680px] w-full border-separate border-spacing-0 text-left';
    table.dataset.role = this.#tableDataRole ?? 'table';

    table.appendChild(this.#buildHeader());
    table.appendChild(this.#buildBody());

    wrapper.appendChild(table);
    if (this.#showPagination) {
      wrapper.appendChild(this.#buildPagination());
    }
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

    for (const column of this.#extraColumnsStart) {
      row.appendChild(this.#buildExtraHeaderCell(column));
    }

    for (const column of this.#columns) {
      const th = document.createElement('th');
      th.className = 'border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600';
      th.textContent = column.label;
      row.appendChild(th);
    }

    for (const column of this.#extraColumnsEnd) {
      row.appendChild(this.#buildExtraHeaderCell(column));
    }

    thead.appendChild(row);
    return thead;
  }

  #buildBody() {
    const tbody = document.createElement('tbody');
    const pageRecords = this.getCurrentPageRecords();

    for (const record of pageRecords) {
      const row = document.createElement('tr');
      const rowIndex = (this.#currentPage - 1) * this.#pageSize + tbody.children.length;

      for (const column of this.#extraColumnsStart) {
        row.appendChild(this.#buildExtraBodyCell(column, record, rowIndex));
      }

      for (const column of this.#columns) {
        const td = document.createElement('td');
        td.className = 'border-b border-slate-100 px-3 py-2 text-sm text-slate-700';
        td.textContent = this.#toDisplayValue(record[column.name]);
        row.appendChild(td);
      }

      for (const column of this.#extraColumnsEnd) {
        row.appendChild(this.#buildExtraBodyCell(column, record, rowIndex));
      }

      if (this.#rowDecorator !== null) {
        this.#rowDecorator(row, record, rowIndex);
      }

      tbody.appendChild(row);
    }

    if (pageRecords.length === 0) {
      const row = document.createElement('tr');
      const td = document.createElement('td');
      td.className = 'px-3 py-6 text-center text-sm text-slate-500';
      td.colSpan = this.#totalColumns() || 1;
      td.textContent = 'No records';
      row.appendChild(td);
      tbody.appendChild(row);
    }

    return tbody;
  }

  #buildPagination() {
    const nav = document.createElement('div');
    nav.className = 'flex items-center justify-center gap-4 px-3 py-3';
    nav.dataset.role = 'table-pagination';

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50';
    prevBtn.dataset.role = 'table-prev';
    prevBtn.setAttribute('aria-label', 'Página anterior');
    const prevIcon = document.createElement('i');
    prevIcon.className = 'fa-solid fa-chevron-left';
    prevIcon.setAttribute('aria-hidden', 'true');
    const prevLabel = document.createElement('span');
    prevLabel.textContent = 'Anterior';
    prevBtn.appendChild(prevIcon);
    prevBtn.appendChild(prevLabel);
    prevBtn.disabled = this.#currentPage <= 1;

    const info = document.createElement('span');
    info.className = 'text-xs font-medium text-slate-500';
    info.dataset.role = 'table-page-info';
    info.textContent = `Page ${this.#currentPage} / ${this.getTotalPages()}`;

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50';
    nextBtn.dataset.role = 'table-next';
    nextBtn.setAttribute('aria-label', 'Página siguiente');
    const nextLabel = document.createElement('span');
    nextLabel.textContent = 'Siguiente';
    const nextIcon = document.createElement('i');
    nextIcon.className = 'fa-solid fa-chevron-right';
    nextIcon.setAttribute('aria-hidden', 'true');
    nextBtn.appendChild(nextLabel);
    nextBtn.appendChild(nextIcon);
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

    const baseColumns = this.#columnsFromSection(schema.fields);
    const customColumns = this.#columnsFromSection(schema.custom_fields);
    const combined = [...baseColumns, ...customColumns];
    const byName = new Map(combined.map((column) => [column.name, column]));

    const ordered = [];
    const orderList = Array.isArray(schema.ui_field_order) ? schema.ui_field_order : [];
    for (const candidate of orderList) {
      if (typeof candidate !== 'string') {
        continue;
      }

      const column = byName.get(candidate);
      if (column) {
        ordered.push(column);
        byName.delete(candidate);
      }
    }

    for (const column of combined) {
      if (byName.has(column.name)) {
        ordered.push(column);
        byName.delete(column.name);
      }
    }

    return ordered;
  }

  #columnsFromSection(section) {
    if (Array.isArray(section)) {
      return section
        .filter((field) => field && typeof field === 'object')
        .map((field) => {
          let name = '';
          if (typeof field.name === 'string') {
            name = field.name;
          } else if (typeof field.key === 'string') {
            name = field.key;
          }

          if (name === '') {
            return null;
          }

          if (field.summaryView === false) {
            return null;
          }

          return {
            name,
            label: field.label ?? name,
          };
        })
        .filter((column) => column !== null);
    }

    if (section && typeof section === 'object') {
      return Object.keys(section).map((name) => {
        const cfg = section[name];
        if (cfg && typeof cfg === 'object' && cfg.summaryView === false) {
          return null;
        }

        const label = cfg && typeof cfg === 'object' ? (cfg.label ?? name) : name;
        return { name, label };
      }).filter((column) => column !== null);
    }

    return [];
  }

  #normalizeExtraColumns(extraColumns) {
    if (!Array.isArray(extraColumns)) {
      return [];
    }

    return extraColumns
      .filter((column) => column && typeof column === 'object' && typeof column.label === 'string' && typeof column.renderCell === 'function')
      .map((column) => ({
        label: column.label,
        position: column.position === 'start' ? 'start' : 'end',
        headerClassName: typeof column.headerClassName === 'string' && column.headerClassName !== '' ? column.headerClassName : null,
        cellClassName: typeof column.cellClassName === 'string' && column.cellClassName !== '' ? column.cellClassName : null,
        renderCell: column.renderCell,
      }));
  }

  #buildExtraHeaderCell(column) {
    const th = document.createElement('th');
    th.className = column.headerClassName ?? 'border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600';
    th.textContent = column.label;
    return th;
  }

  #buildExtraBodyCell(column, record, rowIndex) {
    const td = document.createElement('td');
    td.className = column.cellClassName ?? 'border-b border-slate-100 px-3 py-2 text-sm text-slate-700';
    const rendered = column.renderCell(record, rowIndex);

    if (rendered instanceof Node) {
      td.appendChild(rendered);
    } else if (rendered !== null && rendered !== undefined) {
      td.textContent = String(rendered);
    }

    return td;
  }

  #totalColumns() {
    return this.#extraColumnsStart.length + this.#columns.length + this.#extraColumnsEnd.length;
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
