import { component } from './ComponentFactory.js';

export class Modal {
	#host;
	#overlay;
	#dialog;
	#titleEl;
	#contentEl;
	#isOpen = false;
	#onKeyDown;

	constructor(container = document.body, options = {}) {
		this.#host = this.resolveContainer(container);

		this.#overlay = component.create('modal', {
			title: options.title ?? 'Mensaje',
			content: options.content ?? '',
			onClose: () => this.close(),
		})
			.setVisible(false)
			.addClass('hidden')
			.setData('role', 'modal-overlay');

		this.#dialog = this.#overlay.querySelector('[data-role="ui-modal-dialog"]');
		if (!(this.#dialog instanceof HTMLElement)) {
			throw new TypeError('Modal dialog node not found');
		}
		this.#dialog
			.setData('role', 'modal-dialog')
			.setAttributes({ role: 'dialog', 'aria-modal': 'true' });

		this.#titleEl = this.#overlay.querySelector('[data-role="ui-modal-title"]');
		if (!(this.#titleEl instanceof HTMLElement)) {
			throw new TypeError('Modal title node not found');
		}
		this.#titleEl.setData('role', 'modal-title');

		this.#contentEl = this.#overlay.querySelector('[data-role="ui-modal-content"]');
		if (!(this.#contentEl instanceof HTMLElement)) {
			throw new TypeError('Modal content node not found');
		}
		this.#contentEl.setData('role', 'modal-content');

		this.setContent(options.content ?? '');

		this.#overlay.addEventListener('click', (event) => {
			if (event.target === this.#overlay) {
				this.close();
			}
		});

		this.#onKeyDown = (event) => {
			if (event.key === 'Escape') {
				this.close();
			}
		};
	}

	show() {
		if (!this.#overlay.isConnected) {
			this.#host.appendChild(this.#overlay);
		}

		this.#overlay.hidden = false;
		this.#overlay.dataset.open = 'true';
		this.#overlay.classList.remove('hidden');
		this.#overlay.classList.add('flex');
		document.addEventListener('keydown', this.#onKeyDown);
		this.#isOpen = true;
	}

	close() {
		this.#overlay.hidden = true;
		delete this.#overlay.dataset.open;
		this.#overlay.classList.add('hidden');
		this.#overlay.classList.remove('flex');
		document.removeEventListener('keydown', this.#onKeyDown);
		this.#isOpen = false;
	}

	setContent(content) {
		this.#contentEl.replaceChildren();

		if (content instanceof HTMLElement) {
			this.#contentEl.appendChild(content);
			return;
		}

		this.#contentEl.textContent = String(content);
	}

	setTitle(title) {
		this.#titleEl.textContent = title;
	}

	isOpen() {
		return this.#isOpen;
	}

	resolveContainer(container) {
		if (container instanceof HTMLElement) {
			return container;
		}

		if (typeof container === 'string') {
			const found = document.querySelector(container);
			if (found instanceof HTMLElement) {
				return found;
			}
		}

		throw new TypeError(`Modal container "${String(container)}" not found`);
	}
}
