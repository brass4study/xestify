import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

/**
 * Marca "Xestify": dos chevrons forman la X, seguidos del texto "estify".
 * Estilo propio en frontend/src/css/brand.css (excepción sin Tailwind).
 */
export class LogoComponent extends BaseComponent {
  initialize() {
    this.className = 'logo';

    const icon = component.create('div').setClassName('logo-icon');
    component.create('div').setClassName('chevron left').setParent(icon);
    component.create('div').setClassName('chevron right').setParent(icon);
    icon.setParent(this);

    component.create('span', {
      className: 'logo-text',
      text: 'estify',
    }).setParent(this);

    return this;
  }
}
