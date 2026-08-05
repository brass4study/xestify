export class UserManagement {
  /** @type {HTMLElement} */
  #container;

  /** @type {Array<Object>} */
  #users;

  /**
   * @param {HTMLElement|string} container
   * @param {Array<Object>} users
   */
  constructor(container, users = []) {
    this.#container = this.#resolveContainer(container);
    this.#users = Array.isArray(users) ? users : [];
    this.#render();
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
    subtitle.textContent = 'Administra usuarios, roles y accesos desde este panel.';
    wrapper.appendChild(subtitle);

    const card = document.createElement('div');
    card.className = 'xt-page__card';

    if (this.#users.length === 0) {
      card.innerHTML = '<p>No hay usuarios disponibles todavía.</p>';
    } else {
      const list = document.createElement('ul');
      list.className = 'xt-page__list';

      this.#users.forEach((user) => {
        const item = document.createElement('li');
        const name = typeof user?.name === 'string' && user.name !== '' ? user.name : (typeof user?.email === 'string' ? user.email : 'Usuario');
        const roles = Array.isArray(user?.roles) && user.roles.length > 0 ? user.roles.join(', ') : 'Sin roles';
        item.innerHTML = `<strong>${this.#escapeHtml(name)}</strong> — ${this.#escapeHtml(roles)}`;
        list.appendChild(item);
      });

      card.appendChild(list);
    }

    wrapper.appendChild(card);
    this.#container.appendChild(wrapper);
  }

  #escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
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

    throw new TypeError(`UserManagement: container "${String(container)}" not found`);
  }
}
