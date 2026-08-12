import { AppState } from '../models/StateModel.js';
import { t } from '../models/I18nModel.js';
import { Modal } from '../views/modules/Modal.js';
import { component } from '../views/modules/ComponentFactory.js';
import { ApiError } from '../models/ApiClientModel.js';

export class UiResilienceService {
  static showNotification(payload = {}) {
    const type = typeof payload.type === 'string' && payload.type !== '' ? payload.type : 'info';
    const shouldRenderGlobally = payload.global === true || type === 'error' || type === 'warning';

    const notification = {
      id: payload.id ?? (globalThis.crypto?.randomUUID?.() ?? `notify-${Date.now()}-${Date.now().toString(16)}-${performance.now().toString(16)}`),
      type,
      title: payload.title ?? '',
      message: payload.message ?? '',
      persistent: Boolean(payload.persistent),
      global: shouldRenderGlobally,
    };

    AppState.setNotification(notification);

    const emitNotificationEvent = () => {
      const event = new CustomEvent('xestify:notification-changed', {
        detail: notification,
      });

      if (typeof window !== 'undefined') {
        window.dispatchEvent(event);
      }

      if (typeof document !== 'undefined') {
        document.dispatchEvent(event);
      }
    };

    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(emitNotificationEvent);
    } else {
      emitNotificationEvent();
    }

    return notification;
  }

  static clearNotification() {
    AppState.clearNotification();

    const emitNotificationEvent = () => {
      const event = new CustomEvent('xestify:notification-changed', {
        detail: null,
      });

      if (typeof window !== 'undefined') {
        window.dispatchEvent(event);
      }

      if (typeof document !== 'undefined') {
        document.dispatchEvent(event);
      }
    };

    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(emitNotificationEvent);
    } else {
      emitNotificationEvent();
    }
  }

  static setViewState(container, config = {}) {
    const host = this.resolveHost(container);
    if (!(host instanceof HTMLElement)) {
      return null;
    }

    this.clearViewState(host);

    const type = typeof config.type === 'string' && config.type !== '' ? config.type : 'info';
    const title = typeof config.title === 'string' ? config.title : '';
    const message = typeof config.message === 'string' ? config.message : '';
    const stateEl = component.create('div')
      .setData('role', 'view-state')
      .setData('state', type)
      .setClassName('flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center shadow-sm');

    if (type === 'loading') {
      component.create('div')
        .setClassName('flex h-10 w-10 items-center justify-center rounded-full border-2 border-slate-200 border-t-brand-600 animate-spin')
        .setParent(stateEl);
    }

    if (title !== '') {
      component.create('h3')
        .setClassName('text-base font-semibold text-slate-900')
        .setText(title)
        .setParent(stateEl);
    }

    if (message !== '') {
      component.create('p')
        .setClassName('max-w-md text-sm text-slate-600')
        .setText(message)
        .setParent(stateEl);
    }

    host.appendChild(stateEl);
    return stateEl;
  }

  static clearViewState(container) {
    const host = this.resolveHost(container);
    if (!(host instanceof HTMLElement)) {
      return;
    }

    const stateEl = host.querySelector('[data-role="view-state"]');
    if (stateEl instanceof HTMLElement) {
      stateEl.remove();
    }
  }

  static setButtonPending(button, pendingLabel = 'Procesando...', options = {}) {
    if (!(button instanceof HTMLButtonElement)) {
      return button;
    }

    if (button.dataset.defaultLabel === undefined) {
      button.dataset.defaultLabel = button.textContent ?? '';
    }

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.dataset.pending = 'true';

    if (typeof pendingLabel === 'string' && pendingLabel !== '') {
      button.textContent = pendingLabel;
    }

    if (typeof options.title === 'string' && options.title !== '') {
      button.title = options.title;
    }

    return button;
  }

  static clearButtonPending(button, restoreLabel = undefined) {
    if (!(button instanceof HTMLButtonElement)) {
      return button;
    }

    button.disabled = false;
    button.removeAttribute('aria-busy');
    delete button.dataset.pending;

    if (typeof restoreLabel === 'string' && restoreLabel !== '') {
      button.textContent = restoreLabel;
    } else if (typeof button.dataset.defaultLabel === 'string' && button.dataset.defaultLabel !== '') {
      button.textContent = button.dataset.defaultLabel;
    }

    delete button.dataset.defaultLabel;
    return button;
  }

  static handleError(error, fallbackMessage = 'Ha ocurrido un error inesperado.') {
    if (error instanceof ApiError && error.code === 0) {
      return t('ui.error.network', 'No se pudo completar la solicitud. Revise la conexión e intente nuevamente.');
    }

    return fallbackMessage;
  }

  static confirm(options = {}) {
    return new Promise((resolve) => {
      const container = options.container instanceof HTMLElement ? options.container : document.body;
      const title = typeof options.title === 'string' && options.title !== '' ? options.title : t('ui.actions.confirm', 'Confirmar');
      const message = typeof options.message === 'string' ? options.message : '';
      const confirmLabel = typeof options.confirmLabel === 'string' && options.confirmLabel !== '' ? options.confirmLabel : t('ui.actions.confirm', 'Confirmar');
      const cancelLabel = typeof options.cancelLabel === 'string' && options.cancelLabel !== '' ? options.cancelLabel : t('ui.actions.cancel', 'Cancelar');

      const modal = new Modal(container, { title });
      const body = component.create('div').setClassName('grid gap-3');
      component.create('p')
        .setClassName('text-sm text-slate-700')
        .setText(message)
        .setParent(body);

      const actions = component.create('div').setClassName('flex justify-end gap-2').setParent(body);
      component.create('button', {
        label: cancelLabel,
        variant: 'secondary',
        size: 'xs',
        dataAction: 'cancel-modal',
      }).setParent(actions);
      component.create('button', {
        label: confirmLabel,
        variant: 'primary',
        size: 'xs',
        dataAction: 'confirm-modal',
      }).setParent(actions);

      const closeWith = (result) => {
        modal.close();
        resolve(result);
      };

      modal.show(document.activeElement instanceof HTMLElement ? document.activeElement : null);

      body.querySelector('[data-action="cancel-modal"]')?.addEventListener('click', () => closeWith(false));
      body.querySelector('[data-action="confirm-modal"]')?.addEventListener('click', () => closeWith(true));

      modal.setContent(body);
      modal.show();
      const confirmButton = body.querySelector('[data-action="confirm-modal"]');
      if (confirmButton instanceof HTMLButtonElement) {
        confirmButton.focus();
        confirmButton.dataset.focused = 'true';
        if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
          window.requestAnimationFrame(() => {
            confirmButton.focus();
            confirmButton.dataset.focused = 'true';
          });
        }
      }
    });
  }

  static resolveHost(container) {
    if (container instanceof HTMLElement) {
      return container;
    }

    if (typeof container === 'string') {
      const found = document.querySelector(container);
      if (found instanceof HTMLElement) {
        return found;
      }
    }

    return null;
  }
}
