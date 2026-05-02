/**
 * Modal.js — Reusable modal/dialog component.
 *
 * Supports:
 *  - show()
 *  - close()
 *  - setContent()
 */

export class Modal {
  /** @type {HTMLElement} */
  #host;

  /** @type {HTMLElement} */
  #overlay;

  /** @type {HTMLElement} */
  #dialog;

  /** @type {HTMLElement} */
  #titleEl;

  /** @type {HTMLElement} */
  #contentEl;

  /** @type {boolean} */
  #isOpen = false;

  /** @type {(event: KeyboardEvent) => void} */
  #onKeyDown;

  /**
   * @param {string|HTMLElement} [container]
   * @param {{title?: string, content?: string|HTMLElement}} [options]
   */
  constructor(container = document.body, options = {}) {
    this.#host = this.#resolveContainer(container);

    this.#overlay = document.createElement('div');
    this.#overlay.className = 'xt-modal';
    this.#overlay.hidden = true;

    this.#dialog = document.createElement('section');
    this.#dialog.className = 'xt-modal__dialog';
    this.#dialog.setAttribute('role', 'dialog');
    this.#dialog.setAttribute('aria-modal', 'true');

    const header = document.createElement('header');
    header.className = 'xt-modal__header';

    this.#titleEl = document.createElement('h3');
    this.#titleEl.className = 'xt-modal__title';
    this.#titleEl.textContent = options.title ?? 'Mensaje';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'xt-modal__close';
    closeBtn.setAttribute('aria-label', 'Cerrar diálogo');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => this.close());

    header.appendChild(this.#titleEl);
    header.appendChild(closeBtn);

    this.#contentEl = document.createElement('div');
    this.#contentEl.className = 'xt-modal__content';
    this.setContent(options.content ?? '');

    this.#dialog.appendChild(header);
    this.#dialog.appendChild(this.#contentEl);
    this.#overlay.appendChild(this.#dialog);

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
    this.#overlay.classList.add('xt-modal--open');
    document.addEventListener('keydown', this.#onKeyDown);
    this.#isOpen = true;
  }

  close() {
    this.#overlay.hidden = true;
    this.#overlay.classList.remove('xt-modal--open');
    document.removeEventListener('keydown', this.#onKeyDown);
    this.#isOpen = false;
  }

  /**
   * @param {string|HTMLElement} content
   */
  setContent(content) {
    this.#contentEl.innerHTML = '';

    if (content instanceof HTMLElement) {
      this.#contentEl.appendChild(content);
      return;
    }

    this.#contentEl.textContent = String(content);
  }

  /**
   * @param {string} title
   */
  setTitle(title) {
    this.#titleEl.textContent = title;
  }

  /**
   * @returns {boolean}
   */
  isOpen() {
    return this.#isOpen;
  }

  /**
   * @param {string|HTMLElement} container
   * @returns {HTMLElement}
   */
  #resolveContainer(container) {
    if (container instanceof HTMLElement) {
      return container;
    }

    if (typeof container === 'string') {
      const found = document.querySelector(container);
      if (found instanceof HTMLElement) {
        return found;
      }
    }

    throw new TypeError(`Modal container \"${String(container)}\" not found`);
  }
}
