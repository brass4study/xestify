/**
 * EntityEdit.js — Page controller for creating or editing an entity record.
 */

import { Api, ApiError } from '../../models/ApiClientModel.js';
import { buildPluginModuleUrl } from '../../models/BasePathModel.js';
import { PluginPanelRegistry } from '../../models/PluginPanelModel.js';
import { FormLayout } from '../layout/FormLayout.js';
import { DynamicForm } from '../modules/DynamicForm.js';
import { DynamicTabs } from '../modules/DynamicTabs.js';
import { component } from '../modules/ComponentFactory.js';

export class EntityEdit {
	static #renderSequence = 0;
	#api;
	#container;
	#slug;
	#schema;
	#recordId;
	#shellLayout;
	#title;
	#description;
	#form = null;
	#onSaved;
	#onCancel;
	#pluginPanels = [];
	#renderToken = 0;
	#layout = null;
	#errorBanner = null;

	/**
	 * @param {HTMLElement|string} container
	 * @param {string} slug
	 * @param {Object} schema
	 * @param {{recordId?: string|null, shellLayout?: import('../layout/ShellLayout.js').ShellLayout|null, title?: string, description?: string, api?: Object, initialData?: Object, onSaved?: Function, onCancel?: Function}} options
	 */
	constructor(container, slug, schema, options = {}) {
		this.#container = this.resolveContainer(container);
		this.#slug = slug;
		this.#schema = schema && typeof schema === 'object' ? schema : { fields: [] };
		this.#recordId = options.recordId ?? null;
		this.#shellLayout = options.shellLayout ?? null;
		this.#title = typeof options.title === 'string' ? options.title : null;
		this.#description = typeof options.description === 'string' ? options.description : this.#slug;
		this.#api = (options.api !== null && options.api !== undefined && typeof options.api.post === 'function')
			? options.api
			: new Api();
		this.#onSaved = typeof options.onSaved === 'function' ? options.onSaved : null;
		this.#onCancel = typeof options.onCancel === 'function' ? options.onCancel : null;

		this.#render(options.initialData ?? {});
	}

	async submit() {
		if (this.#form === null) {
			return;
		}

		this.#clearErrors();

		if (!this.#validateFormBeforeSubmit()) {
			return;
		}

		this.#setLoading(true);

		try {
			const saved = await this.#persistFormData();
			await this.#flushPendingPlugins(saved);
			this.#notifySaved(saved);
		} catch (err) {
			this.#handleSubmitError(err);
		} finally {
			this.#setLoading(false);
		}
	}

	#render(initialData) {
		const renderToken = this.#nextRenderToken();
		const fallbackTitle = this.#recordId === null
			? `Nuevo registro: ${this.#slug}`
			: `Editar registro: ${this.#slug}`;
		const titleText = this.#title ?? fallbackTitle;
		this.#layout = FormLayout.create(this.#container, {
			shell: this.#shellLayout,
		})
			.setTitle(titleText)
			.setDescription(this.#description)
			.build();
		const layout = this.#layout;
		const wrapper = layout.getPanel();

		this.#errorBanner = component.create('alert', {
			type: 'error',
			message: '',
		}).setData('role', 'entity-edit-error').setData('type', 'error');
		layout.setNotification(null);

		const formContainer = component.create('div')
			.setData('role', 'entity-edit-form-wrap')
			.setParent(wrapper);

		const schemaWithDefaults = this.#applyInitialData(this.#schema, initialData);
		this.#form = new DynamicForm(schemaWithDefaults, formContainer);
		this.#form.render();

		const saveBtn = component.create('button', {
			label: 'Guardar',
			variant: 'primary',
			size: 'md',
			dataRole: 'entity-edit-save',
			onClick: () => {
				this.submit();
			},
		});
		layout.addAction(saveBtn);

		if (this.#onCancel !== null) {
			const cancelBtn = component.create('button', {
				label: 'Cancelar',
				variant: 'secondary',
				size: 'md',
				dataRole: 'entity-edit-cancel',
				onClick: () => {
					this.#onCancel();
				},
			});
			layout.addAction(cancelBtn);
		}

		void this.#loadAndRenderTabs(wrapper, renderToken, layout);
	}

	async #loadAndRenderTabs(wrapper, renderToken, layout) {
		try {
			const { data } = await this.#api.get(`/entities/${this.#slug}/tabs`);
			if (!this.#isCurrentRender(renderToken)) {
				return;
			}

			const rawTabs = Array.isArray(data?.tabs) ? data.tabs : [];

			if (rawTabs.length === 0) {
				return;
			}

			const recordId = this.#recordId;
			const api = this.#api;
			this.#pluginPanels = [];

			await this.#loadPluginModules(rawTabs);

			if (!this.#isCurrentRender(renderToken)) {
				return;
			}

			const dataPanelEl = component.create('div');
			while (wrapper.firstChild !== null) {
				dataPanelEl.appendChild(wrapper.firstChild);
			}

			const tabDefs = [
				{
					id: 'datos',
					label: 'Datos',
					content: () => dataPanelEl,
				},
				...rawTabs.map((tab) => {
					const panel = PluginPanelRegistry.build(tab.id, {
						endpoint: tab.endpoint ?? '',
						recordId,
						api,
					});
					if (panel !== null) {
						this.#pluginPanels.push(panel);
					}
					return {
						id: tab.id,
						label: tab.label,
						content: () => (panel === null ? this.#buildFallbackPanel(tab.label) : panel.element),
					};
				}),
			];

			wrapper.classList.remove('p-4');
			wrapper.classList.add('px-4', 'pb-4', 'pt-0');

			layout.setHeaderBottom((tabBarContainer) => {
				const dynamicTabs = new DynamicTabs(wrapper, {
					tabs: tabDefs,
					tabBarContainer,
				});
				dynamicTabs.render();
			});

			if (!this.#isCurrentRender(renderToken)) {
				return;
			}
		} catch {
			if (!this.#isCurrentRender(renderToken)) {
				return;
			}

			this.#showGlobalError('No se pudieron cargar las extensiones.');
		}
	}

	async #loadPluginModules(tabs) {
		await Promise.allSettled(
			tabs.map((tab) => import(buildPluginModuleUrl(tab.id)))
		);
	}

	#buildFallbackPanel(label) {
		const el = component.create('div')
			.setClassName('flex flex-col gap-3')
			.setData('role', 'plugin-tab-fallback');
		component.create('p')
			.setClassName('rounded-lg border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500')
			.setData('role', 'placeholder')
			.setText(`Plugin "${label}" no disponible.`)
			.setParent(el);
		return el;
	}

	#applyInitialData(schema, initialData) {
		if (Object.keys(initialData).length === 0) {
			return schema;
		}

		if (Array.isArray(schema.fields)) {
			return {
				...schema,
				fields: this.#applyDefaultsToFieldList(schema.fields, initialData),
				custom_fields: this.#applyDefaultsToFieldList(schema.custom_fields, initialData),
			};
		}

		if (schema.fields !== null && typeof schema.fields === 'object') {
			const fieldsWithDefaults = {};
			for (const [name, config] of Object.entries(schema.fields)) {
				const fieldConfig = config !== null && typeof config === 'object' ? config : {};
				fieldsWithDefaults[name] = Object.hasOwn(initialData, name)
					? { ...fieldConfig, default: initialData[name] }
					: fieldConfig;
			}

			return {
				...schema,
				fields: fieldsWithDefaults,
				custom_fields: this.#applyDefaultsToFieldList(schema.custom_fields, initialData),
			};
		}

		return {
			...schema,
			custom_fields: this.#applyDefaultsToFieldList(schema.custom_fields, initialData),
		};
	}

	#applyDefaultsToFieldList(fields, initialData) {
		if (!Array.isArray(fields)) {
			return fields;
		}

		return fields.map((field) => {
			if (field === null || typeof field !== 'object') {
				return field;
			}

			const fieldName = this.#fieldName(field);
			if (fieldName !== null && Object.hasOwn(initialData, fieldName)) {
				return { ...field, default: initialData[fieldName] };
			}

			return field;
		});
	}

	#fieldName(field) {
		for (const key of ['name', 'key', 'slug']) {
			const value = field[key];
			if (typeof value === 'string' && value.trim() !== '') {
				return value;
			}
		}

		return null;
	}

	#nextRenderToken() {
		EntityEdit.#renderSequence += 1;
		this.#renderToken = EntityEdit.#renderSequence;
		return this.#renderToken;
	}

	#isCurrentRender(renderToken) {
		return this.#renderToken === renderToken;
	}

	#showErrors(errors) {
		const formWrap = this.#container.querySelector('[data-role="entity-edit-form-wrap"]');
		const formEl = formWrap instanceof HTMLElement ? formWrap.querySelector('form') : null;
		const normalizedErrors = this.#normalizeErrors(errors);

		for (const [fieldName, messages] of Object.entries(normalizedErrors)) {
			let input = null;
			if (formEl !== null) {
				input = formEl.querySelector(`[name="${fieldName}"]`);
			}

			const msgList = Array.isArray(messages) ? messages : [String(messages)];
			const errorEl = component.create('ul')
				.setClassName('mt-1 list-disc pl-5 text-xs text-red-700')
				.setData('role', 'field-errors')
				.setData('field', fieldName);

			for (const msg of msgList) {
				component.create('li').setText(msg).setParent(errorEl);
			}

			if (input !== null && input.parentElement !== null) {
				input.parentElement.appendChild(errorEl);
			} else if (formEl !== null) {
				formEl.appendChild(errorEl);
			}
		}
	}

	#normalizeErrors(errors) {
		if (Array.isArray(errors)) {
			return this.#normalizeStructuredErrors(errors);
		}

		if (errors === null || typeof errors !== 'object') {
			return {};
		}

		return errors;
	}

	#normalizeStructuredErrors(errors) {
		const normalized = {};

		for (const error of errors) {
			if (error === null || typeof error !== 'object') {
				continue;
			}

			const field = typeof error.field === 'string' && error.field.trim() !== ''
				? error.field
				: '_global';
			const message = typeof error.message === 'string' && error.message.trim() !== ''
				? error.message
				: 'Error de validacion';

			normalized[field] ??= [];
			normalized[field].push(message);
		}

		return normalized;
	}

	#showGlobalError(message) {
		if (this.#errorBanner !== null && this.#layout !== null) {
			this.#errorBanner.textContent = message;
			this.#layout.setNotification(this.#errorBanner);
		}
	}

	#clearErrors() {
		this.#layout?.setNotification(null);

		const errorLists = this.#container.querySelectorAll('[data-role="field-errors"]');
		for (const el of errorLists) {
			el.remove();
		}
	}

	#setLoading(loading) {
		const saveBtn = this.#container.querySelector('[data-role="entity-edit-save"]');
		if (saveBtn !== null) {
			saveBtn.disabled = loading;
			saveBtn.textContent = loading ? 'Guardando…' : 'Guardar';
		}
	}

	#validateFormBeforeSubmit() {
		const { isValid, errors } = this.#form.validate();
		if (!isValid) {
			this.#showErrors(errors);
			return false;
		}
		return true;
	}

	async #persistFormData() {
		const data = this.#form.getData();
		if (this.#recordId === null) {
			const { data: saved } = await this.#api.post(`/entities/${this.#slug}/records`, data);
			return saved;
		}
		const { data: saved } = await this.#api.put(`/entities/${this.#slug}/records/${this.#recordId}`, data);
		return saved;
	}

	async #flushPendingPlugins(saved) {
		const savedId = (saved !== null && typeof saved === 'object')
			? String(saved.id ?? this.#recordId ?? '')
			: String(this.#recordId ?? '');

		if (savedId === '' || this.#pluginPanels.length === 0) {
			return;
		}

		await Promise.all(this.#pluginPanels.map((p) => p.flush(savedId)));
	}

	#notifySaved(saved) {
		if (this.#onSaved !== null) {
			this.#onSaved(saved);
		}
	}

	#handleSubmitError(err) {
		if (err instanceof ApiError && this.#hasErrorDetails(err.details)) {
			this.#showErrors(err.details);
			return;
		}
		this.#showGlobalError(err instanceof ApiError ? err.message : 'Error desconocido');
	}

	#hasErrorDetails(details) {
		if (Array.isArray(details)) {
			return details.length > 0;
		}

		return details !== null
			&& typeof details === 'object'
			&& Object.keys(details).length > 0;
	}

	resolveContainer(container) {
		if (container instanceof HTMLElement) {
			return container;
		}

		if (typeof container === 'string') {
			const el = document.querySelector(container);
			if (el instanceof HTMLElement) {
				return el;
			}
		}

		throw new TypeError(`EntityEdit: container "${String(container)}" not found`);
	}
}
