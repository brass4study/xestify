import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class FormFieldComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'grid gap-1.5';
    this.dataset.role = 'form-field';

    if (typeof options.label === 'string' && options.label !== '') {
      const label = component.create('label', {
        className: 'text-sm font-medium text-slate-700',
        text: options.label,
      });
      if (typeof options.name === 'string' && options.name !== '') {
        label.setAttribute('for', options.name);
      }
      this.appendChild(label);
    }

    if (options.input instanceof HTMLElement) {
      this.appendChild(options.input);
    }

    if (typeof options.helpText === 'string' && options.helpText !== '') {
      const help = component.create('p', {
        className: 'text-xs text-slate-500',
        text: options.helpText,
      });
      this.appendChild(help);
    }

    return this;
  }
}
