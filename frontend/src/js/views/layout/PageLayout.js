import { ShellLayout } from './ShellLayout.js';
import { component } from '../modules/ComponentFactory.js';

/**
 * Layout de página que sincroniza directamente con una instancia de ShellLayout.
 */
export class PageLayout {
  /** @type {HTMLElement|null} */
  #container;

  /** @type {ShellLayout|null} */
  #shell;

  /** @type {DocumentFragment} */
  #content = document.createDocumentFragment();

  /** @type {HTMLElement|null} */
  #page = null;

  /** @type {HTMLElement|null} */
  #contentTarget = null;

  /** @type {string} */
  #template = 'home';

  /** @type {string} */
  #footerText = '';

  /** @type {HTMLElement|null} */
  #footerTarget = null;

  /** @type {Map<string, HTMLElement>} */
  #targets = new Map();

  /**
   * @param {HTMLElement|null} container
   * @param {{shell?: ShellLayout}} options
   */
  constructor(container = null, options = {}) {
    this.#container = container instanceof HTMLElement ? container : null;
    this.#shell = options.shell instanceof ShellLayout ? options.shell : null;
    if (this.#shell !== null) {
      this.#mountHeader();
    }
  }

  /**
   * @param {HTMLElement|null} container
   * @param {{shell?: ShellLayout}} options
   * @returns {PageLayout}
   */
  static create(container = null, options = {}) {
    return new this(container, options);
  }

  /** @returns {ShellLayout|null} */
  getShell() {
    return this.#shell;
  }

  /** @param {string} template @returns {this} */
  setTemplate(template) {
    this.#template = typeof template === 'string' && template !== '' ? template : 'home';
    this.#shell?.setTemplate(this.#template);
    return this;
  }

  /** @param {string} title @returns {this} */
  setTitle(title) {
    const heading = this.#getTarget('page-header-title');
    if (heading !== null) {
      heading.textContent = typeof title === 'string' ? title : '';
      heading.hidden = heading.textContent === '';
    }
    return this;
  }

  /** @param {string} description @returns {this} */
  setDescription(description) {
    const target = this.#getTarget('page-header-copy');
    target?.querySelector('[data-role="page-header-description"]')?.remove();
    if (target !== null && typeof description === 'string' && description !== '') {
      const paragraph = component.create('typography', {
        text: description,
        size: 'sm',
        weight: 'normal',
        color: 'slate-600',
      }).setData('role', 'page-header-description').setParent(target);
      this.#targets.set('page-header-description', paragraph);
    }
    return this;
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  setBreadcrumbs(content) {
    const target = this.#getTarget('page-header-breadcrumbs');
    if (target === null) {
      return this;
    }
    target.replaceChildren();
    if (Array.isArray(content)
      && content.every((item) => item !== null && typeof item === 'object' && !(item instanceof Node))) {
      component.create('breadcrumb', { items: content })
        .setData('role', 'page-breadcrumbs')
        .setParent(target);
      return this;
    }
    this.#appendContent(target, content);
    return this;
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  setHeaderToolbar(content) {
    const target = this.#getTarget('page-header-toolbar');
    if (target !== null) {
      target.replaceChildren();
      this.#appendContent(target, content);
    }
    return this;
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  setHeaderBottom(content) {
    const target = this.#getTarget('page-header-bottom');
    if (target !== null) {
      target.replaceChildren();
      this.#appendContent(target, content);
      this.#syncHeaderBottom();
    }
    return this;
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  setActions(content) {
    this.#shell?.setMainActions(content);
    return this;
  }

  /** @param {string} name @returns {HTMLElement|null} */
  getTarget(name) {
    return this.#getTarget(name);
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  addMainAction(content) {
    this.#shell?.appendZone('shell-main-actions', content);
    return this;
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  setContent(content) {
    this.#content = document.createDocumentFragment();
    if (this.#shell instanceof ShellLayout) {
      this.#shell.setContent(content);
      this.#page = this.#shell.getTarget('shell-main-content');
      this.#contentTarget = this.#page;
      return this;
    }
    this.#appendToFragment(content);
    return this;
  }

  /** @param {string} text @returns {this} */
  setFooter(text) {
    this.#footerText = typeof text === 'string' ? text : '';
    this.#shell?.setFooter(this.#footerText);
    if (this.#footerTarget instanceof HTMLElement) {
      this.#footerTarget.textContent = this.#footerText;
    }
    return this;
  }

  /** @param {Node|Node[]|Function|null} content @returns {this} */
  setNotification(content) {
    if (this.#shell instanceof ShellLayout) {
      this.#shell.setZone('shell-main-notifications', content);
      return this;
    }
    if (this.#page instanceof HTMLElement) {
      const current = this.#page.querySelector(':scope > [data-page-notification]');
      current?.remove();
      if (content instanceof Node) {
        content.dataset.pageNotification = '';
        this.#page.prepend(content);
      }
    }
    return this;
  }

  /** @returns {this} */
  build() {
    if (this.#shell instanceof ShellLayout) {
      if (this.#content.hasChildNodes()) {
        this.#shell.setContent(this.#content);
      }
      this.#page = this.#shell.getTarget('shell-main-content');
      this.#contentTarget = this.#page;
      return this;
    }
    if (!(this.#container instanceof HTMLElement)) {
      throw new TypeError('PageLayout cannot build without a container or ShellLayout');
    }
    this.#container.replaceChildren();
    const isLogin = this.#template === 'login';
    this.#page = component.create('page', {
      dataRole: isLogin ? 'login-shell' : 'page-layout',
    });
    if (isLogin) {
      this.#page.setClassName('mx-auto flex min-h-screen w-full max-w-[1280px] flex-col px-3 py-6 sm:px-4 sm:py-8');
    }
    this.#contentTarget = component.create(isLogin ? 'main' : 'sectionTag')
      .setData('role', isLogin ? 'login-content' : 'page-content');
    if (isLogin) {
      this.#contentTarget.setClassName('flex flex-1 items-start justify-center');
    }
    this.#contentTarget.append(this.#content);
    this.#page.append(this.#contentTarget);
    if (isLogin && this.#footerText !== '') {
      this.#footerTarget = component.create('footer', {
        className: 'pt-4 text-center text-xs text-slate-500',
        text: this.#footerText,
      })
        .setData('role', 'login-footer')
        .setParent(this.#page);
    }
    this.#container.append(this.#page);
    return this;
  }

  /** @returns {HTMLElement|null} */
  getContentTarget() {
    return this.#contentTarget;
  }

  /** @param {Node|Node[]|Function|null} content */
  #appendToFragment(content) {
    if (typeof content === 'function') {
      const host = component.create('div');
      const built = content(host, this.#shell);
      if (built === undefined) {
        this.#content.append(...host.childNodes);
        return;
      }
      content = built;
    }
    const items = Array.isArray(content) ? content : [content];
    for (const item of items) {
      if (item instanceof Node) {
        this.#content.append(item);
      }
    }
  }

  /** @returns {void} */
  #mountHeader() {
    const host = this.#shell?.getTarget('shell-header');
    if (!(host instanceof HTMLElement)) {
      return;
    }

    let header = host.querySelector(':scope > [data-role="page-header"]');
    if (!(header instanceof HTMLElement)) {
      header = component.create('pageHeader', { title: '', toolbar: [] });
      host.replaceChildren(header);
    }

    const content = header.getTarget?.('content');
    const title = header.getTarget?.('title');
    const copy = header.getTarget?.('copy');
    const toolbar = header.getTarget?.('toolbar');
    if (!(content instanceof HTMLElement)
      || !(title instanceof HTMLElement)
      || !(copy instanceof HTMLElement)
      || !(toolbar instanceof HTMLElement)) {
      throw new TypeError('PageHeaderComponent did not expose the required page zones');
    }

    let breadcrumbs = header.querySelector(':scope > [data-role="page-header-breadcrumbs"]');
    if (!(breadcrumbs instanceof HTMLElement)) {
      breadcrumbs = component.create('div').setData('role', 'page-header-breadcrumbs');
      header.prepend(breadcrumbs);
    }

    let bottom = header.querySelector(':scope > [data-role="page-header-bottom"]');
    if (!(bottom instanceof HTMLElement)) {
      bottom = component.create('div')
        .setData('role', 'page-header-bottom')
        .setClassName('mt-2 -mx-4 px-4')
        .setParent(header);
    }

    title.hidden = title.textContent === '';
    this.#targets = new Map([
      ['page-header', header],
      ['page-header-breadcrumbs', breadcrumbs],
      ['page-header-content', content],
      ['page-header-copy', copy],
      ['page-header-title', title],
      ['page-header-toolbar', toolbar],
      ['page-header-bottom', bottom],
    ]);
    this.#syncHeaderBottom();
  }

  /** @returns {void} */
  #syncHeaderBottom() {
    const header = this.#getTarget('page-header');
    const bottom = this.#getTarget('page-header-bottom');
    if (header === null || bottom === null) {
      return;
    }
    const hasContent = bottom.childElementCount > 0;
    bottom.hidden = !hasContent;
    header.classList.toggle('py-4', !hasContent);
    header.classList.toggle('pt-4', hasContent);
    header.classList.toggle('pb-0', hasContent);
  }

  /** @param {string} name @returns {HTMLElement|null} */
  #getTarget(name) {
    return this.#targets.get(name) ?? null;
  }

  /**
   * @param {HTMLElement} target
   * @param {Node|Node[]|Function|null} content
   */
  #appendContent(target, content) {
    const built = typeof content === 'function' ? content(target, this.#shell) : content;
    const items = Array.isArray(built) ? built : [built];
    for (const item of items) {
      if (item instanceof Node && item.parentNode !== target) {
        target.append(item);
      }
    }
  }
}