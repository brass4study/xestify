import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class SectionComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'flex flex-col gap-3';
    this.dataset.role = options.dataRole ?? 'ui-section';

    if (typeof options.title === 'string' && options.title !== '') {
      component.create('typography', {
        as: 'h3',
        text: options.title,
        size: 'sm',
        weight: 'semibold',
        color: 'slate-900',
      }).setParent(this);
    }

    if (typeof options.subtitle === 'string' && options.subtitle !== '') {
      component.create('typography', {
        as: 'p',
        text: options.subtitle,
        size: 'sm',
        color: 'slate-600',
      }).setParent(this);
    }

    if (options.children instanceof HTMLElement) {
      this.appendChild(options.children);
    }

    return this;
  }
}
