import { InputComponent } from './BaseComponent.js';

export class InputPasswordComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('password');
    this.className = InputComponent.BASE_CLASSNAME;
    return this;
  }
}
