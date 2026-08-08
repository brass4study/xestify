import { component } from './ComponentFactory.js';

export class DynamicForm {
	#fields = [];
	#container;
	#inputs = new Map();

	constructor(schema, container) {
		this.#fields = this.normalizeFields(schema);
		this.#container = this.resolveContainer(container);
	}

	render() {
		this.#inputs.clear();
		this.#container.replaceChildren();

		const form = component.create('form', {
			className: 'grid gap-4',
			novalidate: true,
		});

		for (const field of this.#fields) {
			const input = this.createInput(field);
			const fieldEl = component.create('formField', {
				label: field.label ?? field.name,
				name: this.fieldId(field.name),
				input,
			});
			fieldEl.dataset.role = 'form-field';
			form.appendChild(fieldEl);
			this.#inputs.set(field.name, input);
		}

		this.#container.appendChild(form);

		return form;
	}

	validate() {
		const data = this.getData();
		const errors = {};

		for (const field of this.#fields) {
			const fieldErrors = this.validateField(field, data[field.name]);
			if (fieldErrors.length > 0) {
				errors[field.name] = fieldErrors;
			}
		}

		return {
			isValid: Object.keys(errors).length === 0,
			errors,
		};
	}

	getData() {
		const data = {};

		for (const field of this.#fields) {
			const input = this.#inputs.get(field.name);
			data[field.name] = this.extractValue(field, input).value;
		}

		return data;
	}

	extractValue(field, input) {
		const result = { value: null };

		if (!(input instanceof HTMLElement)) {
			return result;
		}

		if (field.type === 'boolean') {
			const switchInput = typeof input.getInput === 'function'
				? input.getInput()
				: input;
			result.value = switchInput instanceof HTMLInputElement && switchInput.checked;
			return result;
		}

		if (field.type === 'select' && input instanceof HTMLSelectElement) {
			result.value = input.value;
			return result;
		}

		if (input instanceof HTMLTextAreaElement) {
			result.value = input.value;
			return result;
		}

		if (!(input instanceof HTMLInputElement)) {
			return result;
		}

		if (field.type !== 'number') {
			result.value = input.value;
			return result;
		}

		if (input.value === '') {
			return result;
		}

		const parsed = Number(input.value);
		result.value = Number.isNaN(parsed) ? null : parsed;
		return result;
	}

	normalizeFields(schema) {
		if (!schema || typeof schema !== 'object') {
			return [];
		}

		const baseFields = this.normalizeFieldSection(schema.fields);
		const customFields = this.normalizeFieldSection(schema.custom_fields);
		const combined = [...baseFields, ...customFields];
		const byName = new Map(combined.map((field) => [field.name, field]));

		const ordered = [];
		const orderList = Array.isArray(schema.ui_field_order) ? schema.ui_field_order : [];
		for (const candidate of orderList) {
			if (typeof candidate !== 'string') {
				continue;
			}

			const field = byName.get(candidate);
			if (field) {
				ordered.push(field);
				byName.delete(candidate);
			}
		}

		for (const field of combined) {
			if (byName.has(field.name)) {
				ordered.push(field);
				byName.delete(field.name);
			}
		}

		return ordered;
	}

	normalizeFieldSection(rawFields) {
		if (Array.isArray(rawFields)) {
			return rawFields
				.filter((field) => field && typeof field === 'object' && this.fieldName(field) !== null)
				.map((field) => ({ ...field, name: this.fieldName(field) }));
		}

		if (rawFields && typeof rawFields === 'object') {
			return Object.keys(rawFields).map((name) => {
				const config = rawFields[name];
				if (config && typeof config === 'object') {
					return { name, ...config };
				}
				return { name };
			});
		}

		return [];
	}

	fieldName(field) {
		for (const key of ['name', 'key', 'slug']) {
			const value = field[key];
			if (typeof value === 'string' && value.trim() !== '') {
				return value;
			}
		}

		return null;
	}

	resolveContainer(container) {
		if (typeof container === 'string') {
			const element = document.querySelector(container);
			if (!(element instanceof HTMLElement)) {
				throw new TypeError('DynamicForm container not found');
			}
			return element;
		}

		if (container instanceof HTMLElement) {
			return container;
		}

		throw new TypeError('DynamicForm container must be a selector or HTMLElement');
	}

	fieldId(name) {
		return `xf_${String(name).replaceAll(' ', '_')}`;
	}

	createInput(field) {
		const type = field.type ?? 'string';

		if (type === 'select') {
			return this.createSelect(field);
		}

		if (type === 'boolean') {
			return component.create('inputSwitch', {
				id: this.fieldId(field.name),
				name: field.name,
				value: field.default ?? false,
				checked: Boolean(field.default ?? false),
			});
		}

		if (type === 'text') {
			const textarea = component.create('inputTextArea', {
				name: field.name,
				rows: Number.isInteger(field.rows) ? field.rows : 4,
				value: field.default ?? '',
			});
			textarea.id = this.fieldId(field.name);
			return textarea;
		}

		if (type === 'email') {
			const input = component.create('inputEmail', {
				name: field.name,
				value: field.default ?? '',
			});
			input.id = this.fieldId(field.name);
			return input;
		}

		if (type === 'password') {
			const input = component.create('inputPassword', {
				name: field.name,
				value: field.default ?? '',
			});
			input.id = this.fieldId(field.name);
			return input;
		}

		if (type === 'date') {
			const input = component.create('inputDate', {
				name: field.name,
				value: field.default ?? '',
			});
			input.id = this.fieldId(field.name);
			return input;
		}

		const input = component.create('inputText', {
			name: field.name,
			value: field.default ?? '',
		});
		input.id = this.fieldId(field.name);
		return input;
	}

	createSelect(field) {
		const select = component.create('inputSelect', {
			name: field.name,
			value: field.default ?? '',
			options: Array.isArray(field.options) ? field.options : [],
		});
		select.id = this.fieldId(field.name);
		return select;
	}

	validateField(field, value) {
		const errors = [];
		const type = field.type ?? 'string';
		const isRequired = field.required === true;

		const isEmpty =
			value === null ||
			value === undefined ||
			(typeof value === 'string' && value.trim() === '') ||
			(type === 'select' && value === '');

		if (isRequired && isEmpty) {
			errors.push('Field is required');
			return errors;
		}

		if (isEmpty) {
			return errors;
		}

		if (this.isStringLikeType(type)) {
			this.validateStringLike(field, String(value), errors);
		}

		if (type === 'number') {
			this.validateNumber(field, value, errors);
		}

		if (type === 'boolean' && typeof value !== 'boolean') {
			errors.push('Must be a boolean');
		}

		return errors;
	}

	isStringLikeType(type) {
		return ['string', 'text', 'email', 'date', 'timestamp', 'select'].includes(type);
	}

	validateStringLike(field, value, errors) {
		if (field.type === 'email' && !this.isValidEmail(value)) {
			errors.push('Must be a valid email');
		}

		if (field.type === 'date') {
			const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
			if (!dateRegex.test(value)) {
				errors.push('Must be a valid date (YYYY-MM-DD)');
			}
		}

		if (field.type === 'select') {
			const options = Array.isArray(field.options) ? field.options : [];
			const optionValues = options.map((option) => {
				if (typeof option === 'object' && option !== null) {
					return String(option.value ?? '');
				}
				return String(option);
			});

			if (!optionValues.includes(value)) {
				errors.push('Must be one of the allowed options');
			}
		}

		if (typeof field.minLength === 'number' && value.length < field.minLength) {
			errors.push(`Minimum length is ${field.minLength}`);
		}

		if (typeof field.maxLength === 'number' && value.length > field.maxLength) {
			errors.push(`Maximum length is ${field.maxLength}`);
		}
	}

	validateNumber(field, value, errors) {
		if (typeof value !== 'number' || Number.isNaN(value)) {
			errors.push('Must be a number');
			return;
		}

		if (typeof field.min === 'number' && value < field.min) {
			errors.push(`Minimum value is ${field.min}`);
		}

		if (typeof field.max === 'number' && value > field.max) {
			errors.push(`Maximum value is ${field.max}`);
		}
	}

	isValidEmail(value) {
		if (typeof value !== 'string' || value.includes(' ')) {
			return false;
		}

		const at = value.indexOf('@');
		if (at <= 0 || at !== value.lastIndexOf('@')) {
			return false;
		}

		const domain = value.slice(at + 1);
		if (domain.length < 3 || domain.startsWith('.') || domain.endsWith('.')) {
			return false;
		}

		return domain.includes('.');
	}
}
