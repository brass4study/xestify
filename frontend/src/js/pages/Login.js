/**
 * Login.js - Page controller for authentication.
 *
 * Responsibilities:
 *   - Render login form (email/password)
 *   - Validate required fields
 *   - POST /auth/login
 *   - Emit onSuccess({ accessToken }) on successful auth
 *   - Render user-visible errors on failure
 */

import { Api, ApiError } from '../modules/Api.js';

const BASE_URL = '/api/v1';

export class Login {
  /** @type {Api} */
  #api;

  /** @type {HTMLElement} */
  #container;

  /** @type {Function|null} */
  #onSuccess;

  /**
   * @param {string|HTMLElement} container
   * @param {{ api?: Api, onSuccess?: Function }} options
   */
  constructor(container, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#api = (options.api !== null && options.api !== undefined && typeof options.api.post === 'function')
      ? options.api
      : new Api(BASE_URL);
    this.#onSuccess = typeof options.onSuccess === 'function' ? options.onSuccess : null;

    this.#render();
  }

  /**
   * Submit login credentials.
   *
   * @returns {Promise<void>}
   */
  async submit() {
    this.#clearErrors();

    const emailInput = this.#container.querySelector('[name="email"]');
    const passwordInput = this.#container.querySelector('[name="password"]');

    const email = emailInput instanceof HTMLInputElement ? emailInput.value.trim() : '';
    const password = passwordInput instanceof HTMLInputElement ? passwordInput.value : '';

    const validationErrors = {};
    if (email === '') {
      validationErrors.email = ['Required.'];
    }
    if (password === '') {
      validationErrors.password = ['Required.'];
    }

    if (Object.keys(validationErrors).length > 0) {
      this.#showFieldErrors(validationErrors);
      return;
    }

    this.#setLoading(true);

    try {
      const { data } = await this.#api.post('/auth/login', { email, password });
      const accessToken = typeof data?.access_token === 'string' ? data.access_token : '';
      const userEmail = typeof data?.email === 'string' ? data.email : null;

      if (accessToken === '') {
        this.#showGlobalError('Respuesta de autenticacion invalida.');
        return;
      }

      if (this.#onSuccess !== null) {
        this.#onSuccess({ accessToken, email: userEmail });
      }
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.details).length > 0) {
        this.#showFieldErrors(err.details);
      } else if (err instanceof ApiError) {
        this.#showGlobalError(err.message);
      } else {
        this.#showGlobalError('Error desconocido');
      }
    } finally {
      this.#setLoading(false);
    }
  }

  #render() {
    this.#container.replaceChildren();

    const wrapper = document.createElement('section');
    wrapper.className = 'xt-login';

    const title = document.createElement('h2');
    title.className = 'xt-login__title';
    title.textContent = 'Iniciar sesión';
    wrapper.appendChild(title);

    const banner = document.createElement('p');
    banner.className = 'xt-login__error';
    banner.hidden = true;
    wrapper.appendChild(banner);

    const form = document.createElement('form');
    form.className = 'xt-login__form';
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      this.submit();
    });

    const emailLabel = document.createElement('label');
    emailLabel.textContent = 'Email';
    const emailInput = document.createElement('input');
    emailInput.type = 'email';
    emailInput.name = 'email';
    emailInput.autocomplete = 'email';
    emailLabel.appendChild(emailInput);
    form.appendChild(emailLabel);

    const passwordLabel = document.createElement('label');
    passwordLabel.textContent = 'Password';
    const passwordInput = document.createElement('input');
    passwordInput.type = 'password';
    passwordInput.name = 'password';
    passwordLabel.appendChild(passwordInput);
    form.appendChild(passwordLabel);

    const submit = document.createElement('button');
    submit.type = 'submit';
    submit.className = 'xt-btn xt-btn--primary';
    submit.textContent = 'Entrar';
    form.appendChild(submit);

    wrapper.appendChild(form);
    this.#container.appendChild(wrapper);
  }

  /**
   * @param {Record<string, string|string[]>} errors
   */
  #showFieldErrors(errors) {
    const form = this.#container.querySelector('.xt-login__form');
    if (form === null) {
      return;
    }

    for (const [fieldName, messages] of Object.entries(errors)) {
      const input = form.querySelector(`[name="${fieldName}"]`);
      const msgList = Array.isArray(messages) ? messages : [String(messages)];

      const errorEl = document.createElement('ul');
      errorEl.className = 'xt-login__field-errors';
      errorEl.dataset.field = fieldName;

      for (const msg of msgList) {
        const li = document.createElement('li');
        li.textContent = msg;
        errorEl.appendChild(li);
      }

      if (input !== null && input.parentElement !== null) {
        input.parentElement.appendChild(errorEl);
      } else {
        form.appendChild(errorEl);
      }
    }
  }

  /**
   * @param {string} message
   */
  #showGlobalError(message) {
    const banner = this.#container.querySelector('.xt-login__error');
    if (banner !== null) {
      banner.textContent = message;
      banner.hidden = false;
    }
  }

  #clearErrors() {
    const banner = this.#container.querySelector('.xt-login__error');
    if (banner !== null) {
      banner.textContent = '';
      banner.hidden = true;
    }

    const fieldErrors = this.#container.querySelectorAll('.xt-login__field-errors');
    for (const error of fieldErrors) {
      error.remove();
    }
  }

  /**
   * @param {boolean} loading
   */
  #setLoading(loading) {
    const button = this.#container.querySelector('.xt-btn--primary');
    if (button instanceof HTMLButtonElement) {
      button.disabled = loading;
      button.textContent = loading ? 'Entrando...' : 'Entrar';
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

    throw new TypeError(`Login: container "${String(container)}" not found`);
  }
}
