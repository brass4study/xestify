import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class BreadcrumbComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'flex items-center gap-2 text-sm text-slate-600';
    this.setAttribute('aria-label', 'Breadcrumb');

    const list = component.create('ol', {
      className: 'flex flex-wrap items-center gap-2',
    });

    const items = Array.isArray(options.items) ? options.items : Array.isArray(options) ? options : [];
    items.forEach((item, index) => {
      const li = component.create('li', {
        className: 'flex items-center gap-2',
      });

      if (item?.href) {
        const link = component.create('a', {
          className: item.active === true ? 'font-semibold text-slate-900' : 'text-slate-600 hover:text-slate-900',
          text: item.label,
          attributes: { href: item.href },
        });
        if (item.active === true) {
          link.setAttribute('aria-current', 'page');
        }
        li.appendChild(link);
      } else {
        const span = component.create('span', {
          className: item.active === true ? 'font-semibold text-slate-900' : 'text-slate-600',
          text: item.label,
        });
        li.appendChild(span);
      }

      if (index < items.length - 1) {
        const separator = component.create('span', {
          className: 'text-slate-400',
          text: '/',
        });
        li.appendChild(separator);
      }

      list.appendChild(li);
    });

    this.appendChild(list);
    return this;
  }
}
