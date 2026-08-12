import { InputComponent } from './BaseComponent.js';

export class InputTextAreaComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.className = InputComponent.BASE_CLASSNAME;
    this.rows = Number.isInteger(options.rows) ? options.rows : 4;
    return this;
  }
}
