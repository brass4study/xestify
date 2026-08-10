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
      label.setData('role', 'form-field-label');
      if (typeof options.name === 'string' && options.name !== '') {
        label.setAttribute('for', options.name);
      }
      label.setParent(this);
    }

    if (options.input instanceof HTMLElement) {
      this.appendChild(options.input);
    }

    if (typeof options.helpText === 'string' && options.helpText !== '') {
      component.create('p', {
        className: 'text-xs text-slate-500',
        text: options.helpText,
      }).setParent(this);
    }

    return this;
  }
}
