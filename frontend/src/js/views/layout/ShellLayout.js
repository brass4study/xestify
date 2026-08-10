import { component } from '../modules/ComponentFactory.js';

/**
 * Armazón persistente de la aplicación. Solo conoce sus zonas estructurales.
 */
export class ShellLayout {
  /** @type {HTMLElement} */
  #container;

  /** @type {Map<string, HTMLElement>} */
  #targets = new Map();

  /**
   * @param {HTMLElement} container
   */
  constructor(container) {
    if (!(container instanceof HTMLElement)) {
      throw new TypeError('ShellLayout requires an HTMLElement container');
    }
    this.#container = container;
  }

  /**
   * @param {HTMLElement} container
   * @returns {ShellLayout}
   */
  static create(container) {
    return new ShellLayout(container);
  }

  /** @returns {this} */
  build() {
    this.#container.replaceChildren();
    this.#targets.clear();

    const shell = this.#createTarget('shell', 'section',
      'mx-auto flex min-h-screen w-full max-w-[1280px] flex-col');
    const menu = this.#createTarget('shell-menu', 'header',
      'sticky top-0 z-50 flex flex-wrap items-center gap-3 bg-gradient-to-r from-brand-700 via-brand-600 to-brand-500 px-4 py-3 text-white', shell);
    this.#createTarget('shell-menu-nav', 'div', 'min-w-0 flex-1', menu);
    const menuConfig = this.#createTarget('shell-menu-config', 'div', 'ml-auto flex items-center gap-2', menu);
    this.#createTarget('shell-menu-config-user', 'div', '', menuConfig);

    const main = this.#createTarget('shell-main', 'main', 'flex flex-1 flex-col pb-4', shell);
    this.#createTarget('shell-header', 'header', 'pb-4', main);
    this.#createTarget('shell-main-notifications', 'section',
      'flex flex-col gap-2', main, {
        'aria-label': 'Notificaciones',
        'aria-live': 'polite',
      });
    this.#createTarget('shell-main-content', 'section', 'pb-4', main);
    this.#createTarget('shell-main-actions', 'div',
      'flex flex-wrap justify-end gap-2 pb-4', main);
    this.#createTarget('shell-footer', 'footer',
      'px-3 pb-4 pt-1 text-xs text-slate-500 sm:px-4 sm:pb-6', shell);

    this.#syncAutoHiddenZone('shell-main-notifications');
    this.#syncAutoHiddenZone('shell-main-actions');

    this.#container.append(shell);
    return this;
  }

  /**
   * Registra un nuevo punto de integración sin ampliar el contrato base.
   * @param {string} name
   * @param {HTMLElement} target
  * @returns {this}
   */
  registerTarget(name, target) {
    if (typeof name !== 'string' || name === '' || !(target instanceof HTMLElement)) {
      throw new TypeError('ShellLayout target requires a name and HTMLElement');
    }
    this.#targets.set(name, target);
    return this;
  }

  /**
   * @param {string} name
   * @returns {HTMLElement|null}
   */
  getTarget(name) {
    return this.#targets.get(name) ?? null;
  }

  /**
   * @param {string} name
   * @param {Node|Node[]|Function|null} content
  * @returns {this}
   */
  setZone(name, content) {
    const target = this.#requireTarget(name);
    target.replaceChildren();
    this.#appendContent(target, content);
    this.#syncAutoHiddenZone(name, target);
    return this;
  }

  /**
   * @param {string} name
   * @param {Node|Node[]|Function|null} content
  * @returns {this}
   */
  appendZone(name, content) {
    const target = this.#requireTarget(name);
    this.#appendContent(target, content);
    this.#syncAutoHiddenZone(name, target);
    return this;
  }

  /**
   * @param {string} name
  * @returns {this}
   */
  clearZone(name) {
    const target = this.#requireTarget(name);
    target.replaceChildren();
    this.#syncAutoHiddenZone(name, target);
    return this;
  }

  /** @param {string} template @returns {this} */
  setTemplate(template) {
    this.#requireTarget('shell-main-content').dataset.template =
      typeof template === 'string' && template !== '' ? template : 'home';
    return this;
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  setMainActions(content) {
    return this.setZone('shell-main-actions', content);
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  setContent(content) {
    return this.setZone('shell-main-content', content);
  }

  /** @param {string} text @returns {this} */
  setFooter(text) {
    const target = this.#requireTarget('shell-footer');
    target.textContent = typeof text === 'string' ? text : '';
    target.hidden = target.textContent === '';
    return this;
  }

  /**
   * @param {string} name
   * @param {string} tagName
   * @param {string} className
   * @param {HTMLElement|null} parent
   * @param {Object<string, string>} attributes
   * @returns {HTMLElement}
   */
  #createTarget(name, tagName, className, parent = null, attributes = {}) {
    const target = component.create(tagName);
    target.className = className;
    target.dataset.role = name;
    for (const [attribute, value] of Object.entries(attributes)) {
      target.setAttribute(attribute, value);
    }
    if (parent instanceof HTMLElement) {
      parent.append(target);
    }
    this.registerTarget(name, target);
    return target;
  }

  /** @param {string} name @returns {HTMLElement} */
  #requireTarget(name) {
    const target = this.getTarget(name);
    if (!(target instanceof HTMLElement)) {
      throw new TypeError(`ShellLayout target "${String(name)}" is not registered`);
    }
    return target;
  }

  /**
   * @param {HTMLElement} target
   * @param {Node|Node[]|Function|null} content
   */
  #appendContent(target, content) {
    const built = typeof content === 'function' ? content(target, this) : content;
    const items = Array.isArray(built) ? built : [built];
    for (const item of items) {
      if (item instanceof Node && item.parentNode !== target) {
        target.append(item);
      }
    }
  }

  /**
   * @param {string} name
   * @param {HTMLElement|null} resolvedTarget
   */
  #syncAutoHiddenZone(name, resolvedTarget = null) {
    if (name !== 'shell-main-notifications' && name !== 'shell-main-actions') {
      return;
    }
    const target = resolvedTarget instanceof HTMLElement ? resolvedTarget : this.getTarget(name);
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const hasVisibleChildren = Array.from(target.children)
      .some((child) => child instanceof HTMLElement && !child.hidden);
    target.hidden = !hasVisibleChildren;
    if (name === 'shell-main-notifications') {
      target.classList.toggle('pb-4', hasVisibleChildren);
    }
  }

}