/**
 * ContactLensesPanel — plugin-owned frontend for the contact_lenses
 * extension (STORY 10.5, Bloque 3).
 *
 * Self-registers in PluginPanelRegistry under the slug the instance was
 * installed with (defaults to 'contact_lenses').
 *
 * Panel contract:
 *   - get element(): HTMLElement   Mount point for EntityEdit.
 *   - flush(resolvedId): Promise   Persists pending POST/PUT/DELETE to the API.
 *
 * Data model: same generic HISTORY-of-fichas contract as
 * plugins/optometries/plugin.js (see that file's docblock) — one item per
 * saved lens-fitting record, a summary table for the inline tab (reuses
 * DynamicTable.normalizeColumns() exactly like EntityList: only fields with
 * summaryView!==false, Fecha/Notas by default, see schema.json), a much
 * richer per-item form for PluginItemEdit.js.
 *
 * The detail form is built LAYER BY LAYER exactly like optometries' (see
 * frontend/src/js/views/components/ExtensionLayerFields.js for the shared
 * orchestration primitives — groupByLayer/appendLayerTail/formFieldRow/
 * buildRelationField/buildGenericFieldInput are NOT duplicated here),
 * mirroring the manifest.json layers catalog (top / od / os / general):
 *   - top:     Fecha
 *   - od:      one COLUMN = axis gauge 'D' + right-eye measurement table
 *              (Contacto/Queratometría × Esfera/Cilindro/Eje/Adición, plus
 *              Radio/Diámetro/Uso/Pack/Marca/Fabricante/Distribuidor as
 *              extra full-width rows in the SAME table — see
 *              buildMeasurementTable()) + additional fields (origin:
 *              'additional', below the table)
 *   - os:      same, left eye
 *   - general: Notas
 * The 26 original fields + 6 relations stay hand-written/schema-declared
 * (their `layer` in schema.json is metadata that matches where this code
 * places them, not what drives it) — only origin:'additional' fields
 * (added later via PluginConfig's "Añadir campo") render generically, via
 * the shared buildGenericFieldInput().
 *
 * Every "standalone" row (Radio/Diámetro/Uso/Pack, and this eye's
 * relations — Marca/Fabricante/Distribuidor) behaves exactly like
 * optometries' Adición row: one extra row in the measurement table whose
 * single input spans the full row width (colSpan=4, merging Esfera+
 * Cilindro+Eje+Adición — same rowDecorator trick, just spanning one more
 * column here since Adición is its own column, not a shared row like
 * optometries').
 *
 * min/max and select options shown on the hand-written inputs below must
 * stay in sync with plugins/contact_lenses/schema.json by hand — those
 * never travel through the registerTabs tab payload.
 */

import { PluginPanelRegistry } from '../../js/models/PluginPanelModel.js';
import { component } from '../../js/views/modules/ComponentFactory.js';
import { AxisGauge } from '../../js/views/components/AxisGauge.js';
import { DynamicTable } from '../../js/views/modules/DynamicTable.js';
import { UiResilienceService } from '../../js/services/UiResilienceService.js';
import {
  COMPACT_INPUT_CLASSNAME,
  axisLines as sharedAxisLines,
  formFieldRow,
  groupByLayer,
  appendLayerTail,
  buildRelationField,
} from '../../js/views/components/ExtensionLayerFields.js';

const EYE_LETTER = { od: 'D', os: 'I' };

const SECTIONS = [
  { key: 'lens', label: 'Contacto', color: '#dc2626' },
  { key: 'keratometry', label: 'Queratometría', color: '#2563eb' },
];

const BOUNDS = {
  sphere: { min: -20, max: 20 },
  cylinder: { min: -10, max: 10 },
  axis: { min: 0, max: 180 },
  addition: { min: 0, max: 4 },
  base_curve: { min: 6, max: 10 },
  diameter: { min: 12, max: 16 },
};

const REPLACEMENT_SCHEDULE_OPTIONS = [
  { value: '', label: 'Ninguno' },
  { value: 'diario', label: 'Diario' },
  { value: 'quincenal', label: 'Quincenal' },
  { value: 'mensual', label: 'Mensual' },
  { value: 'trimestral', label: 'Trimestral' },
  { value: 'semestral', label: 'Semestral' },
  { value: 'anual', label: 'Anual' },
];

const PACK_OPTIONS = [
  { value: '', label: 'Ninguno' },
  { value: '1', label: '1' },
  { value: '3', label: '3' },
  { value: '6', label: '6' },
  { value: '10', label: '10' },
  { value: '30', label: '30' },
  { value: '90', label: '90' },
];

// ---------------------------------------------------------------------------
// Pure helpers
// ---------------------------------------------------------------------------

/**
 * @param {string} prefix 'od'|'os'
 * @param {Record<string, unknown>} content
 * @returns {Array<{ degrees: number|null, color: string }>}
 */
function axisLines(prefix, content) {
  return sharedAxisLines(prefix, content, SECTIONS);
}

// ---------------------------------------------------------------------------
// Layer builders
// ---------------------------------------------------------------------------

/**
 * Layer `top`: Fecha (+ anything else assigned to that layer).
 *
 * @param {Record<string, unknown>} content
 * @param {Array} relationsForLayer
 * @param {Array} extraFieldsForLayer
 * @param {(targetEntity: string) => Promise<Array>} loadOptions
 */
function buildTopLayer(content, relationsForLayer, extraFieldsForLayer, loadOptions) {
  const layer = document.createElement('div');
  layer.className = 'grid gap-4';

  const dateInput = component.create('inputDate', { name: 'date', value: content.date ?? '', required: true });
  dateInput.addEventListener('input', () => {
    content.date = dateInput.value;
  });
  layer.appendChild(formFieldRow('Fecha', dateInput, 'date'));

  appendLayerTail(layer, relationsForLayer, extraFieldsForLayer, content, loadOptions, { asFormField: true });
  return layer;
}

/**
 * Measurement table for one eye: 2 section rows (Contacto/Queratometría, 4
 * value columns each: Esfera/Cilindro/Eje/Adición) followed by one
 * standalone row per fixed field (Radio/Diámetro/Uso/Pack) and per relation
 * assigned to this eye's layer (Marca/Fabricante/Distribuidor) — all of
 * them behave exactly like optometries' Adición row: a single input placed
 * in the Esfera cell with colSpan=4 (merging Esfera+Cilindro+Eje+Adición —
 * one more column than optometries' colSpan=3, since here Adición is its
 * own column rather than a shared row), the other 3 cells removed. Built
 * on DynamicTable, same conventions as optometries' buildMeasurementTable():
 * no toolbar, compact density, no sortable columns, app-default header
 * styling.
 *
 * @param {string} prefix 'od'|'os'
 * @param {Record<string, unknown>} content
 * @param {() => void} onAxisChange
 * @param {Array<{ key: string, label: string, target_entity: string, required: boolean }>} relationsForLayer
 * @param {(targetEntity: string) => Promise<Array<{ id: string, label: string }>>} loadOptions
 */
function buildMeasurementTable(prefix, content, onAxisChange, relationsForLayer, loadOptions) {
  const host = document.createElement('div');

  const buildNumberInput = (key, bounds, isAxis) => {
    const input = component.create('inputNumber', { value: content[key] ?? '' });
    input.setAttributes({ min: bounds.min, max: bounds.max, step: 'any' });
    input.setClassName(COMPACT_INPUT_CLASSNAME);
    input.addEventListener('input', () => {
      content[key] = input.value === '' ? null : Number(input.value);
      if (isAxis) {
        onAxisChange();
      }
    });
    return input;
  };

  const buildStandaloneNumberInput = (record) => {
    const key = `${prefix}_${record.key}`;
    const input = component.create('inputNumber', { value: content[key] ?? '' });
    input.setAttributes({ min: record.bounds.min, max: record.bounds.max, step: 'any' });
    input.setClassName(COMPACT_INPUT_CLASSNAME);
    input.addEventListener('input', () => {
      content[key] = input.value === '' ? null : Number(input.value);
    });
    return input;
  };

  const buildStandaloneSelectInput = (record) => {
    const key = `${prefix}_${record.key}`;
    const input = component.create('inputSelect', { value: content[key] ?? '', options: record.options });
    input.setClassName(COMPACT_INPUT_CLASSNAME);
    input.getInput().addEventListener('input', () => {
      content[key] = input.value === '' ? null : input.value;
    });
    return input;
  };

  const buildStandaloneRelationInput = (record) => {
    const input = buildRelationField(record.relation, content, loadOptions);
    input.setClassName(COMPACT_INPUT_CLASSNAME);
    return input;
  };

  const records = [
    ...SECTIONS.map((section) => ({ key: section.key, label: section.label, isStandalone: false })),
    { key: 'base_curve', label: 'Radio', isStandalone: true, build: buildStandaloneNumberInput, bounds: BOUNDS.base_curve },
    { key: 'diameter', label: 'Diámetro', isStandalone: true, build: buildStandaloneNumberInput, bounds: BOUNDS.diameter },
    { key: 'replacement_schedule', label: 'Uso', isStandalone: true, build: buildStandaloneSelectInput, options: REPLACEMENT_SCHEDULE_OPTIONS },
    { key: 'pack', label: 'Pack', isStandalone: true, build: buildStandaloneSelectInput, options: PACK_OPTIONS },
    ...relationsForLayer.map((relation) => ({
      key: relation.key, label: relation.label, isStandalone: true, build: buildStandaloneRelationInput, relation,
    })),
  ];

  const extraColumns = [
    {
      key: 'row-label',
      label: '',
      position: 'start',
      renderCell: (record) => {
        const span = document.createElement('span');
        span.className = 'text-xs font-medium text-slate-500 whitespace-nowrap';
        span.textContent = record.label;
        return span;
      },
    },
    ...[['sphere', 'Esfera'], ['cylinder', 'Cilindro'], ['axis', 'Eje'], ['addition', 'Adición']].map(([col, label]) => ({
      key: col,
      label,
      position: 'start',
      renderCell: (record) => {
        if (record.isStandalone) {
          // Only the Esfera cell renders the standalone input; the other 3
          // cells are removed by the rowDecorator below (colSpan trick,
          // same mechanism as optometries' Adición row).
          return col === 'sphere' ? record.build(record) : '';
        }
        return buildNumberInput(`${prefix}_${record.key}_${col}`, BOUNDS[col], col === 'axis');
      },
    })),
  ];

  const table = new DynamicTable(records, { fields: {} }, host, {
    showPagination: false,
    showToolbar: false,
    density: 'compact',
    wrapperClassName: 'overflow-x-auto',
    tableClassName: 'w-full border-separate border-spacing-0 text-left',
    tableDataRole: 'contact-lenses-measurements',
    extraColumns,
    rowDecorator: (tr, record) => {
      if (!record.isStandalone) {
        return;
      }
      const cells = tr.querySelectorAll('td');
      if (cells.length === 5) {
        cells[1].colSpan = 4;
        cells[4].remove();
        cells[3].remove();
        cells[2].remove();
      }
    },
  });
  table.render();

  return host;
}

/**
 * Layer `od`/`os`: ONE column per eye — the axis gauge (bordered box, its
 * big D/I letter identifies the eye) stacked above the measurement table,
 * which itself includes Radio/Diámetro/Uso/Pack AND this eye's relations
 * (Marca/Fabricante/Distribuidor) as extra full-width rows (see
 * buildMeasurementTable()) — only additional fields (origin:'additional',
 * generic, no hand-written UI) still land below the table.
 *
 * @param {string} prefix 'od'|'os'
 * @param {AxisGauge} gauge
 * @param {Record<string, unknown>} content
 * @param {() => void} onAxisChange
 * @param {Array} relationsForLayer
 * @param {Array} extraFieldsForLayer
 * @param {(targetEntity: string) => Promise<Array>} loadOptions
 */
function buildEyeColumn(prefix, gauge, content, onAxisChange, relationsForLayer, extraFieldsForLayer, loadOptions) {
  const column = document.createElement('div');
  column.className = 'flex flex-col gap-3';

  const gaugeBox = document.createElement('div');
  gaugeBox.className = 'rounded-md border border-slate-200 p-2';
  gaugeBox.appendChild(gauge.element);
  column.appendChild(gaugeBox);

  column.appendChild(buildMeasurementTable(prefix, content, onAxisChange, relationsForLayer, loadOptions));

  appendLayerTail(column, [], extraFieldsForLayer, content, loadOptions);
  return column;
}

/**
 * Layer `general`: Notas, then this layer's relations (none by default —
 * all 6 ship as od/os), then additional fields — every field built as a
 * standard form field (label above, input below), same as any other form
 * in the app.
 *
 * @param {Record<string, unknown>} content
 * @param {Array} relationsForLayer
 * @param {Array} extraFieldsForLayer
 * @param {(targetEntity: string) => Promise<Array>} loadOptions
 */
function buildGeneralLayer(content, relationsForLayer, extraFieldsForLayer, loadOptions) {
  const layer = document.createElement('div');
  layer.className = 'grid gap-4 border-slate-200';

  const notesInput = component.create('inputTextArea', { name: 'notes', value: content.notes ?? '', rows: 3 });
  notesInput.addEventListener('input', () => {
    content.notes = notesInput.value;
  });
  layer.appendChild(formFieldRow('Notas', notesInput, 'notes'));

  appendLayerTail(layer, relationsForLayer, extraFieldsForLayer, content, loadOptions, { asFormField: true });
  return layer;
}

// ---------------------------------------------------------------------------
// Detail form (layer orchestration)
// ---------------------------------------------------------------------------

/**
 * Exported (not just used internally) — PluginItemEdit.js, the standalone
 * page that owns creating/editing one ficha (STORY 10.5), dynamically
 * imports this module and calls buildDetailForm() directly to get the same
 * form the inline tab panel used to build itself, without duplicating the
 * layers layout in two places.
 *
 * @param {Record<string, unknown>} content
 * @param {Array<{ key: string, label: string, target_entity: string, required: boolean, layer: string }>} relations
 * @param {(targetEntity: string) => Promise<Array<{ id: string, label: string }>>} loadOptions
 * @param {Array<{ key: string, type: string, label: string, required: boolean, layer: string, options: Array|null, min: number|null, max: number|null }>} extraFields
 *   fields added after install via PluginConfig's "Añadir campo" — no
 *   hand-written UI exists for these, so they render generically at the
 *   end of their assigned layer.
 */
export function buildDetailForm(content, relations, loadOptions, extraFields = []) {
  const form = document.createElement('div');
  form.className = 'flex flex-col gap-4';

  const relationsByLayer = groupByLayer(relations);
  const fieldsByLayer = groupByLayer(extraFields);

  form.appendChild(buildTopLayer(content, relationsByLayer.top, fieldsByLayer.top, loadOptions));

  const gaugeOd = new AxisGauge({ letter: EYE_LETTER.od });
  const gaugeOs = new AxisGauge({ letter: EYE_LETTER.os });
  const refreshGauges = () => {
    gaugeOd.setLines(axisLines('od', content));
    gaugeOs.setLines(axisLines('os', content));
  };
  refreshGauges();

  const eyesRow = document.createElement('div');
  eyesRow.className = 'grid grid-cols-1 gap-6 md:grid-cols-2';
  eyesRow.appendChild(buildEyeColumn('od', gaugeOd, content, refreshGauges, relationsByLayer.od, fieldsByLayer.od, loadOptions));
  eyesRow.appendChild(buildEyeColumn('os', gaugeOs, content, refreshGauges, relationsByLayer.os, fieldsByLayer.os, loadOptions));
  form.appendChild(eyesRow);

  form.appendChild(buildGeneralLayer(content, relationsByLayer.general, fieldsByLayer.general, loadOptions));

  return form;
}

// ---------------------------------------------------------------------------
// Panel
// ---------------------------------------------------------------------------

export class ContactLensesPanel {
  /** @type {HTMLElement} */
  #element;

  /**
   * @param {{
   *   endpoint: string,
   *   recordId: string|null,
   *   api: import('/src/js/models/ApiClientModel.js').Api,
   *   relations: Array<{ key: string, label: string, target_entity: string, required: boolean, layer: string }>,
   *   summaryFields?: Array<{ key: string, type: string, label: string, options: Array|null, summaryView: boolean }>,
   *   uiFieldOrder?: Array<string>,
   *   onNavigateToItem?: (itemId: string|null) => void
   * }} options
   */
  constructor({ endpoint, recordId, api, onNavigateToItem, summaryFields, uiFieldOrder }) {
    this.#element = this.#build(
      endpoint,
      recordId,
      api,
      typeof onNavigateToItem === 'function' ? onNavigateToItem : () => {},
      Array.isArray(summaryFields) ? summaryFields : [],
      Array.isArray(uiFieldOrder) ? uiFieldOrder : [],
    );
  }

  get element() {
    return this.#element;
  }

  async flush() {
    // Intentional no-op, kept for the PluginPanelRegistry panel contract —
    // same reasoning as OptometriesPanel.flush(): adding/editing a ficha
    // navigates to its own page (PluginItemEdit.js) and persists
    // immediately there.
  }

  /**
   * @param {string} endpointTemplate e.g. /plugins/contact_lenses/clients/{id}
   * @param {string|null} recordId
   * @param {object} api
   * @param {(itemId: string|null) => void} onNavigateToItem
   * @param {Array<{ key: string, type: string, label: string, options: Array|null, summaryView: boolean }>} summaryFields
   * @param {Array<string>} uiFieldOrder
   */
  #build(endpointTemplate, recordId, api, onNavigateToItem, summaryFields, uiFieldOrder) {
    const panel = document.createElement('div');
    panel.className = 'flex flex-col gap-4';

    const tableHost = document.createElement('div');
    /** @type {DynamicTable|null} */
    let table = null;
    // Same shape DynamicTable.normalizeColumns() expects from EntityList's
    // real entity schema: only fields with summaryView!==false become
    // columns, ordered by ui_field_order — no hardcoded Fecha/Notas here.
    const tableSchema = { fields: summaryFields, ui_field_order: uiFieldOrder };

    const toRecord = (item) => ({
      ...(item.content && typeof item.content === 'object' ? item.content : {}),
      id: item.id,
    });

    const renderTable = (items) => {
      const records = items.map(toRecord);
      const columns = [
        {
          key: 'delete-action',
          label: 'Acciones',
          position: 'end',
          shrink: true,
          renderCell: (record) => DynamicTable.buildActionButton({
            icon: 'fa-trash',
            tone: 'red',
            label: 'Borrar',
            dataRole: 'ficha-delete',
            onClick: (event) => {
              event.stopPropagation();
              void deleteItem(record.id);
            },
          }),
        },
      ];

      if (table === null) {
        table = new DynamicTable(records, tableSchema, tableHost, {
          showPagination: false,
          extraColumns: columns,
          rowDecorator: (tr, record) => {
            tr.classList.add('cursor-pointer');
            tr.tabIndex = 0;
            tr.setAttribute('role', 'button');
            const activate = () => onNavigateToItem(record.id);
            tr.addEventListener('click', activate);
            tr.addEventListener('keydown', (event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
              }
            });
          },
        });
      } else {
        table.setRecords(records);
      }
      table.render();
    };

    const deleteItem = async (itemId) => {
      const accepted = await UiResilienceService.confirm({
        container: panel,
        title: 'Confirmar borrado',
        message: 'Se eliminará esta ficha de lentillas. Esta acción no se puede deshacer.',
        confirmLabel: 'Borrar',
        cancelLabel: 'Cancelar',
      });
      if (!accepted || recordId === null) {
        return;
      }

      const resolved = endpointTemplate.replace('{id}', recordId);
      try {
        await api.delete(`${resolved}/${itemId}`);
        existing = existing.filter((item) => item.id !== itemId);
        renderTable(existing);
        UiResilienceService.showNotification({ type: 'success', title: 'Éxito', message: 'Ficha eliminada correctamente.' });
      } catch (err) {
        UiResilienceService.showNotification({
          type: 'error',
          title: 'Error',
          message: err?.message ?? 'No se pudo eliminar la ficha.',
        });
      }
    };

    /** @type {Array<{ id: string, content: Record<string, unknown> }>} */
    let existing = [];

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'inline-flex w-fit items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100';
    addBtn.textContent = 'Añadir';
    addBtn.addEventListener('click', () => onNavigateToItem(null));

    panel.appendChild(addBtn);
    panel.appendChild(tableHost);

    if (recordId === null) {
      renderTable(existing);
    } else {
      api.get(endpointTemplate.replace('{id}', recordId))
        .then(({ data }) => {
          existing = Array.isArray(data)
            ? data.map((row) => ({
              id: row.id,
              content: (row.content && typeof row.content === 'object') ? row.content : {},
            }))
            : [];
          renderTable(existing);
        })
        .catch(() => {
          const err = document.createElement('p');
          err.className = 'text-sm text-red-600';
          err.textContent = 'Error al cargar las fichas de lentillas.';
          tableHost.replaceChildren(err);
        });
    }

    return panel;
  }
}

// Self-register — EntityEdit imports this module dynamically and then calls
// PluginPanelRegistry.build('contact_lenses', options).
PluginPanelRegistry.register('contact_lenses', ContactLensesPanel);
