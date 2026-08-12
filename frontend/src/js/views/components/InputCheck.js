import { InputComponent } from './BaseComponent.js';

export class InputCheckComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('checkbox');
    this.checked = options.checked === true;
    this.className = InputComponent.CHECKABLE_CLASSNAME;
    return this;
  }
}
