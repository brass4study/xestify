import { InputComponent } from './BaseComponent.js';

export class InputPhoneComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('tel');
    this.className = InputComponent.BASE_CLASSNAME;
    return this;
  }
}
