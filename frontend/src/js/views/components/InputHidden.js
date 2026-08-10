import { InputComponent } from './BaseComponent.js';

export class InputHiddenComponent extends InputComponent {
  initialize(options = {}) {
    super.initialize(options);
    this.setType('hidden');
    return this;
  }
}