/**
 * Navbar.js - Top navigation bar for the Xestify shell.
 *
 * Responsibilities:
 *   - Show the logged-in user email
 *   - Provide navigation links (EntityList, PluginManager)
 *   - Expose Logout button via onLogout callback
 *   - Emit onNavigate(page) when a nav link is clicked
 */

export class Navbar {
  /** @type {HTMLElement} */
  #container;

  /** @type {string|null} */
  #userEmail;

  /** @type {Function|null} */
  #onLogout;

  /** @type {Function|null} */
  #onNavigate;

  /**
   * @param {string|HTMLElement} container
   * @param {{ userEmail?: string|null, onLogout?: Function, onNavigate?: Function }} options
   */
  constructor(container, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#userEmail = typeof options.userEmail === 'string' ? options.userEmail : null;
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
    const userEl = this.#container.querySelector('.xt-navbar__user');
    if (userEl !== null) {
      userEl.textContent = this.#userEmail ?? '';
      userEl.hidden = this.#userEmail === null;
    }
  }

  #render() {
    this.#container.innerHTML = '';

    const nav = document.createElement('nav');
    nav.className = 'xt-navbar';
    nav.setAttribute('aria-label', 'Navegación principal');

    const brand = document.createElement('span');
    brand.className = 'xt-navbar__brand';
    brand.textContent = 'Xestify';
    nav.appendChild(brand);

    const links = document.createElement('ul');
    links.className = 'xt-navbar__links';

    links.appendChild(this.#makeNavItem('entities', 'Entidades'));
    links.appendChild(this.#makeNavItem('plugins', 'Plugins'));

    nav.appendChild(links);

    const right = document.createElement('div');
    right.className = 'xt-navbar__right';

    const userEl = document.createElement('span');
    userEl.className = 'xt-navbar__user';
    userEl.textContent = this.#userEmail ?? '';
    userEl.hidden = this.#userEmail === null;
    right.appendChild(userEl);

    const logoutBtn = document.createElement('button');
    logoutBtn.type = 'button';
    logoutBtn.className = 'xt-btn xt-btn--secondary xt-navbar__logout';
    logoutBtn.textContent = 'Salir';
    logoutBtn.addEventListener('click', () => {
      if (this.#onLogout !== null) {
        this.#onLogout();
      }
    });
    right.appendChild(logoutBtn);

    nav.appendChild(right);
    this.#container.appendChild(nav);
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
