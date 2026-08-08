import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class SectionComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'flex flex-col gap-3';
    this.dataset.role = options.dataRole ?? 'ui-section';

    if (typeof options.title === 'string' && options.title !== '') {
      const title = component.create('typography', {
        as: 'h3',
        text: options.title,
        size: 'sm',
        weight: 'semibold',
        color: 'slate-900',
      });
      this.appendChild(title);
    }

    if (typeof options.subtitle === 'string' && options.subtitle !== '') {
      const subtitle = component.create('typography', {
        as: 'p',
        text: options.subtitle,
        size: 'sm',
        color: 'slate-600',
      });
      this.appendChild(subtitle);
    }

    if (options.children instanceof HTMLElement) {
      this.appendChild(options.children);
    }

    return this;
  }
}
