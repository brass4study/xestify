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
    wrapper.className = 'xt-page xt-page--users';

    const heading = document.createElement('h2');
    heading.textContent = 'Gestión de usuarios';
    wrapper.appendChild(heading);

    const subtitle = document.createElement('p');
    subtitle.className = 'xt-page__subtitle';
    subtitle.textContent = 'Administra usuarios, roles, accesos y recuperación de contraseña.';
    wrapper.appendChild(subtitle);

    if (this.#message !== null) {
      const feedback = document.createElement('div');
      feedback.className = `xt-page__feedback xt-page__feedback--${this.#messageType ?? 'success'}`;
      feedback.textContent = this.#message;
      wrapper.appendChild(feedback);
    }

    const card = document.createElement('div');
    card.className = 'xt-page__card';

    if (this.#users.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'xt-placeholder';
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
    wrap.className = 'xt-table-wrapper';

    const table = document.createElement('table');
    table.className = 'xt-table';

    const thead = document.createElement('thead');
    const tr = document.createElement('tr');
    ['Avatar', 'Nombre', 'Email', 'Roles', 'Fecha alta', 'Acciones'].forEach((label) => {
      const th = document.createElement('th');
      th.textContent = label;
      tr.appendChild(th);
    });
    thead.appendChild(tr);

    const tbody = document.createElement('tbody');
    this.#users.forEach((user) => {
      const row = document.createElement('tr');

      row.appendChild(this.#avatarCell(user));
      row.appendChild(this.#textCell(this.#displayName(user)));
      row.appendChild(this.#textCell(this.#displayEmail(user)));
      row.appendChild(this.#textCell(this.#displayRoles(user)));
      row.appendChild(this.#textCell(this.#formatDate(user?.created_at)));
      row.appendChild(this.#actionsCell(user));

      tbody.appendChild(row);
    });

    table.appendChild(thead);
    table.appendChild(tbody);
    wrap.appendChild(table);

    return wrap;
  }

  #avatarCell(user) {
    const td = document.createElement('td');
    const avatar = document.createElement('span');
    avatar.className = 'xt-user-avatar';

    if (typeof user?.avatar === 'string' && user.avatar !== '') {
      const image = document.createElement('img');
      image.src = user.avatar;
      image.alt = `Avatar de ${this.#displayName(user)}`;
      avatar.appendChild(image);
    } else {
      avatar.textContent = this.#getInitials(this.#displayName(user), this.#displayEmail(user));
    }

    td.appendChild(avatar);
    return td;
  }

  #textCell(value) {
    const td = document.createElement('td');
    td.textContent = value;
    return td;
  }

  #actionsCell(user) {
    const td = document.createElement('td');
    const actions = document.createElement('div');
    actions.className = 'xt-table__actions';

    const view = this.#makeActionButton('Editar', 'xt-btn xt-btn--sm xt-row-edit-btn', () => {
      this.#openUserDetail(user);
    });

    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-pencil xt-row-edit-btn__icon';
    icon.setAttribute('aria-hidden', 'true');
    view.prepend(icon);

    actions.appendChild(view);
    td.appendChild(actions);

    return td;
  }

  #makeActionButton(label, className, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.textContent = label;
    button.addEventListener('click', onClick);
    return button;
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
