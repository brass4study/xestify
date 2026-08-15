import { InputComponent } from './BaseComponent.js';

let instanceSequence = 0;

function normalizeOption(option) {
	if (typeof option === 'object' && option !== null) {
		return {
			value: String(option.value ?? ''),
			label: String(option.label ?? option.value ?? ''),
		};
	}
	return { value: String(option), label: String(option) };
}

const TRIGGER_STRUCTURAL_CLASSNAME = 'flex items-center justify-between gap-2 text-left';

/**
 * Select propio (trigger + listbox) respaldado por un <select> nativo oculto que
 * conserva el contrato de formulario (FormData, querySelectorAll('select[name]'),
 * autoselección de la primera opción) sin exponer el popup nativo no estilizable.
 */
export class InputSelectComponent extends InputComponent {
	initialize(options = {}) {
		this.className = 'relative w-full';
		this.dataset.role = 'input-select';

		const instanceId = `input-select-${++instanceSequence}`;
		const normalizedOptions = (Array.isArray(options.options) ? options.options : []).map(normalizeOption);

		const nativeSelect = document.createElement('select');
		nativeSelect.hidden = true;
		nativeSelect.tabIndex = -1;
		nativeSelect.setAttribute('aria-hidden', 'true');
		if (typeof options.name === 'string' && options.name !== '') {
			nativeSelect.name = options.name;
		}
		if (options.required === true) {
			nativeSelect.required = true;
		}

		if (typeof options.placeholder === 'string' && options.placeholder !== '') {
			// Blank initial state: a value="" <option> the native <select> lands
			// on by default (no explicit .value set below), shown as the empty
			// trigger label. Deliberately not added to the custom panel/rows
			// list, so it never appears among the selectable "options" once the
			// admin has picked a real one.
			const placeholderOption = document.createElement('option');
			placeholderOption.value = '';
			placeholderOption.textContent = options.placeholder;
			nativeSelect.appendChild(placeholderOption);
		}

		normalizedOptions.forEach(({ value, label }) => {
			const optionEl = document.createElement('option');
			optionEl.value = value;
			optionEl.textContent = label;
			nativeSelect.appendChild(optionEl);
		});

		if (options.value !== '' && options.value !== undefined && options.value !== null) {
			nativeSelect.value = String(options.value);
		}

		const trigger = document.createElement('button');
		trigger.type = 'button';
		trigger.setAttribute('role', 'combobox');
		trigger.setAttribute('aria-haspopup', 'listbox');
		trigger.setAttribute('aria-expanded', 'false');
		trigger.setAttribute('aria-controls', `${instanceId}-listbox`);
		trigger.dataset.role = 'input-select-trigger';

		const triggerLabel = document.createElement('span');
		triggerLabel.className = 'truncate';
		trigger.appendChild(triggerLabel);

		const chevron = document.createElement('i');
		chevron.className = 'fa-solid fa-chevron-down text-[11px] text-slate-400 transition-transform duration-200';
		chevron.setAttribute('aria-hidden', 'true');
		trigger.appendChild(chevron);

		const panel = document.createElement('ul');
		panel.id = `${instanceId}-listbox`;
		panel.setAttribute('role', 'listbox');
		panel.dataset.role = 'input-select-panel';
		panel.className = 'absolute left-0 top-full z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg';

		normalizedOptions.forEach(({ value, label }, index) => {
			const row = document.createElement('li');
			row.id = `${instanceId}-option-${index}`;
			row.setAttribute('role', 'option');
			row.dataset.value = value;
			row.textContent = label;
			row.className = 'cursor-pointer select-none rounded-md px-3 py-2 text-sm text-slate-700 transition-colors duration-150 hover:bg-slate-100';
			row.addEventListener('click', () => {
				this.setSelectedValue(value, true);
				this._closeDropdown();
				this._trigger.focus();
			});
			panel.appendChild(row);
		});

		this.appendChild(nativeSelect);
		this.appendChild(trigger);
		this.appendChild(panel);

		this._selectInput = nativeSelect;
		this._trigger = trigger;
		this._triggerLabel = triggerLabel;
		this._chevron = chevron;
		this._panel = panel;
		this._isOpen = false;
		this._highlightedIndex = -1;
		this._selectDisabled = false;
		this._triggerVisualClassName = InputComponent.BASE_CLASSNAME;

		this._onDocumentClick = (event) => {
			if (event.target instanceof Node && !this.contains(event.target)) {
				this._closeDropdown();
			}
		};
		this._onDocumentKeyDown = (event) => {
			if (event.key === 'Escape') {
				this._closeDropdown();
				this._trigger.focus();
			}
		};

		trigger.addEventListener('click', () => this._toggleDropdown());
		trigger.addEventListener('keydown', (event) => this._handleTriggerKeydown(event));

		Object.defineProperties(this, {
			value: {
				configurable: true,
				get: () => this._selectInput.value,
				set: (value) => { this.setSelectedValue(value); },
			},
			disabled: {
				configurable: true,
				get: () => this._selectDisabled,
				set: (disabled) => { this.setDisabled(disabled); },
			},
		});

		this._applyTriggerClassName();
		this._syncTriggerLabel();

		if (options.disabled === true) {
			this.setDisabled(true);
		}

		return this;
	}

	setId(id) {
		if (typeof id === 'string' && id !== '' && this._trigger instanceof HTMLElement) {
			this._trigger.id = id;
		}
		return this;
	}

	setClassName(className) {
		this._triggerVisualClassName = typeof className === 'string' && className !== ''
			? className
			: InputComponent.BASE_CLASSNAME;
		this._applyTriggerClassName();
		return this;
	}

	setError(message) {
		if (!(this._trigger instanceof HTMLElement)) {
			return this;
		}

		if (typeof message === 'string' && message !== '') {
			this._trigger.classList.remove('border-slate-300', 'focus:border-brand-500');
			this._trigger.classList.add('border-red-300', 'focus:border-red-500');
			this._trigger.setAttribute('aria-invalid', 'true');
			this.dataset.error = message;
		} else {
			this._trigger.classList.remove('border-red-300', 'focus:border-red-500');
			this._trigger.classList.add('border-slate-300', 'focus:border-brand-500');
			this._trigger.removeAttribute('aria-invalid');
			delete this.dataset.error;
		}
		return this;
	}

	focus(focusOptions) {
		this._trigger?.focus(focusOptions);
		return this;
	}

	blur() {
		this._trigger?.blur();
		return this;
	}

	getInput() {
		return this._selectInput;
	}

	setSelectedValue(value, emitChange = false) {
		if (!(this._selectInput instanceof HTMLSelectElement)) {
			return this;
		}

		this._selectInput.value = String(value ?? '');
		this._syncTriggerLabel();

		if (emitChange) {
			this._selectInput.dispatchEvent(new Event('input', { bubbles: true }));
			this._selectInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
		return this;
	}

	setDisabled(disabled) {
		this._selectDisabled = Boolean(disabled);

		if (this._selectInput instanceof HTMLSelectElement) {
			this._selectInput.disabled = this._selectDisabled;
		}

		if (this._trigger instanceof HTMLElement) {
			this._trigger.disabled = this._selectDisabled;
			this._trigger.setAttribute('aria-disabled', String(this._selectDisabled));
			this._trigger.classList.toggle('cursor-not-allowed', this._selectDisabled);
			this._trigger.classList.toggle('opacity-60', this._selectDisabled);
		}

		if (this._selectDisabled) {
			this._closeDropdown();
		}
		return this;
	}

	_applyTriggerClassName() {
		if (!(this._trigger instanceof HTMLElement)) {
			return;
		}
		this._trigger.className = `${this._triggerVisualClassName} ${TRIGGER_STRUCTURAL_CLASSNAME}`.trim();
	}

	_optionRows() {
		return this._panel instanceof HTMLElement ? Array.from(this._panel.querySelectorAll('[role="option"]')) : [];
	}

	_syncTriggerLabel() {
		if (!(this._selectInput instanceof HTMLSelectElement) || !(this._triggerLabel instanceof HTMLElement)) {
			return;
		}

		const selected = this._selectInput.options[this._selectInput.selectedIndex];
		this._triggerLabel.textContent = selected ? selected.textContent : '';

		const currentValue = this._selectInput.value;
		this._optionRows().forEach((row) => {
			const isSelected = row.dataset.value === currentValue;
			row.setAttribute('aria-selected', String(isSelected));
			row.classList.toggle('bg-brand-50', isSelected);
			row.classList.toggle('font-semibold', isSelected);
		});
	}

	_highlightOption(index) {
		const rows = this._optionRows();
		if (rows.length === 0) {
			return;
		}

		const clamped = Math.max(0, Math.min(index, rows.length - 1));
		this._highlightedIndex = clamped;
		rows.forEach((row, i) => row.classList.toggle('bg-slate-100', i === clamped));

		const target = rows[clamped];
		if (target instanceof HTMLElement) {
			this._trigger.setAttribute('aria-activedescendant', target.id);
			target.scrollIntoView({ block: 'nearest' });
		}
	}

	_openDropdown() {
		if (this._selectDisabled || this._isOpen) {
			return;
		}

		this._isOpen = true;
		this._panel.classList.remove('hidden');
		this._panel.style.animation = 'theme-panel-enter 160ms ease-out';
		this._chevron.classList.add('rotate-180');
		this._trigger.setAttribute('aria-expanded', 'true');

		const rows = this._optionRows();
		const currentValue = this._selectInput.value;
		const selectedIndex = rows.findIndex((row) => row.dataset.value === currentValue);
		this._highlightOption(Math.max(selectedIndex, 0));

		document.addEventListener('click', this._onDocumentClick);
		document.addEventListener('keydown', this._onDocumentKeyDown);
	}

	_closeDropdown() {
		if (!this._isOpen) {
			return;
		}

		this._isOpen = false;
		this._panel.classList.add('hidden');
		this._chevron.classList.remove('rotate-180');
		this._trigger.setAttribute('aria-expanded', 'false');
		this._trigger.removeAttribute('aria-activedescendant');
		document.removeEventListener('click', this._onDocumentClick);
		document.removeEventListener('keydown', this._onDocumentKeyDown);
	}

	_toggleDropdown() {
		if (this._isOpen) {
			this._closeDropdown();
		} else {
			this._openDropdown();
		}
	}

	_handleTriggerKeydown(event) {
		const rows = this._optionRows();
		if (rows.length === 0) {
			return;
		}

		if (event.key === 'ArrowDown') {
			event.preventDefault();
			if (!this._isOpen) {
				this._openDropdown();
				return;
			}
			this._highlightOption(this._highlightedIndex + 1);
			return;
		}

		if (event.key === 'ArrowUp') {
			event.preventDefault();
			if (!this._isOpen) {
				this._openDropdown();
				return;
			}
			this._highlightOption(this._highlightedIndex - 1);
			return;
		}

		if (!this._isOpen) {
			return;
		}

		if (event.key === 'Home') {
			event.preventDefault();
			this._highlightOption(0);
			return;
		}

		if (event.key === 'End') {
			event.preventDefault();
			this._highlightOption(rows.length - 1);
			return;
		}

		if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			const target = rows[this._highlightedIndex];
			if (target instanceof HTMLElement) {
				this.setSelectedValue(target.dataset.value, true);
			}
			this._closeDropdown();
			return;
		}

		if (event.key === 'Escape') {
			event.preventDefault();
			this._closeDropdown();
		}
	}
}
