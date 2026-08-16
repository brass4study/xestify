/**
 * PluginManager — UI for listing and managing plugin activation/deactivation.
 */

import { Api } from '../../models/ApiClientModel.js';
import { t } from '../../models/I18nModel.js';
import { UiResilienceService } from '../../services/UiResilienceService.js';
import { ListLayout } from '../layout/ListLayout.js';
import { DynamicTable } from '../modules/DynamicTable.js';
import { component } from '../modules/ComponentFactory.js';

export class PluginManager {
	#container;
	#api;
	#onConfigure;
	#onAdd;
	#onPluginsChanged;
	#plugins = [];
	#updatesBySlug = {};
	#feedbackMessage = null;
	#feedbackType = null;
	#shellLayout;
	#title;
	#description;

	/**
	 * @param {HTMLElement|string} container
	 * @param {Object|undefined} api
	 * @param {{shellLayout?: import('../layout/ShellLayout.js').ShellLayout|null, title?: string, description?: string, onConfigure?: Function, onAdd?: Function, onPluginsChanged?: Function}} options
	 */
	constructor(container, api = undefined, options = {}) {
		const resolved = this.resolveContainer(container);
		this.#container = resolved;
		this.#api = api ?? new Api();
		this.#onConfigure = typeof options.onConfigure === 'function' ? options.onConfigure : () => {};
		this.#onAdd = typeof options.onAdd === 'function' ? options.onAdd : () => {};
		this.#onPluginsChanged = typeof options.onPluginsChanged === 'function' ? options.onPluginsChanged : () => {};
		this.#shellLayout = options.shellLayout ?? null;
		this.#title = typeof options.title === 'string' ? options.title : 'Gestión de plugins';
		this.#description = typeof options.description === 'string'
			? options.description
			: 'Sincroniza, activa y configura plugins del sistema.';
	}

	async init() {
		await this.#refreshData();
	}

	async #refreshData() {
		UiResilienceService.setViewState(this.#container, {
			type: 'loading',
			title: t('ui.loading', 'Cargando…'),
			message: 'Estamos revisando plugins y actualizaciones disponibles.',
		});
		try {
			const [pluginsResponse, updatesResponse] = await Promise.all([
				this.#api.get('/plugins'),
				this.#api.get('/plugins/updates'),
			]);

			if (pluginsResponse?.ok === false) {
				throw new Error(pluginsResponse?.error?.message ?? 'Error al cargar los plugins');
			}

			if (updatesResponse?.ok === false) {
				throw new Error(updatesResponse?.error?.message ?? 'Error al cargar las actualizaciones de plugins');
			}

			const pluginsPayload = pluginsResponse?.data ?? pluginsResponse;
			const updatesPayload = updatesResponse?.data ?? updatesResponse;

			this.#plugins = Array.isArray(pluginsPayload?.plugins) ? pluginsPayload.plugins : [];
			this.#updatesBySlug = this.#indexUpdates(Array.isArray(updatesPayload?.updates) ? updatesPayload.updates : []);
			this.#render();
		} catch (error) {
			this.renderError(`Error al cargar los plugins: ${error.message}`);
		} finally {
			UiResilienceService.clearViewState(this.#container);
		}
	}

	#indexUpdates(updates) {
		return updates.reduce((acc, update) => {
			const slug = String(update?.slug ?? '');
			if (slug !== '') {
				acc[slug] = update;
			}
			return acc;
		}, {});
	}

	#render() {
		const syncButton = this.#createSyncButton();
		const addButton = this.#createAddButton();

		const layout = ListLayout.create(this.#container, { shell: this.#shellLayout })
			.setTitle(this.#title)
			.setDescription(this.#description)
			.setHeaderToolbar([syncButton, addButton])
			.build();
		const contentHost = layout.getContentTarget();
		layout.setNotification(null);
		this.#appendFeedback(layout);
		this.#renderContent(layout, contentHost);
	}

	#createSyncButton() {
		const syncButton = component.create('button', {
			label: t('plugins.sync', 'Sincronizar'),
			variant: 'primary',
			size: 'md',
			dataRole: 'plugin-sync',
			dataAction: 'sync',
			onClick: () => {
				this.#handleSync(syncButton);
			},
		});
		return syncButton;
	}

	#createAddButton() {
		return component.create('button', {
			label: t('plugins.add', 'Añadir plugin'),
			variant: 'secondary',
			size: 'md',
			dataRole: 'plugin-add',
			dataAction: 'add',
			onClick: () => {
				this.#onAdd();
			},
		});
	}

	#appendFeedback(layout) {
		if (this.#feedbackMessage === null) {
			return;
		}

		const type = this.#feedbackType === 'error' ? 'error' : 'success';
		const banner = component.create('alert', {
			type,
			message: this.#feedbackMessage,
		}).setData('role', 'plugin-feedback').setData('type', type);
		layout.setNotification(banner);
	}

	#renderContent(layout, content) {
		if (this.#plugins.length === 0) {
			component.create('emptyState', {
				title: t('plugins.empty.title', 'No hay plugins instalados'),
				description: t('plugins.empty.description', 'Sincroniza los plugins disponibles para comenzar.'),
			})
				.setData('role', 'plugin-empty')
				.setParent(content);
			return;
		}

		this.#renderPluginSection(layout, content, {
			type: 'entity',
			role: 'entities',
			title: t('plugins.section.entities', 'Entidades'),
			description: t('plugins.section.entities.description', 'El orden determina en qué posición aparecen en el menú de navegación.'),
			emptyMessage: t('plugins.section.entities.empty', 'No hay plugins de entidad instalados.'),
		});

		this.#renderPluginSection(layout, content, {
			type: 'extension',
			role: 'extensions',
			title: t('plugins.section.extensions', 'Extensiones'),
			description: t('plugins.section.extensions.description', 'El orden determina en qué posición aparecen sus pestañas dentro de cada entidad.'),
			emptyMessage: t('plugins.section.extensions.empty', 'No hay plugins de extensión instalados.'),
		});
	}

	#renderPluginSection(layout, content, { type, role, title, description, emptyMessage }) {
		const plugins = this.#plugins.filter((plugin) => plugin.plugin_type === type);

		const section = component.create('div')
			.setClassName('mb-4')
			.setData('role', `plugin-section-${role}`)
			.setParent(content);

		if (plugins.length === 0) {
			this.#buildSectionHeader(title, description).setParent(section);
			component.create('p', {
				className: 'text-sm text-slate-400 italic',
				text: emptyMessage,
			})
				.setData('role', `plugin-section-${role}-empty`)
				.setParent(section);
			return;
		}

		const tableHost = component.create('div')
			.setClassName('list-table-host')
			.setData('role', `plugin-table-host-${role}`)
			.setParent(section);

		const rows = plugins.map((plugin, index) => ({
			__plugin: plugin,
			__update: this.#updatesBySlug[String(plugin.slug)] ?? null,
			__index: index,
			name: String(plugin.name || plugin.slug || ''),
		}));

		const schema = {
			fields: [
				{ name: 'name', label: 'Nombre' },
			],
		};

		layout.createTable(rows, schema, {
			container: tableHost,
			onRefresh: () => this.#refreshData(),
			tableDataRole: `plugin-table-${role}`,
			toolbarStart: this.#buildSectionHeader(title, description),
			extraColumns: [
				{
					key: 'version',
					label: 'Versión',
					sortValue: (row) => row.__plugin?.version,
					renderCell: (row) => this.#renderVersionCell(row.__plugin, row.__update),
				},
				{
					key: 'status',
					label: 'Estado',
					sortValue: (row) => row.__plugin?.status,
					renderCell: (row) => this.#renderStatusBadge(row.__plugin),
				},
				{
					key: 'actions',
					label: 'Acciones',
					renderCell: (row) => this.#renderActionsCell(row.__plugin, row.__update, row.__index, plugins.length),
				},
			],
			rowDecorator: (tr, row) => {
				const plugin = row.__plugin;
				tr.className = String(plugin.status ?? '');
				tr.dataset.role = 'plugin-row';
				tr.dataset.slug = String(plugin.slug ?? '');
				if (plugin.status === 'active') {
					tr.classList.add('bg-emerald-50/40');
				} else if (plugin.status === 'inactive') {
					tr.classList.add('bg-amber-50/30');
				}
			},
		});
	}

	#buildSectionHeader(title, description) {
		const header = component.create('div').setClassName('flex flex-col');
		component.create('h3', {
			className: 'text-sm font-semibold text-slate-900',
			text: title,
		}).setParent(header);
		component.create('p', {
			className: 'text-xs text-slate-500',
			text: description,
		}).setParent(header);
		return header;
	}

	#renderVersionCell(plugin, updateInfo) {
		const container = component.create('div').setClassName('flex flex-col gap-1');

		component.create('span').setText(String(plugin.version ?? '')).setParent(container);

		if (updateInfo !== null) {
			component.create('span')
				.setClassName('inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide')
				.setData('role', 'plugin-update-badge')
				.setText(`${t('plugins.update.available', 'Actualización disponible')}: ${String(updateInfo.available_version ?? '')}`)
				.setParent(container);
		}

		return container;
	}

	#renderStatusBadge(plugin) {
		const badge = component.create('span');
		const isActive = plugin.status === 'active';
		return badge
			.setClassName(`${isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'} inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide`)
			.setData('role', 'plugin-status-badge')
			.setText(isActive ? t('plugins.status.active', 'Activo') : t('plugins.status.inactive', 'Inactivo'));
	}

	#renderActionsCell(plugin, updateInfo, index, total) {
		const actions = component.create('div').setClassName('flex flex-wrap gap-1.5');

		const isActive = plugin.status === 'active';
		this.#pluginActionButton(
			isActive ? t('plugins.deactivate', 'Desactivar') : t('plugins.activate', 'Activar'),
			isActive ? 'fa-power-off' : 'fa-play',
			isActive ? 'amber' : 'emerald',
			isActive ? 'deactivate' : 'activate',
			plugin
		).setParent(actions);

		const canConfigure = plugin.status === 'active' && (plugin.plugin_type === 'entity' || plugin.plugin_type === 'extension');
		if (canConfigure) {
			this.#pluginActionButton(t('plugins.configure', 'Configurar'), 'fa-sliders', 'brand', 'configure', plugin).setParent(actions);
		}

		if (updateInfo !== null) {
			this.#pluginActionButton(t('plugins.update', 'Actualizar'), 'fa-rotate', 'amber', 'update', plugin).setParent(actions);
		}

		if (this.#canRollback(plugin)) {
			this.#pluginActionButton(t('plugins.rollback', 'Revertir'), 'fa-clock-rotate-left', 'violet', 'rollback', plugin).setParent(actions);
		}

		if (!isActive) {
			this.#pluginActionButton(t('plugins.delete', 'Borrar'), 'fa-trash', 'red', 'delete', plugin).setParent(actions);
		}

		this.#pluginActionButton(t('plugins.moveUp', 'Subir'), 'fa-arrow-up', 'slate', 'move-up', plugin, index === 0).setParent(actions);
		this.#pluginActionButton(t('plugins.moveDown', 'Bajar'), 'fa-arrow-down', 'slate', 'move-down', plugin, index === total - 1).setParent(actions);

		return actions;
	}

	#pluginActionButton(label, icon, tone, action, plugin, disabled = false) {
		const button = DynamicTable.buildActionButton({
			label,
			icon,
			tone,
			dataRole: 'plugin-action',
			dataAction: action,
			onClick: () => this.#handlePluginAction(action, plugin, button),
			disabled,
		});

		button.dataset.slug = String(plugin.slug ?? '');
		return button;
	}

	#handlePluginAction(action, plugin, button) {
		if (action === 'configure') {
			this.#onConfigure(plugin);
			return;
		}

		if (action === 'update') {
			this.#handleUpdate(plugin, button);
			return;
		}

		if (action === 'rollback') {
			this.#handleRollback(plugin, button);
			return;
		}

		if (action === 'delete') {
			this.#handleDelete(plugin, button);
			return;
		}

		if (action === 'move-up' || action === 'move-down') {
			this.#handleMove(action, plugin, button);
			return;
		}

		this.#handleActionClick(plugin, button);
	}

	#canRollback(plugin) {
		return plugin.can_rollback === true
			|| plugin.can_rollback === 't'
			|| plugin.can_rollback === 1
			|| plugin.can_rollback === '1';
	}

	#handleActionClick(plugin, button) {
		this.#feedbackMessage = null;
		this.#feedbackType = null;

		const newStatus = button.dataset.action === 'activate' ? 'active' : 'inactive';
		UiResilienceService.setButtonPending(button, newStatus === 'active' ? `${t('plugins.activate', 'Activar')}...` : `${t('plugins.deactivate', 'Desactivar')}...`);

		this.#api.put(`/plugins/${plugin.slug}/status`, { status: newStatus })
			.then(async (response) => {
				if (response?.ok === false) {
					throw new Error(response.error?.message ?? 'No se pudo actualizar el estado');
				}

				UiResilienceService.showNotification({
					type: 'success',
					title: t('ui.success', 'Éxito'),
					message: `Plugin "${plugin.name || plugin.slug}" ${newStatus === 'active' ? 'activado' : 'desactivado'} correctamente.`,
				});
				await this.#refreshData();
				this.#onPluginsChanged(plugin);
			})
			.catch((error) => {
				UiResilienceService.clearButtonPending(button, newStatus === 'active' ? t('plugins.activate', 'Activar') : t('plugins.deactivate', 'Desactivar'));
				this.renderError(`No se pudo actualizar el plugin: ${error.message}`);
			});
	}

	async #handleSync(button) {
		this.#feedbackMessage = null;
		this.#feedbackType = null;
		UiResilienceService.setButtonPending(button, `${t('plugins.sync', 'Sincronizar')}...`);

		try {
			const response = await this.#api.post('/plugins/sync', {});
			const payload = response?.data ?? response;
			const outdated = Number(payload?.summary?.outdated ?? 0);
			this.#feedbackType = 'success';
			this.#feedbackMessage = `Sincronización completada. Plugins con actualizaciones disponibles: ${outdated}.`;
			UiResilienceService.showNotification({
				type: 'success',
				title: t('ui.success', 'Éxito'),
				message: this.#feedbackMessage,
			});
			await this.#refreshData();
			this.#onPluginsChanged();
		} catch (error) {
			this.renderError(`No se pudieron sincronizar los plugins: ${error.message}`);
		} finally {
			UiResilienceService.clearButtonPending(button, t('plugins.sync', 'Sincronizar'));
		}
	}

	async #handleUpdate(plugin, button) {
		const accepted = await this.#confirmAction(
			'Confirmar actualización del plugin',
			`¿Actualizar el plugin "${plugin.name || plugin.slug}" a la última versión disponible?`,
			'Actualizar'
		);

		if (!accepted) {
			return;
		}

		this.#feedbackMessage = null;
		this.#feedbackType = null;
		UiResilienceService.setButtonPending(button, 'Actualizando...');

		try {
			await this.#api.post(`/plugins/${plugin.slug}/update`, {});
			this.#feedbackType = 'success';
			this.#feedbackMessage = `Plugin "${plugin.name || plugin.slug}" actualizado correctamente.`;
			UiResilienceService.showNotification({
				type: 'success',
				title: t('ui.success', 'Éxito'),
				message: this.#feedbackMessage,
			});
			await this.#refreshData();
			this.#onPluginsChanged(plugin);
		} catch (error) {
			this.renderError(`No se pudo actualizar el plugin: ${error.message}`);
		} finally {
			UiResilienceService.clearButtonPending(button, 'Actualizar');
		}
	}

	async #handleRollback(plugin, button) {
		const accepted = await this.#confirmAction(
			'Confirmar reversión del plugin',
			`¿Revertir el plugin "${plugin.name || plugin.slug}" a la versión anterior?`,
			'Revertir'
		);

		if (!accepted) {
			return;
		}

		this.#feedbackMessage = null;
		this.#feedbackType = null;
		UiResilienceService.setButtonPending(button, 'Revirtiendo...');

		try {
			await this.#api.post(`/plugins/${plugin.slug}/rollback`, {});
			this.#feedbackType = 'success';
			this.#feedbackMessage = `Plugin "${plugin.name || plugin.slug}" revertido correctamente.`;
			UiResilienceService.showNotification({
				type: 'success',
				title: t('ui.success', 'Éxito'),
				message: this.#feedbackMessage,
			});
			await this.#refreshData();
			this.#onPluginsChanged(plugin);
		} catch (error) {
			this.renderError(`No se pudo revertir el plugin: ${error.message}`);
		} finally {
			UiResilienceService.clearButtonPending(button, 'Revertir');
		}
	}

	async #handleDelete(plugin, button) {
		const name = plugin.name || plugin.slug;
		const accepted = await this.#confirmAction(
			'Confirmar borrado de plugin',
			`Esto borrará el plugin "${name}" y todos los datos asociados a "${name}". Esta acción es irreversible.`,
			'Borrar plugin'
		);

		if (!accepted) {
			return;
		}

		this.#feedbackMessage = null;
		this.#feedbackType = null;
		UiResilienceService.setButtonPending(button, 'Borrando...');

		try {
			await this.#api.delete(`/plugins/${plugin.slug}`);
			this.#feedbackType = 'success';
			this.#feedbackMessage = `Plugin "${name}" borrado correctamente.`;
			UiResilienceService.showNotification({
				type: 'success',
				title: t('ui.success', 'Éxito'),
				message: this.#feedbackMessage,
			});
			await this.#refreshData();
			this.#onPluginsChanged(plugin);
		} catch (error) {
			this.renderError(`No se pudo borrar el plugin: ${error.message}`);
		} finally {
			UiResilienceService.clearButtonPending(button, 'Borrar');
		}
	}

	async #handleMove(action, plugin, button) {
		this.#feedbackMessage = null;
		this.#feedbackType = null;
		UiResilienceService.setButtonPending(button, `${t('plugins.moving', 'Reordenando')}...`);

		try {
			const endpoint = action === 'move-up' ? 'move-up' : 'move-down';
			await this.#api.post(`/plugins/${plugin.slug}/${endpoint}`, {});
			await this.#refreshData();
			this.#onPluginsChanged(plugin);
		} catch (error) {
			this.renderError(`No se pudo reordenar el plugin: ${error.message}`);
		} finally {
			UiResilienceService.clearButtonPending(button, action === 'move-up' ? t('plugins.moveUp', 'Subir') : t('plugins.moveDown', 'Bajar'));
		}
	}

	#confirmAction(title, message, confirmLabel) {
		return UiResilienceService.confirm({
			container: this.#container,
			title,
			message,
			confirmLabel,
			cancelLabel: t('ui.actions.cancel', 'Cancelar'),
		});
	}

	renderError(message) {
		this.#container.replaceChildren();
		const layout = ListLayout.create(this.#container, { shell: this.#shellLayout })
			.setTitle(this.#title)
			.setDescription(this.#description)
			.build();
		UiResilienceService.showNotification({
			type: 'error',
			title: t('ui.error.title', 'Error'),
			message,
		});
		const banner = component.create('alert', {
			type: 'error',
			message,
		}).setData('role', 'plugin-error').setData('type', 'error');
		layout.setNotification(banner);
	}

	resolveContainer(container) {
		if (container instanceof HTMLElement) {
			return container;
		}
		const found = document.querySelector(container);
		if (found instanceof HTMLElement) {
			return found;
		}
		throw new TypeError(`PluginManager container "${String(container)}" not found`);
	}
}
