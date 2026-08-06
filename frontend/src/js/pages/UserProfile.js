import { UserConfig } from './UserConfig.js';

export class UserProfile extends UserConfig {
  /**
   * @param {HTMLElement|string} container
   * @param {Object|null} user
   * @param {import('../modules/Api.js').Api|undefined} api
   */
  constructor(container, user = null, api = undefined) {
    super(container, {
      mode: 'profile',
      user,
      api,
      title: 'Mi perfil',
      subtitle: 'Actualiza tus datos personales y tu contraseña.',
    });
  }

  showRoleField() {
    return false;
  }

  showResetButton() {
    return false;
  }

  showDeleteButton() {
    return false;
  }

  renderAdditionalFields() {
    const initialCurrentPassword = this.getDraftValue('currentPassword');
    const initialNewPassword = this.getDraftValue('newPassword');
    const initialConfirmPassword = this.getDraftValue('confirmPassword');

    return `
      <label>
        <span>Contraseña actual</span>
        <input type="password" name="currentPassword" value="${this.escapeHtml(initialCurrentPassword)}" class="${this.fieldErrorClass('currentPassword')}" />
        ${this.fieldErrorMarkup('currentPassword')}
      </label>
      <label>
        <span>Nueva contraseña</span>
        <input type="password" name="newPassword" value="${this.escapeHtml(initialNewPassword)}" class="${this.fieldErrorClass('newPassword')}" />
        ${this.fieldErrorMarkup('newPassword')}
      </label>
      <div class="xt-password-strength" data-password-strength>
        <div class="xt-password-strength__meter">
          <div class="xt-password-strength__fill"></div>
        </div>
        <div class="xt-password-strength__legend">
          <div class="xt-password-strength__legend-item" data-rule="length"><span class="xt-password-strength__legend-icon">•</span><span>Más de 9 caracteres</span></div>
          <div class="xt-password-strength__legend-item" data-rule="upper"><span class="xt-password-strength__legend-icon">•</span><span>Al menos una mayúscula</span></div>
          <div class="xt-password-strength__legend-item" data-rule="lower"><span class="xt-password-strength__legend-icon">•</span><span>Al menos una minúscula</span></div>
          <div class="xt-password-strength__legend-item" data-rule="symbol"><span class="xt-password-strength__legend-icon">•</span><span>Al menos un símbolo</span></div>
          <div class="xt-password-strength__legend-item" data-rule="different"><span class="xt-password-strength__legend-icon">•</span><span>No puede ser igual a la contraseña actual</span></div>
        </div>
      </div>
      <label>
        <span>Repetir nueva contraseña</span>
        <input type="password" name="confirmPassword" value="${this.escapeHtml(initialConfirmPassword)}" class="${this.fieldErrorClass('confirmPassword')}" />
        ${this.fieldErrorMarkup('confirmPassword')}
      </label>
    `;
  }

  attachAdditionalInteractions(form) {
    form.querySelectorAll('input[name="currentPassword"], input[name="newPassword"], input[name="confirmPassword"]').forEach((input) => {
      if (input instanceof HTMLInputElement) {
        const sync = () => {
          this.syncPasswordDraftValues(form);
        };

        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
      }
    });

    const newPasswordInput = form.querySelector('input[name="newPassword"]');
    const currentPasswordInput = form.querySelector('input[name="currentPassword"]');
    const strengthWidget = form.querySelector('[data-password-strength]');

    if (newPasswordInput instanceof HTMLInputElement && strengthWidget instanceof HTMLElement) {
      const updateStrength = () => {
        const current = currentPasswordInput instanceof HTMLInputElement ? currentPasswordInput.value : '';
        this.updatePasswordStrengthWidget(strengthWidget, newPasswordInput.value, current);
      };

      updateStrength();
      newPasswordInput.addEventListener('input', updateStrength);
      if (currentPasswordInput instanceof HTMLInputElement) {
        currentPasswordInput.addEventListener('input', updateStrength);
      }
    }
  }

  beforeSubmitForm(formData, payload) {
    const currentPassword = this.readFormValue(formData, 'currentPassword');
    const newPassword = this.readFormValue(formData, 'newPassword');
    const confirmPassword = this.readFormValue(formData, 'confirmPassword');

    this.syncPasswordDraftValuesFromFormData(formData);

    const wantsPasswordChange = newPassword !== '' || confirmPassword !== '' || currentPassword !== '';
    if (wantsPasswordChange) {
      if (currentPassword === '') {
        this.setFieldError('currentPassword', 'Debes escribir tu contraseña actual para cambiar la contraseña.');
        this.setPageMessage('Debes escribir tu contraseña actual para cambiar la contraseña.', 'error');
        return false;
      }

      const complexity = this.evaluateProfilePasswordComplexity(newPassword, currentPassword);
      if (!complexity.isValid) {
        const message = this.profilePasswordComplexityMessage(complexity);
        this.setFieldError('newPassword', message);
        this.setPageMessage(message, 'error');
        return false;
      }

      if (newPassword !== confirmPassword) {
        this.setFieldError('confirmPassword', 'La confirmación de la contraseña no coincide.');
        this.setPageMessage('La confirmación de la contraseña no coincide.', 'error');
        return false;
      }

      payload.current_password = currentPassword;
      payload.password = newPassword;
    }

    const currentUser = this.getUser();
    const currentEmail = typeof currentUser?.email === 'string' ? currentUser.email.trim() : '';
    const emailChanged = currentEmail !== '' && payload.email !== currentEmail;
    if (emailChanged && currentPassword === '') {
      this.setFieldError('currentPassword', 'Debes escribir tu contraseña actual para cambiar el email.');
      this.setPageMessage('Debes escribir tu contraseña actual para cambiar el email.', 'error');
      return false;
    }

    return true;
  }

  syncPasswordDraftValues(form) {
    const formData = new FormData(form);
    this.syncPasswordDraftValuesFromFormData(formData);
  }

  syncPasswordDraftValuesFromFormData(formData) {
    this.mergeDraftValues({
      currentPassword: this.readFormValue(formData, 'currentPassword'),
      newPassword: this.readFormValue(formData, 'newPassword'),
      confirmPassword: this.readFormValue(formData, 'confirmPassword'),
    });
  }

  evaluateProfilePasswordComplexity(value, currentPassword) {
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

  profilePasswordComplexityMessage(complexity) {
    const missing = [];
    if (!complexity.rules.length) missing.push('más de 9 caracteres');
    if (!complexity.rules.upper) missing.push('una mayúscula');
    if (!complexity.rules.lower) missing.push('una minúscula');
    if (!complexity.rules.symbol) missing.push('un símbolo');
    if (!complexity.rules.different) missing.push('una contraseña distinta a la actual');
    return `La nueva contraseña debe incluir ${missing.join(', ')}.`;
  }

  updatePasswordStrengthWidget(strengthWidget, value, currentPassword) {
    const complexity = this.evaluateProfilePasswordComplexity(value, currentPassword);
    const fill = strengthWidget.querySelector('.xt-password-strength__fill');
    const items = strengthWidget.querySelectorAll('.xt-password-strength__legend-item');

    if (fill instanceof HTMLElement) {
      fill.style.width = `${complexity.score}%`;
      fill.style.background = this.profileStrengthColor(complexity.score);
    }

    items.forEach((item) => {
      const ruleName = item.dataset.rule;
      const ruleState = complexity.rules[ruleName];
      item.classList.toggle('xt-password-strength__legend-item--ok', ruleState === true);
      item.classList.toggle('xt-password-strength__legend-item--fail', ruleState === false);
      const icon = item.querySelector('.xt-password-strength__legend-icon');
      if (icon instanceof HTMLElement) {
        icon.textContent = this.profileRuleIcon(ruleState);
      }
    });
  }

  profileStrengthColor(score) {
    if (score < 34) {
      return '#dc2626';
    }
    if (score < 67) {
      return '#f59e0b';
    }
    return '#10b981';
  }

  profileRuleIcon(ruleState) {
    if (ruleState === true) {
      return '✓';
    }
    if (ruleState === false) {
      return '✕';
    }
    return '•';
  }
}
