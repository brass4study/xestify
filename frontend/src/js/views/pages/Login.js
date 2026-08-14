/**
 * Login.js - Page controller for authentication.
 */

import { Api, ApiError } from '../../models/ApiClientModel.js';
import { t } from '../../models/I18nModel.js';
import { UiResilienceService } from '../../services/UiResilienceService.js';
import { component } from '../modules/ComponentFactory.js';
import { InputPasswordComponent } from '../components/InputPassword.js';

const MIN_LOADING_MS = 400;
// El segmento antes del primer punto excluye "." explícitamente para que solo
// haya una forma de dividir la cadena (evita el backtracking cuadrático de dos
// cuantificadores sin límite que podían solaparse en el mismo carácter).
const EMAIL_PATTERN = /^[^\s@]+@[^\s@.]+\.[^\s@]+$/;
const INVALID_CREDENTIALS_MESSAGE = 'Las credenciales introducidas son inválidas, por favor vuelva a comprobarlas.';
const SESSION_EXPIRED_MESSAGE = 'Tu sesión ha caducado. Vuelve a iniciar sesión para continuar.';

// NOSONAR - credenciales fijas de los usuarios semilla (STORY 10.1), no un secreto
// real: solo se usan para los botones de acceso rápido con APP_DEBUG=true, y deben
// coincidir exactamente con UserSeeder.php.
const QUICK_ACCESS_ADMIN = { email: 'admin@xestify.local', password: 'admin123' }; // NOSONAR
const QUICK_ACCESS_USER = { email: 'usuario@xestify.local', password: 'usuario123' }; // NOSONAR

export class Login {
	#api;
	#container;
	#onSuccess;
	#isSubmitting = false;
	#appDebug = false;

	constructor(container, options = {}) {
		this.#container = this.resolveContainer(container);
		this.#api = (options.api !== null && options.api !== undefined && typeof options.api.post === 'function')
			? options.api
			: new Api();
		this.#onSuccess = typeof options.onSuccess === 'function' ? options.onSuccess : null;
		this.#appDebug = options.appDebug === true;

		this.render();

		if (options.sessionExpired === true) {
			this.setFeedback({ type: 'warning', message: SESSION_EXPIRED_MESSAGE });
		}
	}

	setAppDebug(enabled) {
		this.#appDebug = enabled === true;
		this.renderQuickAccess();
	}

	async submit() {
		if (this.#isSubmitting) {
			return;
		}

		this.#isSubmitting = true;
		this.clearFeedback();
		this.clearFieldErrors();

		const emailInput = this.#container.querySelector('[name="email"]');
		const passwordInput = this.#container.querySelector('[name="password"]');
		const email = emailInput instanceof HTMLInputElement ? emailInput.value.trim() : '';
		const password = passwordInput instanceof HTMLInputElement ? passwordInput.value : '';

		if (!this.validateBeforeSubmit(email, password, emailInput, passwordInput)) {
			this.#isSubmitting = false;
			return;
		}

		const startedAt = Date.now();
		this.setBusy(true);
		this.setFeedback({ type: 'loading', title: 'Iniciando sesión', message: 'Estamos verificando tus credenciales.' });

		try {
			const { data } = await this.#api.post('/auth/login', { email, password });
			await this.waitForMinimumLoading(startedAt);
			this.handleSubmitSuccess(data);
		} catch (err) {
			await this.waitForMinimumLoading(startedAt);
			this.#isSubmitting = false;
			this.setBusy(false);
			this.handleSubmitError(err);
		}
	}

	/**
	 * Validación síncrona previa al envío (campos requeridos + formato de email).
	 * Ya deja el mensaje/foco listos y devuelve false si el envío debe abortarse.
	 */
	validateBeforeSubmit(email, password, emailInput, passwordInput) {
		const emailMissing = email === '';
		const passwordMissing = password === '';

		if (emailMissing || passwordMissing) {
			if (emailMissing && emailInput instanceof HTMLInputElement) {
				emailInput.setError('Requerido.');
			}
			if (passwordMissing && passwordInput instanceof HTMLInputElement) {
				passwordInput.setError('Requerido.');
			}

			this.setFeedback({ type: 'warning', message: this.buildRequiredMessage(emailMissing, passwordMissing), shake: true });
			this.focusFirstInvalid();
			return false;
		}

		if (!EMAIL_PATTERN.test(email)) {
			if (emailInput instanceof HTMLInputElement) {
				emailInput.setError('Formato inválido.');
			}
			this.setFeedback({ type: 'warning', message: 'Introduce un email con formato válido.', shake: true });
			this.focusFirstInvalid();
			return false;
		}

		return true;
	}

	handleSubmitSuccess(data) {
		const accessToken = typeof data?.access_token === 'string' ? data.access_token : '';
		const userEmail = typeof data?.email === 'string' ? data.email : null;

		if (accessToken === '') {
			this.#isSubmitting = false;
			this.setBusy(false);
			this.setFeedback({ type: 'error', message: 'Respuesta de autenticación inválida.', shake: true });
			return;
		}

		// Se deja el estado "cargando" activo a propósito: el llamador va a
		// sustituir este contenedor por el dashboard en cuanto resuelva el perfil.
		if (this.#onSuccess !== null) {
			this.#onSuccess({ accessToken, email: userEmail });
		}
	}

	handleSubmitError(err) {
		if (err instanceof ApiError && Object.keys(err.details).length > 0) {
			this.applyFieldErrorDetails(err.details);
			this.setFeedback({
				type: 'warning',
				message: this.buildRequiredMessage(
					Array.isArray(err.details.email) && err.details.email.length > 0,
					Array.isArray(err.details.password) && err.details.password.length > 0
				),
				shake: true,
			});
			this.focusFirstInvalid();
			return;
		}

		if (err instanceof ApiError && err.code === 401) {
			this.setFeedback({ type: 'error', message: INVALID_CREDENTIALS_MESSAGE, shake: true });
			return;
		}

		const message = UiResilienceService.handleError(err, t('ui.error.generic', 'No se pudo iniciar sesión.'));
		this.setFeedback({ type: 'error', message, shake: true });
	}

	quickLogin(credentials) {
		if (this.#isSubmitting) {
			return;
		}

		const emailInput = this.#container.querySelector('[name="email"]');
		const passwordInput = this.#container.querySelector('[name="password"]');
		if (emailInput instanceof HTMLInputElement) {
			emailInput.value = credentials.email;
		}
		if (passwordInput instanceof HTMLInputElement) {
			passwordInput.value = credentials.password;
		}

		this.submit();
	}

	render() {
		this.#container.replaceChildren();

		// El contenedor recibido (PageLayout, plantilla "login") ya es un flex que
		// centra un único hijo — se apila wordmark+card+feedback dentro de un único
		// wrapper en vez de añadir clases grid al propio contenedor (rompería ese
		// layout al convivir flex+grid en el mismo elemento).
		const stack = component.create('div').setClassName('grid w-full max-w-lg justify-items-center gap-4');
		stack.setParent(this.#container);

		this.renderWordmark(stack);

		const page = component.create('page', {
			dataRole: 'login-card',
			children: component.create('div'),
		}).setClassName('w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-panel sm:p-8');

		component.create('typography', {
			as: 'h2',
			text: 'Iniciar sesión',
			size: 'xl',
			weight: 'semibold',
			color: 'slate-950',
			align: 'center',
		})
			.setData('role', 'login-title')
			.setParent(page);

		const form = component.create('form', {
			className: 'mt-2 grid gap-4',
			dataRole: 'login-form',
		});
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			this.submit();
		});

		const emailField = component.create('formField', {
			label: 'Email',
			name: 'email',
			input: component.create('inputEmail', { name: 'email', placeholder: 'tu@email.com' }),
		});
		const emailInput = emailField.querySelector('input');
		if (emailInput instanceof HTMLInputElement) {
			emailInput.autocomplete = 'email';
		}
		emailField.setParent(form);

		const passwordField = component.create('formField', {
			label: 'Password',
			name: 'password',
			input: InputPasswordComponent.createToggleable({ name: 'password', placeholder: '••••••••' }),
		});
		passwordField.setParent(form);

		component.create('button', {
			label: 'Entrar',
			icon: 'fa-right-to-bracket',
			variant: 'primary',
			dataRole: 'login-submit',
			type: 'submit',
		})
			.addClass('mt-2 w-full')
			.setParent(form);

		form.setParent(page);

		component.create('div').setData('role', 'login-quick-access').setVisible(false).setParent(page);

		page.setParent(stack);

		component.create('div')
			.setClassName('w-full')
			.setAttribute('aria-live', 'polite')
			.setVisible(false)
			.setData('role', 'login-feedback')
			.setParent(stack);

		this.renderQuickAccess();

		requestAnimationFrame(() => {
			const focusTarget = this.#container.querySelector('[name="email"]');
			if (focusTarget instanceof HTMLElement) {
				focusTarget.focus();
			}
		});
	}

	renderWordmark(parent) {
		const wordmark = component.create('div').setClassName('grid w-full justify-items-center text-center');

		component.create('brandLogo').setParent(wordmark);

		component.create('span', {
			className: 'text-base text-slate-500',
			text: 'La plataforma de gestión que se adapta a tu negocio',
		}).setParent(wordmark);

		wordmark.setParent(parent);
	}

	renderQuickAccess() {
		const container = this.#container.querySelector('[data-role="login-quick-access"]');
		if (container === null) {
			return;
		}

		container.replaceChildren();

		if (!this.#appDebug) {
			container.hidden = true;
			return;
		}

		container.hidden = false;
		container.className = 'mt-6 grid gap-3 border-t border-slate-200 pt-4';

		component.create('p', {
			className: 'text-center text-xs font-medium uppercase tracking-wide text-slate-400',
			text: 'Acceso rápido (modo desarrollo)',
		}).setParent(container);

		const actions = component.create('div').setClassName('grid grid-cols-2 gap-3').setParent(container);

		component.create('button', {
			label: 'Entrar como admin',
			icon: 'fa-user-shield',
			type: 'button',
			dataRole: 'login-quick-admin',
		})
			.setParent(actions)
			.addEventListener('click', () => this.quickLogin(QUICK_ACCESS_ADMIN));

		component.create('button', {
			label: 'Entrar como usuario',
			icon: 'fa-user',
			type: 'button',
			dataRole: 'login-quick-user',
		})
			.setParent(actions)
			.addEventListener('click', () => this.quickLogin(QUICK_ACCESS_USER));
	}

	buildRequiredMessage(emailMissing, passwordMissing) {
		if (emailMissing && passwordMissing) {
			return 'El email y la contraseña son obligatorios.';
		}
		if (emailMissing) {
			return 'El email es obligatorio.';
		}
		if (passwordMissing) {
			return 'La contraseña es obligatoria.';
		}
		return 'Revisa los datos introducidos.';
	}

	applyFieldErrorDetails(details) {
		const emailInput = this.#container.querySelector('[name="email"]');
		const passwordInput = this.#container.querySelector('[name="password"]');

		if (Array.isArray(details.email) && details.email.length > 0 && emailInput instanceof HTMLInputElement) {
			emailInput.setError('Requerido.');
		}
		if (Array.isArray(details.password) && details.password.length > 0 && passwordInput instanceof HTMLInputElement) {
			passwordInput.setError('Requerido.');
		}
	}

	clearFieldErrors() {
		const emailInput = this.#container.querySelector('[name="email"]');
		const passwordInput = this.#container.querySelector('[name="password"]');

		if (emailInput instanceof HTMLInputElement) {
			emailInput.setError();
		}
		if (passwordInput instanceof HTMLInputElement) {
			passwordInput.setError();
		}
	}

	focusFirstInvalid() {
		const invalid = this.#container.querySelector('[aria-invalid="true"]');
		if (invalid instanceof HTMLElement) {
			invalid.focus();
		}
	}

	setBusy(busy) {
		const button = this.#container.querySelector('[data-role="login-submit"]');
		const emailInput = this.#container.querySelector('[name="email"]');
		const passwordInput = this.#container.querySelector('[name="password"]');
		const toggle = this.#container.querySelector('[data-role="password-visibility-toggle"]');

		if (button instanceof HTMLButtonElement) {
			if (busy) {
				UiResilienceService.setButtonPending(button, 'Entrando...');
			} else {
				UiResilienceService.clearButtonPending(button, 'Entrar');
			}
		}
		if (emailInput instanceof HTMLInputElement) {
			emailInput.disabled = busy;
		}
		if (passwordInput instanceof HTMLInputElement) {
			passwordInput.disabled = busy;
		}
		if (toggle instanceof HTMLButtonElement) {
			toggle.disabled = busy;
		}
	}

	async waitForMinimumLoading(startedAt) {
		const elapsed = Date.now() - startedAt;
		if (elapsed < MIN_LOADING_MS) {
			await new Promise((resolve) => { setTimeout(resolve, MIN_LOADING_MS - elapsed); });
		}
	}

	setFeedback({ type = 'info', title = '', message = '', shake = false } = {}) {
		const zone = this.#container.querySelector('[data-role="login-feedback"]');
		if (zone === null) {
			return;
		}

		zone.replaceChildren();
		zone.hidden = false;

		const content = type === 'loading'
			? this.buildLoadingIndicator(title, message)
			: component.create('alert', { type, title, message, showIcon: true });
		content.setParent(zone);

		zone.classList.remove('login-feedback-shake');
		if (shake) {
			zone.getBoundingClientRect(); // fuerza reflow (llamada, no lectura de propiedad) para reiniciar la animación en envíos consecutivos
			zone.classList.add('login-feedback-shake');
		}
	}

	clearFeedback() {
		const zone = this.#container.querySelector('[data-role="login-feedback"]');
		if (zone === null) {
			return;
		}

		zone.hidden = true;
		zone.replaceChildren();
		zone.classList.remove('login-feedback-shake');
	}

	buildLoadingIndicator(title, message) {
		const block = component.create('div').setClassName('w-full rounded-md bg-slate-50 px-4 py-4');

		component.create('loader', { title, description: message }).setParent(block);

		return block;
	}

	resolveContainer(container) {
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
