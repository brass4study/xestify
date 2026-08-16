/**
 * RelatedRecordsPanel — generic "related records" tab content for
 * EntityEdit (STORY 10.3 §9). Lists the records of another entity whose
 * relation field points at the record currently being edited.
 *
 * Unlike Comments (a plugin-owned panel imported dynamically and looked up
 * in PluginPanelRegistry), a reverse relation tab has no owning plugin.js —
 * EntityEdit instantiates this directly for any GET /entities/{slug}/tabs
 * entry with type === 'relation'. Same minimal panel contract as
 * PluginPanelRegistry panels so EntityEdit can mount/flush it identically:
 *   - get element(): HTMLElement
 *   - flush(resolvedId): Promise<void>
 */

import { DynamicTable } from './DynamicTable.js';

export class RelatedRecordsPanel {
	#element;
	#sourceEntity;
	#key;
	#api;
	#onRowClick;

	/**
	 * @param {{
	 *   sourceEntity: string,
	 *   key: string,
	 *   recordId: string|null,
	 *   api: import('../../models/ApiClientModel.js').Api,
	 *   onRowClick?: (id: string) => void
	 * }} options
	 */
	constructor({ sourceEntity, key, recordId, api, onRowClick }) {
		this.#sourceEntity = String(sourceEntity ?? '');
		this.#key = String(key ?? '');
		this.#api = api;
		this.#onRowClick = typeof onRowClick === 'function' ? onRowClick : null;
		this.#element = document.createElement('div');
		this.#element.className = 'flex flex-col gap-3';
		this.#element.dataset.role = 'related-records-panel';

		this.#init(recordId);
	}

	get element() {
		return this.#element;
	}

	/** @param {string|null} recordId */
	#init(recordId) {
		if (recordId === null) {
			this.#renderMessage('Guarda el registro para ver los relacionados.');
		} else {
			void this.#load(recordId);
		}
	}

	/** @param {string} resolvedId */
	async flush(resolvedId) {
		await this.#load(resolvedId);
	}

	async #load(resolvedId) {
		this.#renderMessage('Cargando...');

		try {
			const [recordsResponse, schemaResponse] = await Promise.all([
				this.#api.get(`/entities/${encodeURIComponent(this.#sourceEntity)}/records?field=${encodeURIComponent(this.#key)}&value=${encodeURIComponent(resolvedId)}`),
				this.#api.get(`/entities/${encodeURIComponent(this.#sourceEntity)}/schema`),
			]);

			const rawRecords = Array.isArray(recordsResponse?.data) ? recordsResponse.data : [];
			const schema = schemaResponse?.data?.schema && typeof schemaResponse.data.schema === 'object'
				? schemaResponse.data.schema
				: { fields: [] };

			this.#renderRecords(rawRecords, schema);
		} catch {
			this.#renderMessage('No se pudieron cargar los registros relacionados.');
		}
	}

	#renderRecords(rawRecords, schema) {
		this.#element.replaceChildren();

		if (rawRecords.length === 0) {
			this.#renderMessage('Sin registros relacionados.');
			return;
		}

		const records = rawRecords.map((row) => this.#normalizeRecord(row));
		const host = document.createElement('div');
		this.#element.appendChild(host);

		const table = new DynamicTable(records, schema, host, {
			showPagination: false,
			rowDecorator: this.#onRowClick === null ? undefined : (row, record) => {
				row.classList.add('cursor-pointer');
				row.addEventListener('click', () => this.#onRowClick(String(record.id ?? '')));
			},
		});
		table.render();
	}

	#normalizeRecord(row) {
		const content = this.#extractContentObject(row?.content);

		return {
			...content,
			id: row?.id ?? null,
			created_at: row?.created_at ?? null,
			updated_at: row?.updated_at ?? null,
		};
	}

	#extractContentObject(rawContent) {
		if (rawContent !== null && typeof rawContent === 'object' && !Array.isArray(rawContent)) {
			return rawContent;
		}

		if (typeof rawContent === 'string') {
			try {
				const parsed = JSON.parse(rawContent);
				if (parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)) {
					return parsed;
				}
			} catch {
				return {};
			}
		}

		return {};
	}

	#renderMessage(message) {
		this.#element.replaceChildren();
		const p = document.createElement('p');
		p.className = 'rounded-lg border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500';
		p.dataset.role = 'placeholder';
		p.textContent = message;
		this.#element.appendChild(p);
	}
}
