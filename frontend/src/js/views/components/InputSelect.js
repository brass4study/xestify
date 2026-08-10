import { InputComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class InputSelectComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.className = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500';
    (Array.isArray(options.options) ? options.options : []).forEach((option) => {
      const optionEl = component.create('option');
      if (typeof option === 'object' && option !== null) {
        optionEl.value = String(option.value ?? '');
        optionEl.textContent = String(option.label ?? option.value ?? '');
      } else {
        optionEl.value = String(option);
        optionEl.textContent = String(option);
      }
      optionEl.setParent(this);
    });
    if (options.value !== '' && options.value !== undefined && options.value !== null) {
      this.value = String(options.value);
    }
    return this;
  }
}
