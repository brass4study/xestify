import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class TabsComponent extends BaseComponent {
  initialize(options = {}) {
    this._tabs = Array.isArray(options.tabs) ? [...options.tabs] : [];
    this._activeId = typeof options.activeId === 'string' ? options.activeId : this._tabs[0]?.id ?? null;
    this._onChange = typeof options.onChange === 'function' ? options.onChange : null;
    this._renderContent = typeof options.renderContent === 'function' ? options.renderContent : null;
    this.className = 'flex flex-col';
    this.dataset.role = 'tabs-root';

    const tabBar = component.create('nav', {
      className: 'relative mb-4 flex items-center',
      dataset: { role: 'tabs-bar' },
      attributes: { role: 'tablist', 'aria-label': options.ariaLabel ?? 'Pestañas' },
    });

    const tabList = component.create('div', {
      className: 'flex flex-wrap gap-8 border-b border-slate-200',
      dataset: { role: 'tabs-list' },
    });

    const content = component.create('div', {
      className: 'pt-4 text-sm text-slate-800',
      dataset: { role: 'tabs-content' },
      attributes: { role: 'tabpanel' },
    });

    this._tabBar = tabBar;
    this._tabList = tabList;
    this._content = content;
    this._inkBar = component.create('span', {
      className: 'tabs-ink-bar',
      dataset: { role: 'tabs-ink-bar' },
      attributes: { 'aria-hidden': 'true' },
    });

    this._tabs.forEach((tab) => this._appendTabButton(tab));
    tabList.setParent(tabBar);
    tabBar.setParent(this);
    content.setParent(this);
    this.setActiveTab(this._activeId, false);
    return this;
  }

  addTab(tab) {
    if (this._tabs.some((item) => item.id === tab.id)) {
      return this;
    }
    this._tabs.push(tab);
    this._appendTabButton(tab);
    return this;
  }

  setActiveTab(id, notify = false) {
    if (!this._tabs.some((tab) => tab.id === id)) {
      return this;
    }
    const activeButton = this._tabList.querySelector(`[data-tab-id="${id}"]`);
    const previousRect = this._inkBar.isConnected
      ? this._inkBar.getBoundingClientRect()
      : null;
    this._activeId = id;
    this._tabList.querySelectorAll('[data-role="tabs-button"]').forEach((button) => {
      const isActive = button.dataset.tabId === id;
      this._applyButtonState(button, isActive);
    });
    this._moveInkBar(activeButton, previousRect);
    this._renderActiveContent();
    if (notify && this._onChange !== null) {
      this._onChange(id);
    }
    return this;
  }

  _appendTabButton(tab) {
    const button = component.create('button', {
      label: tab.label ?? tab.id,
      variant: 'ghost',
      dataRole: 'tabs-button',
    })
      .setClassName(this._tabButtonClass(false))
      .setData('tabId', tab.id)
      .setData('state', 'inactive')
      .setAttribute('role', 'tab');
    button.addEventListener('click', () => this.setActiveTab(tab.id, true));
    button.addEventListener('keydown', (event) => this._handleKeydown(event, tab.id));
    button.setParent(this._tabList);
  }

  _handleKeydown(event, currentId) {
    const currentIndex = this._tabs.findIndex((tab) => tab.id === currentId);
    let nextIndex;
    if (event.key === 'ArrowRight') {
      nextIndex = (currentIndex + 1) % this._tabs.length;
    } else if (event.key === 'ArrowLeft') {
      nextIndex = (currentIndex - 1 + this._tabs.length) % this._tabs.length;
    } else if (event.key === 'Home') {
      nextIndex = 0;
    } else if (event.key === 'End') {
      nextIndex = this._tabs.length - 1;
    } else {
      return;
    }
    event.preventDefault();
    const targetTab = this._tabs[nextIndex];
    this.setActiveTab(targetTab.id, true);
    this._tabList.querySelector(`[data-tab-id="${targetTab.id}"]`)?.focus();
  }

  _tabButtonClass(isActive) {
    const baseClass = 'relative inline-flex items-center bg-transparent px-0 py-3 text-[14px] font-normal leading-[1.5715] transition-colors duration-300 focus:outline-none focus:ring-0';
    const stateClass = isActive
      ? 'text-brand-500'
      : 'text-slate-800 hover:text-brand-400';
    return `${baseClass} ${stateClass}`;
  }

  _applyButtonState(button, isActive) {
    button.className = this._tabButtonClass(isActive);
    button.dataset.state = isActive ? 'active' : 'inactive';
    button.setAttribute('aria-selected', String(isActive));
    button.setAttribute('tabindex', isActive ? '0' : '-1');
  }

  _moveInkBar(button, previousRect) {
    if (!(button instanceof HTMLElement)) {
      return;
    }

    this._inkBar.getAnimations().forEach((animation) => animation.cancel());
    button.appendChild(this._inkBar);

    const currentRect = this._inkBar.getBoundingClientRect();
    if (previousRect === null
      || previousRect.width === 0
      || currentRect.width === 0
      || globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    const offset = previousRect.left - currentRect.left;
    const widthScale = previousRect.width / currentRect.width;
    this._inkBar.animate([
      { transform: `translateX(${offset}px) scaleX(${widthScale})` },
      { transform: 'translateX(0) scaleX(1)' },
    ], {
      duration: 300,
      easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
    });
  }

  _renderActiveContent() {
    this._content.replaceChildren();
    if (this._renderContent === null) {
      return;
    }
    const content = this._renderContent(this._activeId);
    if (content instanceof Node) {
      this._content.appendChild(content);
    } else if (content !== undefined && content !== null) {
      this._content.textContent = String(content);
    }
  }
}
