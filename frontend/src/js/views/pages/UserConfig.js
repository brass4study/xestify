import { Api } from '../../models/ApiClientModel.js';
import { AppState } from '../../models/StateModel.js';
import { FormLayout } from '../layout/FormLayout.js';
import { component } from '../modules/ComponentFactory.js';

export class UserConfig {
	#container;
	#mode;
	#user;
	#api;
	#message;
	#messageType;
	#draftValues;
	#fieldErrors;
	#temporaryPassword;
	#currentUserId;
	#onBack;
	#onSaved;
	#onDeleted;
	#title;
	#subtitle;
	#shellLayout;

	/**
	 * @param {HTMLElement|string} container
	 * @param {{mode?: 'profile'|'admin', user?: Object|null, api?: Object, shellLayout?: import('../layout/ShellLayout.js').ShellLayout|null, currentUserId?: string, title?: string, subtitle?: string, onBack?: Function, onSaved?: Function, onDeleted?: Function}} options
	 */
	constructor(container, options = {}) {
		this.#container = this.resolveContainer(container);
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
		this.#shellLayout = options.shellLayout ?? null;

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

	createFieldErrorNode(fieldName) {
		const message = this.#fieldErrors[fieldName];
		if (typeof message !== 'string' || message === '') {
			return null;
		}

		return component.create('p', {
			className: 'mt-1 text-xs font-medium text-red-700',
			text: message,
		});
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
		return [];
	}

	attachAdditionalInteractions(form) {
		if (!(form instanceof HTMLFormElement)) {
			return;
		}
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
		const backAction = this.#onBack === null ? null : component.create('button', {
			label: 'Volver al listado',
			variant: 'secondary',
			dataRole: 'user-config-back',
			onClick: () => {
				this.#onBack();
			},
		});
		const layout = FormLayout.create(this.#container, {
			shell: this.#shellLayout,
		})
			.setTitle(this.#title)
			.setDescription(this.#subtitle);
		layout.setHeaderToolbar(backAction);
		layout.build();
		const panel = layout.getPanel();

		layout.setNotification(null);
		this.#mountFeedback(layout);

		const user = this.#resolveUser();
		if (user === null || typeof user !== 'object') {
			component.create('emptyState', {
				title: 'Usuario no encontrado',
				description: 'No se encontró el usuario solicitado.',
			}).setParent(panel);
			layout.setActions(null);
			return;
		}

		const form = component.create('formTag').setClassName('grid gap-4');
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			void this.#submitForm(form);
		});

		this.#buildFormNodes(form, user);
		const actionControls = this.#buildActions(layout, form);
		this.#attachFormInteractions(form, actionControls);
		this.attachAdditionalInteractions(form);

		form.setParent(panel);
	}

	#buildFormNodes(form, user) {
		const initialName = this.getDraftValue('name', this.#displayName(user));
		const initialEmail = this.getDraftValue('email', this.#displayEmail(user));
		const initialRole = this.getDraftValue('role', this.#primaryRole(user));
		const initialAvatar = this.getDraftValue('avatar', this.#stringValue(user?.avatar));
		const createdAt = this.#formatDateForInput(this.#resolveCreatedAt(user));
		const avatarImage = this.#stringValue(initialAvatar);

		form.replaceChildren();
		form.appendChild(this.#buildAvatarSection(initialName, initialEmail, avatarImage));
		form.appendChild(this.#buildInputField({
			label: 'Nombre',
			name: 'name',
			type: 'text',
			value: initialName,
			errorField: 'name',
		}));
		form.appendChild(this.#buildInputField({
			label: 'Email',
			name: 'email',
			type: 'email',
			value: initialEmail,
			errorField: 'email',
		}));

		if (this.showRoleField()) {
			form.appendChild(this.#buildRoleField(initialRole));
		}

		const additionalFields = this.renderAdditionalFields();
		if (Array.isArray(additionalFields)) {
			for (const fieldNode of additionalFields) {
				if (fieldNode instanceof Node) {
					form.appendChild(fieldNode);
				}
			}
		}

		form.appendChild(this.#buildInputField({
			label: 'Fecha alta',
			name: 'createdAt',
			type: 'date',
			value: createdAt,
			disabled: true,
			baseClassName: 'w-full rounded-lg border-slate-300 bg-slate-100 text-sm text-slate-500',
		}));

		const errorMessage = component.create('p', {
			className: 'text-sm text-red-700',
			hidden: true,
			dataset: { userconfigError: '' },
		});
		errorMessage.setParent(form);

		if (this.#temporaryPassword !== null) {
			form.appendChild(this.#temporaryPasswordNode(this.#temporaryPassword));
		}
	}

	#buildAvatarSection(initialName, initialEmail, avatarImage) {
		const section = component.create('div', {
			className: 'flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3',
		});

		const avatar = component.create('div', {
			className: 'inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-sm font-bold text-brand-700',
		});
		if (avatarImage !== '') {
			component.create('img', {
				attributes: { src: avatarImage, alt: 'Avatar actual' },
				className: 'h-full w-full object-cover',
			}).setParent(avatar);
		} else {
			avatar.setText(this.#getInitials(initialName, initialEmail));
		}

		const info = component.create('div', { className: 'grid gap-0.5' });
		component.create('p', {
			className: 'text-sm font-semibold text-slateui-950',
			text: 'Avatar',
		}).setParent(info);
		component.create('p', {
			className: 'text-xs text-slate-500',
			text: 'JPG/PNG hasta 2MB.',
		}).setParent(info);

		const actions = component.create('div', { className: 'inline-flex flex-wrap items-center gap-2' });
		const selectAvatarButton = component.create('button', {
			label: avatarImage === '' ? 'Subir avatar' : 'Cambiar avatar',
			variant: 'secondary',
			attributes: { type: 'button', 'data-userconfig-select-avatar': '' },
		});
		actions.append(selectAvatarButton);

		if (avatarImage !== '') {
			const removeAvatarButton = component.create('button', {
				label: 'Quitar avatar',
				variant: 'danger',
				attributes: { type: 'button', 'data-userconfig-remove-avatar': '' },
			});
			actions.append(removeAvatarButton);
		}

		const avatarFile = component.create('inputFile', {
			attributes: {
				accept: 'image/png,image/jpeg,image/webp',
			},
			dataset: { userconfigAvatarFile: '' },
			hidden: true,
		});

		const avatarValue = component.create('inputHidden', {
			name: 'avatar',
			value: avatarImage,
			dataset: { userconfigAvatarValue: '' },
		});

		avatar.setParent(section);
		info.setParent(section);
		actions.setParent(section);
		avatarFile.setParent(section);
		avatarValue.setParent(section);
		return section;
	}

	#buildInputField(options) {
		const label = component.create('label');
		component.create('span', {
			className: 'text-sm font-medium text-slate-700',
			text: options.label,
		}).setParent(label);

		const input = component.create('input', {
			attributes: {
				type: options.type,
				name: options.name,
			},
		})
			.setValue(options.value ?? '')
			.setClassName(options.baseClassName ?? `w-full rounded-lg ${this.fieldErrorClass(options.errorField)} text-sm text-slate-900`)
			.setParent(label);
		input.disabled = options.disabled === true;

		if (typeof options.errorField === 'string' && options.errorField !== '') {
			const errorNode = this.createFieldErrorNode(options.errorField);
			if (errorNode instanceof Node) {
				errorNode.setParent(label);
			}
		}

		return label;
	}

	#buildRoleField(initialRole) {
		const label = component.create('label');
		component.create('span', {
			className: 'text-sm font-medium text-slate-700',
			text: 'Rol',
		}).setParent(label);

		component.create('inputSelect', {
			name: 'role',
			value: initialRole,
			options: [
				{ value: 'admin', label: 'Administrador' },
				{ value: 'user', label: 'Usuario' },
			],
		})
			.setClassName('w-full rounded-lg border-slate-300 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500')
			.setParent(label);
		return label;
	}

	#buildActions(layout, form) {
		const saveButton = component.create('button', {
			label: 'Guardar cambios',
			variant: 'primary',
			size: 'md',
			onClick: () => {
				form.requestSubmit();
			},
		});
		layout.addAction(saveButton);
		let resetButton = null;
		let deleteButton = null;

		if (this.showResetButton()) {
			resetButton = component.create('button', {
				label: 'Reset password',
				variant: 'secondary',
				size: 'md',
				attributes: { type: 'button', 'data-userconfig-reset': '' },
			});
			layout.addAction(resetButton);
		}

		if (this.showDeleteButton()) {
			deleteButton = component.create('button', {
				label: 'Borrar usuario',
				variant: 'danger',
				size: 'md',
				attributes: { type: 'button', 'data-userconfig-delete': '' },
			});
			layout.addAction(deleteButton);
		}
		return { resetButton, deleteButton };
	}

	#attachFormInteractions(form, actionControls) {
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

		const { resetButton, deleteButton } = actionControls;
		if (resetButton instanceof HTMLButtonElement) {
			resetButton.addEventListener('click', () => {
				void this.#resetPassword(form);
			});
		}

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

	#mountFeedback(layout) {
		if (this.#message === null) {
			return;
		}

		const type = this.#messageType === 'error' ? 'error' : 'success';
		const banner = component.create('alert', {
			type,
			message: this.#message,
		}).setData('role', 'user-config-feedback').setData('type', type);
		layout.setNotification(banner);
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

	#temporaryPasswordNode(temporaryPassword) {
		const wrap = component.create('div', {
			className: 'flex flex-col gap-2 sm:flex-row sm:items-center',
		});

		const input = component.create('input', {
			attributes: {
				type: 'text',
				readonly: 'readonly',
			},
            value: temporaryPassword,
			className: 'w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-700'
		});

		const copy = component.create('button', {
			label: 'Copiar',
			variant: 'secondary',
			size: 'sm',
			attributes: { type: 'button', 'data-userconfig-copy-password': '' },
		});

		input.setParent(wrap);
		wrap.append(copy);
		return wrap;
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
		return normalizeRoleList(user?.roles);
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

		throw new TypeError(`UserConfig: container "${String(container)}" not found`);
	}
}

	function normalizeRoleList(rawRoles) {
		if (Array.isArray(rawRoles)) {
			return rawRoles
				.filter((role) => typeof role === 'string' && role.trim() !== '')
				.map((role) => role.trim());
		}

		if (typeof rawRoles === 'string') {
			const trimmed = rawRoles.trim();
			if (trimmed === '') {
				return [];
			}

			try {
				const parsed = JSON.parse(trimmed);
				if (Array.isArray(parsed)) {
					return normalizeRoleList(parsed);
				}
			} catch {
				// Fall back to comma-separated or single role formats.
			}

			if (trimmed.includes(',')) {
				return trimmed
					.split(',')
					.map((role) => role.trim())
					.filter((role) => role !== '');
			}

			return [trimmed];
		}

		return [];
	}
