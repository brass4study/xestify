/**
 * Login.js - Page controller for authentication.
 */

import { Api, ApiError } from '../../models/ApiClientModel.js';
import { component } from '../modules/ComponentFactory.js';

export class Login {
	#api;
	#container;
	#onSuccess;

	constructor(container, options = {}) {
		this.#container = this.resolveContainer(container);
		this.#api = (options.api !== null && options.api !== undefined && typeof options.api.post === 'function')
			? options.api
			: new Api();
		this.#onSuccess = typeof options.onSuccess === 'function' ? options.onSuccess : null;

		this.render();
	}

	async submit() {
		this.clearErrors();

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
			this.showFieldErrors(validationErrors);
			return;
		}

		this.setLoading(true);

		try {
			const { data } = await this.#api.post('/auth/login', { email, password });
			const accessToken = typeof data?.access_token === 'string' ? data.access_token : '';
			const userEmail = typeof data?.email === 'string' ? data.email : null;

			if (accessToken === '') {
				this.showGlobalError('Respuesta de autenticacion invalida.');
				return;
			}

			if (this.#onSuccess !== null) {
				this.#onSuccess({ accessToken, email: userEmail });
			}
		} catch (err) {
			if (err instanceof ApiError && Object.keys(err.details).length > 0) {
				this.showFieldErrors(err.details);
			} else if (err instanceof ApiError) {
				this.showGlobalError(err.message);
			} else {
				this.showGlobalError('Error desconocido');
			}
		} finally {
			this.setLoading(false);
		}
	}

	render() {
		this.#container.replaceChildren();

		const page = component.create('page', {
			dataRole: 'login-card',
			children: component.create('div'),
		});
		page.className = 'mx-auto mt-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-panel sm:mt-16 sm:p-8';

		const title = component.create('typography', {
			as: 'h2',
			text: 'Iniciar sesión',
			size: 'xl',
			weight: 'semibold',
			color: 'slate-950',
		});
		title.dataset.role = 'login-title';
		page.appendChild(title);

		const subtitle = component.create('typography', {
			text: 'Accede a tu espacio de trabajo para gestionar entidades y plugins.',
			size: 'sm',
			color: 'slate-500',
		});
		subtitle.className = 'mt-2';
		page.appendChild(subtitle);

		const banner = component.create('alert', {
			type: 'error',
			message: '',
		});
		banner.dataset.role = 'login-error';
		banner.hidden = true;
		page.appendChild(banner);

		const form = component.create('form', {
			className: 'mt-6 grid gap-4',
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
		form.appendChild(emailField);

		const passwordField = component.create('formField', {
			label: 'Password',
			name: 'password',
			input: component.create('inputPassword', { name: 'password', placeholder: '••••••••' }),
		});
		form.appendChild(passwordField);

		const submit = component.create('button', {
			label: 'Entrar',
			variant: 'primary',
			dataRole: 'login-submit',
			type: 'submit',
		});
		submit.className = 'mt-2 inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-300 disabled:cursor-not-allowed disabled:opacity-60';
		form.appendChild(submit);

		page.appendChild(form);
		this.#container.appendChild(page);
	}

	showFieldErrors(errors) {
		const form = this.#container.querySelector('[data-role="login-form"]');
		if (form === null) {
			return;
		}

		for (const [fieldName, messages] of Object.entries(errors)) {
			const input = form.querySelector(`[name="${fieldName}"]`);
			const msgList = Array.isArray(messages) ? messages : [String(messages)];

			const errorEl = component.create('ul', {
				className: 'mt-1 list-disc pl-5 text-xs text-red-700',
				dataset: { role: 'login-field-errors', field: fieldName },
			});

			for (const msg of msgList) {
				const li = component.create('li', { text: msg });
				errorEl.appendChild(li);
			}

			if (input !== null && input.parentElement !== null) {
				input.parentElement.appendChild(errorEl);
			} else {
				form.appendChild(errorEl);
			}
		}
	}

	showGlobalError(message) {
		const banner = this.#container.querySelector('[data-role="login-error"]');
		if (banner !== null) {
			banner.textContent = message;
			banner.hidden = false;
		}
	}

	clearErrors() {
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

	setLoading(loading) {
		const button = this.#container.querySelector('[data-role="login-submit"]');
		if (button instanceof HTMLButtonElement) {
			button.disabled = loading;
			button.textContent = loading ? 'Entrando...' : 'Entrar';
		}
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
