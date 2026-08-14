import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

/**
 * Logo + tagline de marca ("GESTIÓN INTELIGENTE"), listos para reutilizar en
 * cualquier pantalla. Estilo propio en frontend/src/css/brand.css.
 */
export class BrandLogoComponent extends BaseComponent {
  initialize() {
    this.className = 'brand-wrapper';

    component.create('logo').setParent(this);

    component.create('p', {
      className: 'brand-tagline',
      text: 'GESTIÓN INTELIGENTE',
    }).setParent(this);

    return this;
  }
}
