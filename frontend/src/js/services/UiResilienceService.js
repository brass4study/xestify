import { AppState } from '../models/StateModel.js';
import { t } from '../models/I18nModel.js';
import { Modal } from '../views/modules/Modal.js';
import { component } from '../views/modules/ComponentFactory.js';

export class UiResilienceService {
  static showNotification(payload = {}) {
    const notification = {
      id: payload.id ?? (globalThis.crypto?.randomUUID?.() ?? `notify-${Date.now()}-${Date.now().toString(16)}-${performance.now().toString(16)}`),
      type: payload.type ?? 'info',
      title: payload.title ?? '',
      message: payload.message ?? '',
      persistent: Boolean(payload.persistent),
    };

    AppState.setNotification(notification);
    return notification;
  }

  static clearNotification() {
    AppState.clearNotification();
  }

  static handleError(error, fallbackMessage = 'Ha ocurrido un error inesperado.') {
    if (error && typeof error === 'object') {
      if (error.type === 'network' || error.code === 'NETWORK_ERROR') {
        return t('ui.error.network', 'No se pudo completar la solicitud. Revise la conexión e intente nuevamente.');
      }

      if (typeof error.message === 'string' && error.message.trim() !== '') {
        return t('ui.error.generic', fallbackMessage);
      }
    }

    return t('ui.error.generic', fallbackMessage);
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

      body.querySelector('[data-action="cancel-modal"]')?.addEventListener('click', () => closeWith(false));
      body.querySelector('[data-action="confirm-modal"]')?.addEventListener('click', () => closeWith(true));

      modal.setContent(body);
      modal.show();
    });
  }
}
