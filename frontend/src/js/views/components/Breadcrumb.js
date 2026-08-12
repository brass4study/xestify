import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class BreadcrumbComponent extends BaseComponent {
  initialize(options = {}) {
    const config = this.resolveConfig(options);

    this.className = 'inline-flex max-w-full items-center text-sm text-slate-600';
    this.setAttribute('aria-label', 'Breadcrumb');
    this.dataset.role = 'ui-breadcrumb';

    const list = component.create('ol', {
      className: 'flex flex-wrap items-center gap-0.5',
    }).setData('role', 'breadcrumb-list');

    const items = config.items;
    let visualItemIndex = 0;
    items.forEach((item, index) => {
      if (item?.type === 'separator') {
        list.appendChild(this.createSeparatorNode(item.separator ?? config.separator));
        return;
      }

      const isLast = index === items.length - 1;
      const isFirst = visualItemIndex === 0;
      const li = component.create('li', {
        className: 'inline-flex items-center',
      }).setData('role', 'breadcrumb-item');

      const node = this.createItemNode(item, isLast, isFirst);
      node.setParent(li);
      li.setParent(list);
      visualItemIndex += 1;

      if (!isLast) {
        list.appendChild(this.createSeparatorNode(config.separator));
      }
    });

    list.setParent(this);
    return this;
  }

  createItemNode(item, isLast, isFirst) {
    const itemLabel = typeof item?.label === 'string' && item.label !== '' ? item.label : 'Ruta';
    const isActive = item?.active === true || isLast;
    const isLink = typeof item?.href === 'string' && item.href !== '' && !isActive;
    const horizontalPaddingClass = isFirst ? 'pl-0 pr-2' : 'px-2';
    const activeClass = `inline-flex items-center gap-1 rounded ${horizontalPaddingClass} py-1 font-medium text-slate-900`;
    const linkClass = `inline-flex items-center gap-1 rounded ${horizontalPaddingClass} py-1 text-slate-500 transition-all duration-200 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-300`;
    const textClass = `inline-flex items-center gap-1 rounded ${horizontalPaddingClass} py-1 text-slate-500`;
    let itemClass = textClass;
    if (isActive) {
      itemClass = activeClass;
    } else if (isLink) {
      itemClass = linkClass;
    }

    const node = isLink
      ? component.create('a', {
          className: itemClass,
          attributes: { href: item.href },
        })
      : component.create('span', {
          className: itemClass,
        });

    if (isActive) {
      node.setAttribute('aria-current', 'page');
    }

    if (typeof item?.onClick === 'function') {
      node.addEventListener('click', (event) => {
        item.onClick(event);
      });
    }

    this.appendItemIcon(node, item);

    component.create('span', {
      text: itemLabel,
      className: 'truncate',
    }).setParent(node);

    if (Array.isArray(item?.menu) && item.menu.length > 0 && !isActive) {
      return this.wrapWithDropdown(node, item.menu);
    }

    return node;
  }

  appendItemIcon(node, item) {
    if (typeof item?.iconClass === 'string' && item.iconClass !== '') {
      component.create('i', {
        className: `${item.iconClass} text-[12px] text-slate-500`,
      })
        .setAttribute('aria-hidden', 'true')
        .setParent(node);
      return;
    }

    if (item?.icon instanceof Node) {
      node.appendChild(item.icon);
    }
  }

  wrapWithDropdown(triggerNode, menuItems) {
    const wrapper = component.create('span', {
      className: 'relative inline-flex',
      dataset: { role: 'breadcrumb-dropdown' },
    });

    const trigger = component.create('button', {
      className: 'inline-flex items-center gap-1',
      attributes: { type: 'button' },
    }).setData('role', 'breadcrumb-dropdown-trigger');

    triggerNode.setParent(trigger);

    const downIcon = component.create('i', {
      className: 'fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-200',
    })
      .setAttribute('aria-hidden', 'true')
      .setParent(trigger);

    const panel = component.create('ul', {
      className: 'absolute left-0 top-full z-20 mt-1 hidden min-w-[170px] rounded-lg border border-slate-200 bg-white p-1 shadow-lg',
    }).setData('role', 'breadcrumb-dropdown-menu');

    menuItems.forEach((entry) => {
      if (entry === null || typeof entry !== 'object') {
        return;
      }

      const row = component.create('li');
      const rowLabel = typeof entry.label === 'string' && entry.label !== '' ? entry.label : 'Opción';
      const rowNode = typeof entry.href === 'string' && entry.href !== ''
        ? component.create('a', {
            text: rowLabel,
            className: 'block rounded-md px-2 py-1.5 text-sm text-slate-600 transition-colors duration-150 hover:bg-slate-100 hover:text-slate-900',
            attributes: { href: entry.href },
          })
        : component.create('button', {
            text: rowLabel,
            className: 'block w-full rounded-md px-2 py-1.5 text-left text-sm text-slate-600 transition-colors duration-150 hover:bg-slate-100 hover:text-slate-900',
            attributes: { type: 'button' },
          });

      if (typeof entry.onClick === 'function') {
        rowNode.addEventListener('click', (event) => {
          entry.onClick(event);
        });
      }

      rowNode.setParent(row);
      row.setParent(panel);
    });

    const close = () => {
      panel.classList.add('hidden');
      downIcon.classList.remove('rotate-180');
      trigger.setAttribute('aria-expanded', 'false');
    };

    const toggle = () => {
      const isOpen = panel.classList.contains('hidden') === false;
      if (isOpen) {
        close();
        return;
      }

      panel.classList.remove('hidden');
      downIcon.classList.add('rotate-180');
      trigger.setAttribute('aria-expanded', 'true');
    };

    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      toggle();
    });

    wrapper.addEventListener('mouseleave', () => {
      close();
    });

    trigger.setParent(wrapper);
    panel.setParent(wrapper);
    return wrapper;
  }

  createSeparatorNode(separatorValue) {
    const separatorText = typeof separatorValue === 'string' && separatorValue !== '' ? separatorValue : '/';
    const separator = component.create('span', {
      className: 'mx-1 text-slate-400 transition-colors duration-200',
      text: separatorText,
    });
    return separator
      .setData('role', 'breadcrumb-separator')
      .setAttribute('aria-hidden', 'true');
  }

  resolveConfig(options) {
    if (Array.isArray(options)) {
      return {
        items: options,
        separator: '/',
      };
    }

    return {
      items: this.resolveItems(options),
      separator: typeof options.separator === 'string' && options.separator !== '' ? options.separator : '/',
    };
  }

  resolveItems(options) {
    if (Array.isArray(options.items)) {
      return options.items;
    }

    return [];
  }
}
