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
    this.className = options.className ?? 'overflow-x-auto rounded-xl border border-slate-200 bg-white';
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
    const table = component.create('tableElement', {
      className: this._tableClassName,
      dataset: { role: this._tableDataRole },
    });
    table.style.width = this._tableWidth;

    const thead = component.create('thead');
    const headerRow = component.create('tr');
    this._columns.forEach((column) => {
      const th = component.create('th', {
        className: column.headerClassName ?? 'border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600',
        text: column.label ?? '',
      });
      headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = component.create('tbody');
    if (this._rows.length === 0) {
      const emptyRow = component.create('tr');
      const emptyCell = component.create('td', {
        className: 'px-3 py-6 text-center text-sm text-slate-500',
        text: this._emptyMessage ?? 'No hay registros',
      });
      emptyCell.colSpan = this._columns.length || 1;
      emptyRow.appendChild(emptyCell);
      tbody.appendChild(emptyRow);
    } else {
      this._rows.forEach((row, rowIndex) => {
        const tr = component.create('tr');
        tr.className = rowIndex % 2 === 0 ? 'bg-white' : 'bg-slate-50/40';
        this._columns.forEach((column) => {
          const td = component.create('td', {
            className: column.cellClassName ?? 'border-b border-slate-100 px-3 py-2 text-sm text-slate-700',
          });
          const value = this.addCell(row, column, rowIndex);
          if (value instanceof Node) {
            td.appendChild(value);
          } else if (value !== undefined && value !== null) {
            td.textContent = String(value);
          }
          tr.appendChild(td);
        });
        if (this._rowDecorator !== null) {
          this._rowDecorator(tr, row, rowIndex);
        }
        tbody.appendChild(tr);
      });
    }

    table.appendChild(tbody);
    this.replaceChildren(table);
  }
}
