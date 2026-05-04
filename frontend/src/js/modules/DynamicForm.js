/**
 * DynamicForm.js — Schema-driven form renderer for Xestify frontend.
 *
 * Supports field types: string, number, email, date, select, boolean.
 * No listeners and no Proxy; simple imperative API.
 */

export class DynamicForm {
  /** @type {Array<object>} */
  #fields = [];

  /** @type {HTMLElement} */
  #container;

  /** @type {Map<string, HTMLElement>} */
  #inputs = new Map();

  /**
   * @param {object} schema
   * @param {string|HTMLElement} container
   */
  constructor(schema, container) {
    this.#fields = this.#normalizeFields(schema);
    this.#container = this.#resolveContainer(container);
  }

  /**
   * Render the form controls defined by the schema in the provided container.
   *
   * @returns {HTMLFormElement}
   */
  render() {
    this.#inputs.clear();
    this.#container.replaceChildren();

    const form = document.createElement('form');
    form.setAttribute('novalidate', 'novalidate');

    for (const field of this.#fields) {
      const row = document.createElement('div');
      row.className = 'xf-field';

      const label = document.createElement('label');
      label.setAttribute('for', this.#fieldId(field.name));
      label.textContent = field.label ?? field.name;

      const input = this.#createInput(field);
      row.appendChild(label);
      row.appendChild(input);

      form.appendChild(row);
      this.#inputs.set(field.name, input);
    }

    this.#container.appendChild(form);

    return form;
  }

  /**
   * Validate current form values on client-side.
   *
   * @returns {{isValid: boolean, errors: Record<string, string[]>}}
   */
  validate() {
    const data = this.getData();
    const errors = {};

    for (const field of this.#fields) {
      const fieldErrors = this.#validateField(field, data[field.name]);
      if (fieldErrors.length > 0) {
        errors[field.name] = fieldErrors;
      }
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors,
    };
  }

  /**
   * Read current values from rendered controls.
   *
   * @returns {Record<string, any>}
   */
  getData() {
    const data = {};

    for (const field of this.#fields) {
      const input = this.#inputs.get(field.name);
      data[field.name] = this.#extractValue(field, input).value;
    }

    return data;
  }

  #extractValue(field, input) {
    const result = { value: null };

    if (!(input instanceof HTMLElement)) {
      return result;
    }

    if (field.type === 'boolean' && input instanceof HTMLInputElement) {
      result.value = input.checked;
      return result;
    }

    if (field.type === 'select' && input instanceof HTMLSelectElement) {
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

  #normalizeFields(schema) {
    if (!schema || typeof schema !== 'object') {
      return [];
    }

    const rawFields = schema.fields;
    if (Array.isArray(rawFields)) {
      return rawFields
        .filter((field) => field && typeof field === 'object' && typeof field.name === 'string')
        .map((field) => ({ ...field }));
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

  #resolveContainer(container) {
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

  #fieldId(name) {
    return `xf_${String(name).replaceAll(' ', '_')}`;
  }

  #createInput(field) {
    const type = field.type ?? 'string';

    if (type === 'select') {
      return this.#createSelect(field);
    }

    if (type === 'boolean') {
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.id = this.#fieldId(field.name);
      input.name = field.name;
      input.checked = Boolean(field.default ?? false);
      return input;
    }

    const input = document.createElement('input');
    input.id = this.#fieldId(field.name);
    input.name = field.name;

    input.type = this.#toInputType(type);

    if (field.default !== undefined && field.default !== null) {
      input.value = String(field.default);
    }

    return input;
  }

  #createSelect(field) {
    const select = document.createElement('select');
    select.id = this.#fieldId(field.name);
    select.name = field.name;

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- Select --';
    select.appendChild(placeholder);

    const options = Array.isArray(field.options) ? field.options : [];
    for (const option of options) {
      const optionEl = document.createElement('option');
      if (typeof option === 'object' && option !== null) {
        optionEl.value = String(option.value ?? '');
        optionEl.textContent = String(option.label ?? option.value ?? '');
      } else {
        optionEl.value = String(option);
        optionEl.textContent = String(option);
      }
      select.appendChild(optionEl);
    }

    if (field.default !== undefined && field.default !== null) {
      select.value = String(field.default);
    }

    return select;
  }

  #toInputType(type) {
    if (type === 'email' || type === 'number' || type === 'date') {
      return type;
    }
    return 'text';
  }

  #validateField(field, value) {
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

    if (type === 'string' || type === 'email' || type === 'date' || type === 'select') {
      this.#validateStringLike(field, String(value), errors);
    }

    if (type === 'number') {
      this.#validateNumber(field, value, errors);
    }

    if (type === 'boolean' && typeof value !== 'boolean') {
      errors.push('Must be a boolean');
    }

    return errors;
  }

  #validateStringLike(field, value, errors) {
    if (field.type === 'email') {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(value)) {
        errors.push('Must be a valid email');
      }
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

  #validateNumber(field, value, errors) {
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
}
