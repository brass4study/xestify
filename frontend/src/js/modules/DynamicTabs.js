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

import { component } from './ComponentFactory.js';

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
      this.validateTab(tab);
      this.#tabs.push(tab);
    }
  }

  /**
   * Register a tab. Can be called before or after render().
   * @param {TabDef} tab
   */
  registerTab(tab) {
    this.validateTab(tab);
    const exists = this.#tabs.some((t) => t.id === tab.id);
    if (exists) {
      return;
    }
    this.#tabs.push(tab);
    if (this.#rendered && this.#root !== null) {
      this.#root.addTab({ id: tab.id, label: tab.label });
    }
  }

  /** Mount the tabs into the container. */
  render() {
    if (this.#rendered) {
      return;
    }

    this.#rendered = true;

    const initialId = this.resolveInitialTab();
    if (initialId) {
      this.#activeId = initialId;
    }
    this.renderTabs();
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

    if (this.#rendered && this.#root !== null) {
      this.#root.setActiveTab(id, false);
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
    }
    this.#rendered = false;
    this.#activeId = null;
  }

  // Private helpers.

  renderTabs() {
    const activeId = this.#activeId ?? this.resolveInitialTab();
    this.#root = component.create('tabs', {
      tabs: this.#tabs.map((tab) => ({ id: tab.id, label: tab.label })),
      activeId,
      onChange: (id) => {
        this.#activeId = id;
      },
      renderContent: (id) => this.#tabs.find((tab) => tab.id === id)?.content() ?? '',
    });
    this.#container.appendChild(this.#root);
    if (activeId !== null) {
      this.#root.setActiveTab(activeId, false);
    }
  }

  /**
   * @returns {string|null}
   */
  resolveInitialTab() {
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
  validateTab(tab) {
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
