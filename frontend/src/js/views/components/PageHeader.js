import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class PageHeaderComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'grid gap-3 bg-white px-4 py-4';
    this.dataset.role = 'page-header';

    const breadcrumbItems = Array.isArray(options.breadcrumbs)
      ? options.breadcrumbs.filter((item) => item !== null && typeof item === 'object')
      : [];

    if (breadcrumbItems.length > 0) {
      const breadcrumb = component.create('breadcrumb', {
        items: breadcrumbItems,
      });
      breadcrumb
        .setData('role', typeof options.breadcrumbDataRole === 'string' && options.breadcrumbDataRole !== ''
          ? options.breadcrumbDataRole
          : 'page-header-breadcrumb')
        .setParent(this);
    }

    const content = component.create('div', {
      className: 'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
      dataset: { role: 'page-header-content' },
    });

    const titleBlock = component.create('div', {
      className: 'flex flex-col gap-1',
    });

    const title = component.create('h2', {
      className: 'text-lg font-semibold text-slate-900',
      dataset: { role: 'page-title' },
      text: options.title ?? 'Página',
    }).setParent(titleBlock);

    if (typeof options.subtitle === 'string' && options.subtitle !== '') {
      component.create('p', {
        className: 'text-sm text-slate-600',
        text: options.subtitle,
      }).setParent(titleBlock);
    }

    titleBlock.setParent(content);

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
      actions.setParent(content);
    }

    let toolbar = null;
    const toolbarItems = this.resolveToolbarItems(options.toolbar);
    if (toolbarItems.length > 0 || options.toolbar !== undefined) {
      const roles = this.resolveToolbarRoles(options);
      toolbar = this.buildToolbar(toolbarItems, roles);
      toolbar.setParent(content);
    }

    content.setParent(this);

    this._targets = new Map([
      ['content', content],
      ['title', title],
      ['copy', titleBlock],
      ['toolbar', toolbar],
    ]);

    return this;
  }

  /**
   * Public accessor for this component's internal layout zones, so
   * consumers (PageLayout) don't need to querySelector() into markup
   * PageHeaderComponent owns and may change.
   *
   * @param {string} name One of 'content' | 'title' | 'copy' | 'toolbar'.
   * @returns {HTMLElement|null}
   */
  getTarget(name) {
    return this._targets?.get(name) ?? null;
  }

  resolveToolbarItems(toolbarOption) {
    if (Array.isArray(toolbarOption)) {
      return toolbarOption.filter((item) => item instanceof HTMLElement);
    }

    if (toolbarOption instanceof HTMLElement) {
      return [toolbarOption];
    }

    if (toolbarOption !== null && typeof toolbarOption === 'object' && Array.isArray(toolbarOption.nodes)) {
      return toolbarOption.nodes.filter((item) => item instanceof HTMLElement);
    }

    return [];
  }

  resolveToolbarRoles(options) {
    const toolbarDataRole = typeof options.toolbarDataRole === 'string' && options.toolbarDataRole !== ''
      ? options.toolbarDataRole
      : 'page-header-toolbar';

    return {
      toolbarDataRole,
    };
  }

  buildToolbar(items, roles) {
    const toolbar = component.create('div', {
      className: 'ml-auto flex h-full shrink-0 items-end justify-end gap-2',
      dataset: { role: roles.toolbarDataRole },
    });

    for (const item of items) {
      toolbar.appendChild(item);
    }
    return toolbar;
  }
}
