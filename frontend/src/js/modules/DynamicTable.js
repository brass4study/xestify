/**
 * DynamicTable.js — Schema-driven table renderer for Xestify frontend.
 *
 * Features:
 *   - Dynamic columns from schema
 *   - Row rendering from records array
 *   - Basic pagination (prev/next)
 *
 * Rendering is composed from the shared UI primitives instead of hard-coding the
 * table DOM structure in each branch.
 */

import { component } from './ComponentFactory.js';

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

    const button = component.create('button', {
      label: options.label,
      icon: `fa-solid ${options.icon}`,
      dataRole: options.dataRole,
      dataAction: options.dataAction,
      disabled: options.disabled === true,
      onClick: options.onClick,
    });
    const icon = button.querySelector('i');
    if (icon instanceof HTMLElement) {
      icon.style.fontSize = '18px';
      icon.style.lineHeight = '18px';
    }
    button.className = `inline-flex items-center gap-1.5 border-0 bg-transparent px-1 py-1 text-xs font-semibold transition duration-150 focus:outline-none focus:ring-2 ${colorClasses}`;

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
    this.#columns = this.normalizeColumns(schema);
    this.#container = this.resolveContainer(container);
    this.#pageSize = this.normalizePageSize(options.pageSize);
    const extraColumns = this.normalizeExtraColumns(options.extraColumns);
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
    const records = this.getCurrentPageRecords();
    const baseColumns = this.#columns.map((column) => ({
      key: column.name,
      label: column.label ?? column.name,
      render: (record) => this.toDisplayValue(record?.[column.name]),
    }));
    const toTableColumn = (column) => ({
      label: column.label,
      headerClassName: column.headerClassName,
      cellClassName: column.cellClassName,
      render: column.renderCell,
    });
    const columns = [
      ...this.#extraColumnsStart.map(toTableColumn),
      ...baseColumns,
      ...this.#extraColumnsEnd.map(toTableColumn),
    ];
    const table = component.create('table', {
      columns,
      rows: records,
      emptyMessage: 'No records',
      className: this.#wrapperClassName ?? 'overflow-x-auto rounded-xl border border-slate-200 bg-white',
      tableClassName: this.#tableClassName ?? 'w-full min-w-[680px] border-separate border-spacing-0 text-left',
      tableDataRole: this.#tableDataRole ?? 'table',
      rowDecorator: this.#rowDecorator,
    });

    if (this.#showPagination) {
      table.appendChild(this.buildPagination());
    }
    this.#container.appendChild(table);
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
    this.#columns = this.normalizeColumns(schema);
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

  recordToRow(record) {
    const row = {};
    this.#columns.forEach((column) => {
      row[column.name] = this.toDisplayValue(record[column.name]);
    });
    return row;
  }

  buildPagination() {
    const nav = component.create('base', {
      className: 'flex items-center justify-center gap-4 px-3 py-3',
    });
    nav.setData('role', 'table-pagination');

    const prevBtn = component.create('button', {
      label: 'Anterior',
      variant: 'secondary',
      dataRole: 'table-prev',
      ariaLabel: 'Página anterior',
      disabled: this.#currentPage <= 1,
      onClick: () => {
        this.prevPage();
        this.render();
      },
    });
    prevBtn.classList.add('px-3', 'py-1.5', 'text-xs');

    const info = component.create('typography', {
      as: 'span',
      size: 'xs',
      weight: 'medium',
      color: 'slate-500',
      text: `Page ${this.#currentPage} / ${this.getTotalPages()}`,
      className: 'text-xs font-medium text-slate-500',
    });
    info.setData('role', 'table-page-info');

    const nextBtn = component.create('button', {
      label: 'Siguiente',
      variant: 'secondary',
      dataRole: 'table-next',
      ariaLabel: 'Página siguiente',
      disabled: this.#currentPage >= this.getTotalPages(),
      onClick: () => {
        this.nextPage();
        this.render();
      },
    });
    nextBtn.classList.add('px-3', 'py-1.5', 'text-xs');

    nav.appendChild(prevBtn);
    nav.appendChild(info);
    nav.appendChild(nextBtn);

    return nav;
  }

  resolveContainer(container) {
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

  normalizeColumns(schema) {
    if (!schema || typeof schema !== 'object') {
      return [];
    }

    const baseColumns = this.columnsFromSection(schema.fields);
    const customColumns = this.columnsFromSection(schema.custom_fields);
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

  columnsFromSection(section) {
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

  normalizeExtraColumns(extraColumns) {
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

  normalizePageSize(pageSize) {
    if (typeof pageSize !== 'number' || !Number.isInteger(pageSize) || pageSize <= 0) {
      return 10;
    }

    return pageSize;
  }

  toDisplayValue(value) {
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
