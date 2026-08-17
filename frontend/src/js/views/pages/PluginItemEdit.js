/**
 * PluginItemEdit.js — Standalone, navigable page for creating/editing a
 * single item of an extension plugin (STORY 10.5, e.g. one optometries
 * ficha). Routed at #/entity/:slug/:id/:tab/:itemId (or /#new).
 *
 * Generic across extension plugins: it discovers the plugin's own endpoint,
 * relations, and additional-field metadata from GET /entities/:slug/tabs
 * (the same payload Hooks.php already builds for the inline tab), then
 * dynamically imports the plugin's plugin.js and calls its exported
 * buildDetailForm(content, relations, loadOptions, extraFields) to get the
 * actual form — the plugin still owns its own field layout, this page only
 * owns navigation/persistence/chrome.
 */

import { FormLayout } from '../layout/FormLayout.js';
import { component } from '../modules/ComponentFactory.js';
import { buildPluginModuleUrl } from '../../models/BasePathModel.js';
import { UiResilienceService } from '../../services/UiResilienceService.js';
import { t } from '../../models/I18nModel.js';

export class PluginItemEdit {
  #container;
  #api;
  #shellLayout;
  #entitySlug;
  #recordId;
  #pluginSlug;
  #itemId;
  #title;
  #description;
  #onDone;
  #panel;
  #layout;

  /**
   * @param {HTMLElement} container
   * @param {{
   *   api: object, shellLayout?: object|null, entitySlug: string, recordId: string,
   *   pluginSlug: string, itemId: string|null, title?: string, description?: string,
   *   onDone: () => Promise<void>
   * }} options
   */
  constructor(container, options = {}) {
    this.#container = container;
    this.#api = options.api;
    this.#shellLayout = options.shellLayout ?? null;
    this.#entitySlug = options.entitySlug;
    this.#recordId = options.recordId;
    this.#pluginSlug = options.pluginSlug;
    this.#itemId = typeof options.itemId === 'string' && options.itemId !== '' ? options.itemId : null;
    this.#title = typeof options.title === 'string' ? options.title : '';
    this.#description = typeof options.description === 'string' ? options.description : '';
    this.#onDone = typeof options.onDone === 'function' ? options.onDone : (async () => {});
  }

  async init() {
    const layout = FormLayout.create(this.#container, { shell: this.#shellLayout })
      .setTitle(this.#title)
      .setDescription(this.#description)
      .build();
    this.#panel = layout.getPanel();
    this.#layout = layout;

    UiResilienceService.setViewState(this.#panel, {
      type: 'loading',
      title: t('ui.loading', 'Cargando…'),
      message: 'Estamos preparando la ficha.',
    });

    const tab = await this.#loadTab();
    if (tab === null) {
      this.#renderError('No se pudo cargar la configuración de esta extensión.');
      return;
    }

    const endpointTemplate = String(tab.endpoint ?? '').replace('{id}', this.#recordId);
    const relations = Array.isArray(tab.relations) ? tab.relations : [];
    const extraFields = Array.isArray(tab.fields) ? tab.fields : [];

    const content = await this.#loadContent(endpointTemplate);
    if (content === null) {
      this.#renderError(this.#itemId === null
        ? 'No se pudo preparar el formulario.'
        : 'No se encontró la ficha solicitada.');
      return;
    }

    let moduleExports;
    try {
      moduleExports = await import(buildPluginModuleUrl(this.#pluginSlug));
    } catch {
      moduleExports = null;
    }

    if (moduleExports === null || typeof moduleExports.buildDetailForm !== 'function') {
      this.#renderError('Este plugin no soporta edición en página independiente.');
      return;
    }

    UiResilienceService.clearViewState(this.#panel);
    this.#renderForm(moduleExports.buildDetailForm, content, relations, extraFields, endpointTemplate);
  }

  async #loadTab() {
    try {
      const { data } = await this.#api.get(`/entities/${this.#entitySlug}/tabs`);
      const tabs = Array.isArray(data?.tabs) ? data.tabs : [];
      return tabs.find((candidate) => candidate.plugin_name === this.#pluginSlug || candidate.id === this.#pluginSlug) ?? null;
    } catch {
      return null;
    }
  }

  /** @returns {Promise<Record<string, unknown>|null>} */
  async #loadContent(endpointTemplate) {
    if (this.#itemId === null) {
      return { date: new Date().toISOString().slice(0, 10) };
    }

    try {
      const { data } = await this.#api.get(endpointTemplate);
      const rows = Array.isArray(data) ? data : [];
      const found = rows.find((row) => row.id === this.#itemId);
      if (found === undefined) {
        return null;
      }
      return (found.content && typeof found.content === 'object') ? { ...found.content } : {};
    } catch {
      return null;
    }
  }

  #renderForm(buildDetailForm, content, relations, extraFields, endpointTemplate) {
    const optionsCache = new Map();
    const loadOptions = (targetEntity) => {
      if (!optionsCache.has(targetEntity)) {
        optionsCache.set(
          targetEntity,
          this.#api.get(`/entities/${targetEntity}/options`)
            .then(({ data }) => (Array.isArray(data) ? data : []))
            .catch(() => [])
        );
      }
      return optionsCache.get(targetEntity);
    };

    this.#panel.replaceChildren(buildDetailForm(content, relations, loadOptions, extraFields));

    // Actions go through layout.addAction() — with a shell they land in
    // shell-main-actions, exactly like EntityEdit/PluginConfig do, instead
    // of a hand-rolled bar inside the form panel. Same order/variants as
    // EntityEdit: Guardar, Cancelar, Eliminar.
    this.#layout.addAction(component.create('button', {
      label: t('forms.save', 'Guardar'),
      variant: 'primary',
      size: 'md',
      dataRole: 'plugin-item-save',
      onClick: () => void this.#save(endpointTemplate, content),
    }));
    this.#layout.addAction(component.create('button', {
      label: t('ui.actions.cancel', 'Cancelar'),
      variant: 'secondary',
      size: 'md',
      dataRole: 'plugin-item-cancel',
      onClick: () => void this.#onDone(),
    }));
    if (this.#itemId !== null) {
      this.#layout.addAction(component.create('button', {
        label: 'Eliminar',
        variant: 'danger',
        size: 'md',
        dataRole: 'plugin-item-delete',
        onClick: () => void this.#delete(endpointTemplate),
      }));
    }
  }

  async #save(endpointTemplate, content) {
    try {
      if (this.#itemId === null) {
        await this.#api.post(endpointTemplate, content);
      } else {
        await this.#api.put(`${endpointTemplate}/${this.#itemId}`, content);
      }
      UiResilienceService.showNotification({
        type: 'success',
        title: t('ui.success', 'Éxito'),
        message: 'Ficha guardada correctamente.',
      });
      await this.#onDone();
    } catch (err) {
      const details = Array.isArray(err?.details) ? err.details : [];
      const detailMessage = details.length > 0
        ? details.map((d) => d?.message).filter(Boolean).join(' ')
        : '';
      UiResilienceService.showNotification({
        type: 'error',
        title: t('ui.error.title', 'Error'),
        message: detailMessage !== '' ? detailMessage : (err?.message ?? 'No se pudo guardar la ficha.'),
      });
    }
  }

  async #delete(endpointTemplate) {
    const accepted = await UiResilienceService.confirm({
      container: this.#container,
      title: 'Confirmar borrado',
      message: 'Se eliminará esta ficha. Esta acción no se puede deshacer.',
      confirmLabel: 'Borrar',
      cancelLabel: 'Cancelar',
    });
    if (!accepted) {
      return;
    }

    try {
      await this.#api.delete(`${endpointTemplate}/${this.#itemId}`);
      UiResilienceService.showNotification({
        type: 'success',
        title: t('ui.success', 'Éxito'),
        message: 'Ficha eliminada correctamente.',
      });
      await this.#onDone();
    } catch (err) {
      UiResilienceService.showNotification({
        type: 'error',
        title: t('ui.error.title', 'Error'),
        message: err?.message ?? 'No se pudo eliminar la ficha.',
      });
    }
  }

  #renderError(message) {
    UiResilienceService.setViewState(this.#panel, {
      type: 'error',
      title: t('ui.error.title', 'Error'),
      message,
    });
  }
}
