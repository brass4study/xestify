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
        <span class="text-sm font-medium text-slate-700">Contraseña actual</span>
        <input type="password" name="currentPassword" value="${this.escapeHtml(initialCurrentPassword)}" class="w-full rounded-lg text-sm text-slate-900 ${this.fieldErrorClass('currentPassword')}" />
        ${this.fieldErrorMarkup('currentPassword')}
      </label>
      <label>
        <span class="text-sm font-medium text-slate-700">Nueva contraseña</span>
        <input type="password" name="newPassword" value="${this.escapeHtml(initialNewPassword)}" class="w-full rounded-lg text-sm text-slate-900 ${this.fieldErrorClass('newPassword')}" />
        ${this.fieldErrorMarkup('newPassword')}
      </label>
      <div class="grid gap-2" data-password-strength>
        <div class="h-2 overflow-hidden rounded-full bg-slate-200" data-role="password-meter">
          <div class="h-full w-0 bg-slate-400" data-role="password-fill"></div>
        </div>
        <div class="grid gap-1 text-xs text-slate-600" data-role="password-rules">
          <div class="flex items-center gap-2" data-role="password-rule" data-rule="length"><span data-role="password-rule-icon" class="text-slate-400">•</span><span>Más de 9 caracteres</span></div>
          <div class="flex items-center gap-2" data-role="password-rule" data-rule="upper"><span data-role="password-rule-icon" class="text-slate-400">•</span><span>Al menos una mayúscula</span></div>
          <div class="flex items-center gap-2" data-role="password-rule" data-rule="lower"><span data-role="password-rule-icon" class="text-slate-400">•</span><span>Al menos una minúscula</span></div>
          <div class="flex items-center gap-2" data-role="password-rule" data-rule="symbol"><span data-role="password-rule-icon" class="text-slate-400">•</span><span>Al menos un símbolo</span></div>
          <div class="flex items-center gap-2" data-role="password-rule" data-rule="different"><span data-role="password-rule-icon" class="text-slate-400">•</span><span>No puede ser igual a la contraseña actual</span></div>
        </div>
      </div>
      <label>
        <span class="text-sm font-medium text-slate-700">Repetir nueva contraseña</span>
        <input type="password" name="confirmPassword" value="${this.escapeHtml(initialConfirmPassword)}" class="w-full rounded-lg text-sm text-slate-900 ${this.fieldErrorClass('confirmPassword')}" />
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
    const fill = strengthWidget.querySelector('[data-role="password-fill"]');
    const items = strengthWidget.querySelectorAll('[data-role="password-rule"]');

    if (fill instanceof HTMLElement) {
      fill.style.width = `${complexity.score}%`;
      fill.style.background = this.profileStrengthColor(complexity.score);
    }

    items.forEach((item) => {
      const ruleName = item.dataset.rule;
      const ruleState = complexity.rules[ruleName];
      item.classList.toggle('text-emerald-700', ruleState === true);
      item.classList.toggle('text-red-700', ruleState === false);
      const icon = item.querySelector('[data-role="password-rule-icon"]');
      if (icon instanceof HTMLElement) {
        icon.classList.toggle('text-emerald-600', ruleState === true);
        icon.classList.toggle('text-red-600', ruleState === false);
        icon.classList.toggle('text-slate-400', ruleState !== true && ruleState !== false);
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
