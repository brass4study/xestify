/**
 * PluginConfig — Admin UI for configuring active entity and extension plugins.
 */

import { Api } from '../../models/ApiClientModel.js';
import { FormLayout } from '../layout/FormLayout.js';
import { DynamicTable } from '../modules/DynamicTable.js';
import { component } from '../modules/ComponentFactory.js';
import { Modal } from '../modules/Modal.js';
import { UiResilienceService } from '../../services/UiResilienceService.js';

export class PluginConfig {
	#container;
	#api;
	#slug;
	#mode;
	#onBack;
	#onIdentityChanged;
	#onCreated;
	#onDeleted;
	#state = null;
	#availablePlugins = [];
	#message = '';
	#messageType = '';
	#shellLayout;
	#title;
	#description;
	#formElement = null;

	/**
	 * @param {HTMLElement|string} container
	 * @param {{slug?: string, mode?: 'edit'|'create', api?: Object, shellLayout?: import('../layout/ShellLayout.js').ShellLayout|null, title?: string, description?: string, onBack?: Function, onIdentityChanged?: Function, onCreated?: Function, onDeleted?: Function}} options
	 */
	constructor(container, options = {}) {
		this.#container = this.resolveContainer(container);
		this.#slug = String(options.slug ?? '');
		this.#mode = options.mode === 'create' ? 'create' : 'edit';
		this.#api = options.api ?? new Api();
		this.#onBack = this.resolveCallbackOption(options.onBack);
		this.#onIdentityChanged = this.resolveCallbackOption(options.onIdentityChanged);
		this.#onCreated = this.resolveCallbackOption(options.onCreated);
		this.#onDeleted = this.resolveCallbackOption(options.onDeleted);
		this.#shellLayout = options.shellLayout ?? null;
		this.#title = this.resolveStringOption(options.title, 'Configuración de plugin');
		this.#description = this.resolveStringOption(
			options.description,
			'Ajusta las opciones específicas del plugin activo.'
		);
	}

	resolveCallbackOption(candidate) {
		return typeof candidate === 'function' ? candidate : () => {};
	}

	resolveStringOption(candidate, fallback) {
		return typeof candidate === 'string' ? candidate : fallback;
	}

	async init() {
		if (this.#mode === 'create') {
			try {
				const [{ data }, entityOptions] = await Promise.all([
					this.#api.get('/plugins/available'),
					this.loadActiveEntityOptions(),
				]);
				this.#availablePlugins = Array.isArray(data?.available) ? data.available : [];
				this.#state = this.buildCreateState();
				this.#state.config.entity_options = entityOptions;
				this.render();
			} catch (error) {
				this.renderError(`No se pudo cargar la lista de plugins disponibles: ${error.message}`);
			}
			return;
		}

		if (this.#slug === '') {
			this.renderError('El identificador del plugin es obligatorio.');
			return;
		}

		try {
			const { data } = await this.#api.get(`/plugins/${this.#slug}/config`);
			const entityOptions = await this.loadActiveEntityOptions();
			this.#state = this.buildStateFromResponse(data, entityOptions);
			this.render();
		} catch (error) {
			this.renderError(`No se pudo cargar la configuracion: ${error.message}`);
		}
	}

	buildCreateState() {
		return {
			plugin: {
				plugin_name: '',
				plugin_type: '',
				slug: '',
				name: '',
				description: '',
			},
			config: { fields: [], relations: [], target_entity: '*', entity_options: [] },
		};
	}

	/**
	 * Applies the chosen "Tipo" (plugin_name) to state — identity defaults and
	 * the fields/target_entity preview all come from the same /plugins/available
	 * entry, and a full re-render is needed (not just patching two inputs)
	 * since choosing a type also reveals the identity fields and the
	 * "Relación de extensión"/"Campos" sections.
	 */
	applyTypeSelection(pluginName) {
		const match = this.#availablePlugins.find((item) => item.plugin_name === pluginName) ?? null;
		this.#state.plugin.plugin_name = match?.plugin_name ?? '';
		this.#state.plugin.plugin_type = match?.plugin_type ?? '';
		this.#state.plugin.name = match?.label ?? '';
		this.#state.plugin.slug = match?.suggested_slug ?? '';
		this.#state.plugin.description = match?.description ?? '';
		this.#state.config.fields = Array.isArray(match?.config?.fields)
			? match.config.fields.map((field) => ({ ...field }))
			: [];
		this.#state.config.relations = Array.isArray(match?.config?.relations)
			? match.config.relations.map((relation) => ({ ...relation }))
			: [];
		this.#state.config.target_entity = typeof match?.config?.target_entity === 'string'
			? match.config.target_entity
			: '*';
		this.clearNotice();
		this.render();
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

		let deleteButton = null;
		if (this.#mode === 'edit') {
			deleteButton = component.create('button', {
				label: 'Borrar',
				variant: 'danger',
				attributes: { type: 'button', 'data-plugin-config-delete': '' },
			});
			layout.addAction(deleteButton);
		}

		const panel = layout.getPanel();
		panel.addClass('flex', 'flex-col', 'gap-4');
		this.#formElement = panel;

		if (noticeMessage !== '') {
			const banner = component.create('alert', {
				type: noticeType,
				message: noticeMessage,
			}).setData('role', 'plugin-config-notice').setData('type', noticeType);
			layout.setNotification(banner);
		}

		const isCreate = this.#mode === 'create';
		const hasTypeChosen = !isCreate || String(this.#state.plugin.plugin_name ?? '') !== '';
		const identityPanel = this.createSectionPanel(panel);
		component.create('typography', { as: 'h3', text: 'Identidad', size: 'sm', weight: 'semibold', color: 'slate-900' })
			.setParent(identityPanel);

		component.create('typography', {
			text: this.identityHelperText(isCreate, hasTypeChosen),
			size: 'sm',
			color: 'slate-600',
		})
			.setClassName('mt-1')
			.setParent(identityPanel);

		let typeSelect;

		if (isCreate) {
			const typeField = component.create('div').setClassName('mt-3').setParent(identityPanel);
			component.create('typography', { text: 'Tipo', size: 'sm', weight: 'medium', color: 'slate-700' }).setParent(typeField);
			typeSelect = component.create('inputSelect', {
				name: 'plugin-name',
				placeholder: 'Selecciona un tipo…',
				value: String(this.#state.plugin.plugin_name ?? ''),
				options: this.#availablePlugins.map((item) => ({ value: item.plugin_name, label: item.label })),
			})
				.addClass('mt-1', 'sm:max-w-sm')
				.setData('name', 'plugin-name')
				.setParent(typeField);

			typeSelect.addEventListener('change', () => {
				this.applyTypeSelection(typeSelect.value);
			});
		}

		if (hasTypeChosen) {
			const identityGrid = component.create('div')
				.setClassName('mt-3 grid gap-4 sm:grid-cols-2')
				.setParent(identityPanel);

			const slugField = component.create('div').setParent(identityGrid);
			component.create('typography', { text: 'Slug', size: 'sm', weight: 'medium', color: 'slate-700' }).setParent(slugField);
			component.create('inputText')
				.addClass('mt-1')
				.setData('name', 'identity-slug')
				.setValue(String(this.#state.plugin.slug ?? ''))
				.setParent(slugField);

			const nameField = component.create('div').setParent(identityGrid);
			component.create('typography', { text: 'Nombre', size: 'sm', weight: 'medium', color: 'slate-700' }).setParent(nameField);
			component.create('inputText')
				.addClass('mt-1')
				.setData('name', 'identity-name')
				.setValue(String(this.#state.plugin.name ?? ''))
				.setParent(nameField);

			const descriptionField = component.create('div').setClassName('sm:col-span-2').setParent(identityGrid);
			component.create('typography', { text: 'Descripción', size: 'sm', weight: 'medium', color: 'slate-700' }).setParent(descriptionField);
			component.create('inputText')
				.addClass('mt-1')
				.setData('name', 'identity-description')
				.setValue(String(this.#state.plugin.description ?? ''))
				.setParent(descriptionField);
		}

		let addFieldButton;
		let addRelationButton;

		if (hasTypeChosen) {
			if (isExtension) {
				const relationPanel = this.createSectionPanel(panel);
				component.create('typography', { as: 'h3', text: 'Relación de extensión', size: 'sm', weight: 'semibold', color: 'slate-900' })
					.setParent(relationPanel);

				component.create('typography', { text: 'Selecciona la entidad de destino o Todas para aplicar la extensión globalmente.', size: 'sm', color: 'slate-600' })
					.setClassName('mt-1')
					.setParent(relationPanel);

				component.create('inputSelect', {
					name: 'target-entity',
					value: targetEntity,
					options: targetOptions,
				})
					.addClass('mt-2', 'sm:max-w-sm')
					.setData('name', 'target-entity')
					.setParent(relationPanel);
			}

			const fieldsPanel = this.createSectionPanel(panel);
			component.create('typography', { as: 'h3', text: 'Campos', size: 'sm', weight: 'semibold', color: 'slate-900' })
				.setParent(fieldsPanel);

			component.create('typography', { text: fieldsHelperText, size: 'sm', color: 'slate-600' })
				.setClassName('mt-1')
				.setParent(fieldsPanel);

			const tableHost = component.create('div')
				.setClassName('list-table-host mt-3')
				.setData('role', 'list-table-host')
				.setParent(fieldsPanel);

			addFieldButton = component.create('button', {
				label: 'Añadir campo',
				variant: 'secondary',
				size: 'sm',
				dataRole: 'plugin-config-action',
				dataAction: 'add-field',
			});
			addFieldButton.classList.add('mt-3');
			fieldsPanel.append(addFieldButton);

			this.renderFieldsTable(tableHost);

			if (!isExtension) {
				const relationsPanel = this.createSectionPanel(panel);
				component.create('typography', { as: 'h3', text: 'Relaciones', size: 'sm', weight: 'semibold', color: 'slate-900' })
					.setParent(relationsPanel);

				component.create('typography', {
					text: 'Declara a qué otras entidades pertenece un registro de este tipo (por ejemplo, un pedido pertenece a una persona).',
					size: 'sm',
					color: 'slate-600',
				})
					.setClassName('mt-1')
					.setParent(relationsPanel);

				const relationsTableHost = component.create('div')
					.setClassName('list-table-host mt-3')
					.setData('role', 'list-table-host')
					.setParent(relationsPanel);

				addRelationButton = component.create('button', {
					label: 'Añadir relación',
					variant: 'secondary',
					size: 'sm',
					dataRole: 'plugin-config-action',
					dataAction: 'add-relation',
				});
				addRelationButton.classList.add('mt-3');
				relationsPanel.append(addRelationButton);

				this.renderRelationsTable(relationsTableHost);
			}
		}

		this.bindPageActions(panel, { addFieldButton, addRelationButton, backButton, saveButton, deleteButton });
	}

	identityHelperText(isCreate, hasTypeChosen) {
		if (!isCreate) {
			return 'El slug controla la URL de navegación y debe ser único. El nombre y la descripción son informativos.';
		}

		if (!hasTypeChosen) {
			return 'Elige un tipo de plugin para continuar.';
		}

		return 'El slug, nombre y descripción sugeridos vienen del manifest — ajústalos si lo necesitas antes de crear la instancia.';
	}

	createSectionPanel(container) {
		return component.create('div')
			.setClassName('rounded-md border border-slate-200 bg-white p-4')
			.setParent(container);
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

	renderRelationsTable(container) {
		const rows = this.#state?.config?.relations ?? [];
		const records = rows.map((relation, index) => ({
			...relation,
			__rowIndex: index,
		}));

		const headerClassName = 'border-b border-slate-200 bg-slate-50 px-2 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600';
		const baseCellClassName = 'border-b border-slate-100 px-2 py-2';
		const centeredCellClassName = 'border-b border-slate-100 px-2 py-2 text-center';
		const actionsCellClassName = 'border-b border-slate-100 px-2 py-2 whitespace-nowrap';
		const extraColumns = this.buildRelationTableColumns(headerClassName, baseCellClassName, centeredCellClassName, actionsCellClassName);

		const table = new DynamicTable(records, { fields: [] }, container, {
			showPagination: false,
			wrapperClassName: 'overflow-x-auto',
			tableClassName: 'min-w-[760px] w-full border-separate border-spacing-0 text-left',
			tableDataRole: 'plugin-config-relations-table',
			extraColumns,
			rowDecorator: (row, relation) => {
				row.dataset.rowIndex = String(relation.__rowIndex ?? 0);
				row.className = (relation.__rowIndex ?? 0) % 2 === 0 ? 'bg-white' : 'bg-slate-50/40';
			},
		});
		table.render();
	}

	buildRelationTableColumns(headerClassName, baseCellClassName, centeredCellClassName, actionsCellClassName) {
		return [
			{
				label: 'Clave',
				headerClassName,
				cellClassName: baseCellClassName,
				renderCell: (relation) => this.renderRelationKeyInput(relation),
			},
			{
				label: 'Entidad destino',
				headerClassName,
				cellClassName: baseCellClassName,
				renderCell: (relation) => this.renderRelationTargetEntitySelect(relation),
			},
			{
				label: 'Campo destino',
				headerClassName,
				cellClassName: baseCellClassName,
				renderCell: (relation) => this.renderRelationTargetFieldSelect(relation),
			},
			{
				label: 'Etiqueta',
				headerClassName,
				cellClassName: baseCellClassName,
				renderCell: (relation) => this.renderRelationLabelInput(relation),
			},
			{
				label: 'Requerido',
				headerClassName,
				cellClassName: centeredCellClassName,
				renderCell: (relation) => this.renderRelationRequiredInput(relation),
			},
			{
				label: 'Acciones',
				headerClassName,
				cellClassName: actionsCellClassName,
				renderCell: (relation) => this.renderRelationActions(relation),
			},
		];
	}

	renderRelationKeyInput(relation) {
		return component.create('inputText')
			.setClassName(this.tableControlClassName())
			.setData('name', 'relation-key')
			.setValue(String(relation.key ?? ''));
	}

	entityIdentityOptions(targetEntitySlug) {
		const entityOptions = Array.isArray(this.#state?.config?.entity_options) ? this.#state.config.entity_options : [];
		const entity = entityOptions.find((item) => item.slug === targetEntitySlug);
		const identities = entity?.identities && typeof entity.identities === 'object' ? entity.identities : {};
		return Object.keys(identities).map((key) => ({ value: key, label: key }));
	}

	renderRelationTargetEntitySelect(relation) {
		const entityOptions = Array.isArray(this.#state?.config?.entity_options) ? this.#state.config.entity_options : [];
		const rowIndex = Number(relation.__rowIndex ?? 0);
		const select = component.create('inputSelect', {
			name: 'relation-target-entity',
			placeholder: 'Selecciona una entidad…',
			value: String(relation.target_entity ?? ''),
			options: entityOptions.map((entity) => ({ value: entity.slug, label: entity.label })),
		})
			.setClassName(this.tableControlClassName())
			.setData('name', 'relation-target-entity');

		select.addEventListener('change', () => {
			this.syncStateFromDom();
			this.#state.config.relations[rowIndex].target_entity = select.value;
			this.#state.config.relations[rowIndex].target_field = '';
			this.clearNotice();
			this.render();
		});

		return select;
	}

	renderRelationTargetFieldSelect(relation) {
		return component.create('inputSelect', {
			name: 'relation-target-field',
			placeholder: 'Selecciona un campo…',
			value: String(relation.target_field ?? ''),
			options: this.entityIdentityOptions(String(relation.target_entity ?? '')),
		})
			.setClassName(this.tableControlClassName())
			.setData('name', 'relation-target-field');
	}

	renderRelationLabelInput(relation) {
		return component.create('inputText')
			.setClassName(this.tableControlClassName())
			.setData('name', 'relation-label')
			.setValue(String(relation.label ?? ''));
	}

	renderRelationRequiredInput(relation) {
		return component.create('inputSwitch', {
			checked: relation.required === true,
			size: 'small',
		}).setData('name', 'relation-required');
	}

	renderRelationActions(relation) {
		const actions = component.create('div').setClassName('flex flex-wrap items-center gap-2');
		const rowIndex = Number(relation.__rowIndex ?? 0);
		const lastIndex = (this.#state?.config?.relations?.length ?? 1) - 1;

		this.buildRowActionButton('Subir', 'fa-arrow-up', 'slate', 'move-relation-up', rowIndex, rowIndex === 0, () => this.moveRelationRow(rowIndex, -1)).setParent(actions);
		this.buildRowActionButton('Bajar', 'fa-arrow-down', 'slate', 'move-relation-down', rowIndex, rowIndex === lastIndex, () => this.moveRelationRow(rowIndex, 1)).setParent(actions);

		const removeButton = this.buildRowActionButton('Eliminar', 'fa-trash', 'red', 'remove-relation-row', rowIndex, false, () => {
			this.syncStateFromDom();
			this.#state.config.relations.splice(rowIndex, 1);
			this.clearNotice();
			this.render();
		});
		removeButton.addClass('ml-2').setParent(actions);

		return actions;
	}

	moveRelationRow(index, delta) {
		this.syncStateFromDom();
		const rows = this.#state.config.relations;
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

	createEmptyRelation() {
		return { key: '', target_entity: '', target_field: '', label: '', required: false };
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

	tableControlClassName() {
		return 'w-full rounded-lg border border-slate-300 px-3 py-1 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500';
	}

	renderKeyInput(field) {
		const { keyReadonly } = this.fieldMeta(field);
		const input = component.create('inputText')
			.setClassName(this.tableControlClassName())
			.setData('name', 'key')
			.setValue(String(field.key ?? ''));
		input.readOnly = keyReadonly;
		return input;
	}

	renderTypeSelect(field) {
		const { editable } = this.fieldMeta(field);
		const rowIndex = Number(field.__rowIndex ?? 0);
		const wrapper = component.create('div').setClassName('flex items-center gap-1');

		const select = component.create('inputSelect', {
			name: 'type',
			value: field.type || 'string',
			options: this.getFieldTypeOptions(),
		})
			.setClassName(this.tableControlClassName())
			.setData('name', 'type');
		select.disabled = !editable;

		const optionsButton = this.buildRowActionButton('', 'fa-gear', 'slate', 'edit-options', rowIndex, !editable, async () => {
			const result = await this.openOptionsEditor(rowIndex);
			if (result !== null) {
				this.#state.config.fields[rowIndex].options = result;
				this.clearNotice();
				this.render();
			}
		});
		optionsButton.setAttribute('aria-label', this.optionsButtonLabel(field));
		optionsButton.title = this.optionsButtonLabel(field);
		optionsButton.classList.toggle('hidden', field.type !== 'select');

		select.addEventListener('change', () => {
			optionsButton.classList.toggle('hidden', select.value !== 'select');
		});

		select.setParent(wrapper);
		optionsButton.setParent(wrapper);
		return wrapper;
	}

	optionsButtonLabel(field) {
		const count = Array.isArray(field.options) ? field.options.length : 0;
		return count > 0 ? `Configurar opciones (${count})` : 'Configurar opciones';
	}

	renderLabelInput(field) {
		const { editable } = this.fieldMeta(field);
		const input = component.create('inputText')
			.setClassName(this.tableControlClassName())
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

		this.buildRowActionButton('Subir', 'fa-arrow-up', 'slate', 'move-up', rowIndex, rowIndex === 0, () => this.moveRow(rowIndex, -1)).setParent(actions);
		this.buildRowActionButton('Bajar', 'fa-arrow-down', 'slate', 'move-down', rowIndex, rowIndex === lastIndex, () => this.moveRow(rowIndex, 1)).setParent(actions);

		if (!immutableField) {
			const removeButton = this.buildRowActionButton('Eliminar', 'fa-trash', 'red', 'remove-row', rowIndex, false, () => {
				this.syncStateFromDom();
				this.#state.config.fields.splice(rowIndex, 1);
				this.clearNotice();
				this.render();
			});
			removeButton.addClass('ml-2').setParent(actions);
		}

		return actions;
	}

	buildRowActionButton(label, icon, tone, action, rowIndex, disabled, onClick) {
		const button = DynamicTable.buildActionButton({
			label,
			icon,
			tone,
			dataAction: action,
			disabled,
			onClick,
		});
		return button.setData('rowIndex', rowIndex);
	}

	/**
	 * Opens the value/label list editor for a `select` field's options
	 * (gear button next to the "Tipo" select). Resolves with the saved
	 * {value, label}[] array, or null if the admin cancelled — the caller
	 * decides whether to write that back into #state.
	 */
	openOptionsEditor(rowIndex) {
		this.syncStateFromDom();
		const field = this.#state.config.fields[rowIndex];
		if (!field) {
			return Promise.resolve(null);
		}

		const options = Array.isArray(field.options) ? field.options.map((option) => ({ ...option })) : [];
		const title = `Opciones de "${field.key || field.label || 'campo'}"`;

		return new Promise((resolve) => {
			const modal = new Modal(this.#container, { title });
			const body = component.create('div').setClassName('grid gap-3');
			const list = component.create('div').setClassName('grid gap-2').setData('role', 'options-list');
			const errorText = component.create('p')
				.setClassName('hidden text-sm text-red-600')
				.setData('role', 'options-error');

			const syncOptionsFromDom = () => {
				Array.from(list.children).forEach((rowEl, index) => {
					const valueInput = rowEl.querySelector('[data-name="option-value"]');
					const labelInput = rowEl.querySelector('[data-name="option-label"]');
					if (options[index]) {
						options[index] = {
							value: valueInput ? valueInput.value : '',
							label: labelInput ? labelInput.value : '',
						};
					}
				});
			};

			const renderRows = () => {
				list.replaceChildren();
				options.forEach((option, index) => {
					const row = component.create('div').setClassName('flex items-center gap-2');
					const valueInput = component.create('inputText', {
						value: option.value ?? '',
						placeholder: 'Valor',
					})
						.setClassName(this.tableControlClassName())
						.setData('name', 'option-value');
					const labelInput = component.create('inputText', {
						value: option.label ?? '',
						placeholder: 'Etiqueta',
					})
						.setClassName(this.tableControlClassName())
						.setData('name', 'option-label');
					const removeButton = DynamicTable.buildActionButton({
						icon: 'fa-trash',
						tone: 'red',
						dataAction: 'remove-option',
						onClick: () => {
							syncOptionsFromDom();
							options.splice(index, 1);
							renderRows();
						},
					});

					valueInput.setParent(row);
					labelInput.setParent(row);
					removeButton.setParent(row);
					row.setParent(list);
				});
			};
			renderRows();

			const addButton = component.create('button', {
				label: '+ Añadir opción',
				variant: 'secondary',
				size: 'xs',
			});
			addButton.addEventListener('click', () => {
				syncOptionsFromDom();
				options.push({ value: '', label: '' });
				renderRows();
			});

			const actions = component.create('div').setClassName('flex justify-end gap-2');
			const cancelButton = component.create('button', { label: 'Cancelar', variant: 'secondary', size: 'xs' });
			const saveButton = component.create('button', { label: 'Guardar', variant: 'primary', size: 'xs' });

			const closeWith = (result) => {
				modal.destroy();
				resolve(result);
			};

			cancelButton.addEventListener('click', () => closeWith(null));
			saveButton.addEventListener('click', () => {
				syncOptionsFromDom();
				const collected = options
					.map((option) => ({
						value: String(option.value ?? '').trim(),
						label: String(option.label ?? '').trim(),
					}))
					.filter((option) => option.value !== '');

				const seenValues = new Set();
				for (const option of collected) {
					if (seenValues.has(option.value)) {
						errorText.setText(`El valor "${option.value}" está duplicado.`).removeClass('hidden');
						return;
					}
					seenValues.add(option.value);
				}

				closeWith(collected.map((option) => ({ value: option.value, label: option.label || option.value })));
			});

			list.setParent(body);
			addButton.setParent(body);
			errorText.setParent(body);
			cancelButton.setParent(actions);
			saveButton.setParent(actions);
			actions.setParent(body);

			modal.setContent(body);
			modal.show();
		});
	}

	/**
	 * Re-render (add/move/remove row, or an error banner) rebuilds the whole
	 * form from #state. Since #state is only ever written at load time or on
	 * explicit save, any DOM edits made since the last render — other field
	 * rows, or the Slug/Nombre/Descripcion/target-entity inputs — must be
	 * pulled back into #state first or they're silently discarded.
	 */
	syncStateFromDom() {
		const wrapper = this.#formElement;
		if (wrapper === null || this.#state === null) {
			return;
		}

		const slugInput = wrapper.querySelector('[data-name="identity-slug"]');
		const nameInput = wrapper.querySelector('[data-name="identity-name"]');
		const descriptionInput = wrapper.querySelector('[data-name="identity-description"]');
		if (slugInput) {
			this.#state.plugin.slug = slugInput.value;
		}
		if (nameInput) {
			this.#state.plugin.name = nameInput.value;
		}
		if (descriptionInput) {
			this.#state.plugin.description = descriptionInput.value;
		}

		const targetEntityInput = wrapper.querySelector('[data-name="target-entity"]');
		if (targetEntityInput) {
			this.#state.config.target_entity = targetEntityInput.value;
		}

		if (wrapper.querySelector('[data-role="plugin-config-table"]')) {
			this.#state.config.fields = this.collectRowsFromDom(wrapper);
		}

		if (wrapper.querySelector('[data-role="plugin-config-relations-table"]')) {
			this.#state.config.relations = this.collectRelationRowsFromDom(wrapper);
		}
	}

	moveRow(index, delta) {
		this.syncStateFromDom();
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
		if (this.#mode === 'create') {
			await this.createFromDom(wrapper);
			return;
		}

		const slugInput = wrapper.querySelector('[data-name="identity-slug"]');
		if (slugInput?.value.trim() === '') {
			this.syncStateFromDom();
			this.#message = 'El slug no puede quedar vacío.';
			this.#messageType = 'error';
			this.render();
			return;
		}

		try {
			const payload = this.buildPayloadFromDom(wrapper);
			const previousSlug = this.#slug;
			const { data } = await this.#api.put(`/plugins/${this.#slug}/config`, payload);

			this.applySavedState(data);

			const newSlug = String(data?.plugin?.slug ?? previousSlug);
			const slugChanged = newSlug !== '' && newSlug !== previousSlug;
			if (slugChanged) {
				this.#slug = newSlug;
			}

			this.#message = 'Configuración guardada correctamente.';
			this.#messageType = 'success';
			this.render();

			if (slugChanged) {
				this.#onIdentityChanged(previousSlug, data.plugin);
			}
		} catch (error) {
			this.syncStateFromDom();
			this.#message = `Error al guardar: ${error.message}`;
			this.#messageType = 'error';
			this.render();
		}
	}

	async createFromDom(wrapper) {
		const pluginNameInput = wrapper.querySelector('[data-name="plugin-name"]');
		if (!pluginNameInput || pluginNameInput.value.trim() === '') {
			this.syncStateFromDom();
			this.#message = 'Selecciona un tipo de plugin.';
			this.#messageType = 'error';
			this.render();
			return;
		}

		try {
			const payload = this.buildCreatePayloadFromDom(wrapper);
			const { data } = await this.#api.post('/plugins', payload);
			this.#onCreated(data.plugin);
		} catch (error) {
			this.syncStateFromDom();
			this.#message = `Error al registrar el plugin: ${error.message}`;
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
		return ['string', 'text', 'number', 'boolean', 'date', 'timestamp', 'mail', 'phone', 'select', 'uuid']
			.map((type) => ({ value: type, label: type }));
	}

	bindPageActions(wrapper, actions) {
		if (actions.addFieldButton) {
			actions.addFieldButton.addEventListener('click', () => {
				this.syncStateFromDom();
				this.#state.config.fields.push(this.createEmptyField());
				this.clearNotice();
				this.render();
			});
		}

		if (actions.addRelationButton) {
			actions.addRelationButton.addEventListener('click', () => {
				this.syncStateFromDom();
				this.#state.config.relations.push(this.createEmptyRelation());
				this.clearNotice();
				this.render();
			});
		}

		actions.backButton.addEventListener('click', () => {
			this.#onBack();
		});

		actions.saveButton.addEventListener('click', async () => {
			await this.saveFromDom(wrapper);
		});

		if (actions.deleteButton) {
			actions.deleteButton.addEventListener('click', async () => {
				await this.deletePlugin();
			});
		}
	}

	async deletePlugin() {
		const name = String(this.#state?.plugin?.name ?? this.#slug);
		const accepted = await UiResilienceService.confirm({
			container: this.#container,
			title: 'Confirmar borrado de plugin',
			message: `Esto borrará el plugin "${name}" y todos los datos asociados a "${name}". Esta acción es irreversible.`,
			confirmLabel: 'Borrar plugin',
			cancelLabel: 'Cancelar',
		});
		if (!accepted) {
			return;
		}

		const deleteButton = this.#container.querySelector('[data-plugin-config-delete]');
		if (deleteButton instanceof HTMLButtonElement) {
			UiResilienceService.setButtonPending(deleteButton, 'Borrando...');
		}

		try {
			await this.#api.delete(`/plugins/${this.#slug}`);
			this.#onDeleted(this.#state.plugin);
		} catch (error) {
			this.#message = `Error al borrar: ${error.message}`;
			this.#messageType = 'error';
			this.render();
		} finally {
			if (deleteButton instanceof HTMLButtonElement) {
				UiResilienceService.clearButtonPending(deleteButton, 'Borrar');
			}
		}
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
			options: [],
		};
	}

	buildTargetOptions(entityOptions, targetEntity) {
		const hasCurrentTarget = entityOptions.some((entity) => entity.slug === targetEntity);
		const allTargetOptions = hasCurrentTarget || targetEntity === '*'
			? entityOptions
			: [...entityOptions, { slug: targetEntity, label: `${targetEntity} (no activa)` }];

		return [
			{ value: '*', label: 'Todas' },
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
				relations: Array.isArray(data?.config?.relations)
					? data.config.relations.map((relation) => ({ ...relation }))
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
		} else {
			payload.relations = this.collectRelationRowsFromDom(wrapper);
		}

		const slugInput = wrapper.querySelector('[data-name="identity-slug"]');
		const nameInput = wrapper.querySelector('[data-name="identity-name"]');
		const descriptionInput = wrapper.querySelector('[data-name="identity-description"]');
		payload.slug = slugInput ? slugInput.value.trim() : String(this.#state.plugin.slug ?? '');
		payload.name = nameInput ? nameInput.value.trim() : String(this.#state.plugin.name ?? '');
		payload.description = descriptionInput ? descriptionInput.value.trim() : String(this.#state.plugin.description ?? '');

		return payload;
	}

	buildCreatePayloadFromDom(wrapper) {
		const pluginNameInput = wrapper.querySelector('[data-name="plugin-name"]');
		const slugInput = wrapper.querySelector('[data-name="identity-slug"]');
		const nameInput = wrapper.querySelector('[data-name="identity-name"]');
		const descriptionInput = wrapper.querySelector('[data-name="identity-description"]');

		const payload = {
			plugin_name: pluginNameInput ? pluginNameInput.value.trim() : '',
			fields: this.collectRowsFromDom(wrapper),
		};
		const slug = slugInput ? slugInput.value.trim() : '';
		const name = nameInput ? nameInput.value.trim() : '';
		const description = descriptionInput ? descriptionInput.value.trim() : '';

		if (slug !== '') {
			payload.slug = slug;
		}
		if (name !== '') {
			payload.name = name;
		}
		if (description !== '') {
			payload.description = description;
		}

		if (this.isExtensionPlugin()) {
			const targetEntityInput = wrapper.querySelector('[data-name="target-entity"]');
			payload.target_entity = targetEntityInput ? targetEntityInput.value.trim() : '';
		} else {
			payload.relations = this.collectRelationRowsFromDom(wrapper);
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
			options: Array.isArray(original.options) ? original.options : [],
		};
	}

	collectRelationRowsFromDom(wrapper) {
		const rows = [];
		wrapper.querySelectorAll('[data-role="plugin-config-relations-table"] tbody tr[data-row-index]').forEach((rowEl) => {
			rows.push(this.readRelationRowFromDom(rowEl));
		});

		return rows;
	}

	readRelationRowFromDom(rowEl) {
		const keyInput = rowEl.querySelector('[data-name="relation-key"]');
		const targetEntitySelect = rowEl.querySelector('[data-name="relation-target-entity"]');
		const targetFieldSelect = rowEl.querySelector('[data-name="relation-target-field"]');
		const labelInput = rowEl.querySelector('[data-name="relation-label"]');
		const requiredCheckbox = rowEl.querySelector('[data-name="relation-required"]');

		return {
			key: keyInput ? keyInput.value.trim() : '',
			target_entity: targetEntitySelect ? targetEntitySelect.value : '',
			target_field: targetFieldSelect ? targetFieldSelect.value : '',
			label: labelInput ? labelInput.value.trim() : '',
			required: requiredCheckbox ? !!requiredCheckbox.checked : false,
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
					identities: entity?.identities && typeof entity.identities === 'object' ? entity.identities : {},
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
