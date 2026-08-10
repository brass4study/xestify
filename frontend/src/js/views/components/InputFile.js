import { InputComponent } from './BaseComponent.js';

export class InputFileComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('file');
    return this;
  }
}