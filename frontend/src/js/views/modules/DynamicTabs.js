import { component } from './ComponentFactory.js';

export class DynamicTabs {
	#container;
	#tabBarContainer;
	#externalTabBar = null;
	#tabs = [];
	#activeId = null;
	#root = null;
	#rendered = false;

	constructor(container, options = {}) {
		this.#container = typeof container === 'string'
			? document.querySelector(container)
			: container;

		if (!this.#container) {
			throw new Error('DynamicTabs: container not found');
		}

		this.#tabBarContainer = this.resolveTabBarContainer(options.tabBarContainer);

		const initialTabs = options.tabs ?? [];
		for (const tab of initialTabs) {
			this.validateTab(tab);
			this.#tabs.push(tab);
		}
	}

	registerTab(tab) {
		this.validateTab(tab);
		const exists = this.#tabs.some((t) => t.id === tab.id);
		if (exists) {
			return;
		}
		this.#tabs.push(tab);
		if (this.#rendered && this.#root !== null) {
			this.#root.addTab({ id: tab.id, label: tab.label });
		}
	}

	render() {
		if (this.#rendered) {
			return;
		}

		this.#rendered = true;
		const initialId = this.resolveInitialTab();
		if (initialId) {
			this.#activeId = initialId;
		}
		this.renderTabs();
	}

	setActiveTab(id) {
		const exists = this.#tabs.some((tab) => tab.id === id);
		if (!exists) {
			return;
		}

		this.#activeId = id;

		if (this.#rendered && this.#root !== null) {
			this.#root.setActiveTab(id, false);
		}
	}

	getActiveTab() {
		return this.#activeId;
	}

	destroy() {
		if (this.#externalTabBar !== null) {
			this.#externalTabBar.remove();
			this.#externalTabBar = null;
		}

		if (this.#root) {
			this.#root.remove();
			this.#root = null;
		}
		this.#rendered = false;
		this.#activeId = null;
	}

	renderTabs() {
		const activeId = this.#activeId ?? this.resolveInitialTab();
		this.#root = component.create('tabs', {
			tabs: this.#tabs.map((tab) => ({ id: tab.id, label: tab.label })),
			activeId,
			onChange: (id) => {
				this.#activeId = id;
			},
			renderContent: (id) => this.#tabs.find((tab) => tab.id === id)?.content() ?? '',
		});
		this.#container.appendChild(this.#root);
		this.mountExternalTabBar();
		if (activeId !== null) {
			this.#root.setActiveTab(activeId, false);
		}
	}

	mountExternalTabBar() {
		if (!(this.#tabBarContainer instanceof HTMLElement) || this.#root === null) {
			return;
		}

		const tabBar = this.#root.querySelector('[data-role="tabs-bar"]');
		if (!(tabBar instanceof HTMLElement)) {
			return;
		}

		tabBar.classList.remove('mb-4');
		tabBar.classList.add('mb-0', 'w-full');
		this.#tabBarContainer.replaceChildren(tabBar);
		this.#externalTabBar = tabBar;
	}

	resolveTabBarContainer(containerOption) {
		if (containerOption instanceof HTMLElement) {
			return containerOption;
		}

		if (typeof containerOption === 'string') {
			const found = document.querySelector(containerOption);
			if (found instanceof HTMLElement) {
				return found;
			}
		}

		return null;
	}

	resolveInitialTab() {
		if (this.#tabs.length === 0) {
			return null;
		}

		if (typeof this.#activeId === 'string' && this.#activeId !== '') {
			const found = this.#tabs.find((t) => t.id === this.#activeId);
			if (found !== undefined) {
				return found.id;
			}
		}

		return this.#tabs[0].id;
	}

	validateTab(tab) {
		if (!tab || typeof tab.id !== 'string' || !tab.id) {
			throw new Error('DynamicTabs: tab must have a non-empty string id');
		}
		if (typeof tab.label !== 'string' || !tab.label) {
			throw new Error('DynamicTabs: tab must have a non-empty string label');
		}
		if (typeof tab.content !== 'function') {
			throw new TypeError('DynamicTabs: tab.content must be a function');
		}
	}
}
