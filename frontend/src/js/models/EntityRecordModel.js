/**
 * EntityRecordModel.js — Helpers puros para derivar etiquetas legibles de
 * registros de entidad a partir de su schema y su contenido.
 *
 * recordSummaryLabel() replica en frontend el algoritmo de
 * EntityService::summaryContentKeysInOrder() / summaryFieldName() /
 * buildOptionLabel() (backend/src/services/EntityService.php): recorre
 * schema.fields (objeto) y luego schema.custom_fields (array), en ese orden
 * de declaración (no reordena por ui_field_order), descarta cualquier campo
 * con summaryView === false (default: cuenta), concatena con espacio los
 * valores escalares no vacíos y cae al fallbackId si no queda ningún valor.
 * Deliberadamente NO reutiliza columnsFromSection() de DynamicTable.js
 * porque ese método reordena por ui_field_order — aquí se necesita el mismo
 * orden que usa el backend, para que la etiqueta de un registro coincida
 * tanto en su propio breadcrumb como cuando aparece como opción de un
 * <select> de relación en otra entidad.
 */

function summaryFieldName(key, definition) {
	if (definition === null || typeof definition !== 'object' || definition.summaryView === false) {
		return null;
	}

	return typeof key === 'string' && key.trim() !== '' ? key : null;
}

function summaryContentKeysInOrder(schema) {
	const names = [];
	const sections = [schema?.fields, schema?.custom_fields];

	for (const section of sections) {
		if (Array.isArray(section)) {
			for (const definition of section) {
				const name = summaryFieldName(definition?.key, definition);
				if (name !== null) {
					names.push(name);
				}
			}
		} else if (section !== null && typeof section === 'object') {
			for (const key of Object.keys(section)) {
				const name = summaryFieldName(key, section[key]);
				if (name !== null) {
					names.push(name);
				}
			}
		}
	}

	return names;
}

export function recordSummaryLabel(schema, content, fallbackId) {
	const summaryKeys = summaryContentKeysInOrder(schema ?? {});
	const source = content !== null && typeof content === 'object' ? content : {};
	const parts = [];

	for (const key of summaryKeys) {
		const value = source[key];
		if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
			continue;
		}

		const stringValue = String(value).trim();
		if (stringValue !== '') {
			parts.push(stringValue);
		}
	}

	return parts.length === 0 ? String(fallbackId) : parts.join(' ');
}
