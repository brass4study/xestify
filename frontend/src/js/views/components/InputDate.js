import { InputComponent } from './BaseComponent.js';

export class InputDateComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('date');
    this.className = InputComponent.BASE_CLASSNAME;
    return this;
  }
}
