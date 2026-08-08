import { UserConfig } from './UserConfig.js';
import { component } from '../modules/ComponentFactory.js';

export class UserProfile extends UserConfig {
	/**
	 * @param {HTMLElement|string} container
	 * @param {Object|null} user
	 * @param {import('../../models/ApiClientModel.js').Api|undefined} api
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

		return [
			this.buildPasswordField('Contraseña actual', 'currentPassword', initialCurrentPassword, 'currentPassword'),
			this.buildPasswordField('Nueva contraseña', 'newPassword', initialNewPassword, 'newPassword'),
			this.buildPasswordStrengthWidget(),
			this.buildPasswordField('Repetir nueva contraseña', 'confirmPassword', initialConfirmPassword, 'confirmPassword'),
		];
	}

	buildPasswordField(labelText, fieldName, value, errorField) {
		const label = component.create('label');
		label.appendChild(component.create('span', {
			className: 'text-sm font-medium text-slate-700',
			text: labelText,
		}));

		const input = component.create('input', {
			attributes: {
				type: 'password',
				name: fieldName,
			},
			className: `w-full rounded-lg text-sm text-slate-900 ${this.fieldErrorClass(errorField)}`,
		});
		input.value = value;
		label.appendChild(input);

		const errorNode = this.createFieldErrorNode(errorField);
		if (errorNode instanceof Node) {
			label.appendChild(errorNode);
		}

		return label;
	}

	buildPasswordStrengthWidget() {
		const wrap = component.create('div', {
			className: 'grid gap-2',
			dataset: { passwordStrength: '' },
		});

		const meter = component.create('div', {
			className: 'h-2 overflow-hidden rounded-full bg-slate-200',
			dataset: { role: 'password-meter' },
		});
		const fill = component.create('div', {
			className: 'h-full w-0 bg-slate-400',
			dataset: { role: 'password-fill' },
		});
		meter.appendChild(fill);

		const rules = component.create('div', {
			className: 'grid gap-1 text-xs text-slate-600',
			dataset: { role: 'password-rules' },
		});

		const ruleDefs = [
			{ key: 'length', text: 'Más de 9 caracteres' },
			{ key: 'upper', text: 'Al menos una mayúscula' },
			{ key: 'lower', text: 'Al menos una minúscula' },
			{ key: 'symbol', text: 'Al menos un símbolo' },
			{ key: 'different', text: 'No puede ser igual a la contraseña actual' },
		];

		for (const rule of ruleDefs) {
			const row = component.create('div', {
				className: 'flex items-center gap-2',
				dataset: { role: 'password-rule', rule: rule.key },
			});
			const icon = component.create('span', {
				className: 'text-slate-400',
				text: '•',
				dataset: { role: 'password-rule-icon' },
			});
			const text = component.create('span', { text: rule.text });
			row.appendChild(icon);
			row.appendChild(text);
			rules.appendChild(row);
		}

		wrap.appendChild(meter);
		wrap.appendChild(rules);
		return wrap;
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
