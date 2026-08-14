import { InputComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

export class InputPasswordComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('password');
    this.className = InputComponent.BASE_CLASSNAME;
    return this;
  }

  /**
   * Password field with a built-in show/hide toggle (Font Awesome fa-eye /
   * fa-eye-slash), the standard treatment for any password input in this app.
   * Returns the wrapper to hand to FormField as `input` — the bare
   * component.create('inputPassword', ...) factory keeps returning a plain
   * <input> unchanged, since DynamicForm's value extraction requires the
   * stored control to be an actual HTMLInputElement.
   */
  static createToggleable(options = {}) {
    const input = component.create('inputPassword', options);
    input.addClass('pr-10');

    if (input.id === '' && typeof options.name === 'string' && options.name !== '') {
      input.id = options.name;
    }
    if (!input.autocomplete) {
      input.autocomplete = typeof options.autocomplete === 'string' ? options.autocomplete : 'current-password';
    }

    const icon = component.create('i', {
      className: 'fa-solid fa-eye',
      attributes: { 'aria-hidden': 'true' },
    });

    const toggle = component.create('button', {
      type: 'button',
      dataRole: 'password-visibility-toggle',
      ariaLabel: 'Mostrar contraseña',
    }).setClassName('absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-slate-600 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60');
    icon.setParent(toggle);

    toggle.addEventListener('click', () => {
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      icon.className = isHidden ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
      toggle.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });

    const wrapper = component.create('div').setClassName('relative');
    input.setParent(wrapper);
    toggle.setParent(wrapper);

    return wrapper;
  }
}
