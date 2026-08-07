/**
 * DynamicTabs.js — Tab component with plugin-registrable tabs.
 *
 * Supports:
 *  - registerTab(tab)     — add a tab (called from plugins before render)
 *  - render()             — mount tabs into container
 *  - setActiveTab(id)     — programmatically switch tab
 *  - getActiveTab()       — returns current active tab id
 *  - destroy()            — remove DOM and listeners
 *
 * Tab definition:
 *  { id: string, label: string, content: () => HTMLElement|string }
 *
 * Active tab stays local to the component and never overrides SPA route hashes.
 */

/** @typedef {{ id: string, label: string, content: () => HTMLElement|string }} TabDef */

export class DynamicTabs {
  /** @type {HTMLElement} */
  #container;

  /** @type {TabDef[]} */
  #tabs = [];

  /** @type {string|null} */
  #activeId = null;

  /** @type {HTMLElement|null} */
  #root = null;

  /** @type {HTMLElement|null} */
  #tabBar = null;

  /** @type {HTMLElement|null} */
  #tabList = null;

  /** @type {HTMLElement|null} */
  #inkBar = null;

  /** @type {HTMLElement|null} */
  #tabContent = null;

  /** @type {boolean} */
  #rendered = false;

  /**
   * @param {string|HTMLElement} container
   * @param {{ tabs?: TabDef[] }} [options]
   */
  constructor(container, options = {}) {
    this.#container = typeof container === 'string'
      ? document.querySelector(container)
      : container;

    if (!this.#container) {
      throw new Error('DynamicTabs: container not found');
    }

    const initialTabs = options.tabs ?? [];
    for (const tab of initialTabs) {
      this.#validateTab(tab);
      this.#tabs.push(tab);
    }
  }

  /**
   * Register a tab. Can be called before or after render().
   * @param {TabDef} tab
   */
  registerTab(tab) {
    this.#validateTab(tab);
    const exists = this.#tabs.some((t) => t.id === tab.id);
    if (exists) {
      return;
    }
    this.#tabs.push(tab);
    if (this.#rendered) {
      this.#appendTabButton(tab);
    }
  }

  /** Mount the tabs into the container. */
  render() {
    if (this.#rendered) {
      return;
    }

    this.#root = document.createElement('div');
    this.#root.className = 'flex flex-col';
    this.#root.dataset.role = 'tabs-root';

    this.#tabBar = document.createElement('nav');
    this.#tabBar.className = 'relative mb-4 flex items-center';
    this.#tabBar.dataset.role = 'tabs-bar';
    this.#tabBar.setAttribute('role', 'tablist');

    this.#tabList = document.createElement('div');
    this.#tabList.className = 'relative flex flex-wrap gap-8 border-b border-[#f0f0f0]';
    this.#tabList.dataset.role = 'tabs-list';

    this.#inkBar = document.createElement('div');
    this.#inkBar.className = 'pointer-events-none absolute bottom-[-1px] left-0 h-[2px] rounded-full bg-[#1677ff] transition-[width,transform] duration-300 ease-out';
    this.#inkBar.dataset.role = 'tabs-ink-bar';

    this.#tabContent = document.createElement('div');
    this.#tabContent.className = 'pt-4 text-[14px] text-[rgba(0,0,0,0.88)]';
    this.#tabContent.dataset.role = 'tabs-content';

    for (const tab of this.#tabs) {
      this.#appendTabButton(tab);
    }

    this.#tabList.appendChild(this.#inkBar);
    this.#tabBar.appendChild(this.#tabList);
    this.#root.appendChild(this.#tabBar);
    this.#root.appendChild(this.#tabContent);
    this.#container.appendChild(this.#root);

    this.#rendered = true;

    const initialId = this.#resolveInitialTab();
    if (initialId) {
      this.setActiveTab(initialId);
    }
  }

  /**
   * Switch to a tab by id.
   * @param {string} id
   */
  setActiveTab(id) {
    const tab = this.#tabs.find((t) => t.id === id);
    if (!tab) {
      return;
    }

    this.#activeId = id;

    if (this.#rendered) {
      this.#updateTabBar(id);
      this.#renderContent(tab);
    }
  }

  /**
   * Returns the current active tab id.
   * @returns {string|null}
   */
  getActiveTab() {
    return this.#activeId;
  }

  /** Remove DOM nodes and reset state. */
  destroy() {
    if (this.#root) {
      this.#root.remove();
      this.#root = null;
      this.#tabBar = null;
      this.#tabList = null;
      this.#inkBar = null;
      this.#tabContent = null;
    }
    this.#rendered = false;
    this.#activeId = null;
  }

  // Private helpers.

  /**
   * @param {TabDef} tab
   */
  #appendTabButton(tab) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = this.#tabButtonClass(false);
    btn.dataset.role = 'tabs-button';
    btn.dataset.tabId = tab.id;
    btn.setAttribute('role', 'tab');
    btn.textContent = tab.label;

    if (tab.id === this.#activeId) {
      this.#applyButtonState(btn, true);
    } else {
      this.#applyButtonState(btn, false);
    }

    btn.addEventListener('click', () => this.setActiveTab(tab.id));
    this.#tabList.appendChild(btn);
  }

  /**
   * @param {string} activeId
   */
  #updateTabBar(activeId) {
    const buttons = this.#tabBar.querySelectorAll('[data-role="tabs-button"]');
    let activeButton = null;
    for (const btn of buttons) {
      const isActive = btn.dataset.tabId === activeId;
      this.#applyButtonState(btn, isActive);
      if (isActive) {
        activeButton = btn;
      }
    }

    if (activeButton instanceof HTMLElement) {
      this.#syncInkBar(activeButton);
    }
  }

  /**
   * @param {boolean} isActive
   * @returns {string}
   */
  #tabButtonClass(isActive) {
    const baseClass = 'relative inline-flex items-center bg-transparent px-0 py-3 text-[14px] font-normal leading-[1.5715] transition-colors duration-300 focus:outline-none focus:ring-0';
    const activeClass = 'text-[#1677ff]';
    const inactiveClass = 'text-[rgba(0,0,0,0.88)] hover:text-[#4096ff]';
    return `${baseClass} ${isActive ? activeClass : inactiveClass}`;
  }

  /**
   * @param {HTMLElement} button
   * @param {boolean} isActive
   */
  #applyButtonState(button, isActive) {
    button.className = this.#tabButtonClass(isActive);
    button.setAttribute('aria-selected', String(isActive));
  }

  /**
   * @param {HTMLElement} button
   */
  #syncInkBar(button) {
    if (this.#inkBar === null) {
      return;
    }

    this.#inkBar.style.width = `${button.offsetWidth}px`;
    this.#inkBar.style.transform = `translateX(${button.offsetLeft}px)`;
  }

  /**
   * @param {TabDef} tab
   */
  #renderContent(tab) {
    this.#tabContent.replaceChildren();
    const raw = tab.content();
    if (typeof raw === 'string') {
      const wrapper = document.createElement('div');
      wrapper.textContent = raw;
      this.#tabContent.appendChild(wrapper);
    } else if (raw instanceof HTMLElement) {
      this.#tabContent.appendChild(raw);
    }
  }

  /**
   * @returns {string|null}
   */
  #resolveInitialTab() {
    if (this.#tabs.length === 0) {
      return null;
    }

    if (typeof this.#activeId === 'string' && this.#activeId !== '') {
      const found = this.#tabs.find((t) => t.id === this.#activeId);
      if (found !== undefined) {
        return found.id;
      }
    }

    return this.#tabs[0].id;
  }

  /**
   * @param {TabDef} tab
   */
  #validateTab(tab) {
    if (!tab || typeof tab.id !== 'string' || !tab.id) {
      throw new Error('DynamicTabs: tab must have a non-empty string id');
    }
    if (typeof tab.label !== 'string' || !tab.label) {
      throw new Error('DynamicTabs: tab must have a non-empty string label');
    }
    if (typeof tab.content !== 'function') {
      throw new TypeError('DynamicTabs: tab.content must be a function');
    }
  }
}
