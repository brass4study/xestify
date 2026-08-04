/**
 * PluginConfig — Admin UI for configuring active entity and extension plugins.
 *
 * Renders all configurable fields as a table and allows:
 * - toggle active state
 * - edit suggested/additional definitions
 * - reorder rows
 */

import { Api } from '../modules/Api.js';

export class PluginConfig {
  /** @type {HTMLElement} */
  #container;

  /** @type {Api} */
  #api;

  /** @type {string} */
  #slug;

  /** @type {() => void} */
  #onBack;

  /** @type {{ plugin: Object, config: { fields: Array<Object>, target_entity?: string } } | null} */
  #state = null;

  /** @type {string} */
  #message = '';

  /** @type {'success' | 'error' | ''} */
  #messageType = '';

  /**
   * @param {HTMLElement|string} container
   * @param {{ slug: string, api?: Api, onBack?: () => void }} options
   */
  constructor(container, options) {
    this.#container = this.#resolveContainer(container);
    this.#slug = String(options?.slug ?? '');
    this.#api = options?.api ?? new Api();
    this.#onBack = typeof options?.onBack === 'function' ? options.onBack : () => {};
  }

  async init() {
    if (this.#slug === '') {
      this.#renderError('Plugin slug is required.');
      return;
    }

    try {
      const { data } = await this.#api.get(`/plugins/${this.#slug}/config`);
      const pluginType = String(data?.plugin?.plugin_type ?? '');
      const entityOptions = pluginType === 'extension'
        ? await this.#loadActiveEntityOptions()
        : [];
      this.#state = this.#buildStateFromResponse(data, entityOptions);
      this.#render();
    } catch (error) {
      this.#renderError(`No se pudo cargar la configuracion: ${error.message}`);
    }
  }

  #render() {
    if (this.#state === null) {
      this.#renderError('No configuration data available.');
      return;
    }

    const plugin = this.#state.plugin;
    const rows = this.#state.config.fields;
    const isExtension = this.#isExtensionPlugin();
    const targetEntity = String(this.#state.config.target_entity ?? '*');
    const entityOptions = Array.isArray(this.#state.config.entity_options)
      ? this.#state.config.entity_options
      : [];
    const allTargetOptionsHtml = this.#buildTargetOptionsHtml(entityOptions, targetEntity);
    const fieldsHelp = isExtension
      ? 'Define los campos de la extension y a que entidad se aplica. Desactiva una fila para excluirla del schema activo.'
      : 'Reordena, activa/desactiva y ajusta los campos sugeridos. Los campos base obligatorios son visibles pero bloqueados.';

    const wrapper = document.createElement('section');
    wrapper.className = 'xt-plugin-config';
    wrapper.innerHTML = `
      <header class="xt-plugin-config__header">
        <div>
          <h2>Configurar plugin: ${this.#escapeHtml(plugin.name || plugin.slug || this.#slug)}</h2>
          <p class="xt-plugin-config__meta">
            slug: ${this.#escapeHtml(plugin.slug || this.#slug)} | version: ${this.#escapeHtml(plugin.version || '-')} | schema v${this.#escapeHtml(String(plugin.schema_version ?? '-'))}
          </p>
        </div>
      </header>

      ${this.#message === '' ? '' : `<div class="xt-plugin-config__notice xt-plugin-config__notice--${this.#messageType}">${this.#escapeHtml(this.#message)}</div>`}

      <section class="xt-plugin-config__section">
        ${isExtension
          ? `<h3>Relacion de extension</h3>
        <p class="xt-plugin-config__help">Selecciona la entidad destino o <strong>Todos</strong> para aplicar la extension globalmente.</p>
        <select class="xt-plugin-config__input" data-name="target-entity">
            <option value="*" ${this.#selectedAttr('*', targetEntity)}>Todos</option>
            ${allTargetOptionsHtml}
        </select>`
          : ''}

        <h3>Campos</h3>
        <p class="xt-plugin-config__help">${fieldsHelp}</p>
        <div class="xt-plugin-config__table-wrap">
          <table class="xt-plugin-config__table">
            <thead>
              <tr>
                <th>Activo</th>
                <th>Clase</th>
                <th>Clave</th>
                <th>Tipo</th>
                <th>Etiqueta</th>
                <th>Requerido</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody data-role="fields-body"></tbody>
          </table>
        </div>
        <button type="button" class="xt-plugin-config__btn xt-plugin-config__btn--secondary" data-action="add-field">Anadir campo</button>
      </section>

      <footer class="xt-plugin-config__actions">
        <button type="button" class="xt-plugin-config__btn xt-plugin-config__btn--secondary" data-action="back">Volver</button>
        <button type="button" class="xt-plugin-config__btn xt-plugin-config__btn--primary" data-action="save">Guardar</button>
      </footer>
    `;

    const tbody = wrapper.querySelector('[data-role="fields-body"]');
    tbody.innerHTML = rows.map((field, index) => this.#renderRow(field, index)).join('');

    this.#bindTableEvents(wrapper);
    this.#bindPageActions(wrapper);

    this.#container.innerHTML = '';
    this.#container.appendChild(wrapper);
  }

  #renderRow(field, index) {
    const locked = field.locked === true;
    const source = String(field.source ?? 'additional');
    const isBase = source === 'base';
    const immutableField = locked || isBase;
    const keyReadonly = immutableField || source === 'suggested';
    const editable = !immutableField;

    let sourceBadge = '<span class="xt-plugin-config__badge xt-plugin-config__badge--additional">adicional</span>';
    if (source === 'base') {
      sourceBadge = '<span class="xt-plugin-config__badge xt-plugin-config__badge--base">base</span>';
    } else if (source === 'suggested') {
      sourceBadge = '<span class="xt-plugin-config__badge xt-plugin-config__badge--suggested">sugerido</span>';
    }

    return `
      <tr data-row-index="${index}">
        <td>
          <input type="checkbox" data-name="active" ${field.active ? 'checked' : ''} ${immutableField ? 'disabled' : ''}>
        </td>
        <td>
          ${sourceBadge}
        </td>
        <td>
          <input type="text" data-name="key" value="${this.#escapeHtml(field.key || '')}" ${keyReadonly ? 'readonly' : ''}>
        </td>
        <td>
          <select data-name="type" ${editable ? '' : 'disabled'}>
            ${this.#typeOptions(field.type || 'string')}
          </select>
        </td>
        <td>
          <input type="text" data-name="label" value="${this.#escapeHtml(field.label || '')}" ${editable ? '' : 'readonly'}>
        </td>
        <td>
          <input type="checkbox" data-name="required" ${field.required ? 'checked' : ''} ${editable ? '' : 'disabled'}>
        </td>
        <td class="xt-plugin-config__actions-cell">
          <button type="button" class="xt-plugin-config__btn xt-plugin-config__btn--icon" data-action="move-up" data-row-index="${index}" ${index === 0 ? 'disabled' : ''}>↑</button>
          <button type="button" class="xt-plugin-config__btn xt-plugin-config__btn--icon" data-action="move-down" data-row-index="${index}" ${index === this.#state.config.fields.length - 1 ? 'disabled' : ''}>↓</button>
          ${immutableField
            ? ''
            : `<button type="button" class="xt-plugin-config__btn xt-plugin-config__btn--danger" data-action="remove-row" data-row-index="${index}">Eliminar</button>`}
        </td>
      </tr>
    `;
  }

  #bindTableEvents(wrapper) {
    wrapper.querySelectorAll('[data-action="move-up"]').forEach((button) => {
      button.addEventListener('click', () => {
        const index = Number(button.dataset.rowIndex);
        this.#moveRow(index, -1);
      });
    });

    wrapper.querySelectorAll('[data-action="move-down"]').forEach((button) => {
      button.addEventListener('click', () => {
        const index = Number(button.dataset.rowIndex);
        this.#moveRow(index, 1);
      });
    });

    wrapper.querySelectorAll('[data-action="remove-row"]').forEach((button) => {
      button.addEventListener('click', () => {
        const index = Number(button.dataset.rowIndex);
        this.#state.config.fields.splice(index, 1);
        this.#clearNotice();
        this.#render();
      });
    });
  }

  #moveRow(index, delta) {
    const rows = this.#state.config.fields;
    const target = index + delta;
    if (target < 0 || target >= rows.length) {
      return;
    }

    const tmp = rows[index];
    rows[index] = rows[target];
    rows[target] = tmp;
    this.#clearNotice();
    this.#render();
  }

  async #saveFromDom(wrapper) {
    try {
      const payload = this.#buildPayloadFromDom(wrapper);
      const { data } = await this.#api.put(`/plugins/${this.#slug}/config`, payload);

      this.#applySavedState(data);

      this.#message = 'Configuracion guardada correctamente.';
      this.#messageType = 'success';
      this.#render();
    } catch (error) {
      this.#message = `Error al guardar: ${error.message}`;
      this.#messageType = 'error';
      this.#render();
    }
  }

  #clearNotice() {
    this.#message = '';
    this.#messageType = '';
  }

  #renderError(message) {
    const banner = document.createElement('div');
    banner.className = 'xt-plugin-config__notice xt-plugin-config__notice--error';
    banner.textContent = message;
    this.#container.innerHTML = '';
    this.#container.appendChild(banner);
  }

  #typeOptions(selected) {
    const types = ['string', 'text', 'number', 'boolean', 'date', 'timestamp', 'email', 'select', 'uuid'];
    return types
      .map((type) => `<option value="${type}" ${type === selected ? 'selected' : ''}>${type}</option>`)
      .join('');
  }

  #bindPageActions(wrapper) {
    wrapper.querySelector('[data-action="add-field"]').addEventListener('click', () => {
      this.#state.config.fields.push(this.#createEmptyField());
      this.#clearNotice();
      this.#render();
    });

    wrapper.querySelector('[data-action="back"]').addEventListener('click', () => {
      this.#onBack();
    });

    wrapper.querySelector('[data-action="save"]').addEventListener('click', async () => {
      await this.#saveFromDom(wrapper);
    });
  }

  #createEmptyField() {
    return {
      active: true,
      key: '',
      type: 'string',
      label: '',
      required: false,
      locked: false,
      source: 'additional',
    };
  }

  #buildTargetOptionsHtml(entityOptions, targetEntity) {
    const hasCurrentTarget = entityOptions.some((entity) => entity.slug === targetEntity);
    const allTargetOptions = hasCurrentTarget || targetEntity === '*'
      ? entityOptions
      : [...entityOptions, { slug: targetEntity, label: `${targetEntity} (no activa)` }];

    return allTargetOptions
      .map((entity) => {
        const selectedAttr = this.#selectedAttr(entity.slug, targetEntity);
        return `<option value="${this.#escapeHtml(entity.slug)}" ${selectedAttr}>${this.#escapeHtml(entity.label)}</option>`;
      })
      .join('');
  }

  #buildStateFromResponse(data, entityOptions = []) {
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

  #buildPayloadFromDom(wrapper) {
    const payload = { fields: this.#collectRowsFromDom(wrapper) };
    if (this.#isExtensionPlugin()) {
      const targetEntityInput = wrapper.querySelector('[data-name="target-entity"]');
      payload.target_entity = targetEntityInput ? targetEntityInput.value.trim() : '';
    }

    return payload;
  }

  #collectRowsFromDom(wrapper) {
    const rows = [];
    wrapper.querySelectorAll('.xt-plugin-config__table tbody tr[data-row-index]').forEach((rowEl) => {
      rows.push(this.#readRowFromDom(rowEl));
    });

    return rows;
  }

  #readRowFromDom(rowEl) {
    const index = Number(rowEl.dataset.rowIndex);
    const original = this.#state.config.fields[index] ?? {};

    const keyInput = rowEl.querySelector('[data-name="key"]');
    const labelInput = rowEl.querySelector('[data-name="label"]');
    const typeSelect = rowEl.querySelector('[data-name="type"]');
    const activeCheckbox = rowEl.querySelector('[data-name="active"]');
    const requiredCheckbox = rowEl.querySelector('[data-name="required"]');

    return {
      active: activeCheckbox ? !!activeCheckbox.checked : false,
      key: keyInput ? keyInput.value.trim() : '',
      type: typeSelect ? typeSelect.value : 'string',
      label: labelInput ? labelInput.value.trim() : '',
      required: requiredCheckbox ? !!requiredCheckbox.checked : false,
      locked: original.locked === true,
      source: String(original.source ?? 'additional'),
    };
  }

  #applySavedState(data) {
    const entityOptions = Array.isArray(this.#state?.config?.entity_options)
      ? this.#state.config.entity_options
      : [];
    const nextState = this.#buildStateFromResponse(data, entityOptions);
    nextState.plugin = data?.plugin ?? this.#state.plugin;
    this.#state = nextState;
  }

  #isExtensionPlugin() {
    return String(this.#state?.plugin?.plugin_type ?? '') === 'extension';
  }

  async #loadActiveEntityOptions() {
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

  #selectedAttr(value, selectedValue) {
    if (value === selectedValue) {
      return 'selected';
    }

    return '';
  }

  #resolveContainer(container) {
    if (container instanceof HTMLElement) {
      return container;
    }

    const found = document.querySelector(container);
    if (found instanceof HTMLElement) {
      return found;
    }

    throw new TypeError(`PluginConfig container "${String(container)}" not found`);
  }

  #escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    };
    return String(text ?? '').replaceAll(/[&<>"']/g, (char) => map[char]);
  }
}
