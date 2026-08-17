/**
 * OptometriesPanel — plugin-owned frontend for the optometries extension.
 *
 * Self-registers in PluginPanelRegistry under the slug 'optometries'.
 *
 * Panel contract:
 *   - get element(): HTMLElement   Mount point for EntityEdit.
 *   - flush(resolvedId): Promise   Persists pending POST/PUT/DELETE to the API.
 *
 * Data model: a HISTORY of fichas per person (STORY 10.5), not a single
 * record — same generic list storage plugin_extension_data already gives
 * every extension plugin (see plugins/comments/plugin.js), just with a much
 * richer per-item form (grid of measurements per eye + axis gauges) instead
 * of a single textarea, and a summary table instead of a full list of
 * expanded items. The summary table reuses DynamicTable.normalizeColumns()
 * exactly like EntityList: it shows only the fields with summaryView!==false
 * (Fecha/Notas by default, see schema.json), in ui_field_order — driven by
 * Hooks.php::extractSummaryFields(), not hardcoded here.
 *
 * The detail form is built LAYER BY LAYER, mirroring the manifest.json
 * layers catalog (top / od / os / general):
 *   - top:     Fecha
 *   - od:      one COLUMN = axis gauge 'D' + right-eye measurement table
 *   - os:      one COLUMN = axis gauge 'I' + left-eye measurement table
 *   - general: Distancia interpupilar, relations, Notas
 * The 29 original fields stay hand-written (their `layer` in schema.json is
 * metadata that matches where this code places them, not what drives it).
 * What IS placed dynamically by its `layer` value: relations
 * (relation.layer) and fields added later via PluginConfig's "Añadir campo"
 * (origin:'additional', embedded in the tab payload by
 * Hooks.php::extractFields()) — both land at the end of their layer.
 *
 * min/max shown on the hand-written number inputs below must stay in sync
 * with plugins/optometries/schema.json by hand — those never travel through
 * the registerTabs tab payload.
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
  buildGenericFieldInput,
} from '../../js/views/components/ExtensionLayerFields.js';

const EYE_LETTER = { od: 'D', os: 'I' };

const SECTIONS = [
  { key: 'distance', label: 'Para lejos', color: '#dc2626' },
  { key: 'constant', label: 'Constante', color: '#64748b' },
  { key: 'near', label: 'Para cerca', color: '#16a34a' },
  { key: 'contact_lens', label: 'Lentilla', color: '#2563eb' },
];

const BOUNDS = {
  sphere: { min: -20, max: 20 },
  cylinder: { min: -10, max: 10 },
  axis: { min: 0, max: 180 },
  addition: { min: 0, max: 4 },
  pupillary_distance: { min: 40, max: 80 },
};

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
 * Measurement table for one eye, built on DynamicTable (the same reusable
 * component every other table in the app uses) rather than a hand-rolled
 * <table>: no toolbar (Refrescar/Tamaño/Opciones make no sense on a
 * fixed-5-row editor grid), density pinned to compact, no sortable columns
 * (none declares sortValue), and the app's default header styling (no
 * headerClassName overrides). One row per SECTIONS entry, plus the Adición
 * row.
 *
 * @param {string} prefix 'od'|'os'
 * @param {Record<string, unknown>} content
 * @param {() => void} onAxisChange
 */
function buildMeasurementTable(prefix, content, onAxisChange) {
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

  const records = [
    ...SECTIONS.map((section) => ({ key: section.key, label: section.label, isAddition: false })),
    { key: 'addition', label: 'Adición', isAddition: true },
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
    ...[['sphere', 'Esfera'], ['cylinder', 'Cilindro'], ['axis', 'Eje']].map(([col, label]) => ({
      key: col,
      label,
      position: 'start',
      renderCell: (record) => {
        if (record.isAddition) {
          // Only the first value column renders the Adición input; the other
          // two cells are removed by the rowDecorator below (colSpan trick).
          return col === 'sphere'
            ? buildNumberInput(`${prefix}_addition`, BOUNDS.addition, false)
            : '';
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
    tableDataRole: 'optometries-measurements',
    extraColumns,
    // Neither DynamicTable nor Table knows about colSpan; rowDecorator is
    // the sanctioned post-render hook, so the Adición input can span the
    // full Esfera→Eje width as in the sketch.
    rowDecorator: (tr, record) => {
      if (!record.isAddition) {
        return;
      }
      const cells = tr.querySelectorAll('td');
      if (cells.length === 4) {
        cells[1].colSpan = 3;
        cells[3].remove();
        cells[2].remove();
      }
    },
  });
  table.render();

  return host;
}

/**
 * Layer `od`/`os`: ONE column per eye — the axis gauge (in a bordered box,
 * its big D/I letter identifies the eye, no extra heading) stacked directly
 * above that eye's measurement table, then any relations/additional fields
 * assigned to this eye's layer.
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

  column.appendChild(buildMeasurementTable(prefix, content, onAxisChange));

  appendLayerTail(column, relationsForLayer, extraFieldsForLayer, content, loadOptions);
  return column;
}

/**
 * Layer `general`: Distancia interpupilar, then this layer's relations
 * (Oftalmólogo/Optometrista), then Notas, then additional fields — every
 * field built as a standard form field (label above, input below), same
 * as any other form in the app.
 *
 * @param {Record<string, unknown>} content
 * @param {Array} relationsForLayer
 * @param {Array} extraFieldsForLayer
 * @param {(targetEntity: string) => Promise<Array>} loadOptions
 */
function buildGeneralLayer(content, relationsForLayer, extraFieldsForLayer, loadOptions) {
  const layer = document.createElement('div');
  layer.className = 'grid gap-4 border-t border-slate-200 pt-4';

  const pdInput = component.create('inputNumber', { name: 'pupillary_distance', value: content.pupillary_distance ?? '' });
  pdInput.setAttributes({ min: BOUNDS.pupillary_distance.min, max: BOUNDS.pupillary_distance.max, step: 'any' });
  pdInput.addEventListener('input', () => {
    content.pupillary_distance = pdInput.value === '' ? null : Number(pdInput.value);
  });
  layer.appendChild(formFieldRow('Distancia interpupilar', pdInput, 'pupillary_distance'));

  relationsForLayer.forEach((relation) => {
    const input = buildRelationField(relation, content, loadOptions);
    layer.appendChild(formFieldRow(relation.label, input, relation.key));
  });

  const notesInput = component.create('inputTextArea', { name: 'notes', value: content.notes ?? '', rows: 3 });
  notesInput.addEventListener('input', () => {
    content.notes = notesInput.value;
  });
  layer.appendChild(formFieldRow('Notas', notesInput, 'notes'));

  extraFieldsForLayer.forEach((field) => {
    const input = buildGenericFieldInput(field, content);
    layer.appendChild(formFieldRow(field.label, input, field.key));
  });

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

export class OptometriesPanel {
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
    // Intentional no-op, kept for the PluginPanelRegistry panel contract:
    // adding/editing a ficha navigates to its own page (PluginItemEdit.js)
    // and persists immediately there, and deleting from the list below also
    // calls the API immediately — nothing is staged against the owner
    // record's own save anymore (STORY 10.5 follow-up).
  }

  /**
   * @param {string} endpointTemplate e.g. /plugins/optometries/clients/{id}
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
        message: 'Se eliminará esta ficha de optometría. Esta acción no se puede deshacer.',
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
          err.textContent = 'Error al cargar las fichas de optometría.';
          tableHost.replaceChildren(err);
        });
    }

    return panel;
  }
}

// Self-register — EntityEdit imports this module dynamically and then calls
// PluginPanelRegistry.build('optometries', options).
PluginPanelRegistry.register('optometries', OptometriesPanel);
