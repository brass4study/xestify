import { component } from './ComponentFactory.js';

export class Modal {
	#host;
	#overlay;
	#dialog;
	#titleEl;
	#contentEl;
	#isOpen = false;
	#onKeyDown;
	#returnFocusElement = null;
	#focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

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

		const contentId = this.#contentEl.id || 'xestify-modal-content';
		const titleId = this.#titleEl.id || 'xestify-modal-title';
		this.#contentEl.id = contentId;
		this.#titleEl.id = titleId;
		this.#overlay.setAttribute('aria-labelledby', this.#titleEl.id);
		this.#overlay.setAttribute('aria-describedby', this.#contentEl.id);
		this.#dialog.setAttribute('tabindex', '-1');
		this.#dialog.setAttribute('aria-labelledby', this.#titleEl.id);
		this.#dialog.setAttribute('aria-describedby', this.#contentEl.id);

		this.setContent(options.content ?? '');

		this.#overlay.addEventListener('click', (event) => {
			if (event.target === this.#overlay) {
				this.close();
			}
		});

		this.#onKeyDown = (event) => {
			if (event.key === 'Escape') {
				event.preventDefault();
				this.close();
				return;
			}

			if (event.key === 'Tab') {
				this.#handleTabKey(event);
			}
		};
	}

	show(returnFocusElement = null) {
		if (returnFocusElement instanceof HTMLElement) {
			this.#returnFocusElement = returnFocusElement;
		} else {
			this.#returnFocusElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
		}

		if (!this.#overlay.isConnected) {
			this.#host.appendChild(this.#overlay);
		}

		this.#overlay.hidden = false;
		this.#overlay.dataset.open = 'true';
		this.#overlay.classList.remove('hidden');
		this.#overlay.classList.add('flex');
		document.addEventListener('keydown', this.#onKeyDown);
		this.#isOpen = true;

		window.requestAnimationFrame(() => {
			this.#focusInitialElement();
		});
	}

	close() {
		this.#overlay.hidden = true;
		delete this.#overlay.dataset.open;
		this.#overlay.classList.add('hidden');
		this.#overlay.classList.remove('flex');
		document.removeEventListener('keydown', this.#onKeyDown);
		this.#isOpen = false;

		if (this.#returnFocusElement instanceof HTMLElement && document.contains(this.#returnFocusElement)) {
			this.#returnFocusElement.focus();
		}
		this.#returnFocusElement = null;
	}

	destroy() {
		this.close();
		this.#overlay.remove();
	}

	#handleTabKey(event) {
		const focusable = this.#getFocusableElements();
		if (focusable.length === 0) {
			event.preventDefault();
			this.#dialog.focus();
			return;
		}

		const first = focusable[0];
		const last = focusable.at(-1);
		const activeElement = document.activeElement;
		if (event.shiftKey && activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	#getFocusableElements() {
		return Array.from(this.#dialog.querySelectorAll(this.#focusableSelector))
			.filter((element) => element instanceof HTMLElement)
			.filter((element) => {
				if (element.hasAttribute('disabled')) {
					return false;
				}
				const tabIndex = element.getAttribute('tabindex');
				if (tabIndex === '-1') {
					return false;
				}
				return element.getAttribute('aria-hidden') !== 'true';
			});
	}

	#focusInitialElement() {
		const focusable = this.#getFocusableElements();
		const target = focusable[0] ?? this.#dialog;
		if (target instanceof HTMLElement) {
			target.focus();
		}
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
