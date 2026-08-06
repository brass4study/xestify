import { DynamicTable } from '../modules/DynamicTable.js';

export class UserManager {
  /** @type {HTMLElement} */
  #container;

  /** @type {object|null} */
  #api;

  /** @type {Array<Object>} */
  #users;

  /** @type {string|null} */
  #message;

  /** @type {'success'|'error'|null} */
  #messageType;

  /**
   * @param {HTMLElement|string} container
   * @param {{
   *   api?: object,
   *   users?: Array<Object>
   * }|Array<Object>} [options]
   */
  constructor(container, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#api = null;
    this.#users = [];
    this.#message = null;
    this.#messageType = null;

    if (Array.isArray(options)) {
      this.#users = options;
      this.#render();
      return;
    }

    this.#api = options.api ?? null;
    this.#users = Array.isArray(options.users) ? options.users : [];

    this.#render();
  }

  async init() {
    await this.#loadUsers();
    this.#render();
  }

  async #loadUsers() {
    if (!this.#canCallApi()) {
      return;
    }

    try {
      const response = await this.#api.get('/users');
      const rows = response?.data;
      this.#users = Array.isArray(rows) ? rows : [];
    } catch (error) {
      this.#users = [];
      this.#setMessage(error?.message ?? 'No se pudo cargar la lista de usuarios.', 'error');
    }
  }

  #render() {
    this.#container.replaceChildren();

    const wrapper = document.createElement('section');
    wrapper.className = 'grid gap-4';

    const heading = document.createElement('h2');
    heading.className = 'text-2xl font-semibold tracking-tight text-slateui-950';
    heading.textContent = 'Gestión de usuarios';
    wrapper.appendChild(heading);

    const subtitle = document.createElement('p');
    subtitle.className = 'text-sm text-slate-500';
    subtitle.textContent = 'Administra usuarios, roles, accesos y recuperación de contraseña.';
    wrapper.appendChild(subtitle);

    if (this.#message !== null) {
      const feedback = document.createElement('div');
      feedback.className = `rounded-lg border px-3 py-2 text-sm ${this.#messageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`;
      feedback.textContent = this.#message;
      wrapper.appendChild(feedback);
    }

    const card = document.createElement('div');
    card.className = 'rounded-2xl border border-slate-200 bg-white p-4 shadow-panel';

    if (this.#users.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500';
      empty.textContent = 'No hay usuarios disponibles todavía.';
      card.appendChild(empty);
    } else {
      card.appendChild(this.#renderTable());
    }

    wrapper.appendChild(card);

    this.#container.appendChild(wrapper);
  }

  #renderTable() {
    const wrap = document.createElement('div');

    const rows = this.#users.map((user) => ({
      __raw: user,
      name: this.#displayName(user),
      email: this.#displayEmail(user),
      roles: this.#displayRoles(user),
      created_at: this.#formatDate(user?.created_at),
    }));

    const schema = {
      fields: [
        { name: 'name', label: 'Nombre' },
        { name: 'email', label: 'Email' },
        { name: 'roles', label: 'Roles' },
        { name: 'created_at', label: 'Fecha alta' },
      ],
    };

    const table = new DynamicTable(rows, schema, wrap, {
      extraColumns: [
        {
          label: 'Avatar',
          position: 'start',
          renderCell: (row) => this.#avatarContent(row.__raw),
        },
        {
          label: 'Acciones',
          renderCell: (row) => this.#buildEditAction(row.__raw),
        },
      ],
    });

    table.render();
    return wrap;
  }

  #avatarContent(user) {
    const avatar = document.createElement('span');
    avatar.className = 'inline-flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-xs font-bold text-brand-700';

    if (typeof user?.avatar === 'string' && user.avatar !== '') {
      const image = document.createElement('img');
      image.src = user.avatar;
      image.alt = `Avatar de ${this.#displayName(user)}`;
      avatar.appendChild(image);
    } else {
      avatar.textContent = this.#getInitials(this.#displayName(user), this.#displayEmail(user));
    }

    return avatar;
  }

  #buildEditAction(user) {
    const actions = document.createElement('div');
    actions.className = 'flex flex-wrap gap-1.5';

    const view = DynamicTable.buildActionButton({
      label: 'Editar',
      icon: 'fa-pen',
      tone: 'sky',
      onClick: () => {
      this.#openUserDetail(user);
      },
    });

    actions.appendChild(view);
    return actions;
  }

  #openUserDetail(user) {
    const id = this.#getUserId(user);
    if (id === '') {
      return;
    }

    if (typeof window !== 'undefined' && typeof window.location?.hash === 'string') {
      window.location.hash = `#/usuarios/${encodeURIComponent(id)}`;
      return;
    }

    this.#setMessage('No se pudo navegar a la ficha del usuario.', 'error');
    this.#render();
  }

  #displayName(user) {
    if (typeof user?.name === 'string' && user.name.trim() !== '') {
      return user.name.trim();
    }

    return this.#displayEmail(user);
  }

  #displayEmail(user) {
    if (typeof user?.email === 'string' && user.email.trim() !== '') {
      return user.email.trim();
    }

    return 'Sin email';
  }

  #displayRoles(user) {
    const roles = this.#userRoles(user);
    if (roles.includes('admin')) {
      return 'Administrador';
    }

    return 'Usuario';
  }

  #userRoles(user) {
    if (Array.isArray(user?.roles)) {
      return user.roles.filter((role) => typeof role === 'string' && role.trim() !== '');
    }

    return [];
  }

  #formatDate(value) {
    if (typeof value !== 'string' || value.trim() === '') {
      return 'N/D';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return 'N/D';
    }

    return date.toLocaleDateString('es-ES');
  }

  #getUserId(user) {
    if (typeof user?.id === 'string') {
      return user.id;
    }

    return '';
  }

  #getInitials(name, email) {
    const base = typeof name === 'string' && name !== '' ? name : email;
    const words = String(base).trim().split(/\s+/).filter(Boolean);
    if (words.length === 0) {
      return 'US';
    }

    if (words.length === 1) {
      return words[0].slice(0, 2).toUpperCase();
    }

    return `${words[0][0]}${words[1][0]}`.toUpperCase();
  }

  #setMessage(message, type) {
    this.#message = message;
    this.#messageType = type;
  }

  #canCallApi() {
    return this.#api !== null && typeof this.#api.get === 'function' && typeof this.#api.put === 'function' && typeof this.#api.delete === 'function';
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

    throw new TypeError(`UserManager: container "${String(container)}" not found`);
  }
}
