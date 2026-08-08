import { InputComponent } from './BaseComponent.js';

export class InputTextAreaComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.className = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500';
    this.rows = Number.isInteger(options.rows) ? options.rows : 4;
    return this;
  }
}
