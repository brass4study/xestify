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
    this.#container.className = 'relative inline-flex items-center';
    this.#container.dataset.role = 'user-menu';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'inline-flex items-center gap-2 rounded-full px-2.5 py-1.5 text-sm text-white transition hover:bg-white/20';
    trigger.dataset.role = 'user-menu-trigger';
    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-expanded', 'false');

    const avatarWrap = document.createElement('div');
    avatarWrap.className = 'inline-flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-white text-xs font-bold text-brand-700';
    avatarWrap.dataset.role = 'user-menu-avatar';

    if (this.#avatar !== null && this.#avatar !== '') {
      const img = document.createElement('img');
      img.src = this.#avatar;
      img.alt = 'Avatar del usuario';
      avatarWrap.appendChild(img);
    } else {
      avatarWrap.textContent = this.#getInitials();
    }

    const label = document.createElement('span');
    label.className = 'max-w-[180px] truncate text-left text-xs font-medium sm:text-sm';
    label.dataset.role = 'user-menu-label';
    label.textContent = this.#getDisplayName();

    trigger.appendChild(avatarWrap);
    trigger.appendChild(label);
    this.#container.appendChild(trigger);

    const menu = document.createElement('div');
    menu.className = 'absolute right-0 top-[calc(100%+2px)] z-[120] hidden min-w-56 flex-col rounded-xl border border-slate-200 bg-white p-1.5 shadow-float';
    menu.hidden = true;
    menu.dataset.role = 'user-menu-items';
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
      item.className = 'rounded-lg px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100';
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

    trigger.addEventListener('click', () => {
      this.#setOpen(!this.#open);
    });

    trigger.addEventListener('focus', () => {
      this.#setOpen(true);
    });

    this.#container.addEventListener('focusout', () => {
      queueMicrotask(() => {
        if (!this.#container.contains(document.activeElement)) {
          this.#setOpen(false);
        }
      });
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

    const trigger = this.#container.querySelector('[data-role="user-menu-trigger"]');
    if (trigger instanceof HTMLElement) {
      trigger.setAttribute('aria-expanded', String(this.#open));
    }

    const menu = this.#container.querySelector('[data-role="user-menu-items"]');
    if (menu instanceof HTMLElement) {
      menu.hidden = !this.#open;
      menu.classList.toggle('hidden', !this.#open);
      menu.classList.toggle('flex', this.#open);
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
