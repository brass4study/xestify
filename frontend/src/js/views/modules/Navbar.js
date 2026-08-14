import { component } from './ComponentFactory.js';
import { UserMenu } from './UserMenu.js';
import { hashFromPage } from '../../controllers/RouteMapController.js';

export class Navbar {
	#container;
	#userContainer;
	#userEmail;
	#userName;
	#avatar;
	#roles;
	#onLogout;
	#onNavigate;
	#entities;
	#activePage;
	#canManagePlugins;
	#showUserMenu;
	#showBrand;
	#orientation;
	#linksContainer;
	#linksOrientation;

	constructor(container, options = {}) {
		this.#container = this.resolveContainer(container);
		this.#userContainer = this.resolveContainer(options.userContainer);
		this.#userEmail = typeof options.userEmail === 'string' ? options.userEmail : null;
		this.#userName = typeof options.userName === 'string' ? options.userName : null;
		this.#avatar = typeof options.avatar === 'string' ? options.avatar : null;
		this.#roles = Array.isArray(options.roles) ? options.roles : [];
		this.#entities = Array.isArray(options.entities) ? [...options.entities] : [];
		this.#linksContainer = this.resolveOptionalContainer(options.linksContainer);
		this.#linksOrientation = options.linksOrientation === 'side' ? 'side' : 'top';
		this.#activePage = typeof options.currentPage === 'string' ? options.currentPage : '';
		this.#canManagePlugins = Boolean(options.canManagePlugins);
		this.#showUserMenu = options.showUserMenu !== false;
		this.#showBrand = options.showBrand !== false;
		this.#orientation = options.orientation === 'top' ? 'top' : 'side';
		this.#onLogout = typeof options.onLogout === 'function' ? options.onLogout : null;
		this.#onNavigate = typeof options.onNavigate === 'function' ? options.onNavigate : null;

		this.render();
	}

	setShowBrand(showBrand) {
		const normalized = Boolean(showBrand);
		if (this.#showBrand === normalized) {
			return;
		}

		this.#showBrand = normalized;
		this.render();
	}

	setLayoutTargets(options = {}) {
		const previousContainer = this.#container;
		const previousUserContainer = this.#userContainer;
		const previousLinksContainer = this.#linksContainer;

		if (options.container !== undefined) {
			this.#container = this.resolveContainer(options.container);
		}
		if (options.userContainer !== undefined) {
			this.#userContainer = this.resolveContainer(options.userContainer);
		}
		if (options.linksContainer !== undefined) {
			this.#linksContainer = this.resolveOptionalContainer(options.linksContainer);
		}
		if (options.linksOrientation !== undefined) {
			this.#linksOrientation = options.linksOrientation === 'side' ? 'side' : 'top';
		}
		if (options.orientation !== undefined) {
			this.#orientation = options.orientation === 'top' ? 'top' : 'side';
		}

		if (previousContainer !== this.#container) {
			previousContainer.replaceChildren();
		}
		if (previousUserContainer !== this.#userContainer) {
			previousUserContainer.replaceChildren();
		}
		if (previousLinksContainer instanceof HTMLElement && previousLinksContainer !== this.#linksContainer) {
			previousLinksContainer.replaceChildren();
		}

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

	setOrientation(orientation) {
		const normalized = orientation === 'top' ? 'top' : 'side';
		if (this.#orientation === normalized) {
			return;
		}

		this.#orientation = normalized;
		this.render();
	}

	renderUserMenu() {
		if (!this.#showUserMenu) {
			return;
		}

		const userEl = this.#userContainer.querySelector('[data-role="navbar-user"]');
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
		if (this.#showUserMenu) {
			this.#userContainer.replaceChildren();
		}

		const navClassName = this.#orientation === 'side'
			? 'flex min-w-0 flex-col items-stretch gap-3'
			: 'flex min-w-0 flex-wrap items-center gap-3 text-white';

		const nav = component.create('nav', {
			className: navClassName,
			dataset: { role: 'navbar' },
			attributes: { 'aria-label': 'Navegación principal' },
		});

		if (this.#showBrand) {
			const brand = component.create('span', {
				className: 'pr-2',
				dataset: { role: 'navbar-brand' },
			});
			component.create('logo').setParent(brand);
			brand.setParent(nav);
		}

		const linksOrientation = this.#linksOrientation ?? this.#orientation;
		const linksClassName = linksOrientation === 'side'
			? 'order-1 flex w-full flex-col gap-1.5 pt-0'
			: 'order-3 flex w-full flex-wrap gap-1.5 pt-1 sm:order-2 sm:w-auto sm:pt-0';

		const links = component.create('ul', {
			className: linksClassName,
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

		links.setParent(this.#linksContainer ?? nav);

		if (this.#showUserMenu) {
			component.create('div', {
				className: 'relative min-w-0',
				dataset: { role: 'navbar-user' },
			}).setParent(this.#userContainer);
		}
		nav.setParent(this.#container);
		if (this.#showUserMenu) {
			this.renderUserMenu();
		}

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
			attributes: { href: hashFromPage(page) || '#' },
		});
		a.addEventListener('click', (event) => {
			if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
				return;
			}

			event.preventDefault();
			this.setActive(page);
			if (this.#onNavigate !== null) {
				this.#onNavigate(page);
			}
		});
		a.setParent(li);
		return li;
	}

	setActive(page) {
		this.#activePage = page;
		const searchRoot = this.#linksContainer instanceof HTMLElement ? this.#linksContainer : this.#container;
		const links = searchRoot.querySelectorAll('[data-role="navbar-link"]');
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

	resolveOptionalContainer(container) {
		if (container === undefined || container === null) {
			return null;
		}

		return this.resolveContainer(container);
	}
}
