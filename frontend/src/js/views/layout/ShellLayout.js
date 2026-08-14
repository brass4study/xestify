import { component } from '../modules/ComponentFactory.js';
import { normalizeUiPreferences } from '../../models/ThemeModel.js';

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
      'relative mx-auto flex min-h-screen w-full max-w-[1280px] flex-col');
    const floatingUi = this.#createTarget('shell-floating-ui', 'div',
      'pointer-events-none flex justify-center px-4 pt-4', shell);
    floatingUi.style.position = 'fixed';
    floatingUi.style.inset = '0';
    floatingUi.style.zIndex = '9999';
    floatingUi.style.isolation = 'isolate';
    floatingUi.style.pointerEvents = 'none';
    floatingUi.style.display = 'flex';
    floatingUi.style.justifyContent = 'center';
    const menu = this.#createTarget('shell-menu', 'header',
      'top-0 z-50 mx-5 flex flex-wrap items-center gap-3 bg-gradient-to-r from-brand-700 via-brand-600 to-brand-500 px-4 py-3 text-white', shell);
    this.#createTarget('shell-menu-nav', 'div', 'min-w-0 flex-1', menu);
    const menuConfig = this.#createTarget('shell-menu-config', 'div', 'ml-auto flex items-center gap-2', menu);
    this.#createTarget('shell-menu-config-theme', 'div', '', menuConfig);
    this.#createTarget('shell-menu-config-user', 'div', '', menuConfig);
    const mixedBar = this.#createTarget('shell-mixed-bar', 'header',
      'top-0 z-50 hidden w-full items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 text-slate-900', shell);
    this.#createTarget('shell-mixed-bar-nav', 'div', 'min-w-0 flex-1', mixedBar);
    const mixedBarConfig = this.#createTarget('shell-mixed-bar-config', 'div', 'ml-auto flex items-center gap-2', mixedBar);
    this.#createTarget('shell-mixed-bar-config-theme', 'div', '', mixedBarConfig);
    this.#createTarget('shell-mixed-bar-config-user', 'div', '', mixedBarConfig);

    const body = this.#createTarget('shell-body', 'div', 'flex flex-1 gap-4', shell);
    const main = this.#createTarget('shell-main', 'main', 'flex flex-1 flex-col pb-4', body);
    this.#createTarget('shell-header', 'header', 'mx-5 pb-4', main);
    this.#createTarget('shell-main-notifications', 'section',
      'mx-5 flex flex-col gap-2', main, {
        'aria-label': 'Notificaciones',
        'aria-live': 'polite',
      });
    this.#createTarget('shell-main-content', 'section', 'mx-5 pb-4', main);
    this.#createTarget('shell-main-actions', 'div',
      'mx-5 flex flex-wrap justify-end gap-2 pb-4', main);
    this.#createTarget('shell-footer', 'footer',
      'mx-5 px-3 pb-4 pt-1 text-xs text-slate-500 sm:px-4 sm:pb-6', shell);

    this.#syncAutoHiddenZone('shell-main-notifications');
    this.#syncAutoHiddenZone('shell-main-actions');

    this.#container.append(shell);
    return this;
  }

  /** @param {Object} settings @returns {this} */
  applyUiPreferences(settings) {
    const normalized = normalizeUiPreferences(settings);
    const shell = this.#requireTarget('shell');
    const body = this.#requireTarget('shell-body');
    const menu = this.#requireTarget('shell-menu');
    const menuNav = this.#requireTarget('shell-menu-nav');
    const menuConfig = this.#requireTarget('shell-menu-config');
    const mixedBar = this.#requireTarget('shell-mixed-bar');
    const mixedBarNav = this.#requireTarget('shell-mixed-bar-nav');
    const mixedBarConfig = this.#requireTarget('shell-mixed-bar-config');
    const mixedBarConfigTheme = this.#requireTarget('shell-mixed-bar-config-theme');
    const mixedBarConfigUser = this.#requireTarget('shell-mixed-bar-config-user');
    const main = this.#requireTarget('shell-main');
    const header = this.#requireTarget('shell-header');
    const footer = this.#requireTarget('shell-footer');
    const isMixedNavigation = normalized.navigationMode === 'mixed';
    const isSideNavigation = normalized.navigationMode === 'side' || isMixedNavigation;
    const hasMenuNavigation = normalized.regions.menu;
    const hasMenuConfig = normalized.regions.menuHeader;
    const showMenuConfig = hasMenuConfig;
    const shellLayoutClass = isSideNavigation || normalized.contentWidth === 'fluid'
      ? 'flex min-h-screen w-full flex-col'
      : 'mx-auto flex min-h-screen w-full max-w-[1280px] flex-col';

    shell.className = [
      shellLayoutClass,
      normalized.weakMode ? 'opacity-90 saturate-75' : '',
    ].join(' ').trim();
    this.#syncModeClasses({
      body,
      main,
      header,
      menu,
      mixedBar,
      menuConfig,
      normalized,
      isSideNavigation,
    });
    this.#syncSideStructure(isSideNavigation, shell, body, main, menu, footer);
    this.#syncMixedStructure(isMixedNavigation, shell, body, mixedBar);

    this.#syncModeVisibility({
      header,
      footer,
      menu,
      menuNav,
      menuConfig,
      mixedBar,
      mixedBarNav,
      mixedBarConfig,
      mixedBarConfigTheme,
      mixedBarConfigUser,
      normalized,
      hasMenuNavigation,
      hasMenuConfig,
      showMenuConfig,
      isMixedNavigation,
    });

    shell.dataset.uiPageStyle = normalized.pageStyle;
    shell.dataset.uiMenuStyle = normalized.menuStyle;
    shell.dataset.uiNavigationMode = normalized.navigationMode;
    shell.dataset.uiSideMenuType = normalized.sideMenuType;
    shell.dataset.uiContentWidth = normalized.contentWidth;
    shell.dataset.uiFixedHeader = normalized.fixedHeader ? 'true' : 'false';
    shell.dataset.uiFixedSidebar = normalized.fixedSidebar ? 'true' : 'false';
    shell.dataset.uiSplitMenus = normalized.splitMenus ? 'true' : 'false';
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

  /**
   * Mantiene la jerarquía del shell coherente entre top y side.
   * @param {boolean} isSideNavigation
   * @param {HTMLElement} shell
   * @param {HTMLElement} body
   * @param {HTMLElement} main
   * @param {HTMLElement} menu
   * @param {HTMLElement} footer
   */
  #syncSideStructure(isSideNavigation, shell, body, main, menu, footer) {
    if (isSideNavigation) {
      if (menu.parentElement !== body) {
        body.prepend(menu);
      }
      if (footer.parentElement !== main) {
        main.append(footer);
      }
      return;
    }

    if (menu.parentElement !== shell) {
      shell.prepend(menu);
    }
    if (footer.parentElement !== shell) {
      shell.append(footer);
    }
  }

  /**
   * Mantiene la barra superior de mixed en la parte alta del shell.
   * @param {boolean} isMixedNavigation
   * @param {HTMLElement} shell
   * @param {HTMLElement} body
   * @param {HTMLElement} mixedBar
   */
  #syncMixedStructure(isMixedNavigation, shell, body, mixedBar) {
    if (!isMixedNavigation) {
      mixedBar.hidden = true;
      return;
    }

    mixedBar.hidden = false;
    if (mixedBar.parentElement !== shell) {
      body.before(mixedBar);
    }
  }

  /**
   * @param {Object} options
   */
  #syncModeClasses(options) {
    const {
      body,
      main,
      header,
      menu,
      mixedBar,
      menuConfig,
      normalized,
      isSideNavigation,
    } = options;

    body.className = isSideNavigation ? 'flex flex-1 gap-0' : 'flex flex-1 gap-4';
    main.className = isSideNavigation ? 'flex flex-1 flex-col' : 'flex flex-1 flex-col pb-4';
    header.className = isSideNavigation ? 'pb-4' : 'mx-5 pb-4';
    menu.className = this.#menuClassName(normalized);
    mixedBar.className = this.#mixedBarClassName(normalized);
    if (menuConfig.parentElement !== menu) {
      menu.append(menuConfig);
    }
    menuConfig.className = isSideNavigation
      ? 'mt-auto flex w-full items-center gap-2'
      : 'ml-auto flex items-center gap-2';
  }

  /**
   * @param {Object} options
   */
  #syncModeVisibility(options) {
    const {
      header,
      footer,
      menu,
      menuNav,
      menuConfig,
      mixedBar,
      mixedBarNav,
      mixedBarConfig,
      mixedBarConfigTheme,
      mixedBarConfigUser,
      normalized,
      hasMenuNavigation,
      hasMenuConfig,
      showMenuConfig,
      isMixedNavigation,
    } = options;

    if (menuNav.parentElement === menu && menuNav.nextSibling !== menuConfig) {
      menuConfig.before(menuNav);
    }
    if (mixedBarNav.parentElement !== mixedBar) {
      mixedBar.prepend(mixedBarNav);
    }
    if (mixedBarConfig.parentElement !== mixedBar) {
      mixedBar.append(mixedBarConfig);
    }
    if (mixedBarConfigTheme.parentElement !== mixedBarConfig) {
      mixedBarConfig.append(mixedBarConfigTheme);
    }
    if (mixedBarConfigUser.parentElement !== mixedBarConfig) {
      mixedBarConfig.append(mixedBarConfigUser);
    }

    menu.hidden = !hasMenuNavigation && !showMenuConfig;
    menuNav.hidden = !hasMenuNavigation;
    menuConfig.hidden = isMixedNavigation || !hasMenuConfig;
    mixedBar.hidden = !isMixedNavigation;
    mixedBarNav.hidden = !isMixedNavigation;
    mixedBarConfig.hidden = !isMixedNavigation || !hasMenuConfig;
    header.hidden = !normalized.regions.header;
    footer.hidden = !normalized.regions.footer || footer.textContent === '';
  }

  /** @param {Object} settings @returns {string} */
  #menuClassName(settings) {
    if (settings.navigationMode === 'side' || settings.navigationMode === 'mixed') {
      const sideStyleClassMap = {
        brand: 'bg-[color:var(--x-ui-secondary)] text-slate-100 border-r border-slate-800',
        dark: 'bg-slate-950 text-slate-100 border-r border-slate-800',
        light: 'bg-white text-slate-900 border-r border-slate-200',
      };

      return [
        'sticky top-0',
        'z-50 flex h-screen w-60 shrink-0 flex-col items-stretch gap-3 overflow-y-auto px-3 py-3',
        sideStyleClassMap[settings.menuStyle] ?? sideStyleClassMap.brand,
      ].join(' ');
    }

    const styleClassMap = {
      brand: 'bg-gradient-to-r from-brand-700 via-brand-600 to-brand-500 text-white',
      dark: 'bg-slate-950 text-slate-100',
      light: 'border-b border-slate-200 bg-white text-slate-900',
    };

    return [
      settings.fixedHeader ? 'top-0' : 'relative',
      'z-50 mx-5 flex flex-wrap items-center gap-3 px-4 py-3',
      styleClassMap[settings.menuStyle] ?? styleClassMap.brand,
    ].join(' ');
  }

  /** @param {Object} settings @returns {string} */
  #mixedBarClassName(settings) {
    if (settings.navigationMode !== 'mixed') {
      return 'hidden';
    }

    return [
      settings.fixedHeader ? 'top-0' : 'relative',
      'z-50 flex w-full items-center gap-3 border-b border-slate-200 bg-white px-4 py-1 text-slate-900',
    ].join(' ');
  }

}