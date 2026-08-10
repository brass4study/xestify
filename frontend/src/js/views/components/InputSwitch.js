import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

function toBoolean(value) {
  return value === true || value === 1 || value === '1' || value === 'true';
}

const ROOT_BASE_CLASSES = [
  'relative',
  'inline-flex',
  'shrink-0',
  'items-center',
  'rounded-full',
  'select-none',
  'align-middle',
  'transition-colors',
  'duration-200',
  'ease-out',
];

const ROOT_SIZE_CLASSES = {
  medium: ['h-6', 'w-11'],
  small: ['h-4', 'w-7'],
};

const ROOT_ENABLED_CLASSES = {
  checked: ['bg-brand-600', 'hover:bg-brand-500'],
  unchecked: ['bg-slate-400', 'hover:bg-slate-500'],
};

const ROOT_DISABLED_CLASSES = ['cursor-not-allowed', 'opacity-60'];
const ROOT_INTERACTIVE_CLASSES = ['cursor-pointer'];

const INPUT_CLASSES = ['peer', 'absolute', 'inset-0', 'h-full', 'w-full', 'cursor-inherit', 'opacity-0'];

const CONTENT_BASE_CLASSES = [
  'pointer-events-none',
  'w-full',
  'overflow-hidden',
  'text-[10px]',
  'font-semibold',
  'leading-none',
  'text-white',
  'transition-all',
  'duration-200',
  'ease-out',
  'peer-focus-visible:outline',
  'peer-focus-visible:outline-2',
  'peer-focus-visible:outline-offset-2',
  'peer-focus-visible:outline-brand-300',
];

const CONTENT_SIZE_CLASSES = {
  medium: {
    checked: ['px-1.5', 'pr-6', 'text-left'],
    unchecked: ['px-1.5', 'pl-6', 'text-right'],
  },
  small: {
    checked: ['hidden'],
    unchecked: ['hidden'],
  },
};

const INDICATOR_BASE_CLASSES = [
  'pointer-events-none',
  'absolute',
  'left-0.5',
  'top-0.5',
  'rounded-full',
  'bg-white',
  'shadow',
  'transition-transform',
  'duration-200',
  'ease-out',
];

const INDICATOR_SIZE_CLASSES = {
  medium: ['h-5', 'w-5'],
  small: ['h-3', 'w-3'],
};

const INDICATOR_TRANSLATE_CLASSES = {
  medium: 'translate-x-5',
  small: 'translate-x-3',
};

const SPINNER_BASE_CLASSES = [
  'absolute',
  'inset-1',
  'rounded-full',
  'border',
  'border-brand-600',
  'border-r-transparent',
  'animate-spin',
];

export class InputSwitchComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = '';
    this.dataset.role = 'input-switch';
    this.dataset.size = options.size === 'small' ? 'small' : 'medium';

    const input = component.create('input', {
      name: options.name,
      required: options.required,
    })
      .setType('checkbox')
      .setClassName(INPUT_CLASSES.join(' '))
      .setAttribute('role', 'switch');
    input.checked = toBoolean(options.checked ?? options.value ?? options.defaultChecked);

    if (typeof options.id === 'string' && options.id !== '') {
      input.id = options.id;
    }

    const content = component.create('span', {
      className: CONTENT_BASE_CLASSES.join(' '),
      attributes: { 'aria-hidden': 'true' },
    }).setData('role', 'switch-content');

    const indicator = component.create('span', {
      className: `${INDICATOR_BASE_CLASSES.join(' ')} ${INDICATOR_SIZE_CLASSES[this.dataset.size].join(' ')}`,
      attributes: { 'aria-hidden': 'true' },
    }).setData('role', 'switch-indicator');

    const spinner = component.create('span', {
      className: 'hidden',
      attributes: { 'aria-hidden': 'true' },
    })
      .setData('role', 'switch-spinner')
      .addClass(SPINNER_BASE_CLASSES)
      .setParent(indicator);

    input.setParent(this);
    content.setParent(this);
    indicator.setParent(this);
    this._switchInput = input;
    this._switchContent = content;
    this._switchIndicator = indicator;
    this._switchSpinner = spinner;
    this._checkedChildren = options.checkedChildren ?? '';
    this._unCheckedChildren = options.unCheckedChildren ?? '';
    this._switchDisabled = options.disabled === true;
    this._switchLoading = options.loading === true;

    Object.defineProperties(this, {
      checked: {
        configurable: true,
        get: () => this.getChecked(),
        set: (checked) => {
          this.setChecked(checked);
        },
      },
      disabled: {
        configurable: true,
        get: () => this._switchDisabled,
        set: (disabled) => {
          this.setDisabled(disabled);
        },
      },
    });

    input.addEventListener('change', (event) => {
      this._syncSwitchState();
      if (typeof options.onChange === 'function') {
        options.onChange(input.checked, event);
      }
    });
    input.addEventListener('click', (event) => {
      if (typeof options.onClick === 'function') {
        options.onClick(input.checked, event);
      }
    });

    this._applySwitchAvailability();
    this._syncSwitchState();
    return this;
  }

  getInput() {
    return this._switchInput;
  }

  getChecked() {
    return this._switchInput instanceof HTMLInputElement && this._switchInput.checked;
  }

  setChecked(checked, emitChange = false) {
    if (!(this._switchInput instanceof HTMLInputElement)) {
      return this;
    }

    this._switchInput.checked = toBoolean(checked);
    this._syncSwitchState();
    if (emitChange) {
      this._switchInput.dispatchEvent(new Event('change', { bubbles: true }));
    }
    return this;
  }

  toggle() {
    if (this._switchDisabled || this._switchLoading) {
      return this;
    }
    return this.setChecked(!this.getChecked(), true);
  }

  setDisabled(disabled) {
    this._switchDisabled = Boolean(disabled);
    this._applySwitchAvailability();
    return this;
  }

  setLoading(loading) {
    this._switchLoading = Boolean(loading);
    this._applySwitchAvailability();
    return this;
  }

  focus(options) {
    this._switchInput?.focus(options);
    return this;
  }

  blur() {
    this._switchInput?.blur();
    return this;
  }

  _applySwitchAvailability() {
    if (!(this._switchInput instanceof HTMLInputElement)) {
      return;
    }

    const unavailable = this._switchDisabled || this._switchLoading;
    this._switchInput.disabled = unavailable;
    this.dataset.disabled = String(this._switchDisabled);
    this.dataset.loading = String(this._switchLoading);
    this.setAttribute('aria-disabled', unavailable ? 'true' : 'false');
    this.setAttribute('aria-busy', this._switchLoading ? 'true' : 'false');
    if (this._switchSpinner instanceof HTMLElement) {
      this._switchSpinner.classList.toggle('hidden', !this._switchLoading);
    }
    this._syncVisualState();
  }

  _syncSwitchState() {
    if (!(this._switchInput instanceof HTMLInputElement)) {
      return;
    }

    const checked = this._switchInput.checked;
    this.dataset.checked = String(checked);
    this._switchInput.setAttribute('aria-checked', String(checked));
    this._switchContent.textContent = String(checked ? this._checkedChildren : this._unCheckedChildren);
    this._syncVisualState();
  }

  _syncVisualState() {
    if (!(this._switchInput instanceof HTMLInputElement)) {
      return;
    }

    const size = this.dataset.size === 'small' ? 'small' : 'medium';
    const checked = this._switchInput.checked;
    const unavailable = this._switchDisabled || this._switchLoading;

    this.className = '';
    const nextClasses = [...ROOT_BASE_CLASSES, ...ROOT_SIZE_CLASSES[size]];
    if (unavailable) {
      nextClasses.push(...ROOT_DISABLED_CLASSES);
    } else {
      nextClasses.push(...ROOT_INTERACTIVE_CLASSES, ...(checked ? ROOT_ENABLED_CLASSES.checked : ROOT_ENABLED_CLASSES.unchecked));
    }
    this.classList.add(...nextClasses);

    if (this._switchContent instanceof HTMLElement) {
      const checkedClasses = CONTENT_SIZE_CLASSES[size].checked;
      const uncheckedClasses = CONTENT_SIZE_CLASSES[size].unchecked;
      this._switchContent.classList.remove(...checkedClasses, ...uncheckedClasses);
      this._switchContent.classList.add(...(checked ? checkedClasses : uncheckedClasses));
    }

    if (this._switchIndicator instanceof HTMLElement) {
      this._switchIndicator.classList.toggle(INDICATOR_TRANSLATE_CLASSES[size], checked);
    }
  }
}