import { component } from './ComponentFactory.js';
import { UserMenu } from './UserMenu.js';

export class Navbar {
	#container;
	#userEmail;
	#userName;
	#avatar;
	#roles;
	#onLogout;
	#onNavigate;
	#entities;
	#activePage;
	#canManagePlugins;

	constructor(container, options = {}) {
		this.#container = this.resolveContainer(container);
		this.#userEmail = typeof options.userEmail === 'string' ? options.userEmail : null;
		this.#userName = typeof options.userName === 'string' ? options.userName : null;
		this.#avatar = typeof options.avatar === 'string' ? options.avatar : null;
		this.#roles = Array.isArray(options.roles) ? options.roles : [];
		this.#entities = Array.isArray(options.entities) ? [...options.entities] : [];
		this.#activePage = typeof options.currentPage === 'string' ? options.currentPage : '';
		this.#canManagePlugins = Boolean(options.canManagePlugins);
		this.#onLogout = typeof options.onLogout === 'function' ? options.onLogout : null;
		this.#onNavigate = typeof options.onNavigate === 'function' ? options.onNavigate : null;

		this.render();
	}

	setUserEmail(email) {
		this.#userEmail = typeof email === 'string' ? email : null;
		this.renderUserMenu();
	}

	setUserName(name) {
		this.#userName = typeof name === 'string' ? name : null;
		this.renderUserMenu();
	}

	setAvatar(avatar) {
		this.#avatar = typeof avatar === 'string' ? avatar : null;
		this.renderUserMenu();
	}

	setRoles(roles) {
		this.#roles = Array.isArray(roles) ? roles : [];
		this.renderUserMenu();
	}

	renderUserMenu() {
		const userEl = this.#container.querySelector('[data-role="navbar-user"]');
		if (userEl === null) {
			return;
		}

		const hasIdentity = this.#userEmail !== null || this.#userName !== null;
		userEl.hidden = !hasIdentity;
		if (!hasIdentity) {
			userEl.replaceChildren();
			return;
		}

		userEl.replaceChildren();
		new UserMenu(userEl, {
			name: this.#userName,
			email: this.#userEmail,
			avatar: this.#avatar,
			roles: this.#roles,
			onSelect: (action) => {
				if (action === 'logout' && this.#onLogout !== null) {
					this.#onLogout();
					return;
				}
				if (action === 'profile' && this.#onNavigate !== null) {
					this.#onNavigate('profile');
					return;
				}
				if (action === 'users' && this.#onNavigate !== null) {
					this.#onNavigate('users');
				}
			},
		});
	}

	setEntities(entities) {
		this.#entities = Array.isArray(entities) ? [...entities] : [];
		this.render();
	}

	render() {
		this.#container.replaceChildren();

		const nav = component.create('nav', {
			className: 'flex flex-wrap items-center gap-3 border border-brand-200 bg-gradient-to-r from-brand-700 via-brand-600 to-brand-500 px-4 py-3 text-white',
			dataset: { role: 'navbar' },
			attributes: { 'aria-label': 'Navegación principal' },
		});

		const brand = component.create('span', {
			className: 'pr-2 text-xl font-semibold tracking-tight',
			dataset: { role: 'navbar-brand' },
			text: 'Xestify',
		});
		nav.appendChild(brand);

		const links = component.create('ul', {
			className: 'order-3 flex w-full flex-wrap gap-1.5 pt-1 sm:order-2 sm:w-auto sm:pt-0',
			dataset: { role: 'navbar-links' },
		});

		for (const entity of this.#entities) {
			const slug = typeof entity?.slug === 'string' ? entity.slug : '';
			if (slug === '') {
				continue;
			}
			const label = typeof entity?.label === 'string' ? entity.label : slug;
			links.appendChild(this.makeNavItem(`entity:${slug}`, label));
		}

		if (this.#canManagePlugins) {
			links.appendChild(this.makeNavItem('plugins', 'Plugins'));
		}

		nav.appendChild(links);

		const right = component.create('div', {
			className: 'order-2 ml-auto flex items-center gap-2 sm:order-3',
			dataset: { role: 'navbar-right' },
		});

		const userEl = component.create('div', {
			className: 'relative min-w-0',
			dataset: { role: 'navbar-user' },
		});
		right.appendChild(userEl);

		nav.appendChild(right);
		this.#container.appendChild(nav);
		this.renderUserMenu();

		if (this.#activePage !== '') {
			this.setActive(this.#activePage);
		}
	}

	makeNavItem(page, label) {
		const li = component.create('li');
		const a = component.create('a', {
			className: 'inline-flex items-center rounded-md px-3 py-1.5 text-sm font-medium text-white/85 transition hover:bg-white/15 hover:text-white',
			dataset: { role: 'navbar-link', page },
			text: label,
			attributes: { href: '#' },
		});
		a.addEventListener('click', (event) => {
			event.preventDefault();
			this.setActive(page);
			if (this.#onNavigate !== null) {
				this.#onNavigate(page);
			}
		});
		li.appendChild(a);
		return li;
	}

	setActive(page) {
		this.#activePage = page;
		const links = this.#container.querySelectorAll('[data-role="navbar-link"]');
		for (const link of links) {
			if (link instanceof HTMLElement) {
				const isActive = link.dataset.page === page;
				link.classList.toggle('bg-white/20', isActive);
				link.classList.toggle('text-white', isActive);
				link.setAttribute('aria-current', isActive ? 'page' : 'false');
			}
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

		throw new TypeError(`Navbar: container "${String(container)}" not found`);
	}
}
