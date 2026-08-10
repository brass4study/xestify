import { UserConfig } from './UserConfig.js';
import { component } from '../modules/ComponentFactory.js';
import { evaluatePasswordStrength } from '../components/PasswordStrength.js';

const PROFILE_PASSWORD_RULES = Object.freeze([
	{ key: 'length', label: 'Más de 9 caracteres', missing: 'más de 9 caracteres', test: (value) => value.length > 9 },
	{ key: 'upper', label: 'Al menos una mayúscula', missing: 'una mayúscula', test: (value) => /[A-Z]/.test(value) },
	{ key: 'lower', label: 'Al menos una minúscula', missing: 'una minúscula', test: (value) => /[a-z]/.test(value) },
	{ key: 'symbol', label: 'Al menos un símbolo', missing: 'un símbolo', test: (value) => /[^A-Za-z0-9]/.test(value) },
	{
		key: 'different',
		label: 'No puede ser igual a la contraseña actual',
		missing: 'una contraseña distinta a la actual',
		test: (value, context) => value !== '' && value !== context.currentPassword,
	},
]);

export class UserProfile extends UserConfig {
	/**
	 * @param {HTMLElement|string} container
	 * @param {Object|null} user
	 * @param {import('../../models/ApiClientModel.js').Api|undefined} api
	 * @param {Object} options
	 */
	constructor(container, user = null, api = undefined, options = {}) {
		super(container, {
			mode: 'profile',
			user,
			api,
			title: 'Mi perfil',
			subtitle: 'Actualiza tus datos personales y tu contraseña.',
			...options,
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
			this.buildPasswordStrengthWidget(initialNewPassword),
			this.buildPasswordField('Repetir nueva contraseña', 'confirmPassword', initialConfirmPassword, 'confirmPassword'),
		];
	}

	buildPasswordField(labelText, fieldName, value, errorField) {
		const label = component.create('label');
		component.create('span', {
			className: 'text-sm font-medium text-slate-700',
			text: labelText,
		}).setParent(label);

		component.create('input', {
			attributes: {
				type: 'password',
				name: fieldName,
			},
			className: `w-full rounded-lg text-sm text-slate-900 ${this.fieldErrorClass(errorField)}`,
		})
			.setValue(value)
			.setParent(label);

		const errorNode = this.createFieldErrorNode(errorField);
		if (errorNode instanceof Node) {
			errorNode.setParent(label);
		}

		return label;
	}

	buildPasswordStrengthWidget(initialValue = '') {
		return component.create('passwordStrength', {
			rules: PROFILE_PASSWORD_RULES,
			value: initialValue,
		});
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
			const updateStrength = (showWidget = false) => {
				const current = currentPasswordInput instanceof HTMLInputElement ? currentPasswordInput.value : '';
				strengthWidget.update({
					value: newPasswordInput.value,
					context: { currentPassword: current },
					visible: showWidget,
				});
			};

			updateStrength();
			newPasswordInput.addEventListener('input', () => updateStrength(true));
			if (currentPasswordInput instanceof HTMLInputElement) {
				currentPasswordInput.addEventListener('input', () => updateStrength(false));
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

			const complexity = evaluatePasswordStrength(
				PROFILE_PASSWORD_RULES,
				newPassword,
				{ currentPassword }
			);
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

	profilePasswordComplexityMessage(complexity) {
		const missing = complexity.failedRules.map((rule) => rule.missing ?? rule.label);
		return `La nueva contraseña debe incluir ${missing.join(', ')}.`;
	}
}
