import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

const ALERT_VARIANTS = {
  info: 'border-slate-200 bg-slate-50 text-slate-700',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  warning: 'border-amber-200 bg-amber-50 text-amber-800',
  error: 'border-red-200 bg-red-50 text-red-800',
};

export class AlertComponent extends BaseComponent {
  initialize(options = {}) {
    const type = options.type ?? 'info';
    this.className = `rounded-xl border px-4 py-3 text-sm ${ALERT_VARIANTS[type] ?? ALERT_VARIANTS.info}`;
    this.setAttribute('role', 'status');

    if (typeof options.title === 'string' && options.title !== '') {
      const titleEl = component.create('div', {
        className: 'font-semibold',
        text: options.title,
      });
      this.appendChild(titleEl);
    }

    if (typeof options.message === 'string' && options.message !== '') {
      const messageEl = component.create('div', {
        className: options.title ? 'mt-1' : '',
        text: options.message,
      });
      this.appendChild(messageEl);
    }

    return this;
  }

  setTitle(title) {
    this.setText(String(title ?? ''));
    return this;
  }

  setMessage(message) {
    this.setText(String(message ?? ''));
    return this;
  }

  setType(type) {
    this.className = `rounded-xl border px-4 py-3 text-sm ${ALERT_VARIANTS[type] ?? ALERT_VARIANTS.info}`;
    return this;
  }
}
