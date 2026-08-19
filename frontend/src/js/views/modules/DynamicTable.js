import { component } from './ComponentFactory.js';

export class DynamicTable {
	static PAGE_SIZE_OPTIONS = [10, 20, 50, 100, 200];
	static DENSITY_OPTIONS = ['compact', 'middle', 'relaxed'];
	static PAGE_SIZE_COOKIE = 'xestify_table_page_size';
	static DENSITY_COOKIE = 'xestify_table_density';
	static COOKIE_MAX_AGE = 31536000;
	#records = [];
	#columns = [];
	#extraColumnsStart = [];
	#extraColumnsEnd = [];
	#rowDecorator = null;
	#toolbarStart = null;
	#showPagination = true;
	#showToolbar = true;
	#wrapperClassName = null;
	#tableClassName = null;
	#tableDataRole = null;
	#container;
	#pageSize = 10;
	#currentPage = 1;
	#sortKey = null;
	#sortDirection = null;
	#sortValue = null;
	#density = 'middle';
	#visibleColumns = new Set();
	#onRefresh = null;
	#isRefreshing = false;
	#remoteMode = false;
	#totalRecords = 0;
	#onQueryChange = null;

	static buildActionButton(options) {
		const tone = typeof options?.tone === 'string' ? options.tone : 'theme';
		const palette = {
			theme: 'text-brand-700 hover:text-brand-500 focus:ring-brand-200',
			brand: 'text-brand-700 hover:text-brand-500 focus:ring-brand-200',
			slate: 'text-brand-700 hover:text-brand-500 focus:ring-brand-200',
			sky: 'text-sky-700 hover:text-sky-600 focus:ring-sky-200',
			emerald: 'text-emerald-700 hover:text-emerald-600 focus:ring-emerald-200',
			amber: 'text-amber-700 hover:text-amber-600 focus:ring-amber-200',
			red: 'text-red-700 hover:text-red-600 focus:ring-red-200',
			violet: 'text-violet-700 hover:text-violet-600 focus:ring-violet-200',
		};

		const colorClasses = palette[tone] ?? palette.theme;

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
			icon.classList.add('text-lg', 'leading-none');
		}
		button.setClassName(`inline-flex items-center gap-1.5 bg-transparent px-1 py-1 text-xs font-semibold transition duration-150 focus:outline-none focus:ring-2 ${colorClasses}`);
		button.dataset.tableActionButton = 'true';

		return button;
	}

	constructor(records, schema, container, options = {}) {
		this.#records = Array.isArray(records) ? [...records] : [];
		this.#columns = this.normalizeColumns(schema);
		this.#visibleColumns = new Set(this.#columns.map((column) => column.name));
		this.#container = this.resolveContainer(container);
		this.#pageSize = DynamicTable.getPreferredPageSize(this.normalizePageSize(options.pageSize));
		// An explicit density option pins the table to that density (e.g. an
		// embedded editor grid that must stay compact); otherwise the user's
		// cookie preference applies as always.
		this.#density = DynamicTable.DENSITY_OPTIONS.includes(options.density)
			? options.density
			: DynamicTable.getPreferredDensity();
		const extraColumns = this.normalizeExtraColumns(options.extraColumns);
		this.#extraColumnsStart = extraColumns.filter((column) => column.position === 'start');
		this.#extraColumnsEnd = extraColumns.filter((column) => column.position === 'end');
		extraColumns.forEach((column) => this.#visibleColumns.add(column.key));
		this.#rowDecorator = typeof options.rowDecorator === 'function' ? options.rowDecorator : null;
		this.#toolbarStart = options.toolbarStart instanceof HTMLElement ? options.toolbarStart : null;
		this.#showPagination = options.showPagination !== false;
		this.#showToolbar = options.showToolbar !== false;
		this.#wrapperClassName = typeof options.wrapperClassName === 'string' && options.wrapperClassName !== '' ? options.wrapperClassName : null;
		this.#tableClassName = typeof options.tableClassName === 'string' && options.tableClassName !== '' ? options.tableClassName : null;
		this.#tableDataRole = typeof options.tableDataRole === 'string' && options.tableDataRole !== '' ? options.tableDataRole : null;
		this.#onRefresh = typeof options.onRefresh === 'function' ? options.onRefresh : null;
		this.#remoteMode = typeof options.onQueryChange === 'function';
		this.#onQueryChange = this.#remoteMode ? options.onQueryChange : null;
		this.#totalRecords = this.#remoteMode && Number.isInteger(options.totalRecords)
			? Math.max(0, options.totalRecords)
			: this.#records.length;
	}

	render() {
		this.#container.replaceChildren();
		const root = component.create('div', {
			className: 'relative overflow-visible rounded-md border border-slate-200 bg-white',
		}).setData('role', 'dynamic-table');
		if (this.#showToolbar) {
			this.buildToolbar().setParent(root);
		}
		const records = this.getCurrentPageRecords();
		const baseColumns = this.#columns
			.filter((column) => this.#visibleColumns.has(column.name))
			.map((column) => ({
			key: column.name,
			label: column.label ?? column.name,
			sortable: true,
			sortDirection: this.#sortKey === column.name ? this.#sortDirection : null,
			onSort: () => this.sortBy(column.name),
			render: (record) => this.formatCellValue(record?.[column.name], column),
			width: column.width,
		}));
		const toTableColumn = (column) => ({
			key: column.key,
			label: column.label,
			headerClassName: column.headerClassName,
			cellClassName: column.cellClassName,
			render: column.renderCell,
			sortable: column.sortValue !== null,
			sortDirection: this.#sortKey === column.key ? this.#sortDirection : null,
			onSort: () => this.sortBy(column.key, column.sortValue),
			shrink: column.shrink,
			width: column.width,
		});
		const columns = [
			...this.#extraColumnsStart.filter((column) => this.#visibleColumns.has(column.key)).map(toTableColumn),
			...baseColumns,
			...this.#extraColumnsEnd.filter((column) => this.#visibleColumns.has(column.key)).map(toTableColumn),
		];
		const table = component.create('table', {
			columns,
			rows: records,
			emptyMessage: 'No records',
			className: this.#wrapperClassName ?? 'overflow-x-auto',
			tableClassName: this.#tableClassName ?? 'w-full min-w-[680px] border-separate border-spacing-0 text-left',
			tableDataRole: this.#tableDataRole ?? 'table',
			rowDecorator: this.#rowDecorator,
			density: this.#density,
		});

		table.setParent(root);
		if (this.#showPagination) {
			this.buildPagination().setParent(root);
		}
		root.setParent(this.#container);
	}

	setRecords(records) {
		this.#records = Array.isArray(records) ? [...records] : [];
		this.#totalRecords = this.#records.length;
		this.#currentPage = 1;
	}

	setPageData(records, totalRecords, page = this.#currentPage) {
		this.#records = Array.isArray(records) ? [...records] : [];
		this.#totalRecords = Number.isInteger(totalRecords) ? Math.max(0, totalRecords) : this.#records.length;
		this.#currentPage = Math.max(1, page);
	}

	sortBy(columnName, sortValue = null) {
		if (typeof columnName !== 'string' || columnName === '') {
			return;
		}
		if (this.#sortKey !== columnName) {
			this.#sortKey = columnName;
			this.#sortDirection = 'asc';
			this.#sortValue = typeof sortValue === 'function' ? sortValue : (record) => record?.[columnName];
		} else if (this.#sortDirection === 'asc') {
			this.#sortDirection = 'desc';
		} else {
			this.#sortKey = null;
			this.#sortDirection = null;
			this.#sortValue = null;
		}
		this.#currentPage = 1;
		if (this.#remoteMode) {
			void this.#requestRemoteData();
		} else {
			this.render();
		}
	}

	nextPage() {
		if (this.#currentPage < this.getTotalPages()) {
			this.#currentPage += 1;
		}
	}

	prevPage() {
		if (this.#currentPage > 1) {
			this.#currentPage -= 1;
		}
	}

	getCurrentPage() {
		return this.#currentPage;
	}

	getTotalPages() {
		const total = this.#remoteMode ? this.#totalRecords : this.#records.length;
		if (total === 0) {
			return 1;
		}

		return Math.max(1, Math.ceil(total / this.#pageSize));
	}

	getCurrentPageRecords() {
		if (this.#remoteMode) {
			return [...this.#records];
		}
		if (!this.#showPagination) {
			// showPagination: false hides the pager controls but callers still
			// expect every record to render — without this, slicing to
			// #pageSize (default 10) silently drops rows past the first page
			// with no way left in the UI to reach them.
			return this.getSortedRecords();
		}
		const start = (this.#currentPage - 1) * this.#pageSize;
		return this.getSortedRecords().slice(start, start + this.#pageSize);
	}

	async #requestRemoteData() {
		if (this.#onQueryChange === null) {
			return;
		}
		const result = await this.#onQueryChange({
			page: this.#currentPage,
			pageSize: this.#pageSize,
			sort: this.#sortKey,
			direction: this.#sortDirection,
		});
		if (result && typeof result === 'object') {
			this.setPageData(result.records, result.total, result.page ?? this.#currentPage);
		}
		this.render();
	}

	#goToPage(page) {
		this.#currentPage = Math.min(this.getTotalPages(), Math.max(1, page));
		if (this.#remoteMode) {
			void this.#requestRemoteData();
		} else {
			this.render();
		}
	}

	#setPageSize(pageSize) {
		if (!DynamicTable.PAGE_SIZE_OPTIONS.includes(pageSize)) {
			return;
		}
		this.#pageSize = pageSize;
		DynamicTable.#writePreference(DynamicTable.PAGE_SIZE_COOKIE, pageSize);
		this.#currentPage = 1;
		if (this.#remoteMode) {
			void this.#requestRemoteData();
		} else {
			this.render();
		}
	}

	getSortedRecords() {
		if (this.#sortKey === null || this.#sortDirection === null || this.#sortValue === null) {
			return [...this.#records];
		}
		const direction = this.#sortDirection === 'asc' ? 1 : -1;
		return [...this.#records].sort((left, right) => {
			const leftValue = this.#sortValue(left);
			const rightValue = this.#sortValue(right);
			if (typeof leftValue === 'number' && typeof rightValue === 'number') {
				return (leftValue - rightValue) * direction;
			}
			return String(leftValue ?? '').localeCompare(String(rightValue ?? ''), 'es', {
				numeric: true,
				sensitivity: 'base',
			}) * direction;
		});
	}

	buildToolbar() {
		const justify = this.#toolbarStart !== null ? 'justify-between' : 'justify-end';
		const toolbar = component.create('div', {
			className: `flex min-h-12 items-center ${justify} gap-3 border-b border-slate-100 px-3 py-2`,
		}).setData('role', 'table-toolbar');

		if (this.#toolbarStart !== null) {
			this.#toolbarStart.setParent(toolbar);
		}

		const buttons = component.create('div', { className: 'flex shrink-0 items-center gap-1' });
		this.#createToolbarButton('Refrescar', 'fa-arrows-rotate', 'table-refresh', () => this.refresh()).setParent(buttons);
		this.#buildDensityMenu().setParent(buttons);
		this.#buildSettingsMenu().setParent(buttons);
		buttons.setParent(toolbar);

		return toolbar;
	}

	#createToolbarButton(label, icon, role, onClick) {
		const button = component.create('button', {
			icon,
			ariaLabel: label,
			dataRole: role,
			onClick,
		});
		button.title = label;
		button.setClassName('inline-flex h-8 w-8 items-center justify-center rounded-md border-0 bg-transparent p-0 text-slate-600 transition duration-200 hover:bg-slate-100 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-200 disabled:cursor-wait disabled:opacity-50');
		return button;
	}

	#buildDensityMenu() {
		const wrapper = component.create('div', { className: 'relative' });
		const trigger = this.#createToolbarButton('Tamaño', 'fa-up-right-and-down-left-from-center', 'table-density', () => {
			this.#toggleMenu(wrapper);
		});
		trigger.setParent(wrapper);
		const menu = component.create('div', {
			className: 'absolute right-0 top-10 z-30 hidden w-36 origin-top-right rounded-md border border-slate-200 bg-white py-1 shadow-lg transition duration-200',
		}).setData('role', 'table-density-menu');
		[
			['compact', 'Compacto'],
			['middle', 'Medio'],
			['relaxed', 'Amplio'],
		].forEach(([value, label]) => {
			const option = component.create('button', {
				label,
				icon: this.#density === value ? 'fa-check' : '',
				dataRole: 'table-density-option',
				onClick: () => {
					this.#density = value;
					DynamicTable.#writePreference(DynamicTable.DENSITY_COOKIE, value);
					this.render();
				},
			});
			option.dataset.density = value;
			option.setClassName('flex w-full items-center justify-start gap-2 bg-white px-3 py-2 text-left text-sm font-normal text-slate-700 transition-colors hover:bg-slate-50');
			option.setParent(menu);
		});
		menu.setParent(wrapper);
		return wrapper;
	}

	#buildSettingsMenu() {
		const wrapper = component.create('div', { className: 'relative' });
		const trigger = this.#createToolbarButton('Opciones', 'fa-gear', 'table-settings', () => {
			this.#toggleMenu(wrapper);
		});
		trigger.setParent(wrapper);
		const menu = component.create('div', {
			className: 'absolute right-0 top-10 z-30 hidden w-52 origin-top-right rounded-md border border-slate-200 bg-white p-2 shadow-lg transition duration-200',
		}).setData('role', 'table-settings-menu');
		component.create('typography', {
			as: 'span',
			text: 'Columnas visibles',
			className: 'block px-2 pb-2 text-xs font-semibold text-slate-500',
		}).setParent(menu);
		const configurableColumns = [
			...this.#extraColumnsStart.map((column) => ({ name: column.key, label: column.label })),
			...this.#columns,
			...this.#extraColumnsEnd.map((column) => ({ name: column.key, label: column.label })),
		];
		configurableColumns.forEach((column) => {
			const row = component.create('label', {
				className: 'flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-slate-700 transition-colors hover:bg-slate-50',
			});
			const input = component.create('inputCheck', {
				checked: this.#visibleColumns.has(column.name),
			});
			input.dataset.column = column.name;
			input.addEventListener('change', () => {
				if (input.checked) {
					this.#visibleColumns.add(column.name);
				} else if (this.#visibleColumns.size > 1) {
					this.#visibleColumns.delete(column.name);
				} else {
					input.checked = true;
					return;
				}
				this.render();
			});
			input.setParent(row);
			component.create('span', { text: column.label ?? column.name }).setParent(row);
			row.setParent(menu);
		});
		menu.setParent(wrapper);
		return wrapper;
	}

	#toggleMenu(wrapper) {
		const menu = wrapper.querySelector('[data-role$="-menu"]');
		if (menu instanceof HTMLElement) {
			menu.classList.toggle('hidden');
		}
	}

	async refresh() {
		if (this.#isRefreshing) {
			return;
		}
		this.#isRefreshing = true;
		const button = this.#container.querySelector('[data-role="table-refresh"]');
		if (button instanceof HTMLButtonElement) {
			button.disabled = true;
			button.firstElementChild?.classList.add('fa-spin');
		}
		try {
			if (this.#remoteMode) {
				await this.#requestRemoteData();
			} else if (this.#onRefresh !== null) {
				await this.#onRefresh(this);
			}
		} finally {
			this.#isRefreshing = false;
			if (!this.#remoteMode) {
				this.render();
			}
		}
	}

	buildPagination() {
		const nav = component.create('base', {
			className: 'flex flex-wrap items-center justify-end gap-2 px-3 py-3',
		}).setData('role', 'table-pagination');


		const info = component.create('typography', {
			as: 'span',
			size: 'xs',
			weight: 'medium',
			color: 'slate-500',
			text: this.#paginationInfo(),
			className: 'text-xs font-medium text-slate-500',
		});
		info.setData('role', 'table-page-info');

		const prevBtn = component.create('button', {
			icon: 'fa-chevron-left',
			variant: 'secondary',
			dataRole: 'table-prev',
			ariaLabel: 'Página anterior',
			disabled: this.#currentPage <= 1,
			onClick: () => {
				this.#goToPage(this.#currentPage - 1);
			},
		});
		prevBtn.setClassName('inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white p-0 text-xs text-slate-700 transition hover:border-brand-500 hover:text-brand-700 disabled:opacity-40');

		const nextBtn = component.create('button', {
			icon: 'fa-chevron-right',
			variant: 'secondary',
			dataRole: 'table-next',
			ariaLabel: 'Página siguiente',
			disabled: this.#currentPage >= this.getTotalPages(),
			onClick: () => {
				this.#goToPage(this.#currentPage + 1);
			},
		});
		nextBtn.setClassName('inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white p-0 text-xs text-slate-700 transition hover:border-brand-500 hover:text-brand-700 disabled:opacity-40');

		info.setParent(nav);
		prevBtn.setParent(nav);
		this.#paginationItems().forEach((item) => {
			if (typeof item === 'number') {
				this.#buildPageButton(item).setParent(nav);
			} else {
				this.#buildPageJump(item).setParent(nav);
			}
		});
		nextBtn.setParent(nav);
		this.#buildPageSizeMenu().setParent(nav);

		return nav;
	}

	#paginationItems() {
		const totalPages = this.getTotalPages();
		if (totalPages <= 10) {
			return Array.from({ length: totalPages }, (_, index) => index + 1);
		}

		let start = Math.max(2, this.#currentPage - 2);
		let end = Math.min(totalPages - 1, this.#currentPage + 2);
		if (this.#currentPage <= 4) {
			start = 2;
			end = 5;
		} else if (this.#currentPage >= totalPages - 3) {
			start = totalPages - 4;
			end = totalPages - 1;
		}

		const items = [1];
		if (start > 2) {
			items.push('jump-prev');
		}
		for (let page = start; page <= end; page += 1) {
			items.push(page);
		}
		if (end < totalPages - 1) {
			items.push('jump-next');
		}
		items.push(totalPages);
		return items;
	}

	#buildPageButton(page) {
		const pageButton = component.create('button', {
			label: String(page),
			ariaLabel: `Página ${page}`,
			dataRole: 'table-page',
			onClick: () => this.#goToPage(page),
		});
		pageButton.dataset.page = String(page);
		pageButton.setClassName(page === this.#currentPage
			? 'inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-brand-600 bg-brand-600 px-2 text-xs font-semibold text-white transition'
			: 'inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-slate-300 bg-white px-2 text-xs text-slate-700 transition hover:border-brand-500 hover:text-brand-700');
		return pageButton;
	}

	#buildPageJump(direction) {
		const isPrevious = direction === 'jump-prev';
		const targetPage = isPrevious
			? Math.max(1, this.#currentPage - 5)
			: Math.min(this.getTotalPages(), this.#currentPage + 5);
		const label = isPrevious ? 'Retroceder 5 páginas' : 'Avanzar 5 páginas';
		const button = component.create('button', {
			ariaLabel: label,
			dataRole: 'table-page-jump',
			onClick: () => this.#goToPage(targetPage),
		});
		button.dataset.direction = isPrevious ? 'prev' : 'next';
		button.title = label;
		button.setClassName('group inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-transparent bg-white px-2 text-xs text-slate-500 transition hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-200');
		component.create('span', {
			text: '...',
			className: 'inline group-hover:hidden group-focus:hidden',
		}).setData('role', 'table-page-jump-ellipsis').setParent(button);
		component.create('i', {
			className: `fa-solid ${isPrevious ? 'fa-angles-left' : 'fa-angles-right'} hidden group-hover:inline-flex group-focus:inline-flex`,
			attributes: { 'aria-hidden': 'true' },
		}).setData('role', 'table-page-jump-icon').setParent(button);
		return button;
	}

	#paginationInfo() {
		const total = this.#remoteMode ? this.#totalRecords : this.#records.length;
		if (total === 0) {
			return '0-0 de 0 registros';
		}
		const start = ((this.#currentPage - 1) * this.#pageSize) + 1;
		const visibleCount = this.#remoteMode
			? this.#records.length
			: Math.min(this.#pageSize, total - start + 1);
		const end = Math.min(total, start + visibleCount - 1);
		return `${start}-${end} de ${total} registros`;
	}

	#buildPageSizeMenu() {
		const wrapper = component.create('div', { className: 'relative' });
		const trigger = component.create('button', {
			label: `${this.#pageSize} / página`,
			icon: 'fa-chevron-up',
			ariaLabel: 'Registros por página',
			dataRole: 'table-page-size',
			onClick: () => this.#toggleMenu(wrapper),
		});
		trigger.setClassName('inline-flex h-8 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-xs text-slate-700 transition hover:border-brand-500 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-200');
		trigger.setParent(wrapper);

		const menu = component.create('div', {
			className: 'absolute bottom-full right-0 z-30 mb-2 hidden w-32 origin-bottom-right rounded-md border border-slate-200 bg-white py-1 shadow-lg transition duration-200',
		}).setData('role', 'table-page-size-menu');
		DynamicTable.PAGE_SIZE_OPTIONS.forEach((pageSize) => {
			const option = component.create('button', {
				label: `${pageSize} / página`,
				icon: this.#pageSize === pageSize ? 'fa-check' : '',
				dataRole: 'table-page-size-option',
				onClick: () => this.#setPageSize(pageSize),
			});
			option.dataset.pageSize = String(pageSize);
			option.setClassName('flex w-full items-center justify-start gap-2 bg-white px-3 py-2 text-left text-xs font-normal text-slate-700 transition-colors hover:bg-slate-50');
			option.setParent(menu);
		});
		menu.setParent(wrapper);
		return wrapper;
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

					if (name === '' || field.summaryView === false) {
						return null;
					}

					return {
						name,
						label: field.label ?? name,
						type: typeof field.type === 'string' ? field.type : null,
						options: Array.isArray(field.options) ? field.options : null,
						width: typeof field.width === 'string' && field.width !== '' ? field.width : null,
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
				const type = cfg && typeof cfg === 'object' && typeof cfg.type === 'string' ? cfg.type : null;
				const options = cfg && typeof cfg === 'object' && Array.isArray(cfg.options) ? cfg.options : null;
				const width = cfg && typeof cfg === 'object' && typeof cfg.width === 'string' && cfg.width !== '' ? cfg.width : null;
				return { name, label, type, options, width };
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
			.map((column, index) => ({
				key: typeof column.key === 'string' && column.key !== '' ? column.key : `extra-column-${index}`,
				label: column.label,
				position: column.position === 'start' ? 'start' : 'end',
				headerClassName: typeof column.headerClassName === 'string' && column.headerClassName !== '' ? column.headerClassName : null,
				cellClassName: typeof column.cellClassName === 'string' && column.cellClassName !== '' ? column.cellClassName : null,
				renderCell: column.renderCell,
				sortValue: typeof column.sortValue === 'function' ? column.sortValue : null,
				shrink: column.shrink === true,
				width: typeof column.width === 'string' && column.width !== '' ? column.width : null,
			}));
	}

	normalizePageSize(pageSize) {
		if (typeof pageSize !== 'number' || !Number.isInteger(pageSize) || pageSize <= 0) {
			return 10;
		}

		return pageSize;
	}

	static getPreferredPageSize(fallback = 10) {
		const value = Number.parseInt(DynamicTable.#readPreference(DynamicTable.PAGE_SIZE_COOKIE) ?? '', 10);
		return DynamicTable.PAGE_SIZE_OPTIONS.includes(value) ? value : fallback;
	}

	static getPreferredDensity() {
		const value = DynamicTable.#readPreference(DynamicTable.DENSITY_COOKIE);
		return DynamicTable.DENSITY_OPTIONS.includes(value) ? value : 'middle';
	}

	static #readPreference(name) {
		const prefix = `${encodeURIComponent(name)}=`;
		const cookie = document.cookie
			.split('; ')
			.find((entry) => entry.startsWith(prefix));
		return cookie === undefined ? null : decodeURIComponent(cookie.slice(prefix.length));
	}

	static #writePreference(name, value) {
		document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(String(value))}; Max-Age=${DynamicTable.COOKIE_MAX_AGE}; Path=/; SameSite=Lax`;
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

	/**
	 * `date` fields are stored as "YYYY-MM-DD" (native <input type="date">
	 * format); the table must show them as "dd/mm/yyyy" for the user.
	 */
	formatDateValue(value) {
		if (typeof value !== 'string') {
			return this.toDisplayValue(value);
		}

		const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
		if (match === null) {
			return this.toDisplayValue(value);
		}

		const [, year, month, day] = match;
		return `${day}/${month}/${year}`;
	}

	/**
	 * `select` fields store the option's raw value in the record (e.g.
	 * "ophthalmologist"); the table must show its human label (e.g.
	 * "Oftalmólogo") the same way the edit form's InputSelect does.
	 */
	formatCellValue(value, column) {
		if (column.type === 'date') {
			return this.formatDateValue(value);
		}

		if (column.type !== 'select' || !Array.isArray(column.options)) {
			return this.toDisplayValue(value);
		}

		if (value === null || value === undefined) {
			return '';
		}

		const stringValue = String(value);
		const match = column.options.find((option) => {
			const optionValue = option && typeof option === 'object' ? option.value : option;
			return String(optionValue ?? '') === stringValue;
		});

		if (match === undefined) {
			return this.toDisplayValue(value);
		}

		return match && typeof match === 'object' ? String(match.label ?? match.value ?? '') : String(match);
	}
}
