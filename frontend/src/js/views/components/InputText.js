import { InputComponent } from './BaseComponent.js';

export class InputTextComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('text');
    this.className = InputComponent.BASE_CLASSNAME;
    return this;
  }
}
