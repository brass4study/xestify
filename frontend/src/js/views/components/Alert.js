import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

const ALERT_VARIANTS = {
  info: 'bg-slate-50 text-slate-700',
  success: 'bg-emerald-50 text-emerald-800',
  warning: 'bg-amber-50 text-amber-800',
  error: 'bg-red-50 text-red-800',
};

const ALERT_ICONS = {
  info: 'fa-circle-info',
  success: 'fa-circle-check',
  warning: 'fa-triangle-exclamation',
  error: 'fa-circle-xmark',
};

function baseClassName(type) {
  return `flex items-start gap-3 rounded-md px-4 py-3 text-sm ${ALERT_VARIANTS[type] ?? ALERT_VARIANTS.info}`;
}

export class AlertComponent extends BaseComponent {
  initialize(options = {}) {
    const type = options.type ?? 'info';
    this.className = baseClassName(type);
    this.setAttribute('role', 'status');

    // Icono opcional (Font Awesome), apagado por defecto para no alterar el
    // aspecto de los Alert ya existentes en el resto de la app que no lo piden.
    if (options.showIcon === true) {
      const iconName = typeof options.icon === 'string' && options.icon !== ''
        ? options.icon
        : ALERT_ICONS[type] ?? ALERT_ICONS.info;

      component.create('i', {
        className: `fa-solid ${iconName} mt-0.5 shrink-0 text-base`,
        attributes: { 'aria-hidden': 'true' },
      }).setParent(this);
    }

    const content = component.create('div').setClassName('min-w-0 flex-1');

    if (typeof options.title === 'string' && options.title !== '') {
      component.create('div', {
        className: 'font-semibold',
        text: options.title,
      }).setParent(content);
    }

    if (typeof options.message === 'string' && options.message !== '') {
      component.create('div', {
        className: options.title ? 'mt-1' : '',
        text: options.message,
      }).setParent(content);
    }

    content.setParent(this);

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
    this.className = baseClassName(type);
    return this;
  }
}
