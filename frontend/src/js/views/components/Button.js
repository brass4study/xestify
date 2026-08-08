import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class ButtonComponent extends BaseComponent {
  initialize(options = {}) {
    this.type = options.type ?? 'button';
    this.className = [
      'inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-1',
      this.variantClassName(options.variant),
    ].join(' ');

    if (typeof options.label === 'string' && options.label !== '') {
      this.textContent = options.label;
    }

    if (typeof options.ariaLabel === 'string' && options.ariaLabel !== '') {
      this.setAttribute('aria-label', options.ariaLabel);
    }

    if (typeof options.dataRole === 'string' && options.dataRole !== '') {
      this.dataset.role = options.dataRole;
    }

    if (typeof options.dataAction === 'string' && options.dataAction !== '') {
      this.dataset.action = options.dataAction;
    }

    if (options.disabled === true) {
      this.disabled = true;
    }

    if (typeof options.onClick === 'function') {
      this.addEventListener('click', options.onClick);
    }

    if (typeof options.icon === 'string' && options.icon !== '') {
      const icon = component.create('i', {
        className: `inline-flex items-center ${options.icon}`,
        attributes: { 'aria-hidden': 'true' },
      });
      this.prepend(icon);
    }

    return this;
  }

  variantClassName(variant) {
    if (variant === 'primary') {
      return 'border border-brand-600 bg-brand-600 text-white hover:bg-brand-700 focus:ring-brand-200';
    }

    if (variant === 'danger') {
      return 'border border-red-600 bg-red-600 text-white hover:bg-red-700 focus:ring-red-200';
    }

    if (variant === 'success') {
      return 'border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-200';
    }

    return 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-200';
  }
}
