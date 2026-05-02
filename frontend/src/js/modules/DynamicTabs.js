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
 * Active tab persists in URL hash: #tab-<id>
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
    this.#root.className = 'xt-tabs';

    this.#tabBar = document.createElement('nav');
    this.#tabBar.className = 'xt-tabs__bar';
    this.#tabBar.setAttribute('role', 'tablist');

    this.#tabContent = document.createElement('div');
    this.#tabContent.className = 'xt-tabs__content';

    for (const tab of this.#tabs) {
      this.#appendTabButton(tab);
    }

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
      this.#persistHash(id);
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
      this.#tabContent = null;
    }
    this.#rendered = false;
    this.#activeId = null;
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  /**
   * @param {TabDef} tab
   */
  #appendTabButton(tab) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'xt-tabs__btn';
    btn.dataset.tabId = tab.id;
    btn.setAttribute('role', 'tab');
    btn.textContent = tab.label;

    if (tab.id === this.#activeId) {
      btn.classList.add('xt-tabs__btn--active');
      btn.setAttribute('aria-selected', 'true');
    } else {
      btn.setAttribute('aria-selected', 'false');
    }

    btn.addEventListener('click', () => this.setActiveTab(tab.id));
    this.#tabBar.appendChild(btn);
  }

  /**
   * @param {string} activeId
   */
  #updateTabBar(activeId) {
    const buttons = this.#tabBar.querySelectorAll('.xt-tabs__btn');
    for (const btn of buttons) {
      const isActive = btn.dataset.tabId === activeId;
      btn.classList.toggle('xt-tabs__btn--active', isActive);
      btn.setAttribute('aria-selected', String(isActive));
    }
  }

  /**
   * @param {TabDef} tab
   */
  #renderContent(tab) {
    this.#tabContent.innerHTML = '';
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
   * @param {string} id
   */
  #persistHash(id) {
    if (typeof history !== 'undefined' && history.replaceState) {
      history.replaceState(null, '', `#tab-${id}`);
    }
  }

  /**
   * @returns {string|null}
   */
  #resolveInitialTab() {
    if (this.#tabs.length === 0) {
      return null;
    }
    const hash = typeof location !== 'undefined' ? location.hash : '';
    const match = hash.match(/^#tab-(.+)$/);
    if (match) {
      const fromHash = match[1];
      const found = this.#tabs.find((t) => t.id === fromHash);
      if (found) {
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
      throw new Error('DynamicTabs: tab.content must be a function');
    }
  }
}
