import { Api } from '../modules/Api.js';
import { AppState } from '../modules/State.js';

export class UserProfile {
  /** @type {HTMLElement} */
  #container;

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

  /**
   * @param {HTMLElement|string} container
   * @param {Object|null} user
   * @param {Api|undefined} api
   */
  constructor(container, user = null, api = undefined) {
    this.#container = this.#resolveContainer(container);
    this.#user = user;
    this.#api = api ?? new Api();
    this.#message = null;
    this.#messageType = null;
    this.#draftValues = {};
    this.#fieldErrors = {};
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
    subtitle.textContent = 'Actualiza tus datos personales y tu contraseña.';
    wrapper.appendChild(subtitle);

    if (this.#message !== null) {
      const feedback = document.createElement('div');
      feedback.className = `xt-page__feedback xt-page__feedback--${this.#messageType ?? 'success'}`;
      feedback.textContent = this.#message;
      wrapper.appendChild(feedback);
    }

    const card = document.createElement('div');
    card.className = 'xt-page__card';

    const form = document.createElement('form');
    form.className = 'xt-profile-form';
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      void this.#submit(form);
    });

    const stateUser = AppState.getUser();
    const resolvedUser = stateUser && typeof stateUser === 'object' ? stateUser : this.#user;
    const initialName = this.#getDraftValue('name', typeof resolvedUser?.name === 'string' && resolvedUser.name !== '' ? resolvedUser.name : '');
    const initialEmail = this.#getDraftValue('email', typeof resolvedUser?.email === 'string' && resolvedUser.email !== '' ? resolvedUser.email : '');
    const initialCurrentPassword = this.#getDraftValue('currentPassword');
    const initialNewPassword = this.#getDraftValue('newPassword');
    const initialConfirmPassword = this.#getDraftValue('confirmPassword');
    const initials = this.#getInitials(initialName || initialEmail || 'Usuario');
    const avatarImage = typeof resolvedUser?.avatar === 'string' && resolvedUser.avatar !== '' ? resolvedUser.avatar : '';

    form.innerHTML = this.#buildFormMarkup({
      initialName,
      initialEmail,
      initialCurrentPassword,
      initialNewPassword,
      initialConfirmPassword,
      initials,
      avatarImage,
    });

    this.#attachFormInteractions(form);

    card.appendChild(form);
    wrapper.appendChild(card);
    this.#container.appendChild(wrapper);
  }

  async #submit(form) {
    this.#syncDraftValues(form);
    this.#fieldErrors = {};

    const formData = new FormData(form);
    const payload = await this.#buildProfilePayload(formData, form);
    const currentPassword = this.#readFormValue(formData, 'currentPassword');
    const newPassword = this.#readFormValue(formData, 'newPassword');
    const confirmPassword = this.#readFormValue(formData, 'confirmPassword');

    if (!this.#handlePasswordValidation(payload, currentPassword, newPassword, confirmPassword)) {
      this.#render();
      return;
    }

    if (!this.#handleEmailValidation(currentPassword, payload)) {
      this.#render();
      return;
    }

    try {
      const response = await this.#api.put('/users/me', payload);
      const userPayload = response?.data ?? response;
      const updatedUser = {
        ...AppState.getUser(),
        ...userPayload,
        email: typeof userPayload?.email === 'string' ? userPayload.email : payload.email,
        name: typeof userPayload?.name === 'string' ? userPayload.name : payload.name,
      };
      AppState.setUser(updatedUser);
      this.#user = updatedUser;
      this.#setMessage('Perfil actualizado correctamente.', 'success');
      this.#render();
    } catch (error) {
      if (error?.message === 'Current password is incorrect.' || error?.message === 'La contraseña actual es incorrecta.') {
        this.#fieldErrors.currentPassword = error.message;
      }
      this.#setMessage(error?.message ?? 'No se pudo actualizar el perfil.', 'error');
      this.#render();
    }
  }

  #setMessage(message, type) {
    this.#message = message;
    this.#messageType = type;
  }

  #buildFormMarkup({
    initialName,
    initialEmail,
    initialCurrentPassword,
    initialNewPassword,
    initialConfirmPassword,
    initials,
    avatarImage,
  }) {
    const avatarMarkup = avatarImage !== ''
      ? `<img class="xt-profile-form__avatar-image" src="${this.#escapeHtml(avatarImage)}" alt="Avatar actual" />`
      : `<span>${this.#escapeHtml(initials)}</span>`;

    return `
      <div class="xt-profile-form__avatar">
        <div class="xt-profile-form__avatar-circle">${avatarMarkup}</div>
        <div>
          <p class="xt-profile-form__avatar-title">Avatar</p>
          <p class="xt-profile-form__avatar-help">Se usará la inicial del nombre o email si no hay imagen.</p>
        </div>
      </div>
      <div class="xt-profile-form__upload">
        <button class="xt-btn xt-btn--secondary" type="button" data-avatar-trigger>Subir avatar</button>
        <input type="file" name="avatar" accept="image/*" />
      </div>
      <label>
        <span>Nombre</span>
        <input type="text" name="name" value="${this.#escapeHtml(initialName)}" />
      </label>
      <label>
        <span>Email</span>
        <input type="email" name="email" value="${this.#escapeHtml(initialEmail)}" />
      </label>
      <label>
        <span>Contraseña actual</span>
        <input type="password" name="currentPassword" value="${this.#escapeHtml(initialCurrentPassword)}" class="${this.#isFieldError('currentPassword') ? 'xt-profile-form__input--error' : ''}" />
        ${this.#renderFieldErrorMarkup('currentPassword')}
      </label>
      <label>
        <span>Nueva contraseña</span>
        <input type="password" name="newPassword" value="${this.#escapeHtml(initialNewPassword)}" class="${this.#isFieldError('newPassword') ? 'xt-profile-form__input--error' : ''}" />
        ${this.#renderFieldErrorMarkup('newPassword')}
      </label>
      <div class="xt-password-strength" data-password-strength>
        <div class="xt-password-strength__meter">
          <div class="xt-password-strength__fill"></div>
        </div>
        <div class="xt-password-strength__legend">
          <div class="xt-password-strength__legend-item" data-rule="length">
            <span class="xt-password-strength__legend-icon">•</span>
            <span>Más de 9 caracteres</span>
          </div>
          <div class="xt-password-strength__legend-item" data-rule="upper">
            <span class="xt-password-strength__legend-icon">•</span>
            <span>Al menos una mayúscula</span>
          </div>
          <div class="xt-password-strength__legend-item" data-rule="lower">
            <span class="xt-password-strength__legend-icon">•</span>
            <span>Al menos una minúscula</span>
          </div>
          <div class="xt-password-strength__legend-item" data-rule="symbol">
            <span class="xt-password-strength__legend-icon">•</span>
            <span>Al menos un símbolo</span>
          </div>
          <div class="xt-password-strength__legend-item" data-rule="different">
            <span class="xt-password-strength__legend-icon">•</span>
            <span>No puede ser igual a la contraseña actual</span>
          </div>
        </div>
      </div>
      <label>
        <span>Confirmar nueva contraseña</span>
        <input type="password" name="confirmPassword" value="${this.#escapeHtml(initialConfirmPassword)}" class="${this.#isFieldError('confirmPassword') ? 'xt-profile-form__input--error' : ''}" />
        ${this.#renderFieldErrorMarkup('confirmPassword')}
      </label>
      <button class="xt-btn xt-btn--primary" type="submit">Guardar cambios</button>
    `;
  }

  #attachFormInteractions(form) {
    form.querySelectorAll('input[name="name"], input[name="email"], input[name="currentPassword"], input[name="newPassword"], input[name="confirmPassword"]').forEach((input) => {
      if (input instanceof HTMLInputElement) {
        input.addEventListener('input', () => this.#syncDraftValues(form));
      }
    });

    const newPasswordInput = form.querySelector('input[name="newPassword"]');
    const currentPasswordInput = form.querySelector('input[name="currentPassword"]');
    const avatarInput = form.querySelector('input[name="avatar"]');
    const avatarTrigger = form.querySelector('[data-avatar-trigger]');
    const strengthWidget = form.querySelector('[data-password-strength]');

    if (newPasswordInput instanceof HTMLInputElement && strengthWidget instanceof HTMLElement) {
      const updateStrength = () => this.#updatePasswordStrength(
        strengthWidget,
        newPasswordInput.value,
        currentPasswordInput instanceof HTMLInputElement ? currentPasswordInput.value : ''
      );
      updateStrength();
      newPasswordInput.addEventListener('input', updateStrength);
      if (currentPasswordInput instanceof HTMLInputElement) {
        currentPasswordInput.addEventListener('input', updateStrength);
      }
    }

    if (avatarInput instanceof HTMLInputElement && avatarTrigger instanceof HTMLButtonElement) {
      avatarTrigger.addEventListener('click', () => {
        avatarInput.click();
      });

      avatarInput.addEventListener('change', async () => {
        const file = avatarInput.files?.[0];
        if (!file) {
          return;
        }

        const preview = form.querySelector('.xt-profile-form__avatar-circle');
        const dataUrl = await this.#readFileAsDataUrl(file);
        if (preview instanceof HTMLElement) {
          preview.innerHTML = `<img class="xt-profile-form__avatar-image" src="${this.#escapeHtml(dataUrl)}" alt="Avatar nuevo" />`;
        }
      });
    }
  }

  async #buildProfilePayload(formData, form) {
    const rawName = formData.get('name');
    const rawEmail = formData.get('email');
    const payload = {
      name: typeof rawName === 'string' ? rawName.trim() : '',
      email: typeof rawEmail === 'string' ? rawEmail.trim() : '',
    };

    const avatarFile = form.querySelector('input[name="avatar"]') instanceof HTMLInputElement
      ? form.querySelector('input[name="avatar"]').files?.[0]
      : null;

    if (avatarFile) {
      payload.avatar = await this.#readFileAsDataUrl(avatarFile);
    }

    return payload;
  }

  #handlePasswordValidation(payload, currentPassword, newPassword, confirmPassword) {
    const wantsPasswordChange = newPassword !== '' || confirmPassword !== '' || currentPassword !== '';
    if (!wantsPasswordChange) {
      return true;
    }

    if (currentPassword === '') {
      this.#fieldErrors.currentPassword = 'Debes escribir tu contraseña actual para cambiar la contraseña.';
      this.#setMessage('Debes escribir tu contraseña actual para cambiar la contraseña.', 'error');
      return false;
    }

    const complexity = this.#evaluatePasswordComplexity(newPassword, currentPassword);
    if (!complexity.isValid) {
      this.#fieldErrors.newPassword = this.#passwordComplexityMessage(complexity);
      this.#setMessage(this.#passwordComplexityMessage(complexity), 'error');
      return false;
    }

    if (newPassword !== confirmPassword) {
      this.#fieldErrors.confirmPassword = 'La confirmación de la contraseña no coincide.';
      this.#setMessage('La confirmación de la contraseña no coincide.', 'error');
      return false;
    }

    payload.current_password = currentPassword;
    payload.password = newPassword;
    return true;
  }

  #handleEmailValidation(currentPassword, payload) {
    const emailChanged = typeof this.#user?.email === 'string' && this.#user.email !== '' && payload.email !== this.#user.email;
    if (!emailChanged || currentPassword !== '') {
      return true;
    }

    this.#fieldErrors.currentPassword = 'Debes escribir tu contraseña actual para cambiar el email.';
    this.#setMessage('Debes escribir tu contraseña actual para cambiar el email.', 'error');
    return false;
  }

  #updatePasswordStrength(strengthWidget, value, currentPassword) {
    const complexity = this.#evaluatePasswordComplexity(value, currentPassword);
    const fill = strengthWidget.querySelector('.xt-password-strength__fill');
    const items = strengthWidget.querySelectorAll('.xt-password-strength__legend-item');

    if (fill instanceof HTMLElement) {
      fill.style.width = `${complexity.score}%`;
      fill.style.background = this.#getStrengthColor(complexity.score);
    }

    items.forEach((item) => {
      const ruleName = item.dataset.rule;
      const ruleState = complexity.rules[ruleName];
      item.classList.toggle('xt-password-strength__legend-item--ok', ruleState === true);
      item.classList.toggle('xt-password-strength__legend-item--fail', ruleState === false);
      const icon = item.querySelector('.xt-password-strength__legend-icon');
      if (icon instanceof HTMLElement) {
        icon.textContent = this.#getRuleIcon(ruleState);
      }
    });
  }

  #evaluatePasswordComplexity(value, currentPassword) {
    const normalizedValue = String(value ?? '');
    const rules = {
      length: normalizedValue.length > 9,
      upper: /[A-Z]/.test(normalizedValue),
      lower: /[a-z]/.test(normalizedValue),
      symbol: /[^A-Za-z0-9]/.test(normalizedValue),
      different: normalizedValue !== '' && normalizedValue !== currentPassword,
    };

    const metRules = Object.values(rules).filter(Boolean).length;
    const score = normalizedValue === '' ? 0 : Math.min(100, Math.round((metRules / 5) * 100));
    const isValid = normalizedValue.length > 9 && rules.upper && rules.lower && rules.symbol && rules.different;

    return { score, isValid, rules };
  }

  #getStrengthColor(score) {
    if (score < 34) {
      return '#dc2626';
    }

    if (score < 67) {
      return '#f59e0b';
    }

    return '#10b981';
  }

  #getRuleIcon(ruleState) {
    if (ruleState === true) {
      return '✓';
    }

    if (ruleState === false) {
      return '✕';
    }

    return '•';
  }

  #passwordComplexityMessage(complexity) {
    const missingRules = [];
    if (!complexity.rules.length) missingRules.push('más de 9 caracteres');
    if (!complexity.rules.upper) missingRules.push('una mayúscula');
    if (!complexity.rules.lower) missingRules.push('una minúscula');
    if (!complexity.rules.symbol) missingRules.push('un símbolo');
    if (!complexity.rules.different) missingRules.push('una contraseña distinta a la actual');

    return `La nueva contraseña debe incluir ${missingRules.join(', ')}.`;
  }

  #syncDraftValues(form) {
    const formData = new FormData(form);
    this.#draftValues = {
      name: this.#readFormValue(formData, 'name'),
      email: this.#readFormValue(formData, 'email'),
      currentPassword: this.#readFormValue(formData, 'currentPassword'),
      newPassword: this.#readFormValue(formData, 'newPassword'),
      confirmPassword: this.#readFormValue(formData, 'confirmPassword'),
    };
  }

  #getDraftValue(fieldName, fallback = '') {
    return typeof this.#draftValues[fieldName] === 'string' ? this.#draftValues[fieldName] : fallback;
  }

  #renderFieldErrorMarkup(fieldName) {
    const message = this.#fieldErrors[fieldName];
    if (!message) {
      return '';
    }

    return `<p class="xt-profile-form__field-error">${this.#escapeHtml(message)}</p>`;
  }

  #isFieldError(fieldName) {
    return typeof this.#fieldErrors[fieldName] === 'string' && this.#fieldErrors[fieldName] !== '';
  }

  #readFileAsDataUrl(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
      reader.onerror = () => reject(new Error('No se pudo leer el archivo de avatar.'));
      reader.readAsDataURL(file);
    });
  }

  #readFormValue(formData, fieldName) {
    const value = formData.get(fieldName);
    if (typeof value === 'string') {
      return value.trim();
    }
    return '';
  }

  #getInitials(source) {
    const parts = String(source).split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
      return 'U';
    }
    return parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
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
