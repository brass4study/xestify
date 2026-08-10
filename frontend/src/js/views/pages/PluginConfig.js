/**
 * PluginConfig — Admin UI for configuring active entity and extension plugins.
 */

import { Api } from '../../models/ApiClientModel.js';
import { FormLayout } from '../layout/FormLayout.js';
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
	#shellLayout;
	#title;
	#description;

	/**
	 * @param {HTMLElement|string} container
	 * @param {{slug?: string, api?: Object, shellLayout?: import('../layout/ShellLayout.js').ShellLayout|null, title?: string, description?: string, onBack?: Function}} options
	 */
	constructor(container, options) {
		this.#container = this.resolveContainer(container);
		this.#slug = String(options?.slug ?? '');
		this.#api = options?.api ?? new Api();
		this.#onBack = typeof options?.onBack === 'function' ? options.onBack : () => {};
		this.#shellLayout = options?.shellLayout ?? null;
		this.#title = typeof options?.title === 'string' ? options.title : 'Configuración de plugin';
		this.#description = typeof options?.description === 'string'
			? options.description
			: 'Ajusta las opciones específicas del plugin activo.';
	}

	async init() {
		if (this.#slug === '') {
			this.renderError('El identificador del plugin es obligatorio.');
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
			this.renderError('No hay datos de configuración disponibles.');
			return;
		}

		const isExtension = this.isExtensionPlugin();
		const targetEntity = String(this.#state.config.target_entity ?? '*');
		const entityOptions = Array.isArray(this.#state.config.entity_options)
			? this.#state.config.entity_options
			: [];
		const targetOptions = this.buildTargetOptions(entityOptions, targetEntity);
		const fieldsHelperText = this.fieldsHelperText(isExtension);
		const noticeMessage = this.#message === '' ? '' : this.#message;
		const noticeType = this.noticeType();

		const layout = FormLayout.create(this.#container, {
			shell: this.#shellLayout,
		})
			.setTitle(this.#title)
			.setDescription(this.#description)
			.build();
		layout.setNotification(null);
		const backButton = component.create('button', {
					label: 'Volver al listado',
					variant: 'secondary',
					dataRole: 'plugin-config-action',
					dataAction: 'back',
				});
		const saveButton = component.create('button', {
					label: 'Guardar',
					variant: 'primary',
					dataRole: 'plugin-config-action',
					dataAction: 'save',
				});
		layout.setHeaderToolbar(backButton);
		layout.addAction(saveButton);
		const panel = layout.getPanel();

		if (noticeMessage !== '') {
			const banner = component.create('alert', {
				type: noticeType,
				message: noticeMessage,
			}).setData('role', 'plugin-config-notice').setData('type', noticeType);
			layout.setNotification(banner);
		}

		if (isExtension) {
			component.create('typography', { as: 'h3', text: 'Relación de extensión', size: 'sm', weight: 'semibold', color: 'slate-900' })
				.setParent(panel);

			component.create('typography', { text: 'Selecciona la entidad de destino o Todas para aplicar la extensión globalmente.', size: 'sm', color: 'slate-600' })
				.setClassName('mt-1')
				.setParent(panel);

			component.create('inputSelect', {
				name: 'target-entity',
				value: targetEntity,
				options: targetOptions,
			})
				.setClassName('mt-2 w-full rounded-lg border-slate-300 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500 sm:max-w-sm')
				.setData('name', 'target-entity')
				.setParent(panel);
		}

		component.create('typography', { as: 'h3', text: 'Campos', size: 'sm', weight: 'semibold', color: 'slate-900' })
			.setClassName(isExtension ? 'mt-5' : 'mt-0')
			.setParent(panel);

		component.create('typography', { text: fieldsHelperText, size: 'sm', color: 'slate-600' })
			.setClassName('mt-1')
			.setParent(panel);

		const tableHost = component.create('div')
			.setClassName('list-table-host mt-3')
			.setData('role', 'list-table-host')
			.setParent(panel);

		const addFieldButton = component.create('button', {
			label: 'Añadir campo',
			variant: 'secondary',
			size: 'sm',
			dataRole: 'plugin-config-action',
			dataAction: 'add-field',
		});
		addFieldButton.classList.add('mt-3');
		panel.append(addFieldButton);

		this.renderFieldsTable(tableHost);
		this.bindTableEvents(panel);
		this.bindPageActions(panel, { addFieldButton, backButton, saveButton });
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
			wrapperClassName: 'overflow-x-auto',
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
			return 'Define los campos de la extensión y a qué entidad se aplica. Desactiva una fila para excluirla del esquema activo.';
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
		const badge = component.create('span')
			.setData('role', 'field-source')
			.setClassName('inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide');

		if (source === 'base') {
			badge.addClass('bg-red-100', 'text-red-700').setText('base');
		} else if (source === 'suggested') {
			badge.addClass('bg-sky-100', 'text-sky-700').setText('sugerido');
		} else {
			badge.addClass('bg-emerald-100', 'text-emerald-700').setText('adicional');
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
		return input.setData('name', 'active');
	}

	renderKeyInput(field) {
		const { keyReadonly } = this.fieldMeta(field);
		const input = component.create('inputText')
			.setClassName('w-full rounded-md border-slate-300 text-sm')
			.setData('name', 'key')
			.setValue(String(field.key ?? ''));
		input.readOnly = keyReadonly;
		return input;
	}

	renderTypeSelect(field) {
		const { editable } = this.fieldMeta(field);
		const select = component.create('inputSelect', {
			name: 'type',
			value: field.type || 'string',
			options: this.getFieldTypeOptions(),
		})
			.setClassName('w-full rounded-md border-slate-300 text-sm')
			.setData('name', 'type');
		select.disabled = !editable;
		return select;
	}

	renderLabelInput(field) {
		const { editable } = this.fieldMeta(field);
		const input = component.create('inputText')
			.setClassName('w-full rounded-md border-slate-300 text-sm')
			.setData('name', 'label')
			.setValue(String(field.label ?? ''));
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
		return input.setData('name', 'required');
	}

	renderSummaryViewInput(field) {
		const { editable } = this.fieldMeta(field);
		const input = component.create('inputSwitch', {
			checked: field.summaryView !== false,
			disabled: !editable,
			size: 'small',
		});
		return input.setData('name', 'summaryView');
	}

	renderActions(field) {
		const { immutableField } = this.fieldMeta(field);
		const actions = component.create('div').setClassName('flex flex-wrap items-center gap-2');
		const rowIndex = Number(field.__rowIndex ?? 0);
		const lastIndex = (this.#state?.config?.fields?.length ?? 1) - 1;

		this.buildRowActionButton('Subir', 'fa-arrow-up', 'slate', 'move-up', rowIndex, rowIndex === 0).setParent(actions);
		this.buildRowActionButton('Bajar', 'fa-arrow-down', 'slate', 'move-down', rowIndex, rowIndex === lastIndex).setParent(actions);

		if (!immutableField) {
			const removeButton = this.buildRowActionButton('Eliminar', 'fa-trash', 'red', 'remove-row', rowIndex, false);
			removeButton.addClass('ml-2').setParent(actions);
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
		return button.setData('rowIndex', rowIndex);
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

			this.#message = 'Configuración guardada correctamente.';
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
		this.#container.replaceChildren();
		const layout = FormLayout.create(this.#container, { shell: this.#shellLayout })
			.setTitle(this.#title)
			.setDescription(this.#description)
			.build();
		const banner = component.create('alert', {
			type: 'error',
			message,
		}).setData('role', 'plugin-config-error').setData('type', 'error');
		layout.setNotification(banner);
	}

	getFieldTypeOptions() {
		return ['string', 'text', 'number', 'boolean', 'date', 'timestamp', 'email', 'select', 'uuid']
			.map((type) => ({ value: type, label: type }));
	}

	bindPageActions(wrapper, actions) {
		actions.addFieldButton.addEventListener('click', () => {
			this.#state.config.fields.push(this.createEmptyField());
			this.clearNotice();
			this.render();
		});

		actions.backButton.addEventListener('click', () => {
			this.#onBack();
		});

		actions.saveButton.addEventListener('click', async () => {
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
