import { DynamicTable } from '../modules/DynamicTable.js';
import { hashFromPage, userDetailPage } from '../../controllers/RouteMapController.js';
import { component } from '../modules/ComponentFactory.js';

export class UserManager {
	#container;
	#api;
	#users;
	#message;
	#messageType;
	#onViewUser;

	constructor(container, options = {}) {
		this.#container = this.resolveContainer(container);
		this.#api = null;
		this.#users = [];
		this.#message = null;
		this.#messageType = null;
		this.#onViewUser = null;

		if (Array.isArray(options)) {
			this.#users = options;
			this.#render();
			return;
		}

		this.#api = options.api ?? null;
		this.#users = Array.isArray(options.users) ? options.users : [];
		this.#onViewUser = typeof options.onViewUser === 'function' ? options.onViewUser : null;

		this.#render();
	}

	async init() {
		await this.#loadUsers();
		this.#render();
	}

	async #loadUsers() {
		if (!this.#canCallApi()) {
			return;
		}

		try {
			const response = await this.#api.get('/users');
			const rows = response?.data;
			this.#users = Array.isArray(rows) ? rows : [];
		} catch (error) {
			this.#users = [];
			this.#setMessage(error?.message ?? 'No se pudo cargar la lista de usuarios.', 'error');
		}
	}

	#render() {
		this.#container.replaceChildren();

		const page = component.create('page', { dataRole: 'user-manager-page' });
		page.appendChild(component.create('pageHeader', {
			title: 'Gestión de usuarios',
			subtitle: 'Administra usuarios, roles, accesos y recuperación de contraseña.',
		}));

		if (this.#message !== null) {
			const feedback = component.create('alert', {
				type: this.#messageType === 'error' ? 'error' : 'success',
				message: this.#message,
			});
			feedback.dataset.role = 'user-manager-feedback';
			page.appendChild(feedback);
		}

		const card = component.create('div');
		card.className = 'rounded-2xl border border-slate-200 bg-white p-4 shadow-panel';

		if (this.#users.length === 0) {
			card.appendChild(component.create('emptyState', {
				title: 'Sin usuarios',
				description: 'No hay usuarios disponibles todavía.',
			}));
		} else {
			card.appendChild(this.#renderTable());
		}

		page.appendChild(card);
		this.#container.appendChild(page);
	}

	#renderTable() {
		const wrap = component.create('div');

		const rows = this.#users.map((user) => ({
			__raw: user,
			name: this.#displayName(user),
			email: this.#displayEmail(user),
			roles: this.#displayRoles(user),
			created_at: this.#formatDate(user?.created_at),
		}));

		const schema = {
			fields: [
				{ name: 'name', label: 'Nombre' },
				{ name: 'email', label: 'Email' },
				{ name: 'roles', label: 'Roles' },
				{ name: 'created_at', label: 'Fecha alta' },
			],
		};

		const table = new DynamicTable(rows, schema, wrap, {
			extraColumns: [
				{
					label: 'Avatar',
					position: 'start',
					renderCell: (row) => this.#avatarContent(row.__raw),
				},
				{
					label: 'Acciones',
					renderCell: (row) => this.#buildEditAction(row.__raw),
				},
			],
		});

		table.render();
		return wrap;
	}

	#avatarContent(user) {
		const avatar = component.create('span');
		avatar.className = 'inline-flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-xs font-bold text-brand-700';

		if (typeof user?.avatar === 'string' && user.avatar !== '') {
			const image = component.create('img');
			image.src = user.avatar;
			image.alt = `Avatar de ${this.#displayName(user)}`;
			avatar.appendChild(image);
		} else {
			avatar.textContent = this.#getInitials(this.#displayName(user), this.#displayEmail(user));
		}

		return avatar;
	}

	#buildEditAction(user) {
		const actions = component.create('div');
		actions.className = 'flex flex-wrap gap-1.5';

		const view = DynamicTable.buildActionButton({
			label: 'Editar',
			icon: 'fa-pencil',
			tone: 'sky',
			onClick: () => {
				this.#openUserDetail(user);
			},
		});

		actions.appendChild(view);
		return actions;
	}

	#openUserDetail(user) {
		const id = this.#getUserId(user);
		if (id === '') {
			return;
		}

		if (this.#onViewUser !== null) {
			this.#onViewUser(user);
			return;
		}

		if (typeof window !== 'undefined' && typeof window.location?.hash === 'string') {
			window.location.hash = hashFromPage(userDetailPage(id));
			return;
		}

		this.#setMessage('No se pudo navegar a la ficha del usuario.', 'error');
		this.#render();
	}

	#displayName(user) {
		if (typeof user?.name === 'string' && user.name.trim() !== '') {
			return user.name.trim();
		}

		return this.#displayEmail(user);
	}

	#displayEmail(user) {
		if (typeof user?.email === 'string' && user.email.trim() !== '') {
			return user.email.trim();
		}

		return 'Sin email';
	}

	#displayRoles(user) {
		const roles = this.#userRoles(user);
		if (roles.includes('admin')) {
			return 'Administrador';
		}

		return 'Usuario';
	}

	#userRoles(user) {
		return normalizeRoleList(user?.roles);
	}

	#formatDate(value) {
		if (typeof value !== 'string' || value.trim() === '') {
			return 'N/D';
		}

		const date = new Date(value);
		if (Number.isNaN(date.getTime())) {
			return 'N/D';
		}

		return date.toLocaleDateString('es-ES');
	}

	#getUserId(user) {
		if (typeof user?.id === 'string') {
			return user.id;
		}

		return '';
	}

	#getInitials(name, email) {
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

	#setMessage(message, type) {
		this.#message = message;
		this.#messageType = type;
	}

	#canCallApi() {
		return this.#api !== null && typeof this.#api.get === 'function' && typeof this.#api.put === 'function' && typeof this.#api.delete === 'function';
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

		throw new TypeError(`UserManager: container "${String(container)}" not found`);
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
