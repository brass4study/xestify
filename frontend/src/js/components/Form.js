import { BaseComponent } from './BaseComponent.js';

export class FormComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = typeof options.className === 'string' && options.className !== '' ? options.className : 'grid gap-4';
    if (options.novalidate !== false) {
      this.setAttribute('novalidate', 'novalidate');
    }
    if (typeof options.id === 'string' && options.id !== '') {
      this.id = options.id;
    }
    if (typeof options.name === 'string' && options.name !== '') {
      this.name = options.name;
    }
    if (typeof options.dataRole === 'string' && options.dataRole !== '') {
      this.dataset.role = options.dataRole;
    }
    return this;
  }
}
