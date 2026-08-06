/**
 * Navbar.js - Top navigation bar for the Xestify shell.
 *
 * Responsibilities:
 *   - Show the logged-in user and avatar menu
 *   - Provide navigation links (EntityList, PluginManager)
 *   - Emit onNavigate(page) when a nav link is clicked
 */

import { UserMenu } from './UserMenu.js';

export class Navbar {
  /** @type {HTMLElement} */
  #container;

  /** @type {string|null} */
  #userEmail;

  /** @type {string|null} */
  #userName;

  /** @type {string|null} */
  #avatar;

  /** @type {Array<string>} */
  #roles;

  /** @type {Function|null} */
  #onLogout;

  /** @type {Function|null} */
  #onNavigate;

  /** @type {Array<object>} */
  #entities;

  /** @type {string} */
  #activePage;

  /** @type {boolean} */
  #canManagePlugins;

  /**
   * @param {string|HTMLElement} container
   * @param {{
   *   userEmail?: string|null,
   *   userName?: string|null,
   *   avatar?: string|null,
   *   roles?: Array<string>,
   *   entities?: Array<object>,
   *   currentPage?: string,
   *   canManagePlugins?: boolean,
   *   onLogout?: Function,
   *   onNavigate?: Function
   * }} options
   */
  constructor(container, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#userEmail = typeof options.userEmail === 'string' ? options.userEmail : null;
    this.#userName = typeof options.userName === 'string' ? options.userName : null;
    this.#avatar = typeof options.avatar === 'string' ? options.avatar : null;
    this.#roles = Array.isArray(options.roles) ? options.roles : [];
    this.#entities = Array.isArray(options.entities) ? [...options.entities] : [];
    this.#activePage = typeof options.currentPage === 'string' ? options.currentPage : '';
    this.#canManagePlugins = Boolean(options.canManagePlugins);
    this.#onLogout = typeof options.onLogout === 'function' ? options.onLogout : null;
    this.#onNavigate = typeof options.onNavigate === 'function' ? options.onNavigate : null;

    this.#render();
  }

  /**
   * Update the displayed user email without re-rendering the whole navbar.
   *
   * @param {string|null} email
   */
  setUserEmail(email) {
    this.#userEmail = typeof email === 'string' ? email : null;
    this.#renderUserMenu();
  }

  /**
   * @param {string|null} name
   */
  setUserName(name) {
    this.#userName = typeof name === 'string' ? name : null;
    this.#renderUserMenu();
  }

  /**
   * @param {string|null} avatar
   */
  setAvatar(avatar) {
    this.#avatar = typeof avatar === 'string' ? avatar : null;
    this.#renderUserMenu();
  }

  /**
   * @param {Array<string>} roles
   */
  setRoles(roles) {
    this.#roles = Array.isArray(roles) ? roles : [];
    this.#renderUserMenu();
  }

  #renderUserMenu() {
    const userEl = this.#container.querySelector('.xt-navbar__user');
    if (userEl === null) {
      return;
    }

    const hasIdentity = this.#userEmail !== null || this.#userName !== null;
    userEl.hidden = !hasIdentity;
    if (!hasIdentity) {
      userEl.replaceChildren();
      return;
    }

    userEl.replaceChildren();
    new UserMenu(userEl, {
      name: this.#userName,
      email: this.#userEmail,
      avatar: this.#avatar,
      roles: this.#roles,
      onSelect: (action) => {
        if (action === 'logout' && this.#onLogout !== null) {
          this.#onLogout();
          return;
        }
        if (action === 'profile' && this.#onNavigate !== null) {
          this.#onNavigate('profile');
          return;
        }
        if (action === 'users' && this.#onNavigate !== null) {
          this.#onNavigate('users');
        }
      },
    });
  }

  /**
   * @param {Array<object>} entities
   */
  setEntities(entities) {
    this.#entities = Array.isArray(entities) ? [...entities] : [];
    this.#render();
  }

  #render() {
    this.#container.replaceChildren();

    const nav = document.createElement('nav');
    nav.className = 'xt-navbar';
    nav.setAttribute('aria-label', 'Navegación principal');

    const brand = document.createElement('span');
    brand.className = 'xt-navbar__brand';
    brand.textContent = 'Xestify';
    nav.appendChild(brand);

    const links = document.createElement('ul');
    links.className = 'xt-navbar__links';

    for (const entity of this.#entities) {
      const slug = typeof entity?.slug === 'string' ? entity.slug : '';
      if (slug === '') {
        continue;
      }
      const label = typeof entity?.label === 'string' ? entity.label : slug;
      links.appendChild(this.#makeNavItem(`entity:${slug}`, label));
    }

    if (this.#canManagePlugins) {
      links.appendChild(this.#makeNavItem('plugins', 'Plugins'));
    }

    nav.appendChild(links);

    const right = document.createElement('div');
    right.className = 'xt-navbar__right';

    const userEl = document.createElement('div');
    userEl.className = 'xt-navbar__user';
    right.appendChild(userEl);

    nav.appendChild(right);
    this.#container.appendChild(nav);
    this.#renderUserMenu();

    if (this.#activePage !== '') {
      this.#setActive(this.#activePage);
    }
  }

  /**
   * @param {string} page
   * @param {string} label
   * @returns {HTMLLIElement}
   */
  #makeNavItem(page, label) {
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = '#';
    a.className = 'xt-navbar__link';
    a.dataset.page = page;
    a.textContent = label;
    a.addEventListener('click', (event) => {
      event.preventDefault();
      this.#setActive(page);
      if (this.#onNavigate !== null) {
        this.#onNavigate(page);
      }
    });
    li.appendChild(a);
    return li;
  }

  /**
   * Mark the given page link as active and remove active from others.
   *
   * @param {string} page
   */
  #setActive(page) {
    this.#activePage = page;
    const links = this.#container.querySelectorAll('.xt-navbar__link');
    for (const link of links) {
      if (link instanceof HTMLElement) {
        const isActive = link.dataset.page === page;
        link.classList.toggle('xt-navbar__link--active', isActive);
        link.setAttribute('aria-current', isActive ? 'page' : 'false');
      }
    }
  }

  /**
   * @param {string|HTMLElement} container
   * @returns {HTMLElement}
   */
  #resolveContainer(container) {
    if (container instanceof HTMLElement) {
      return container;
    }

    if (typeof container === 'string') {
      const el = document.querySelector(container);
      if (el instanceof HTMLElement) {
        return el;
      }
    }

    throw new TypeError(`Navbar: container "${String(container)}" not found`);
  }
}
