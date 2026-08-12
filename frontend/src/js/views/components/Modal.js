import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

let nextModalTitleId = 0;

export class ModalComponent extends BaseComponent {
  initialize(options = {}) {
    const titleId = `ui-modal-title-${++nextModalTitleId}`;

    this.className = 'fixed inset-0 z-[300] flex items-center justify-center bg-slate-950/50 p-4';
    this.dataset.role = 'ui-modal-overlay';
    this.setAttribute('aria-labelledby', titleId);

    const dialog = component.create('sectionTag', {
      className: 'w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-float',
      dataset: { role: 'ui-modal-dialog' },
    });
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');

    const header = component.create('header', {
      className: 'flex items-center justify-between border-b border-slate-200 px-4 py-3',
    });

    component.create('h3', {
      className: 'text-base font-semibold text-slate-900',
      dataset: { role: 'ui-modal-title' },
      text: options.title ?? 'Mensaje',
    }).setId(titleId).setParent(header);

    if (typeof options.onClose === 'function') {
      const closeButton = component.create('button', { label: '×', ariaLabel: 'Cerrar diálogo', onClick: options.onClose });
      closeButton
        .setClassName('inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-lg text-slate-600 transition hover:bg-slate-100')
        .setParent(header);
    }

    const content = component.create('div', {
      className: 'p-4 text-sm text-slate-700',
      dataset: { role: 'ui-modal-content' },
    });
    if (options.content instanceof HTMLElement) {
      content.appendChild(options.content);
    } else if (typeof options.content === 'string') {
      content.textContent = options.content;
    }

    header.setParent(dialog);
    content.setParent(dialog);
    dialog.setParent(this);
    return this;
  }
}
