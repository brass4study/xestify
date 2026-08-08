import { InputComponent } from './BaseComponent.js';

export class InputRadioComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('radio');
    this.className = 'h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500';
    return this;
  }
}
