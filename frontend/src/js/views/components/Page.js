import { BaseComponent } from './BaseComponent.js';

export class PageComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'flex flex-col gap-4';
    this.dataset.role = options.dataRole ?? 'ui-page';

    if (options.children instanceof Node) {
      this.appendChild(options.children);
    }

    return this;
  }
}
