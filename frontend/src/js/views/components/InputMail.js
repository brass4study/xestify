import { InputComponent } from './BaseComponent.js';

export class InputMailComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('email');
    this.className = InputComponent.BASE_CLASSNAME;
    return this;
  }
}
