/**
 * PluginConfig — Admin UI for configuring active entity and extension plugins.
 */

import { Api } from '../../models/ApiClientModel.js';
import { DynamicTable } from '../modules/DynamicTable.js';
import { component } from '../modules/ComponentFactory.js';

export class PluginConfig {
	#container;
	#api;
	#slug;
	#onBack;
	#state = null;
	#message = '';
	#messageType = '';

	constructor(container, options) {
		this.#container = this.resolveContainer(container);
		this.#slug = String(options?.slug ?? '');
		this.#api = options?.api ?? new Api();
		this.#onBack = typeof options?.onBack === 'function' ? options.onBack : () => {};
	}

	async init() {
		if (this.#slug === '') {
			this.renderError('Plugin slug is required.');
			return;
		}

		try {
			const { data } = await this.#api.get(`/plugins/${this.#slug}/config`);
			const pluginType = String(data?.plugin?.plugin_type ?? '');
			const entityOptions = pluginType === 'extension'
				? await this.loadActiveEntityOptions()
				: [];
			this.#state = this.buildStateFromResponse(data, entityOptions);
			this.render();
		} catch (error) {
			this.renderError(`No se pudo cargar la configuracion: ${error.message}`);
		}
	}

	render() {
		if (this.#state === null) {
			this.renderError('No configuration data available.');
			return;
		}

		const plugin = this.#state.plugin;
		const isExtension = this.isExtensionPlugin();
		const targetEntity = String(this.#state.config.target_entity ?? '*');
		const entityOptions = Array.isArray(this.#state.config.entity_options)
			? this.#state.config.entity_options
			: [];
		const targetOptions = this.buildTargetOptions(entityOptions, targetEntity);
		const fieldsHelperText = this.fieldsHelperText(isExtension);
		const noticeMessage = this.#message === '' ? '' : this.#message;
		const noticeType = this.noticeType();

		const page = component.create('page', { dataRole: 'plugin-config-page' });
		const header = component.create('pageHeader', {
			title: `Configurar plugin: ${plugin.name || plugin.slug || this.#slug}`,
			subtitle: `slug: ${plugin.slug || this.#slug} | version: ${plugin.version || '-'} | schema v${String(plugin.schema_version ?? '-')}`,
		});
		page.appendChild(header);

		if (noticeMessage !== '') {
			const notice = component.create('alert', { type: noticeType, message: noticeMessage });
			notice.dataset.role = 'plugin-config-notice';
			page.appendChild(notice);
		}

		const shell = component.create('section', { dataRole: 'plugin-config-shell' });
		shell.className = 'rounded-xl border border-slate-200 bg-slate-50 p-3';

		if (isExtension) {
			const relationshipTitle = component.create('typography', { as: 'h3', text: 'Relación de extension', size: 'sm', weight: 'semibold', color: 'slate-900' });
			shell.appendChild(relationshipTitle);

			const relationshipHelp = component.create('typography', { text: 'Selecciona la entidad destino o Todos para aplicar la extension globalmente.', size: 'sm', color: 'slate-600' });
			relationshipHelp.className = 'mt-1';
			shell.appendChild(relationshipHelp);

			const targetSelect = component.create('inputSelect', {
				name: 'target-entity',
				value: targetEntity,
				options: targetOptions,
			});
			targetSelect.className = 'mt-2 w-full rounded-lg border-slate-300 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500 sm:max-w-sm';
			targetSelect.dataset.name = 'target-entity';
			shell.appendChild(targetSelect);
		}

		const fieldsTitle = component.create('typography', { as: 'h3', text: 'Campos', size: 'sm', weight: 'semibold', color: 'slate-900' });
		fieldsTitle.className = isExtension ? 'mt-5' : 'mt-0';
		shell.appendChild(fieldsTitle);

		const fieldsHelp = component.create('typography', { text: fieldsHelperText, size: 'sm', color: 'slate-600' });
		fieldsHelp.className = 'mt-1';
		shell.appendChild(fieldsHelp);

		const tableHost = component.create('div');
		tableHost.className = 'mt-3';
		tableHost.dataset.role = 'fields-table-host';
		shell.appendChild(tableHost);

		const addFieldButton = component.create('button', {
			label: 'Añadir campo',
			dataRole: 'plugin-config-action',
			dataAction: 'add-field',
		});
		addFieldButton.className = 'mt-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100';
		shell.appendChild(addFieldButton);

		page.appendChild(shell);

		const footer = component.create('footer');
		footer.className = 'mt-4 flex flex-wrap gap-2';
		footer.appendChild(component.create('button', {
			label: 'Volver',
			dataRole: 'plugin-config-action',
			dataAction: 'back',
		}));
		footer.appendChild(component.create('button', {
			label: 'Guardar',
			variant: 'primary',
			dataRole: 'plugin-config-action',
			dataAction: 'save',
		}));
		page.appendChild(footer);

		this.renderFieldsTable(tableHost);
		this.bindTableEvents(page);
		this.bindPageActions(page);

		this.#container.replaceChildren();
		this.#container.appendChild(page);
	}

	renderFieldsTable(container) {
		const rows = this.#state?.config?.fields ?? [];
		const records = rows.map((field, index) => ({
			...field,
			__rowIndex: index,
		}));

		const headerClassName = 'border-b border-slate-200 bg-slate-50 px-2 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600';
		const baseCellClassName = 'border-b border-slate-100 px-2 py-2';
		const centeredCellClassName = 'border-b border-slate-100 px-2 py-2 text-center';
		const actionsCellClassName = 'border-b border-slate-100 px-2 py-2 whitespace-nowrap';
		const extraColumns = this.buildFieldTableColumns(headerClassName, baseCellClassName, centeredCellClassName, actionsCellClassName);

		const table = new DynamicTable(records, { fields: [] }, container, {
			showPagination: false,
			wrapperClassName: 'overflow-x-auto rounded-xl border border-slate-200 bg-white',
			tableClassName: 'min-w-[920px] w-full border-separate border-spacing-0 text-left',
			tableDataRole: 'plugin-config-table',
			extraColumns,
			rowDecorator: (row, field) => {
				row.dataset.rowIndex = String(field.__rowIndex ?? 0);
				row.className = (field.__rowIndex ?? 0) % 2 === 0 ? 'bg-white' : 'bg-slate-50/40';
			},
		});

		table.render();
	}

	fieldMeta(field) {
		const locked = field.locked === true;
		const source = String(field.source ?? 'additional');
		const isBase = source === 'base';
		const immutableField = locked || isBase;
		const keyReadonly = immutableField || source === 'suggested';
		const editable = !immutableField;
		return { source, immutableField, keyReadonly, editable };
	}

	fieldsHelperText(isExtension) {
		if (isExtension) {
			return 'Define los campos de la extension y a que entidad se aplica. Desactiva una fila para excluirla del schema activo.';
		}

		return 'Reordena, activa/desactiva y ajusta los campos sugeridos. Los campos base obligatorios son visibles pero bloqueados.';
	}

	buildFieldTableColumns(headerClassName, baseCellClassName, centeredCellClassName, actionsCellClassName) {
		return [
			{
				label: 'Activo',
				headerClassName,
				cellClassName: centeredCellClassName,
				renderCell: (field) => this.renderActiveInput(field),
			},
			{
				label: 'Clase',
				headerClassName,
				cellClassName: baseCellClassName,
				renderCell: (field) => this.renderSourceBadge(field),
			},
			{
				label: 'Clave',
				headerClassName,
				cellClassName: baseCellClassName,
				renderCell: (field) => this.renderKeyInput(field),
			},
			{
				label: 'Tipo',
				headerClassName,
				cellClassName: baseCellClassName,
				renderCell: (field) => this.renderTypeSelect(field),
			},
			{
				label: 'Etiqueta',
				headerClassName,
				cellClassName: baseCellClassName,
				renderCell: (field) => this.renderLabelInput(field),
			},
			{
				label: 'Requerido',
				headerClassName,
				cellClassName: centeredCellClassName,
				renderCell: (field) => this.renderRequiredInput(field),
			},
			{
				label: 'Cabecera',
				headerClassName,
				cellClassName: centeredCellClassName,
				renderCell: (field) => this.renderSummaryViewInput(field),
			},
			{
				label: 'Acciones',
				headerClassName,
				cellClassName: actionsCellClassName,
				renderCell: (field) => this.renderActions(field),
			},
		];
	}

	renderSourceBadge(field) {
		const { source } = this.fieldMeta(field);
		const badge = component.create('span');
		badge.dataset.role = 'field-source';
		badge.className = 'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide';

		if (source === 'base') {
			badge.classList.add('bg-red-100', 'text-red-700');
			badge.textContent = 'base';
		} else if (source === 'suggested') {
			badge.classList.add('bg-sky-100', 'text-sky-700');
			badge.textContent = 'sugerido';
		} else {
			badge.classList.add('bg-emerald-100', 'text-emerald-700');
			badge.textContent = 'adicional';
		}

		return badge;
	}

	renderActiveInput(field) {
		const { immutableField } = this.fieldMeta(field);
		const input = component.create('inputSwitch', {
			checked: field.active === true,
			disabled: immutableField,
			size: 'small',
		});
		input.dataset.name = 'active';
		return input;
	}

	renderKeyInput(field) {
		const { keyReadonly } = this.fieldMeta(field);
		const input = component.create('inputText');
		input.className = 'w-full rounded-md border-slate-300 text-sm';
		input.dataset.name = 'key';
		input.value = String(field.key ?? '');
		input.readOnly = keyReadonly;
		return input;
	}

	renderTypeSelect(field) {
		const { editable } = this.fieldMeta(field);
		const select = component.create('inputSelect', {
			name: 'type',
			value: field.type || 'string',
			options: this.getFieldTypeOptions(),
		});
		select.className = 'w-full rounded-md border-slate-300 text-sm';
		select.dataset.name = 'type';
		select.disabled = !editable;
		return select;
	}

	renderLabelInput(field) {
		const { editable } = this.fieldMeta(field);
		const input = component.create('inputText');
		input.className = 'w-full rounded-md border-slate-300 text-sm';
		input.dataset.name = 'label';
		input.value = String(field.label ?? '');
		input.readOnly = !editable;
		return input;
	}

	renderRequiredInput(field) {
		const { editable } = this.fieldMeta(field);
		const input = component.create('inputSwitch', {
			checked: field.required === true,
			disabled: !editable,
			size: 'small',
		});
		input.dataset.name = 'required';
		return input;
	}

	renderSummaryViewInput(field) {
		const { editable } = this.fieldMeta(field);
		const input = component.create('inputSwitch', {
			checked: field.summaryView !== false,
			disabled: !editable,
			size: 'small',
		});
		input.dataset.name = 'summaryView';
		return input;
	}

	renderActions(field) {
		const { immutableField } = this.fieldMeta(field);
		const actions = component.create('div');
		actions.className = 'flex flex-wrap items-center gap-2';
		const rowIndex = Number(field.__rowIndex ?? 0);
		const lastIndex = (this.#state?.config?.fields?.length ?? 1) - 1;

		actions.appendChild(this.buildRowActionButton('Subir', 'fa-arrow-up', 'slate', 'move-up', rowIndex, rowIndex === 0));
		actions.appendChild(this.buildRowActionButton('Bajar', 'fa-arrow-down', 'slate', 'move-down', rowIndex, rowIndex === lastIndex));

		if (!immutableField) {
			const removeButton = this.buildRowActionButton('Eliminar', 'fa-trash', 'red', 'remove-row', rowIndex, false);
			removeButton.classList.add('ml-2');
			actions.appendChild(removeButton);
		}

		return actions;
	}

	buildRowActionButton(label, icon, tone, action, rowIndex, disabled) {
		const button = DynamicTable.buildActionButton({
			label,
			icon,
			tone,
			dataAction: action,
			disabled,
			onClick: () => {},
		});
		button.dataset.rowIndex = String(rowIndex);
		return button;
	}

	bindTableEvents(wrapper) {
		wrapper.querySelectorAll('[data-action="move-up"]').forEach((button) => {
			button.addEventListener('click', () => {
				const index = Number(button.dataset.rowIndex);
				this.moveRow(index, -1);
			});
		});

		wrapper.querySelectorAll('[data-action="move-down"]').forEach((button) => {
			button.addEventListener('click', () => {
				const index = Number(button.dataset.rowIndex);
				this.moveRow(index, 1);
			});
		});

		wrapper.querySelectorAll('[data-action="remove-row"]').forEach((button) => {
			button.addEventListener('click', () => {
				const index = Number(button.dataset.rowIndex);
				this.#state.config.fields.splice(index, 1);
				this.clearNotice();
				this.render();
			});
		});
	}

	moveRow(index, delta) {
		const rows = this.#state.config.fields;
		const target = index + delta;
		if (target < 0 || target >= rows.length) {
			return;
		}

		const tmp = rows[index];
		rows[index] = rows[target];
		rows[target] = tmp;
		this.clearNotice();
		this.render();
	}

	async saveFromDom(wrapper) {
		try {
			const payload = this.buildPayloadFromDom(wrapper);
			const { data } = await this.#api.put(`/plugins/${this.#slug}/config`, payload);

			this.applySavedState(data);

			this.#message = 'Configuracion guardada correctamente.';
			this.#messageType = 'success';
			this.render();
		} catch (error) {
			this.#message = `Error al guardar: ${error.message}`;
			this.#messageType = 'error';
			this.render();
		}
	}

	clearNotice() {
		this.#message = '';
		this.#messageType = '';
	}

	noticeType() {
		if (this.#messageType === 'error') {
			return 'error';
		}

		if (this.#messageType === 'success') {
			return 'success';
		}

		return 'info';
	}

	renderError(message) {
		const banner = component.create('div');
		banner.className = 'rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700';
		banner.textContent = message;
		this.#container.replaceChildren();
		this.#container.appendChild(banner);
	}

	getFieldTypeOptions() {
		return ['string', 'text', 'number', 'boolean', 'date', 'timestamp', 'email', 'select', 'uuid']
			.map((type) => ({ value: type, label: type }));
	}

	bindPageActions(wrapper) {
		wrapper.querySelector('[data-action="add-field"]').addEventListener('click', () => {
			this.#state.config.fields.push(this.createEmptyField());
			this.clearNotice();
			this.render();
		});

		wrapper.querySelector('[data-action="back"]').addEventListener('click', () => {
			this.#onBack();
		});

		wrapper.querySelector('[data-action="save"]').addEventListener('click', async () => {
			await this.saveFromDom(wrapper);
		});
	}

	createEmptyField() {
		return {
			active: true,
			key: '',
			type: 'string',
			label: '',
			required: false,
			summaryView: true,
			locked: false,
			source: 'additional',
		};
	}

	buildTargetOptions(entityOptions, targetEntity) {
		const hasCurrentTarget = entityOptions.some((entity) => entity.slug === targetEntity);
		const allTargetOptions = hasCurrentTarget || targetEntity === '*'
			? entityOptions
			: [...entityOptions, { slug: targetEntity, label: `${targetEntity} (no activa)` }];

		return [
			{ value: '*', label: 'Todos' },
			...allTargetOptions.map((entity) => ({
				value: entity.slug,
				label: entity.label,
			})),
		];
	}

	buildStateFromResponse(data, entityOptions = []) {
		return {
			plugin: data?.plugin ?? {},
			config: {
				fields: Array.isArray(data?.config?.fields)
					? data.config.fields.map((field) => ({ ...field }))
					: [],
				target_entity: typeof data?.config?.target_entity === 'string'
					? data.config.target_entity
					: '*',
				entity_options: entityOptions,
			},
		};
	}

	buildPayloadFromDom(wrapper) {
		const payload = { fields: this.collectRowsFromDom(wrapper) };
		if (this.isExtensionPlugin()) {
			const targetEntityInput = wrapper.querySelector('[data-name="target-entity"]');
			payload.target_entity = targetEntityInput ? targetEntityInput.value.trim() : '';
		}

		return payload;
	}

	collectRowsFromDom(wrapper) {
		const rows = [];
		wrapper.querySelectorAll('[data-role="plugin-config-table"] tbody tr[data-row-index]').forEach((rowEl) => {
			rows.push(this.readRowFromDom(rowEl));
		});

		return rows;
	}

	readRowFromDom(rowEl) {
		const index = Number(rowEl.dataset.rowIndex);
		const original = this.#state.config.fields[index] ?? {};

		const keyInput = rowEl.querySelector('[data-name="key"]');
		const labelInput = rowEl.querySelector('[data-name="label"]');
		const typeSelect = rowEl.querySelector('[data-name="type"]');
		const activeCheckbox = rowEl.querySelector('[data-name="active"]');
		const requiredCheckbox = rowEl.querySelector('[data-name="required"]');
		const summaryViewCheckbox = rowEl.querySelector('[data-name="summaryView"]');

		return {
			active: activeCheckbox ? !!activeCheckbox.checked : false,
			key: keyInput ? keyInput.value.trim() : '',
			type: typeSelect ? typeSelect.value : 'string',
			label: labelInput ? labelInput.value.trim() : '',
			required: requiredCheckbox ? !!requiredCheckbox.checked : false,
			summaryView: summaryViewCheckbox ? !!summaryViewCheckbox.checked : true,
			locked: original.locked === true,
			source: String(original.source ?? 'additional'),
		};
	}

	applySavedState(data) {
		const entityOptions = Array.isArray(this.#state?.config?.entity_options)
			? this.#state.config.entity_options
			: [];
		const nextState = this.buildStateFromResponse(data, entityOptions);
		nextState.plugin = data?.plugin ?? this.#state.plugin;
		this.#state = nextState;
	}

	isExtensionPlugin() {
		return String(this.#state?.plugin?.plugin_type ?? '') === 'extension';
	}

	async loadActiveEntityOptions() {
		try {
			const { data } = await this.#api.get('/entities');
			if (!Array.isArray(data)) {
				return [];
			}

			return data
				.filter((entity) => typeof entity?.slug === 'string' && entity.slug.trim() !== '')
				.map((entity) => ({
					slug: String(entity.slug),
					label: String(entity.label ?? entity.slug),
				}));
		} catch {
			return [];
		}
	}

	resolveContainer(container) {
		if (container instanceof HTMLElement) {
			return container;
		}

		const found = document.querySelector(container);
		if (found instanceof HTMLElement) {
			return found;
		}

		throw new TypeError(`PluginConfig container "${String(container)}" not found`);
	}
}
