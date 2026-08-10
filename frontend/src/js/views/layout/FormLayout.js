import { PageLayout } from './PageLayout.js';
import { component } from '../modules/ComponentFactory.js';

export class FormLayout extends PageLayout {
  #panel = null;
  #actions = null;

  /** @returns {this} */
  build() {
    this.#panel = component.create('section')
      .setClassName('rounded-md border border-slate-200 bg-white p-4')
      .setData('role', 'form-panel');
    if (this.getShell() === null) {
      this.#actions = component.create('div')
        .setClassName('mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end')
        .setData('role', 'form-actions');
      this.setContent([this.#panel, this.#actions]);
    } else {
      this.#actions = null;
      this.setActions(null);
      this.setContent(this.#panel);
    }
    this.setTemplate('detail');
    super.build();
    return this;
  }

  /** @returns {HTMLElement|null} */
  getPanel() {
    return this.#panel;
  }

  /**
   * @param {Node|Node[]|Function} content
   * @returns {this}
   */
  addAction(content) {
    if (this.getShell() !== null) {
      const items = Array.isArray(content) ? content : [content];
      for (const item of items) {
        this.addMainAction(item);
      }
      return this;
    }
    if (!(this.#actions instanceof HTMLElement)) {
      throw new TypeError('FormLayout must be built before adding actions');
    }
    const items = Array.isArray(content) ? content : [content];
    for (const item of items) {
      const built = typeof item === 'function' ? item(this.#actions, this.getShell()) : item;
      if (built instanceof Node && built.parentNode !== this.#actions) {
        this.#actions.appendChild(built);
      }
    }
    return this;
  }
}
