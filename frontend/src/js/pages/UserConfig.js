import { Api } from '../modules/Api.js';
import { AppState } from '../modules/State.js';

export class UserConfig {
  /** @type {HTMLElement} */
  #container;

  /** @type {'admin'|'profile'} */
  #mode;

  /** @type {Object|null} */
  #user;

  /** @type {Api} */
  #api;

  /** @type {string|null} */
  #message;

  /** @type {'success'|'error'|null} */
  #messageType;

  /** @type {Record<string, string>} */
  #draftValues;

  /** @type {Record<string, string>} */
  #fieldErrors;

  /** @type {string|null} */
  #temporaryPassword;

  /** @type {string|null} */
  #currentUserId;

  /** @type {Function|null} */
  #onBack;

  /** @type {Function|null} */
  #onSaved;

  /** @type {Function|null} */
  #onDeleted;

  /** @type {string} */
  #title;

  /** @type {string} */
  #subtitle;

  /**
   * @param {HTMLElement|string} container
   * @param {{
   *   mode?: 'admin'|'profile',
   *   user?: Object|null,
   *   api?: Api,
   *   currentUserId?: string|null,
   *   onBack?: Function,
   *   onSaved?: Function,
   *   onDeleted?: Function,
   *   title?: string,
   *   subtitle?: string
   * }} [options]
   */
  constructor(container, options = {}) {
    this.#container = this.#resolveContainer(container);
    this.#mode = options.mode === 'profile' ? 'profile' : 'admin';
    this.#user = options.user ?? null;
    this.#api = options.api ?? new Api();
    this.#message = null;
    this.#messageType = null;
    this.#draftValues = {};
    this.#fieldErrors = {};
    this.#temporaryPassword = null;
    this.#currentUserId = typeof options.currentUserId === 'string' ? options.currentUserId : null;
    this.#onBack = typeof options.onBack === 'function' ? options.onBack : null;
    this.#onSaved = typeof options.onSaved === 'function' ? options.onSaved : null;
    this.#onDeleted = typeof options.onDeleted === 'function' ? options.onDeleted : null;

    if (typeof options.title === 'string') {
      this.#title = options.title;
    } else if (this.#mode === 'profile') {
      this.#title = 'Mi perfil';
    } else {
      this.#title = 'Configuración de usuario';
    }

    if (typeof options.subtitle === 'string') {
      this.#subtitle = options.subtitle;
    } else if (this.#mode === 'profile') {
      this.#subtitle = 'Actualiza tus datos personales.';
    } else {
      this.#subtitle = 'Página de configuración del usuario seleccionado.';
    }

    this.#render();
  }

  setUser(user) {
    this.#user = user ?? null;
    this.#temporaryPassword = null;
    this.#render();
  }

  getUser() {
    return this.#user;
  }

  getMode() {
    return this.#mode;
  }

  getDraftValue(fieldName, fallback = '') {
    const value = this.#draftValues[fieldName];
    return typeof value === 'string' ? value : fallback;
  }

  mergeDraftValues(values) {
    if (values === null || typeof values !== 'object') {
      return;
    }

    this.#draftValues = {
      ...this.#draftValues,
      ...values,
    };
  }

  setFieldError(fieldName, message) {
    if (typeof fieldName !== 'string' || fieldName === '') {
      return;
    }

    this.#fieldErrors[fieldName] = String(message ?? '');
  }

  clearFieldErrors() {
    this.#fieldErrors = {};
  }

  isFieldError(fieldName) {
    return typeof this.#fieldErrors[fieldName] === 'string' && this.#fieldErrors[fieldName] !== '';
  }

  fieldErrorClass(fieldName) {
    return this.isFieldError(fieldName)
      ? 'border-red-300 ring-2 ring-red-200 focus:border-red-400 focus:ring-red-200'
      : 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
  }

  fieldErrorMarkup(fieldName) {
    const message = this.#fieldErrors[fieldName];
    if (typeof message !== 'string' || message === '') {
      return '';
    }

    return `<p class="mt-1 text-xs font-medium text-red-700">${this.escapeHtml(message)}</p>`;
  }

  setPageMessage(message, type) {
    this.#message = message;
    this.#messageType = type;
  }

  readFormValue(formData, fieldName) {
    const value = formData.get(fieldName);
    if (typeof value === 'string') {
      return value.trim();
    }

    return '';
  }

  escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  // Hooks de extensión: permiten variar comportamiento sin tocar el render base.
  showRoleField() {
    return this.#mode === 'admin';
  }

  showResetButton() {
    return this.#mode === 'admin';
  }

  showDeleteButton() {
    return this.#mode === 'admin';
  }

  renderAdditionalFields() {
    return '';
  }

  attachAdditionalInteractions() {
    // No-op: pensado para subclases.
  }

  beforeSubmitForm() {
    return true;
  }

  getUpdateEndpoint(userId) {
    if (this.#mode === 'profile') {
      return '/users/me';
    }

    return `/users/${userId}`;
  }

  getResetEndpoint(userId) {
    if (this.#mode === 'profile') {
      return '';
    }

    return `/users/${userId}/password`;
  }

  shouldSyncAppStateAfterSave() {
    return this.#mode === 'profile';
  }

  #render() {
    this.#container.replaceChildren();

    const wrapper = document.createElement('section');
    wrapper.className = 'grid gap-4';
    wrapper.dataset.page = 'user-config';

    const heading = document.createElement('h2');
    heading.className = 'text-2xl font-semibold tracking-tight text-slateui-950';
    heading.textContent = this.#title;
    wrapper.appendChild(heading);

    const subtitle = document.createElement('p');
    subtitle.className = 'text-sm text-slate-500';
    subtitle.textContent = this.#subtitle;
    wrapper.appendChild(subtitle);

    const top = document.createElement('div');
    top.className = 'flex flex-wrap items-center justify-between gap-2';
    if (this.#onBack !== null) {
      const back = this.#makeButton('Volver al listado', '', () => {
        this.#onBack();
      });
      top.appendChild(back);
    }
    wrapper.appendChild(top);

    const feedback = this.#feedbackNode();
    if (feedback !== null) {
      wrapper.appendChild(feedback);
    }

    const card = document.createElement('div');
    card.className = 'rounded-2xl border border-slate-200 bg-white p-4 shadow-panel';

    const user = this.#resolveUser();
    if (user === null || typeof user !== 'object') {
      const empty = document.createElement('p');
      empty.className = 'rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500';
      empty.textContent = 'No se encontró el usuario solicitado.';
      card.appendChild(empty);
      wrapper.appendChild(card);
      this.#container.appendChild(wrapper);
      return;
    }

    const form = document.createElement('form');
    form.className = 'grid gap-4';
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      void this.#submitForm(form);
    });

    form.innerHTML = this.#buildFormMarkup(user);
    this.#attachFormInteractions(form);
    this.attachAdditionalInteractions(form);

    card.appendChild(form);
    wrapper.appendChild(card);
    this.#container.appendChild(wrapper);
  }

  #buildFormMarkup(user) {
    const initialName = this.getDraftValue('name', this.#displayName(user));
    const initialEmail = this.getDraftValue('email', this.#displayEmail(user));
    const initialRole = this.getDraftValue('role', this.#primaryRole(user));
    const initialAvatar = this.getDraftValue('avatar', this.#stringValue(user?.avatar));
    const createdAt = this.#formatDateForInput(this.#resolveCreatedAt(user));

    let avatarMarkup = `<span>${this.escapeHtml(this.#getInitials(initialName, initialEmail))}</span>`;
    const avatarImage = this.#stringValue(initialAvatar);
    if (avatarImage !== '') {
      avatarMarkup = `<img class="h-full w-full object-cover" src="${this.escapeHtml(avatarImage)}" alt="Avatar actual" />`;
    }

    const adminSelected = initialRole === 'admin' ? 'selected' : '';
    const userSelected = initialRole === 'user' ? 'selected' : '';

    const roleMarkup = this.showRoleField()
      ? `
      <label>
        <span class="text-sm font-medium text-slate-700">Rol</span>
        <select class="w-full rounded-lg border-slate-300 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500" name="role">
          <option value="admin" ${adminSelected}>Administrador</option>
          <option value="user" ${userSelected}>Usuario</option>
        </select>
      </label>
      `
      : '';

    const additionalFieldsMarkup = this.renderAdditionalFields();

    const resetButtonMarkup = this.showResetButton()
      ? '<button class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" type="button" data-userconfig-reset>Reset password</button>'
      : '';

    const deleteButtonMarkup = this.showDeleteButton()
      ? '<button class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50" type="button" data-userconfig-delete>Borrar usuario</button>'
      : '';

    const temporaryPasswordMarkup = this.#temporaryPassword !== null
      ? this.#temporaryPasswordMarkup(this.#temporaryPassword)
      : '';

    const uploadLabel = avatarImage === '' ? 'Subir avatar' : 'Cambiar avatar';
    const removeAvatarMarkup = avatarImage === ''
      ? ''
      : '<button class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" type="button" data-userconfig-remove-avatar>Quitar avatar</button>';

    return `
      <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-sm font-bold text-brand-700">${avatarMarkup}</div>
        <div class="grid gap-0.5">
          <p class="text-sm font-semibold text-slateui-950">Avatar</p>
          <p class="text-xs text-slate-500">JPG/PNG hasta 2MB.</p>
        </div>
        <div class="inline-flex flex-wrap items-center gap-2">
          <button class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" type="button" data-userconfig-select-avatar>${uploadLabel}</button>
          ${removeAvatarMarkup}
        </div>
        <input type="file" accept="image/png,image/jpeg,image/webp" data-userconfig-avatar-file hidden />
        <input type="hidden" name="avatar" value="${this.escapeHtml(avatarImage)}" data-userconfig-avatar-value />
      </div>
      <label>
        <span class="text-sm font-medium text-slate-700">Nombre</span>
        <input class="w-full rounded-lg ${this.fieldErrorClass('name')} text-sm text-slate-900" type="text" name="name" value="${this.escapeHtml(initialName)}" />
        ${this.fieldErrorMarkup('name')}
      </label>
      <label>
        <span class="text-sm font-medium text-slate-700">Email</span>
        <input class="w-full rounded-lg ${this.fieldErrorClass('email')} text-sm text-slate-900" type="email" name="email" value="${this.escapeHtml(initialEmail)}" />
        ${this.fieldErrorMarkup('email')}
      </label>
      ${roleMarkup}
      ${additionalFieldsMarkup}
      <label>
        <span class="text-sm font-medium text-slate-700">Fecha alta</span>
        <input class="w-full rounded-lg border-slate-300 bg-slate-100 text-sm text-slate-500" type="date" name="createdAt" value="${this.escapeHtml(createdAt)}" disabled />
      </label>
      <p class="text-sm text-red-700" hidden data-userconfig-error></p>
      <div class="flex flex-wrap gap-2">
        <button class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700" type="submit">Guardar cambios</button>
        ${resetButtonMarkup}
        ${deleteButtonMarkup}
      </div>
      ${temporaryPasswordMarkup}
    `;
  }

  #attachFormInteractions(form) {
    form.querySelectorAll('input[name="name"], input[name="email"], select[name="role"]').forEach((input) => {
      if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement) {
        input.addEventListener('input', () => this.#syncDraftValues(form));
        input.addEventListener('change', () => this.#syncDraftValues(form));
      }
    });

    const avatarInput = form.querySelector('[data-userconfig-avatar-file]');
    const selectAvatarButton = form.querySelector('[data-userconfig-select-avatar]');

    if (selectAvatarButton instanceof HTMLButtonElement && avatarInput instanceof HTMLInputElement) {
      selectAvatarButton.addEventListener('click', () => {
        avatarInput.click();
      });
    }

    if (avatarInput instanceof HTMLInputElement) {
      avatarInput.addEventListener('change', () => {
        void this.#handleAvatarSelection(form, avatarInput);
      });
    }

    const removeAvatarButton = form.querySelector('[data-userconfig-remove-avatar]');
    if (removeAvatarButton instanceof HTMLButtonElement) {
      removeAvatarButton.addEventListener('click', () => {
        this.#setAvatarValue(form, '');
        this.#syncDraftValues(form);
        this.#render();
      });
    }

    const resetButton = form.querySelector('[data-userconfig-reset]');
    if (resetButton instanceof HTMLButtonElement) {
      resetButton.addEventListener('click', () => {
        void this.#resetPassword(form);
      });
    }

    const deleteButton = form.querySelector('[data-userconfig-delete]');
    if (deleteButton instanceof HTMLButtonElement) {
      const user = this.#resolveUser();
      if (this.#isCurrentUser(user)) {
        deleteButton.disabled = true;
        deleteButton.title = 'No puedes borrar tu propia cuenta.';
      }

      deleteButton.addEventListener('click', () => {
        void this.#deleteUser(form);
      });
    }

    const copyButton = form.querySelector('[data-userconfig-copy-password]');
    if (copyButton instanceof HTMLButtonElement && this.#temporaryPassword !== null) {
      copyButton.addEventListener('click', async () => {
        const copied = await this.copyToClipboard(this.#temporaryPassword ?? '');
        if (copied) {
          this.setPageMessage('Contraseña copiada al portapapeles.', 'success');
        } else {
          this.setPageMessage('No se pudo copiar automáticamente. Cópiala manualmente.', 'error');
        }
        this.#render();
      });
    }
  }

  async #submitForm(form) {
    const user = this.#resolveUser();
    if (user === null || typeof user !== 'object') {
      return;
    }

    this.#syncDraftValues(form);
    this.clearFieldErrors();

    const formData = new FormData(form);
    const payload = {
      name: this.readFormValue(formData, 'name'),
      email: this.readFormValue(formData, 'email'),
      avatar: this.readFormValue(formData, 'avatar'),
    };

    if (this.showRoleField()) {
      payload.roles = [this.readFormValue(formData, 'role') || 'user'];
    }

    const errorNode = form.querySelector('[data-userconfig-error]');
    const canSubmit = this.beforeSubmitForm(formData, payload, errorNode);
    if (!canSubmit) {
      this.#render();
      return;
    }

    try {
      const userId = this.#getUserId(user);
      const endpoint = this.getUpdateEndpoint(userId);
      const response = await this.#api.put(endpoint, payload);
      const updated = response?.data ?? response;

      this.#user = { ...user, ...updated };
      this.#temporaryPassword = null;
      this.setPageMessage('Usuario actualizado correctamente.', 'success');

      if (this.shouldSyncAppStateAfterSave()) {
        const current = AppState.getUser();
        const merged = {
          ...(current && typeof current === 'object' ? current : {}),
          ...this.#user,
          ...updated,
        };
        AppState.setUser(merged);
      }

      if (this.#onSaved !== null) {
        this.#onSaved(updated);
      }

      this.#render();
    } catch (apiError) {
      if (errorNode instanceof HTMLElement) {
        errorNode.textContent = apiError?.message ?? 'No se pudo actualizar el usuario.';
        errorNode.hidden = false;
      }
      this.setPageMessage(apiError?.message ?? 'No se pudo actualizar el usuario.', 'error');
      this.#render();
    }
  }

  async #resetPassword(form) {
    const user = this.#resolveUser();
    if (user === null || typeof user !== 'object') {
      return;
    }

    const endpoint = this.getResetEndpoint(this.#getUserId(user));
    if (endpoint === '') {
      return;
    }

    const errorNode = form.querySelector('[data-userconfig-error]');

    try {
      const response = await this.#api.put(endpoint, {});
      const generated = String(response?.data?.temporary_password ?? '');
      if (generated === '') {
        throw new Error('No se recibió la contraseña temporal.');
      }

      this.#temporaryPassword = generated;
      this.setPageMessage('Contraseña temporal generada. Solo se mostrará esta vez.', 'success');
      if (errorNode instanceof HTMLElement) {
        errorNode.hidden = true;
      }
      this.#render();
    } catch (apiError) {
      if (errorNode instanceof HTMLElement) {
        errorNode.textContent = apiError?.message ?? 'No se pudo generar la contraseña temporal.';
        errorNode.hidden = false;
      }
      this.setPageMessage(apiError?.message ?? 'No se pudo generar la contraseña temporal.', 'error');
    }
  }

  async #deleteUser(form) {
    const user = this.#resolveUser();
    if (user === null || typeof user !== 'object') {
      return;
    }

    if (this.#isCurrentUser(user)) {
      this.setPageMessage('No puedes borrar tu propia cuenta.', 'error');
      this.#render();
      return;
    }

    const accepted = window.confirm(`¿Seguro que quieres borrar al usuario ${this.#displayName(user)}? Esta acción es irreversible.`);
    if (!accepted) {
      return;
    }

    const errorNode = form.querySelector('[data-userconfig-error]');

    try {
      const userId = this.#getUserId(user);
      await this.#api.delete(`/users/${userId}`);
      this.setPageMessage('Usuario borrado correctamente.', 'success');
      if (this.#onDeleted !== null) {
        this.#onDeleted(userId);
      }
    } catch (apiError) {
      if (errorNode instanceof HTMLElement) {
        errorNode.textContent = apiError?.message ?? 'No se pudo borrar el usuario.';
        errorNode.hidden = false;
      }
      this.setPageMessage(apiError?.message ?? 'No se pudo borrar el usuario.', 'error');
    }
  }

  #syncDraftValues(form) {
    const formData = new FormData(form);
    this.#draftValues = {
      name: this.readFormValue(formData, 'name'),
      email: this.readFormValue(formData, 'email'),
      role: this.readFormValue(formData, 'role'),
      avatar: this.readFormValue(formData, 'avatar'),
    };
  }

  async #handleAvatarSelection(form, avatarInput) {
    const file = avatarInput.files?.item(0) ?? null;
    if (!(file instanceof File)) {
      return;
    }

    if (file.size > 2 * 1024 * 1024) {
      this.setPageMessage('El avatar no puede superar 2MB.', 'error');
      this.#render();
      return;
    }

    try {
      const imageDataUrl = await this.#readFileAsDataUrl(file);
      this.#setAvatarValue(form, imageDataUrl);
      this.#syncDraftValues(form);
      this.setPageMessage('Avatar actualizado. Guarda cambios para confirmar.', 'success');
      this.#render();
    } catch {
      this.setPageMessage('No se pudo leer la imagen seleccionada.', 'error');
      this.#render();
    }
  }

  #setAvatarValue(form, value) {
    const avatarValueInput = form.querySelector('[data-userconfig-avatar-value]');
    if (avatarValueInput instanceof HTMLInputElement) {
      avatarValueInput.value = value;
    }
  }

  #readFileAsDataUrl(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => {
        if (typeof reader.result === 'string') {
          resolve(reader.result);
          return;
        }

        reject(new Error('Formato no soportado.'));
      };
      reader.onerror = () => reject(reader.error ?? new Error('No se pudo leer el archivo.'));
      reader.readAsDataURL(file);
    });
  }

  async copyToClipboard(text) {
    if (typeof text !== 'string' || text === '') {
      return false;
    }

    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      return true;
    }

    return false;
  }

  #feedbackNode() {
    if (this.#message === null) {
      return null;
    }

    const feedback = document.createElement('div');
    feedback.className = `rounded-lg border px-3 py-2 text-sm ${this.#messageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`;
    feedback.textContent = this.#message;
    return feedback;
  }

  #resolveUser() {
    if (this.#mode === 'profile') {
      const stateUser = AppState.getUser();
      if (stateUser !== null && typeof stateUser === 'object') {
        return stateUser;
      }
    }

    return this.#user;
  }

  #temporaryPasswordMarkup(temporaryPassword) {
    return `
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <input type="text" readonly value="${this.escapeHtml(temporaryPassword)}" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-700" />
        <button class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" type="button" data-userconfig-copy-password>Copiar</button>
      </div>
    `;
  }

  #makeButton(label, className, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `${className} inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100`;
    button.textContent = label;
    button.addEventListener('click', onClick);
    return button;
  }

  #stringValue(value) {
    if (typeof value === 'string' && value !== '') {
      return value;
    }
    return '';
  }

  #displayName(user) {
    const name = this.#stringValue(user?.name).trim();
    if (name !== '') {
      return name;
    }
    return this.#displayEmail(user);
  }

  #displayEmail(user) {
    const email = this.#stringValue(user?.email).trim();
    if (email !== '') {
      return email;
    }
    return 'Sin email';
  }

  #userRoles(user) {
    if (Array.isArray(user?.roles)) {
      return user.roles.filter((role) => typeof role === 'string' && role.trim() !== '');
    }
    return [];
  }

  #primaryRole(user) {
    const roles = this.#userRoles(user);
    if (roles.includes('admin')) {
      return 'admin';
    }
    if (roles.includes('user') || roles.includes('operador')) {
      return 'user';
    }
    return 'user';
  }

  #getUserId(user) {
    return typeof user?.id === 'string' ? user.id : '';
  }

  #isCurrentUser(user) {
    const id = this.#getUserId(user);
    return this.#currentUserId !== null && id !== '' && this.#currentUserId === id;
  }

  #formatDateForInput(value) {
    if (typeof value !== 'string' || value.trim() === '') {
      return '';
    }

    const normalized = value.trim();
    const isoDatePattern = /^\d{4}-\d{2}-\d{2}/;
    if (isoDatePattern.test(normalized)) {
      return normalized.slice(0, 10);
    }

    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) {
      return '';
    }

    return date.toISOString().slice(0, 10);
  }

  #resolveCreatedAt(user) {
    const createdAt = this.#stringValue(user?.created_at);
    if (createdAt !== '') {
      return createdAt;
    }

    return this.#stringValue(user?.creation_stamp);
  }

  #getInitials(name, email = '') {
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

    throw new TypeError(`UserConfig: container "${String(container)}" not found`);
  }
}
