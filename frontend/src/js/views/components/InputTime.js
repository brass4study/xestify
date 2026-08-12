import { InputComponent } from './BaseComponent.js';

export class InputTimeComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('time');
    this.className = InputComponent.BASE_CLASSNAME;
    return this;
  }
}
