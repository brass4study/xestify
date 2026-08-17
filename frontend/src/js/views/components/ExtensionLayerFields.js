/**
 * ExtensionLayerFields — shared building blocks for extension plugins whose
 * detail form is built LAYER BY LAYER from a manifest.json `layers` catalog
 * (STORY 10.5 "layers" convention: top/od/os/general). Extracted from
 * plugins/optometries/plugin.js once plugins/contact-lenses/plugin.js
 * needed the exact same generic pieces — the plugin-specific parts (its own
 * measurement grid shape, SECTIONS/BOUNDS, which standalone fields go
 * where) stay in each plugin's own plugin.js; only the layer-orchestration
 * primitives that don't vary between plugins live here.
 */

import { component } from '../modules/ComponentFactory.js';

/**
 * Compact variant of InputComponent.BASE_CLASSNAME for inputs living inside
 * a plugin's own measurement tables — same recipe PluginConfig.js's
 * tableControlClassName() already uses for inputs in table cells (py-1
 * instead of py-2). Must REPLACE the base className via setClassName(),
 * not classList.add() on top of it: that would leave both py-1 and py-2
 * present and let CSS order pick the winner.
 */
export const COMPACT_INPUT_CLASSNAME = 'w-full rounded-lg border border-slate-300 px-2 py-1 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500';

/** @param {string|null|undefined} iso */
export function formatDate(iso) {
  if (typeof iso !== 'string' || iso === '') {
    return '';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

/** @param {unknown} value */
export function numOrNull(value) {
  return typeof value === 'number' && !Number.isNaN(value) ? value : null;
}

/**
 * Axis values for one eye's gauge, one per section — parameterized by
 * `sections` (each plugin supplies its own SECTIONS list/colors) rather
 * than a fixed shape, since the field-key template `${prefix}_${key}_axis`
 * is the only thing genuinely shared between plugins.
 *
 * @param {string} prefix 'od'|'os'
 * @param {Record<string, unknown>} content
 * @param {Array<{ key: string, color: string }>} sections
 * @returns {Array<{ degrees: number|null, color: string }>}
 */
export function axisLines(prefix, content, sections) {
  return sections.map((section) => ({
    degrees: numOrNull(content[`${prefix}_${section.key}_axis`]),
    color: section.color,
  }));
}

/**
 * Label on the LEFT, input on the right — fits narrow od/os eye columns.
 *
 * @param {string} labelText
 * @param {HTMLElement} inputEl
 * @param {{ alignTop?: boolean }} [options] alignTop for tall inputs (textarea)
 */
export function labeledRow(labelText, inputEl, { alignTop = false } = {}) {
  const row = document.createElement('div');
  row.className = 'grid grid-cols-1 gap-1 sm:grid-cols-[9rem_1fr] sm:gap-2 '
    + (alignTop ? 'sm:items-start' : 'sm:items-center');
  const label = document.createElement('label');
  label.className = 'text-xs font-medium text-slate-500' + (alignTop ? ' sm:pt-2' : '');
  label.textContent = labelText;
  row.appendChild(label);
  row.appendChild(inputEl);
  return row;
}

/**
 * Standard form field — label above the input, exactly like every other
 * form in the app (DynamicForm.js wraps each field the same way). Used for
 * the top and general layers.
 *
 * @param {string} labelText
 * @param {HTMLElement} inputEl
 * @param {string} [name] optional stable id for the label's `for` attribute
 */
export function formFieldRow(labelText, inputEl, name = '') {
  return component.create('formField', { label: labelText, name, input: inputEl })
    .setData('role', 'form-field');
}

/**
 * Buckets items (relations or additional fields) by their `layer` value.
 * An unrecognized/missing layer falls back to 'general' so nothing is ever
 * silently dropped from the form.
 *
 * @template {{ layer?: string }} T
 * @param {Array<T>} items
 * @returns {{ top: Array<T>, od: Array<T>, os: Array<T>, general: Array<T> }}
 */
export function groupByLayer(items) {
  const byLayer = { top: [], od: [], os: [], general: [] };
  items.forEach((item) => {
    const bucket = byLayer[item.layer] ?? byLayer.general;
    bucket.push(item);
  });
  return byLayer;
}

/**
 * @param {{ key: string, label: string, target_entity: string, required: boolean }} relation
 * @param {Record<string, unknown>} content
 * @param {(targetEntity: string) => Promise<Array<{ id: string, label: string }>>} loadOptions
 */
export function buildRelationField(relation, content, loadOptions) {
  const select = component.create('inputSelect', {
    options: [],
    value: content[relation.key] ?? '',
    disabled: true,
    placeholder: 'Cargando…',
  });

  select.getInput().addEventListener('input', () => {
    content[relation.key] = select.value === '' ? null : select.value;
  });

  loadOptions(relation.target_entity).then((options) => {
    select.setOptions(
      options.map((option) => ({ value: option.id, label: option.label })),
      { placeholder: relation.required ? undefined : 'Sin asignar', disabled: false }
    );
  });

  return select;
}

/**
 * Renders one field the plugin doesn't have hand-written UI for
 * (origin:'additional', added later via PluginConfig's "Añadir campo" —
 * see each plugin's Hooks.php::extractFields()). Mirrors the type→
 * component mapping already established in DynamicForm.js's
 * createInput()/createSelect(), not reinvented. Every input mutates
 * `content[field.key]` live on its own change event, same convention as
 * every other input these plugins build (never collect-on-submit).
 *
 * @param {{ key: string, type: string, label: string, required: boolean, options: Array<{value: string, label: string}>|null, min: number|null, max: number|null }} field
 * @param {Record<string, unknown>} content
 */
export function buildGenericFieldInput(field, content) {
  const value = content[field.key] ?? '';

  if (field.type === 'number') {
    const input = component.create('inputNumber', { value });
    const attrs = { step: 'any' };
    if (typeof field.min === 'number') attrs.min = field.min;
    if (typeof field.max === 'number') attrs.max = field.max;
    input.setAttributes(attrs);
    input.addEventListener('input', () => {
      content[field.key] = input.value === '' ? null : Number(input.value);
    });
    return input;
  }

  if (field.type === 'boolean') {
    const input = component.create('inputSwitch', { value: value === true });
    const switchInput = typeof input.getInput === 'function' ? input.getInput() : input;
    switchInput.addEventListener('change', () => {
      content[field.key] = switchInput.checked;
    });
    return input;
  }

  if (field.type === 'date' || field.type === 'timestamp') {
    const input = component.create('inputDate', { value });
    input.addEventListener('input', () => {
      content[field.key] = input.value;
    });
    return input;
  }

  if (field.type === 'select') {
    const input = component.create('inputSelect', {
      value,
      options: Array.isArray(field.options) ? field.options : [],
      placeholder: field.required ? undefined : 'Sin asignar',
    });
    input.getInput().addEventListener('input', () => {
      content[field.key] = input.value === '' ? null : input.value;
    });
    return input;
  }

  if (field.type === 'text') {
    const input = component.create('inputTextArea', { value, rows: 3 });
    input.addEventListener('input', () => {
      content[field.key] = input.value;
    });
    return input;
  }

  // string, mail, phone, uuid — plain text input as a safe fallback.
  const input = component.create('inputText', { value });
  input.addEventListener('input', () => {
    content[field.key] = input.value;
  });
  return input;
}

/**
 * Appends one row per relation, then per additional field — the shared
 * "end of layer" tail every layer builder finishes with. `asFormField`
 * switches between the two layout styles: the standard label-above-input
 * formField (top/general layers) or the compact label-left row that fits
 * narrow od/os eye columns (the default).
 *
 * @param {HTMLElement} container
 * @param {Array<{ key: string, label: string, target_entity: string, required: boolean }>} relationsForLayer
 * @param {Array<{ key: string, type: string, label: string }>} extraFieldsForLayer
 * @param {Record<string, unknown>} content
 * @param {(targetEntity: string) => Promise<Array<{ id: string, label: string }>>} loadOptions
 * @param {{ asFormField?: boolean }} [options]
 */
export function appendLayerTail(container, relationsForLayer, extraFieldsForLayer, content, loadOptions, { asFormField = false } = {}) {
  relationsForLayer.forEach((relation) => {
    const input = buildRelationField(relation, content, loadOptions);
    container.appendChild(asFormField
      ? formFieldRow(relation.label, input, relation.key)
      : labeledRow(relation.label, input));
  });
  extraFieldsForLayer.forEach((field) => {
    const input = buildGenericFieldInput(field, content);
    container.appendChild(asFormField
      ? formFieldRow(field.label, input, field.key)
      : labeledRow(field.label, input, { alignTop: field.type === 'text' }));
  });
}
