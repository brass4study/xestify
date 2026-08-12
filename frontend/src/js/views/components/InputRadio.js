import { InputComponent } from './BaseComponent.js';

export class InputRadioComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('radio');
    this.className = InputComponent.CHECKABLE_CLASSNAME;
    return this;
  }
}
