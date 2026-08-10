import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class SkeletonComponent extends BaseComponent {
  initialize(options = {}) {
    const rows = Number.isInteger(options.rows) && options.rows > 0 ? options.rows : 3;
    this.className = 'flex flex-col gap-3';

    for (let index = 0; index < rows; index += 1) {
      component.create('div', {
        className: index === rows - 1
          ? 'h-3 w-2/3 animate-pulse rounded-full bg-slate-200'
          : 'h-3 w-full animate-pulse rounded-full bg-slate-200',
        dataset: { role: 'skeleton-line' },
      }).setParent(this);
    }

    return this;
  }
}
