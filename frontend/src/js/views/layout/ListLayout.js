import { PageLayout } from './PageLayout.js';
import { DynamicTable } from '../modules/DynamicTable.js';
import { component } from '../modules/ComponentFactory.js';

export class ListLayout extends PageLayout {
  #section = null;
  #contentHost = null;

  /** @returns {this} */
  build() {
    this.#section = component.create('section')
      .setClassName('overflow-visible')
      .setData('role', 'list-section');
    this.#contentHost = component.create('div')
      .setClassName('list-content-host')
      .setData('role', 'list-content-host')
      .setParent(this.#section);
    this.setTemplate('list').setContent(this.#section);
    super.build();
    return this;
  }

  /** @returns {HTMLElement|null} */
  getContentTarget() {
    return this.#contentHost;
  }

  /**
   * @param {{dataRole?: string, className?: string}} options
   * @returns {HTMLElement}
   */
  createTableHost(options = {}) {
    const role = typeof options.dataRole === 'string' && options.dataRole !== ''
      ? options.dataRole
      : 'list-table-host';
    const host = component.create('div')
      .setClassName(`list-table-host ${options.className ?? ''}`.trim())
      .setData('role', role);
    if (this.#contentHost instanceof HTMLElement) {
      this.#contentHost.append(host);
    }
    return host;
  }

  /**
   * @param {Object[]} records
   * @param {Object} schema
   * @param {Object} options
   * @returns {DynamicTable}
   */
  createTable(records, schema, options = {}) {
    const host = options.container instanceof HTMLElement ? options.container : this.createTableHost(options);
    const table = new DynamicTable(records, schema, host, {
      showPagination: options.showPagination ?? true,
      pageSize: options.pageSize,
      onRefresh: options.onRefresh,
      totalRecords: options.totalRecords,
      onQueryChange: options.onQueryChange,
      extraColumns: options.extraColumns,
      rowDecorator: options.rowDecorator,
      wrapperClassName: options.wrapperClassName ?? 'overflow-x-auto',
      tableClassName: options.tableClassName ?? 'w-full min-w-[680px] border-separate border-spacing-0 text-left',
      tableDataRole: options.tableDataRole,
    });
    table.render();
    return table;
  }
}