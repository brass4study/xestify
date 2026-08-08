import { InputComponent } from './BaseComponent.js';

export class InputDateComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('date');
    this.className = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500';
    return this;
  }
}
