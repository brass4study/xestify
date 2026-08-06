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
    this.#overlay.className = 'fixed inset-0 z-[300] hidden items-center justify-center bg-slate-950/50 p-4';
    this.#overlay.dataset.role = 'modal-overlay';
    this.#overlay.hidden = true;

    this.#dialog = document.createElement('section');
    this.#dialog.className = 'w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-float';
    this.#dialog.dataset.role = 'modal-dialog';
    this.#dialog.setAttribute('role', 'dialog');
    this.#dialog.setAttribute('aria-modal', 'true');

    const header = document.createElement('header');
    header.className = 'flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3';

    this.#titleEl = document.createElement('h3');
    this.#titleEl.className = 'text-base font-semibold text-slateui-950';
    this.#titleEl.dataset.role = 'modal-title';
    this.#titleEl.textContent = options.title ?? 'Mensaje';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-lg text-slate-600 transition hover:bg-slate-100';
    closeBtn.dataset.role = 'modal-close';
    closeBtn.setAttribute('aria-label', 'Cerrar diálogo');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => this.close());

    header.appendChild(this.#titleEl);
    header.appendChild(closeBtn);

    this.#contentEl = document.createElement('div');
    this.#contentEl.className = 'p-4 text-sm text-slate-700';
    this.#contentEl.dataset.role = 'modal-content';
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

  /**
   * @param {string|HTMLElement} content
   */
  setContent(content) {
    this.#contentEl.replaceChildren();

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

    throw new TypeError(`Modal container "${String(container)}" not found`);
  }
}
