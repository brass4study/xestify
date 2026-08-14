import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

const SPINNER_SIZE_CLASSES = {
  sm: 'h-5 w-5 text-lg',
  md: 'h-8 w-8 text-3xl',
  lg: 'h-12 w-12 text-5xl',
};

/**
 * Spinner + título/descripción opcionales, apilados en vertical. Sin fondo ni
 * borde propios (transparente) — el contenedor visual, si hace falta uno, lo
 * aporta quien use este componente.
 */
export class LoaderComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'flex w-full flex-col items-center text-center';

    const spinnerSize = SPINNER_SIZE_CLASSES[options.spinnerSize] ?? SPINNER_SIZE_CLASSES.md;

    component.create('i', {
      className: `fa-solid fa-spinner fa-spin flex items-center justify-center text-brand-600 ${spinnerSize}`,
      attributes: { 'aria-hidden': 'true' },
    }).setParent(this);

    if (typeof options.title === 'string' && options.title !== '') {
      component.create('span', {
        className: 'text-sm font-semibold text-slate-900',
        text: options.title,
      }).setParent(this);
    }

    if (typeof options.description === 'string' && options.description !== '') {
      component.create('span', {
        className: 'text-sm text-slate-600',
        text: options.description,
      }).setParent(this);
    }

    return this;
  }
}
