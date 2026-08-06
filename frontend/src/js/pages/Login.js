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
      : new Api();
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
    wrapper.className = 'mx-auto mt-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-panel sm:mt-16 sm:p-8';
    wrapper.dataset.role = 'login-card';

    const title = document.createElement('h2');
    title.className = 'text-2xl font-semibold tracking-tight text-slateui-950';
    title.dataset.role = 'login-title';
    title.textContent = 'Iniciar sesión';
    wrapper.appendChild(title);

    const subtitle = document.createElement('p');
    subtitle.className = 'mt-2 text-sm text-slate-500';
    subtitle.textContent = 'Accede a tu espacio de trabajo para gestionar entidades y plugins.';
    wrapper.appendChild(subtitle);

    const banner = document.createElement('p');
    banner.className = 'mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700';
    banner.dataset.role = 'login-error';
    banner.hidden = true;
    wrapper.appendChild(banner);

    const form = document.createElement('form');
    form.className = 'mt-6 grid gap-4';
    form.dataset.role = 'login-form';
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      this.submit();
    });

    const emailLabel = document.createElement('label');
    emailLabel.className = 'grid gap-1.5 text-sm font-medium text-slate-700';
    emailLabel.textContent = 'Email';
    const emailInput = document.createElement('input');
    emailInput.type = 'email';
    emailInput.name = 'email';
    emailInput.autocomplete = 'email';
    emailInput.className = 'w-full rounded-lg border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500';
    emailLabel.appendChild(emailInput);
    form.appendChild(emailLabel);

    const passwordLabel = document.createElement('label');
    passwordLabel.className = 'grid gap-1.5 text-sm font-medium text-slate-700';
    passwordLabel.textContent = 'Password';
    const passwordInput = document.createElement('input');
    passwordInput.type = 'password';
    passwordInput.name = 'password';
    passwordInput.className = 'w-full rounded-lg border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500';
    passwordLabel.appendChild(passwordInput);
    form.appendChild(passwordLabel);

    const submit = document.createElement('button');
    submit.type = 'submit';
    submit.className = 'mt-2 inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-300 disabled:cursor-not-allowed disabled:opacity-60';
    submit.dataset.role = 'login-submit';
    submit.textContent = 'Entrar';
    form.appendChild(submit);

    wrapper.appendChild(form);
    this.#container.appendChild(wrapper);
  }

  /**
   * @param {Record<string, string|string[]>} errors
   */
  #showFieldErrors(errors) {
    const form = this.#container.querySelector('[data-role="login-form"]');
    if (form === null) {
      return;
    }

    for (const [fieldName, messages] of Object.entries(errors)) {
      const input = form.querySelector(`[name="${fieldName}"]`);
      const msgList = Array.isArray(messages) ? messages : [String(messages)];

      const errorEl = document.createElement('ul');
      errorEl.className = 'mt-1 list-disc pl-5 text-xs text-red-700';
      errorEl.dataset.role = 'login-field-errors';
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
    const banner = this.#container.querySelector('[data-role="login-error"]');
    if (banner !== null) {
      banner.textContent = message;
      banner.hidden = false;
    }
  }

  #clearErrors() {
    const banner = this.#container.querySelector('[data-role="login-error"]');
    if (banner !== null) {
      banner.textContent = '';
      banner.hidden = true;
    }

    const fieldErrors = this.#container.querySelectorAll('[data-role="login-field-errors"]');
    for (const error of fieldErrors) {
      error.remove();
    }
  }

  /**
   * @param {boolean} loading
   */
  #setLoading(loading) {
    const button = this.#container.querySelector('[data-role="login-submit"]');
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
