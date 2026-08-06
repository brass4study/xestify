/**
 * PluginConfig — Admin UI for configuring active entity and extension plugins.
 *
 * Renders all configurable fields as a table and allows:
 * - toggle active state
 * - edit suggested/additional definitions
 * - reorder rows
 */

import { Api } from '../modules/Api.js';
import { DynamicTable } from '../modules/DynamicTable.js';

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
    const isExtension = this.#isExtensionPlugin();
    const targetEntity = String(this.#state.config.target_entity ?? '*');
    const entityOptions = Array.isArray(this.#state.config.entity_options)
      ? this.#state.config.entity_options
      : [];
    const allTargetOptionsHtml = this.#buildTargetOptionsHtml(entityOptions, targetEntity);
    const fieldsHelp = isExtension
      ? 'Define los campos de la extension y a que entidad se aplica. Desactiva una fila para excluirla del schema activo.'
      : 'Reordena, activa/desactiva y ajusta los campos sugeridos. Los campos base obligatorios son visibles pero bloqueados.';
    const feedbackTone = this.#messageType === 'error'
      ? 'border-red-200 bg-red-50 text-red-700'
      : 'border-emerald-200 bg-emerald-50 text-emerald-700';
    const feedbackHtml = this.#message === ''
      ? ''
      : `<div class="${feedbackTone} mt-3 rounded-lg border px-3 py-2 text-sm">${this.#escapeHtml(this.#message)}</div>`;

    const wrapper = document.createElement('section');
    wrapper.className = 'rounded-2xl border border-slate-200 bg-white p-4 shadow-panel';
    wrapper.innerHTML = `
      <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-xl font-semibold tracking-tight text-slateui-950">Configurar plugin: ${this.#escapeHtml(plugin.name || plugin.slug || this.#slug)}</h2>
          <p class="mt-1 text-sm text-slate-500">
            slug: ${this.#escapeHtml(plugin.slug || this.#slug)} | version: ${this.#escapeHtml(plugin.version || '-')} | schema v${this.#escapeHtml(String(plugin.schema_version ?? '-'))}
          </p>
        </div>
      </header>

      ${feedbackHtml}

      <section class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
        ${isExtension
          ? `<h3>Relacion de extension</h3>
        <p class="mt-1 text-sm text-slate-600">Selecciona la entidad destino o <strong>Todos</strong> para aplicar la extension globalmente.</p>
        <select class="mt-2 w-full rounded-lg border-slate-300 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500 sm:max-w-sm" data-name="target-entity">
            <option value="*" ${this.#selectedAttr('*', targetEntity)}>Todos</option>
            ${allTargetOptionsHtml}
        </select>`
          : ''}

        <h3 class="${isExtension ? 'mt-5' : 'mt-0'} text-base font-semibold text-slateui-950">Campos</h3>
        <p class="mt-1 text-sm text-slate-600">${fieldsHelp}</p>
        <div class="mt-3" data-role="fields-table-host"></div>
        <button type="button" class="mt-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" data-action="add-field" data-role="plugin-config-action">Añadir campo</button>
      </section>

      <footer class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" data-action="back" data-role="plugin-config-action">Volver</button>
        <button type="button" class="inline-flex items-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700" data-action="save" data-role="plugin-config-action">Guardar</button>
      </footer>
    `;

    const tableHost = wrapper.querySelector('[data-role="fields-table-host"]');
    if (tableHost instanceof HTMLElement) {
      this.#renderFieldsTable(tableHost);
    }

    this.#bindTableEvents(wrapper);
    this.#bindPageActions(wrapper);

    this.#container.innerHTML = '';
    this.#container.appendChild(wrapper);
  }

  #renderFieldsTable(container) {
    const rows = this.#state?.config?.fields ?? [];
    const records = rows.map((field, index) => ({
      ...field,
      __rowIndex: index,
    }));

    const headerClassName = 'border-b border-slate-200 bg-slate-50 px-2 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600';
    const baseCellClassName = 'border-b border-slate-100 px-2 py-2';
    const centeredCellClassName = 'border-b border-slate-100 px-2 py-2 text-center';
    const actionsCellClassName = 'border-b border-slate-100 px-2 py-2 whitespace-nowrap';

    const table = new DynamicTable(records, { fields: [] }, container, {
      showPagination: false,
      wrapperClassName: 'overflow-x-auto rounded-xl border border-slate-200 bg-white',
      tableClassName: 'min-w-[920px] w-full border-separate border-spacing-0 text-left',
      tableDataRole: 'plugin-config-table',
      extraColumns: [
        {
          label: 'Activo',
          headerClassName,
          cellClassName: centeredCellClassName,
          renderCell: (field) => this.#renderActiveInput(field),
        },
        {
          label: 'Clase',
          headerClassName,
          cellClassName: baseCellClassName,
          renderCell: (field) => this.#renderSourceBadge(field),
        },
        {
          label: 'Clave',
          headerClassName,
          cellClassName: baseCellClassName,
          renderCell: (field) => this.#renderKeyInput(field),
        },
        {
          label: 'Tipo',
          headerClassName,
          cellClassName: baseCellClassName,
          renderCell: (field) => this.#renderTypeSelect(field),
        },
        {
          label: 'Etiqueta',
          headerClassName,
          cellClassName: baseCellClassName,
          renderCell: (field) => this.#renderLabelInput(field),
        },
        {
          label: 'Requerido',
          headerClassName,
          cellClassName: centeredCellClassName,
          renderCell: (field) => this.#renderRequiredInput(field),
        },
        {
          label: 'Cabecera',
          headerClassName,
          cellClassName: centeredCellClassName,
          renderCell: (field) => this.#renderSummaryViewInput(field),
        },
        {
          label: 'Acciones',
          headerClassName,
          cellClassName: actionsCellClassName,
          renderCell: (field) => this.#renderActions(field),
        },
      ],
      rowDecorator: (row, field) => {
        row.dataset.rowIndex = String(field.__rowIndex ?? 0);
        row.className = (field.__rowIndex ?? 0) % 2 === 0 ? 'bg-white' : 'bg-slate-50/40';
      },
    });

    table.render();
  }

  #fieldMeta(field) {
    const locked = field.locked === true;
    const source = String(field.source ?? 'additional');
    const isBase = source === 'base';
    const immutableField = locked || isBase;
    const keyReadonly = immutableField || source === 'suggested';
    const editable = !immutableField;
    return { source, immutableField, keyReadonly, editable };
  }

  #renderSourceBadge(field) {
    const { source } = this.#fieldMeta(field);
    const badge = document.createElement('span');
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

  #renderActiveInput(field) {
    const { immutableField } = this.#fieldMeta(field);
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.dataset.name = 'active';
    input.checked = field.active === true;
    input.disabled = immutableField;
    return input;
  }

  #renderKeyInput(field) {
    const { keyReadonly } = this.#fieldMeta(field);
    const input = document.createElement('input');
    input.className = 'w-full rounded-md border-slate-300 text-sm';
    input.type = 'text';
    input.dataset.name = 'key';
    input.value = String(field.key ?? '');
    input.readOnly = keyReadonly;
    return input;
  }

  #renderTypeSelect(field) {
    const { editable } = this.#fieldMeta(field);
    const select = document.createElement('select');
    select.className = 'w-full rounded-md border-slate-300 text-sm';
    select.dataset.name = 'type';
    select.disabled = !editable;
    select.innerHTML = this.#typeOptions(field.type || 'string');
    return select;
  }

  #renderLabelInput(field) {
    const { editable } = this.#fieldMeta(field);
    const input = document.createElement('input');
    input.className = 'w-full rounded-md border-slate-300 text-sm';
    input.type = 'text';
    input.dataset.name = 'label';
    input.value = String(field.label ?? '');
    input.readOnly = !editable;
    return input;
  }

  #renderRequiredInput(field) {
    const { editable } = this.#fieldMeta(field);
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.dataset.name = 'required';
    input.checked = field.required === true;
    input.disabled = !editable;
    return input;
  }

  #renderSummaryViewInput(field) {
    const { editable } = this.#fieldMeta(field);
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.dataset.name = 'summaryView';
    input.checked = field.summaryView !== false;
    input.disabled = !editable;
    return input;
  }

  #renderActions(field) {
    const { immutableField } = this.#fieldMeta(field);
    const actions = document.createElement('div');
    actions.className = 'flex flex-wrap items-center gap-2';
    const rowIndex = Number(field.__rowIndex ?? 0);
    const lastIndex = (this.#state?.config?.fields?.length ?? 1) - 1;

    actions.appendChild(this.#buildRowActionButton('Subir', 'fa-arrow-up', 'slate', 'move-up', rowIndex, rowIndex === 0));
    actions.appendChild(this.#buildRowActionButton('Bajar', 'fa-arrow-down', 'slate', 'move-down', rowIndex, rowIndex === lastIndex));

    if (!immutableField) {
      const removeButton = this.#buildRowActionButton('Eliminar', 'fa-trash', 'red', 'remove-row', rowIndex, false);
      removeButton.classList.add('ml-2');
      actions.appendChild(removeButton);
    }

    return actions;
  }

  #buildRowActionButton(label, icon, tone, action, rowIndex, disabled) {
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
    banner.className = 'rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700';
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
      summaryView: true,
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
    wrapper.querySelectorAll('[data-role="plugin-config-table"] tbody tr[data-row-index]').forEach((rowEl) => {
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
