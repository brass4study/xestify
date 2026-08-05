export class UserProfile {
  /** @type {HTMLElement} */
  #container;

  /** @type {Object|null} */
  #user;

  /**
   * @param {HTMLElement|string} container
   * @param {Object|null} user
   */
  constructor(container, user = null) {
    this.#container = this.#resolveContainer(container);
    this.#user = user;
    this.#render();
  }

  #render() {
    this.#container.replaceChildren();

    const wrapper = document.createElement('section');
    wrapper.className = 'xt-page xt-page--profile';

    const heading = document.createElement('h2');
    heading.textContent = 'Mi perfil';
    wrapper.appendChild(heading);

    const subtitle = document.createElement('p');
    subtitle.className = 'xt-page__subtitle';
    subtitle.textContent = 'Gestiona tu identidad y tus preferencias de acceso.';
    wrapper.appendChild(subtitle);

    const card = document.createElement('div');
    card.className = 'xt-page__card';

    const email = this.#user?.email ?? 'Sin email';
    const roles = Array.isArray(this.#user?.roles) && this.#user.roles.length > 0 ? this.#user.roles.join(', ') : 'Sin roles';

    card.innerHTML = `
      <p><strong>Email:</strong> ${this.#escapeHtml(email)}</p>
      <p><strong>Roles:</strong> ${this.#escapeHtml(roles)}</p>
      <p><strong>Estado:</strong> Activo</p>
    `;

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

    throw new TypeError(`UserProfile: container "${String(container)}" not found`);
  }
}
