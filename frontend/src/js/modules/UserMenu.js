/**
 * UserMenu.js - Dropdown menu for the logged-in user in the navbar.
 */

export class UserMenu {
  /** @type {HTMLElement} */
  #container;

  /** @type {string|null} */
  #name;

  /** @type {string|null} */
  #email;

  /** @type {string|null} */
  #avatar;

  /** @type {Array<string>} */
  #roles;

  /** @type {Function|null} */
  #onSelect;

  /** @type {boolean} */
  #open;

  /**
   * @param {string|HTMLElement} container
   * @param {{
   *   name?: string|null,
   *   email?: string|null,
   *   avatar?: string|null,
   *   roles?: Array<string>,
   *   onSelect?: Function
   * }} options
   */
  constructor(container, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#name = typeof options.name === 'string' ? options.name : null;
    this.#email = typeof options.email === 'string' ? options.email : null;
    this.#avatar = typeof options.avatar === 'string' ? options.avatar : null;
    this.#roles = Array.isArray(options.roles) ? options.roles : [];
    this.#onSelect = typeof options.onSelect === 'function' ? options.onSelect : null;
    this.#open = false;
    this.#render();
  }

  #render() {
    this.#container.replaceChildren();
    this.#container.classList.add('xt-user-menu');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'xt-user-menu__trigger';
    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-expanded', 'false');

    const avatarWrap = document.createElement('div');
    avatarWrap.className = 'xt-user-menu__avatar';

    if (this.#avatar !== null && this.#avatar !== '') {
      const img = document.createElement('img');
      img.src = this.#avatar;
      img.alt = 'Avatar del usuario';
      avatarWrap.appendChild(img);
    } else {
      avatarWrap.textContent = this.#getInitials();
    }

    const label = document.createElement('span');
    label.className = 'xt-user-menu__label';
    label.textContent = this.#getDisplayName();

    trigger.appendChild(avatarWrap);
    trigger.appendChild(label);
    this.#container.appendChild(trigger);

    const menu = document.createElement('div');
    menu.className = 'xt-user-menu__menu';
    menu.hidden = true;
    menu.setAttribute('role', 'menu');

    const actions = [
      { key: 'profile', label: 'Mi perfil' },
      { key: 'users', label: 'Gestión de usuarios' },
      { key: 'logout', label: 'Cerrar sesion' },
    ];

    for (const action of actions) {
      const isAdminOnly = action.key === 'users';
      if (isAdminOnly && !this.#isAdmin()) {
        continue;
      }
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'xt-user-menu__item';
      item.dataset.menuAction = action.key;
      item.textContent = action.label;
      item.addEventListener('click', () => {
        this.#setOpen(false);
        if (this.#onSelect !== null) {
          this.#onSelect(action.key);
        }
      });
      menu.appendChild(item);
    }

    this.#container.appendChild(menu);

    this.#container.addEventListener('mouseenter', () => {
      this.#setOpen(true);
    });

    this.#container.addEventListener('mouseleave', () => {
      this.#setOpen(false);
    });

    this.#container.style.position = 'relative';
    this.#container.style.paddingBottom = '0.4rem';

    trigger.addEventListener('click', () => {
      this.#setOpen(!this.#open);
    });

    trigger.addEventListener('focus', () => {
      this.#setOpen(true);
    });

    trigger.addEventListener('blur', () => {
      this.#setOpen(false);
    });

    document.addEventListener('click', (event) => {
      if (!(event.target instanceof HTMLElement)) {
        return;
      }
      if (!this.#container.contains(event.target)) {
        this.#setOpen(false);
      }
    });
  }

  #setOpen(isOpen) {
    this.#open = Boolean(isOpen);
    this.#container.classList.toggle('xt-user-menu--open', this.#open);

    const trigger = this.#container.querySelector('.xt-user-menu__trigger');
    if (trigger instanceof HTMLElement) {
      trigger.setAttribute('aria-expanded', String(this.#open));
    }

    const menu = this.#container.querySelector('.xt-user-menu__menu');
    if (menu instanceof HTMLElement) {
      menu.hidden = !this.#open;
      menu.classList.toggle('is-open', this.#open);
    }
  }

  #getDisplayName() {
    if (this.#name !== null && this.#name !== '') {
      return this.#name;
    }
    return this.#email ?? 'Usuario';
  }

  #getInitials() {
    const source = this.#name ?? this.#email ?? 'Usuario';
    const parts = source.split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
      return 'U';
    }
    const initials = parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase());
    return initials.join('');
  }

  #isAdmin() {
    return this.#roles.includes('admin');
  }

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

    throw new TypeError(`UserMenu: container "${String(container)}" not found`);
  }
}
