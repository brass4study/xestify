/**
 * EntityRecordModel.js — Helpers puros para derivar etiquetas legibles de
 * registros de entidad a partir de su schema y su contenido.
 *
 * recordSummaryLabel() replica en frontend el algoritmo de
 * EntityService::summaryKeysToDisplay() / summaryFieldDisplayValue() /
 * buildOptionLabel() (backend/src/services/EntityService.php), para que la
 * etiqueta de un registro coincida tanto en su propio breadcrumb como
 * cuando aparece como opción de un <select> de relación en otra entidad.
 *
 * Prioridad de campos mostrados (claves de contenido, no traducciones):
 *   1. .type + .name + .surnames, si los tres están declarados en el schema
 *      (schema.fields o schema.custom_fields), en ese orden.
 *   2. si no, .type + .name + .description, si los tres están declarados,
 *      en ese orden.
 *   3. si no, los 2 primeros campos con summaryView !== false, en el mismo
 *      orden de visualización que usa DynamicTable.normalizeColumns()
 *      (frontend/src/js/views/modules/DynamicTable.js): primero los
 *      nombrados en schema.ui_field_order, en ese orden; el resto, en su
 *      orden de declaración (fields antes que custom_fields).
 *
 * El valor de un campo `select` se resuelve a su label declarado en
 * options (igual que DynamicTable.formatCellValue()) en vez de mostrar el
 * value crudo guardado en el contenido.
 */

function summaryFieldDefinitions(schema) {
	const definitions = new Map();

	const fields = schema?.fields;
	if (fields !== null && typeof fields === 'object' && !Array.isArray(fields)) {
		for (const key of Object.keys(fields)) {
			const definition = fields[key];
			if (key.trim() !== '' && definition !== null && typeof definition === 'object') {
				definitions.set(key, definition);
			}
		}
	}

	const customFields = schema?.custom_fields;
	if (Array.isArray(customFields)) {
		for (const definition of customFields) {
			const key = definition?.key;
			if (typeof key === 'string' && key.trim() !== '' && definition !== null && typeof definition === 'object') {
				definitions.set(key, definition);
			}
		}
	}

	return definitions;
}

function reorderByUiFieldOrder(keys, uiFieldOrder) {
	const remaining = new Set(keys);
	const ordered = [];

	if (Array.isArray(uiFieldOrder)) {
		for (const candidate of uiFieldOrder) {
			if (typeof candidate === 'string' && remaining.has(candidate)) {
				ordered.push(candidate);
				remaining.delete(candidate);
			}
		}
	}

	for (const key of keys) {
		if (remaining.has(key)) {
			ordered.push(key);
		}
	}

	return ordered;
}

function orderedSummaryKeys(schema, definitions) {
	const declarationOrder = [];
	for (const [key, definition] of definitions) {
		if (definition.summaryView !== false) {
			declarationOrder.push(key);
		}
	}

	return reorderByUiFieldOrder(declarationOrder, schema?.ui_field_order);
}

function hasAllFields(definitions, keys) {
	return keys.every((key) => definitions.has(key));
}

function summaryKeysToDisplay(schema, definitions) {
	if (hasAllFields(definitions, ['type', 'name', 'surnames'])) {
		return ['type', 'name', 'surnames'];
	}

	if (hasAllFields(definitions, ['type', 'name', 'description'])) {
		return ['type', 'name', 'description'];
	}

	return orderedSummaryKeys(schema, definitions).slice(0, 2);
}

/**
 * `select` fields store the option's raw value in the record; the summary
 * must show its human label, same as DynamicTable.formatCellValue().
 */
function summaryFieldDisplayValue(rawValue, definition) {
	if (typeof rawValue !== 'string' && typeof rawValue !== 'number' && typeof rawValue !== 'boolean') {
		return null;
	}

	if (definition.type === 'select' && Array.isArray(definition.options)) {
		const stringValue = String(rawValue);
		const match = definition.options.find((option) => {
			const optionValue = option && typeof option === 'object' ? option.value : option;
			return String(optionValue ?? '') === stringValue;
		});

		if (match !== undefined) {
			return match && typeof match === 'object' ? String(match.label ?? match.value ?? '') : String(match);
		}
	}

	return String(rawValue);
}

export function recordSummaryLabel(schema, content, fallbackId) {
	const definitions = summaryFieldDefinitions(schema ?? {});
	const keys = summaryKeysToDisplay(schema ?? {}, definitions);
	const source = content !== null && typeof content === 'object' ? content : {};
	const parts = [];

	for (const key of keys) {
		const displayValue = summaryFieldDisplayValue(source[key], definitions.get(key));
		if (displayValue === null) {
			continue;
		}

		const trimmed = displayValue.trim();
		if (trimmed !== '') {
			parts.push(trimmed);
		}
	}

	return parts.length === 0 ? String(fallbackId) : parts.join(' ');
}
