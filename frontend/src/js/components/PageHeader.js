import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class PageHeaderComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-start sm:justify-between';
    this.dataset.role = 'page-header';

    const titleBlock = component.create('div', {
      className: 'flex flex-col gap-1',
    });

    const title = component.create('h2', {
      className: 'text-lg font-semibold text-slate-900',
      dataset: { role: 'page-title' },
      text: options.title ?? 'Página',
    });
    titleBlock.appendChild(title);

    if (typeof options.subtitle === 'string' && options.subtitle !== '') {
      const subtitle = component.create('p', {
        className: 'text-sm text-slate-600',
        text: options.subtitle,
      });
      titleBlock.appendChild(subtitle);
    }

    this.appendChild(titleBlock);

    if (Array.isArray(options.actions) && options.actions.length > 0) {
      const actions = component.create('div', {
        className: 'flex flex-wrap items-center gap-2',
        dataset: { role: 'page-actions' },
      });
      options.actions.forEach((action) => {
        if (action instanceof HTMLElement) {
          actions.appendChild(action);
        }
      });
      this.appendChild(actions);
    }

    return this;
  }
}
