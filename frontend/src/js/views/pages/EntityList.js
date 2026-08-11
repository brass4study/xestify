/**
 * EntityList.js — Page controller for listing and browsing entities.
 *
 * Responsibilities:
 *   - Load available entity list from GET /entities
 *   - On entity click, load its records from GET /entities/:slug/records
 *   - Render records using DynamicTable
 *   - Expose a "Crear nuevo registro" button that invokes an optional callback
 */

import { Api, ApiError } from '../../models/ApiClientModel.js';
import { AppState } from '../../models/StateModel.js';
import { t } from '../../models/I18nModel.js';
import { UiResilienceService } from '../../services/UiResilienceService.js';
import { ListLayout } from '../layout/ListLayout.js';
import { DynamicTable } from '../modules/DynamicTable.js';
import { component } from '../modules/ComponentFactory.js';

export class EntityList {
	static PAGE_SIZE = 20;
	/** @type {Api} */
	#api;

	/** @type {HTMLElement} */
	#container;

	/** @type {Function|null} */
	#onCreateNew;

	/** @type {Function|null} */
	#onEdit;

	#shellLayout;
	#title;
	#description;
	#layout = null;
	#notificationRole = null;

	/**
	 * @param {string|HTMLElement} container
	 * @param {{api?: Api, shellLayout?: import('../layout/ShellLayout.js').ShellLayout|null, title?: string, description?: string, onCreateNew?: Function, onEdit?: Function}} options
	 */
	constructor(container, options = {}) {
		this.#container = this.resolveContainer(container);
		this.#api = (options.api !== null && options.api !== undefined && typeof options.api.get === 'function')
			? options.api
			: new Api();
		this.#onCreateNew = typeof options.onCreateNew === 'function'
			? options.onCreateNew
			: null;
		this.#onEdit = typeof options.onEdit === 'function'
			? options.onEdit
			: null;
		this.#shellLayout = options.shellLayout ?? null;
		this.#title = typeof options.title === 'string' ? options.title : null;
		this.#description = typeof options.description === 'string' ? options.description : null;
	}

	/**
	 * Load entities and store them in AppState.
	 *
	 * @returns {Promise<void>}
	 */
	async init() {
		this.#container.replaceChildren();
		this.#layout = ListLayout.create(this.#container, { shell: this.#shellLayout })
			.setTitle(this.#title ?? 'Entidades')
			.setDescription(this.#description ?? 'Selecciona una entidad para consultar sus registros.')
			.build();
		this.#setLoading(true);

		try {
			const { data } = await this.#api.get('/entities');
			const entities = Array.isArray(data) ? data : [];
			AppState.setEntities(entities);
			this.#setLoading(false);

			if (entities.length === 0) {
				UiResilienceService.setViewState(this.#layout.getContentTarget(), {
					type: 'empty',
					title: t('entities.empty.title', 'Sin entidades'),
					message: t('entities.empty.description', 'No hay entidades disponibles.'),
				});
			} else {
				UiResilienceService.clearViewState(this.#layout.getContentTarget());
			}
		} catch (err) {
			this.#setLoading(false);
			this.#handleError(err);
		}
	}

	/**
	 * Load records for the given entity slug and render the table.
	 *
	 * @param {string} slug
	 * @returns {Promise<void>}
	 */
	async loadEntity(slug) {
		this.#setLoading(true);
		this.#clearError();
		AppState.setCurrentEntity(slug);

		try {
			const { data, meta } = await this.#api.get(this.#recordsUrl(slug));
			const records = this.#normalizeRecords(data);
			AppState.setRecords(records);

			const schema = this.#schemaForSlug(slug);
			this.#renderRecords(records, schema, slug, meta);
		} catch (err) {
			this.#handleError(err);
		} finally {
			this.#setLoading(false);
		}
	}

	/**
	 * @param {Array<object>} records
	 * @param {object} schema
	 * @param {string} slug
	 * @param {object|undefined} meta
	 */
	#renderRecords(records, schema, slug, meta = undefined) {
		this.#layout = ListLayout.create(this.#container, { shell: this.#shellLayout })
			.setTitle(this.#title ?? this.#entityLabelForSlug(slug))
			.setDescription(this.#description ?? this.#createLabelForSlug(slug))
			.setHeaderToolbar(this.#createRecordButton(slug))
			.build();
		const layout = this.#layout;

		const extraColumns = this.#onEdit === null
			? []
			: [
					{
						label: 'Acciones',
						renderCell: (record) => DynamicTable.buildActionButton({
							label: 'Editar',
							icon: 'fa-pen',
							dataRole: 'record-edit',
							onClick: () => {
								if (this.#onEdit !== null) {
									this.#onEdit(slug, record.id ?? null, record);
								}
							},
						}),
					},
				];

		layout.createTable(records, schema, {
			extraColumns,
			pageSize: DynamicTable.getPreferredPageSize(EntityList.PAGE_SIZE),
			totalRecords: Number.isInteger(meta?.total) ? meta.total : records.length,
			onQueryChange: async (query) => {
				try {
					const response = await this.#api.get(this.#recordsUrl(slug, query));
					const refreshedRecords = this.#normalizeRecords(response.data);
					AppState.setRecords(refreshedRecords);
					return {
						records: refreshedRecords,
						total: Number.isInteger(response.meta?.total) ? response.meta.total : refreshedRecords.length,
						page: Number.isInteger(response.meta?.page) ? response.meta.page : query.page,
					};
				} catch (error) {
					this.#handleError(error);
					return null;
				}
			},
		});
	}

	#recordsUrl(slug, query = {}) {
		const page = Number.isInteger(query.page) ? query.page : 1;
		const pageSize = Number.isInteger(query.pageSize)
			? query.pageSize
			: DynamicTable.getPreferredPageSize(EntityList.PAGE_SIZE);
		const sort = typeof query.sort === 'string' && query.sort !== '' ? query.sort : 'created_at';
		const direction = query.direction === 'desc' ? 'desc' : 'asc';
		return `/entities/${encodeURIComponent(slug)}/records?page=${page}&page_size=${pageSize}&sort=${encodeURIComponent(sort)}&direction=${direction}`;
	}

	#createRecordButton(slug) {
		return component.create('button', {
			label: this.#createLabelForSlug(slug),
			variant: 'primary',
			size: 'md',
			dataRole: 'record-create',
			onClick: () => {
				if (this.#onCreateNew !== null) {
					this.#onCreateNew(slug);
				}
			},
		});
	}

	#schemaForSlug(slug) {
		const entities = AppState.getEntities();
		const found = entities.find((e) => e.slug === slug);
		return found ?? { slug, fields: [] };
	}

	#entityLabelForSlug(slug) {
		const entities = AppState.getEntities();
		const found = entities.find((e) => e.slug === slug);

		if (found !== undefined && typeof found.label === 'string' && found.label.trim() !== '') {
			return found.label;
		}

		return slug;
	}

	#createLabelForSlug(slug) {
		const entities = AppState.getEntities();
		const found = entities.find((e) => e.slug === slug);
		const singular = found !== undefined && typeof found.label_singular === 'string' && found.label_singular !== ''
			? found.label_singular
			: slug;

		return `Crear ${singular.toLowerCase()}`;
	}

	#normalizeRecords(data) {
		if (!Array.isArray(data)) {
			return [];
		}

		return data.map((row) => this.#normalizeRecord(row));
	}

	#normalizeRecord(row) {
		if (row === null || typeof row !== 'object') {
			return {};
		}

		const source = /** @type {Record<string, unknown>} */ (row);
		const content = this.#extractContentObject(source.content);

		return {
			...content,
			id: source.id ?? null,
			entity_slug: source.entity_slug ?? null,
			created_at: source.created_at ?? null,
			updated_at: source.updated_at ?? null,
		};
	}

	#extractContentObject(rawContent) {
		if (rawContent !== null && typeof rawContent === 'object' && !Array.isArray(rawContent)) {
			return /** @type {Record<string, unknown>} */ (rawContent);
		}

		if (typeof rawContent === 'string') {
			try {
				const parsed = JSON.parse(rawContent);
				if (parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)) {
					return /** @type {Record<string, unknown>} */ (parsed);
				}
			} catch {
				return {};
			}
		}

		return {};
	}

	#setLoading(loading) {
		AppState.setLoading(loading);

		if (loading) {
			UiResilienceService.setViewState(this.#layout?.getContentTarget() ?? this.#container, {
				type: 'loading',
				title: t('ui.loading', 'Cargando…'),
				message: 'Estamos preparando la vista y sus datos.',
			});
			this.#showNotification('info', 'Cargando…', 'entity-loading');
		} else {
			UiResilienceService.clearViewState(this.#layout?.getContentTarget() ?? this.#container);
			if (this.#notificationRole === 'entity-loading') {
				this.#layout?.setNotification(null);
				this.#notificationRole = null;
			}
		}
	}

	#clearError() {
		AppState.setError(null);
		if (this.#notificationRole === 'entity-error') {
			this.#layout?.setNotification(null);
			this.#notificationRole = null;
		}
	}

	#handleError(err) {
		const message = err instanceof ApiError ? err.message : 'Error desconocido';
		AppState.setError({ message });

		this.#showNotification('error', message, 'entity-error');
	}

	#showNotification(type, message, role) {
		const normalizedMessage = typeof message === 'string' && message.trim() !== '' ? message : t('ui.error.generic', 'Ha ocurrido un error inesperado.');
		UiResilienceService.showNotification({
			type,
			title: type === 'error' ? t('ui.error.title', 'Error') : t('ui.loading', 'Cargando…'),
			message: normalizedMessage,
		});
		const banner = component.create('alert', { type, message: normalizedMessage })
			.setData('role', role)
			.setData('type', type);
		this.#layout?.setNotification(banner);
		this.#notificationRole = role;
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

		throw new TypeError(`EntityList: container "${String(container)}" not found`);
	}
}
