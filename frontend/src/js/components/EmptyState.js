import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class EmptyStateComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center';
    this.dataset.role = 'empty-state';

    const title = component.create('h3', {
      className: 'text-base font-semibold text-slate-900',
      dataset: { role: 'empty-title' },
      text: options.title ?? 'Sin contenido',
    });
    this.appendChild(title);

    if (typeof options.description === 'string' && options.description !== '') {
      const description = component.create('p', {
        className: 'max-w-md text-sm text-slate-600',
        dataset: { role: 'empty-description' },
        text: options.description,
      });
      this.appendChild(description);
    }

    if (options.action instanceof HTMLElement) {
      this.appendChild(options.action);
    }

    return this;
  }
}
