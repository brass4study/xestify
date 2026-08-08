import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class SpinnerComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'flex items-center justify-center gap-3 text-sm text-slate-600';
    this.dataset.role = 'spinner';

    const spinner = component.create('div', {
      className: 'h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-brand-600',
      attributes: { 'aria-hidden': 'true' },
    });

    const label = component.create('span', {
      text: options.label ?? 'Cargando...',
    });

    this.appendChild(spinner);
    this.appendChild(label);
    return this;
  }
}
