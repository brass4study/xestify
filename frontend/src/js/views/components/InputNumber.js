import { InputComponent } from './BaseComponent.js';

export class InputNumberComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('number');
    this.className = InputComponent.BASE_CLASSNAME;
    return this;
  }
}
