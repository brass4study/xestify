import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class TableComponent extends BaseComponent {
  initialize(options = {}) {
    this._columns = Array.isArray(options.columns) ? [...options.columns] : [];
    this._rows = Array.isArray(options.rows) ? [...options.rows] : [];
    this._emptyMessage = options.emptyMessage ?? 'No hay registros';
    this._tableClassName = options.tableClassName ?? 'w-full min-w-[680px] border-separate border-spacing-0 text-left';
    this._tableWidth = options.tableWidth ?? '100%';
    this._tableDataRole = options.tableDataRole ?? 'table';
    this._rowDecorator = typeof options.rowDecorator === 'function' ? options.rowDecorator : null;
    this._density = options.density ?? 'middle';
    this.className = options.className ?? 'overflow-x-auto rounded-md border border-slate-200 bg-white';
    this.dataset.role = options.dataRole ?? 'table-wrapper';
    this._render();
    return this;
  }

  addColumn(column) {
    this._columns.push(column);
    this._render();
    return this;
  }

  addRow(row) {
    this._rows.push(row);
    this._render();
    return this;
  }

  addCell(row, column, rowIndex) {
    const cell = column.render ? column.render(row, rowIndex) : row[column.key];
    return cell;
  }

  _render() {
    const densityClasses = {
      compact: { header: 'px-3 py-2.5', cell: 'px-3 py-1' },
      middle: { header: 'px-3 py-2.5', cell: 'px-3 py-2.5' },
      relaxed: { header: 'px-3 py-4', cell: 'px-3 py-4' },
    };
    const spacing = densityClasses[this._density] ?? densityClasses.middle;
    const table = component.create('tableElement', {
      className: this._tableClassName,
      dataset: { role: this._tableDataRole },
    });
    table.style.width = this._tableWidth;

    const thead = component.create('thead');
    const headerRow = component.create('tr');
    this._columns.forEach((column) => {
      const isSorted = column.sortDirection === 'asc' || column.sortDirection === 'desc';
      const isSortable = column.sortable === true && typeof column.onSort === 'function';
      const header = component.create('th', {
        className: column.headerClassName ?? `group border-b border-slate-100 bg-slate-50 ${spacing.header} text-xs font-semibold uppercase tracking-wide text-slate-600`,
      });
      if (isSortable) {
        header.classList.add('transition-colors', 'duration-300', 'ease-out', 'hover:bg-slate-200');
      } else {
        header.classList.add('cursor-default');
      }
      if (column.shrink === true) {
        header.classList.add('w-[1%]', 'whitespace-nowrap');
      }
      if (isSorted) {
        header.classList.add('bg-slate-200');
        header.setData('sorted', 'true');
      }
      if (isSortable) {
        header.setData('sortable', 'true');
        header.setAttribute('aria-sort', this.sortAriaValue(column.sortDirection));
        const sortButton = component.create('button', {
          label: column.label ?? '',
          ariaLabel: `Ordenar por ${column.label ?? ''}`,
          dataRole: 'table-sort',
          onClick: column.onSort,
        });
        this.buildSortIndicator(column.sortDirection).setParent(sortButton);
        sortButton.setClassName('inline-flex w-full items-center justify-between gap-2 bg-transparent text-left text-xs font-semibold uppercase tracking-wide text-slate-600 transition-colors duration-300 ease-out group-hover:text-brand-700 focus:outline-none');
        sortButton.dataset.column = column.key;
        sortButton.setParent(header);
      } else {
        header.textContent = column.label ?? '';
      }
      header.setParent(headerRow);
    });
    headerRow.setParent(thead);
    thead.setParent(table);

    const tbody = component.create('tbody');
    if (this._rows.length === 0) {
      const emptyRow = component.create('tr');
      const emptyCell = component.create('td', {
        className: 'px-3 py-6 text-center text-sm text-slate-500',
        text: this._emptyMessage ?? 'No hay registros',
      });
      emptyCell.colSpan = this._columns.length || 1;
      emptyCell.setParent(emptyRow);
      emptyRow.setParent(tbody);
    } else {
      this._rows.forEach((row, rowIndex) => {
        const tr = component.create('tr')
          .setClassName(rowIndex % 2 === 0 ? 'bg-white' : 'bg-slate-50/40');
        this._columns.forEach((column) => {
          const isSorted = column.sortDirection === 'asc' || column.sortDirection === 'desc';
          const td = component.create('td', {
            className: column.cellClassName ?? `border-b border-slate-100 ${spacing.cell} text-sm text-slate-700 transition-all duration-200`,
          });
          if (isSorted) {
            td.classList.add('bg-slate-50');
            td.setData('sorted', 'true');
          }
          if (column.shrink === true) {
            td.classList.add('w-[1%]', 'whitespace-nowrap');
          }
          const value = this.addCell(row, column, rowIndex);
          if (value instanceof Node) {
            td.appendChild(value);
          } else if (value !== undefined && value !== null) {
            td.textContent = String(value);
          }
          td.setParent(tr);
        });
        if (this._rowDecorator !== null) {
          this._rowDecorator(tr, row, rowIndex);
        }
        tr.classList.add('transition-colors', 'duration-150', 'hover:bg-slate-50');
        tr.setParent(tbody);
      });
    }

    tbody.setParent(table);
    this.replaceChildren(table);
  }

  sortAriaValue(direction) {
    if (direction === 'asc') {
      return 'ascending';
    }
    if (direction === 'desc') {
      return 'descending';
    }
    return 'none';
  }

  buildSortIndicator(direction) {
    const indicator = component.create('span', {
      className: 'ml-auto inline-flex h-4 w-3 shrink-0 flex-col items-center justify-center leading-none',
    }).setData('role', 'table-sort-indicator');
    component.create('i', {
      className: `fa-solid fa-caret-up -mb-px text-[12px] transition-colors duration-300 ${direction === 'asc' ? 'text-brand-500' : 'text-slate-300 group-hover:text-slate-400'}`,
      attributes: { 'aria-hidden': 'true' },
    }).setData('role', 'table-sort-up').setParent(indicator);
    component.create('i', {
      className: `fa-solid fa-caret-down -mt-px text-[12px] transition-colors duration-300 ${direction === 'desc' ? 'text-brand-500' : 'text-slate-300 group-hover:text-slate-400'}`,
      attributes: { 'aria-hidden': 'true' },
    }).setData('role', 'table-sort-down').setParent(indicator);
    return indicator;
  }
}
