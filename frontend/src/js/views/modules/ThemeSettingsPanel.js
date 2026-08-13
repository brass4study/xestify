import { t } from '../../models/I18nModel.js';
import { getUiPreferences, getUiThemeSchema, subscribeUi, updateUiPreferences } from '../../models/ThemeModel.js';
import { component } from './ComponentFactory.js';

export class ThemeSettingsPanel {
  #container;
  #root;
  #trigger;
  #backdrop;
  #panel;
  #panelBody;
  #expanded;
  #unsubscribe;
  #onDocumentPointerDown;
  #onDocumentKeyDown;

  constructor(container) {
    if (!(container instanceof HTMLElement)) {
      throw new TypeError('ThemeSettingsPanel requires an HTMLElement container');
    }

    this.#container = container;
    this.#root = null;
    this.#trigger = null;
    this.#backdrop = null;
    this.#panel = null;
    this.#panelBody = null;
    this.#expanded = false;
    this.#unsubscribe = subscribeUi(() => {
      this.#renderPanelBody();
    });
    this.#onDocumentPointerDown = (event) => {
      if (!this.#expanded || !(this.#root instanceof HTMLElement)) {
        return;
      }

      if (this.#root.contains(event.target)) {
        return;
      }

      this.#expanded = false;
      this.#syncPanelVisibility();
    };
    this.#onDocumentKeyDown = (event) => {
      if (event.key !== 'Escape' || !this.#expanded) {
        return;
      }

      this.#expanded = false;
      this.#syncPanelVisibility();
    };

    document.addEventListener('pointerdown', this.#onDocumentPointerDown);
    document.addEventListener('keydown', this.#onDocumentKeyDown);

    this.#render();
  }

  destroy() {
    if (typeof this.#unsubscribe === 'function') {
      this.#unsubscribe();
      this.#unsubscribe = null;
    }

    if (typeof this.#onDocumentPointerDown === 'function') {
      document.removeEventListener('pointerdown', this.#onDocumentPointerDown);
      this.#onDocumentPointerDown = null;
    }

    if (typeof this.#onDocumentKeyDown === 'function') {
      document.removeEventListener('keydown', this.#onDocumentKeyDown);
      this.#onDocumentKeyDown = null;
    }

    this.#container.replaceChildren();
  }

  setContainer(container) {
    if (!(container instanceof HTMLElement)) {
      throw new TypeError('ThemeSettingsPanel requires an HTMLElement container');
    }

    if (this.#container === container) {
      return;
    }

    this.#container.replaceChildren();
    this.#container = container;
    this.#render();
  }

  #render() {
    this.#container.replaceChildren();

    const wrapper = component.create('div')
      .setData('role', 'theme-settings-root')
      .setClassName('theme-settings-root relative');

    const trigger = component.create('div')
      .setClassName('theme-settings-trigger inline-flex h-12 w-12 items-center justify-center rounded-full text-white transition hover:bg-white/20')
      .setData('role', 'theme-settings-trigger')
      .setAttributes({
        role: 'button',
        tabindex: '0',
        'aria-label': t('theme.panel.open', 'Abrir configuración visual'),
        'aria-haspopup': 'dialog',
        'aria-expanded': 'false',
      });

    component.create('i', {
      className: 'fa-solid fa-palette leading-none',
      attributes: { 'aria-hidden': 'true' },
    }).setParent(trigger);

    trigger.addEventListener('click', () => {
      this.#expanded = !this.#expanded;
      this.#syncPanelVisibility();
    });

    trigger.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      event.preventDefault();
      this.#expanded = !this.#expanded;
      this.#syncPanelVisibility();
    });

    const backdrop = component.create('div')
      .setClassName('theme-settings-backdrop')
      .setData('role', 'theme-settings-backdrop');
    backdrop.setAttribute('style', 'position:fixed;inset:0;z-index:88;');
    backdrop.hidden = true;
    backdrop.addEventListener('click', () => {
      this.#expanded = false;
      this.#syncPanelVisibility();
    });

    const panel = component.create('sectionTag', {
      className: 'theme-settings-drawer overflow-hidden border-l border-slate-200 bg-white shadow-float',
      attributes: {
        'aria-label': t('theme.panel.ariaLabel', 'Configuración visual'),
      },
      dataset: { role: 'theme-settings-panel' },
    });
    panel.setAttribute('style', 'position:fixed;top:0;right:0;z-index:90;height:100vh;width:min(24rem, calc(100vw - 1rem));');
    panel.hidden = true;

    const header = component.create('div').setClassName('theme-drawer-header').setParent(panel);
    component.create('h3', {
      className: 'text-base font-semibold text-slate-900',
      text: t('theme.panel.title', 'Configuración UI'),
    }).setParent(header);
    component.create('button', {
      ariaLabel: t('theme.panel.close', 'Cerrar configuración visual'),
      icon: 'fa-xmark',
      onClick: () => {
        this.#expanded = false;
        this.#syncPanelVisibility();
      },
    }).setClassName('theme-drawer-close inline-flex items-center justify-center text-slate-500 transition hover:text-slate-900').setParent(header);

    this.#panelBody = component.create('div').setClassName('grid').setData('role', 'theme-panel-grid').setParent(panel);

    wrapper.append(trigger);
    wrapper.append(backdrop);
    wrapper.append(panel);
    this.#container.append(wrapper);

    this.#trigger = trigger;
    this.#backdrop = backdrop;
    this.#root = wrapper;
    this.#panel = panel;
    this.#renderPanelBody();
    this.#syncPanelVisibility();
  }

  #renderPanelBody() {
    if (!(this.#panelBody instanceof HTMLElement)) {
      return;
    }

    const settings = getUiPreferences();
    const schema = getUiThemeSchema();
    this.#panelBody.replaceChildren();

    this.#panelBody.append([
      this.#buildPageStyleSection(schema.pageStyle, settings.pageStyle),
      this.#buildThemeColorSection(schema.themeColor, settings.themeColor),
      this.#buildNavigationModeSection(schema.navigationMode, settings.navigationMode),
    ]);
  }

  #buildNavigationModeSection(options, selectedValue) {
    const section = this.#createSection(t('theme.sections.navigationMode', 'Modo de navegación')).setData('role', 'theme-section-navigation-mode');
    const group = component.create('div').setClassName('mt-3 flex').setData('role', 'theme-navigation-mode-group').setParent(section);

    for (const option of options) {
      const active = option.value === selectedValue;
      const button = component.create('button', {
        type: 'button',
        ariaLabel: option.label,
        onClick: () => {
          updateUiPreferences({ navigationMode: option.value });
        },
      }).setClassName('theme-option-card relative overflow-hidden')
        .setData('role', 'theme-navigation-mode-option')
        .setData('value', option.value);

      this.#createNavigationModePreview(option.value).setParent(button);
      this.#createCheckBadge(active).setParent(button);
      button.setParent(group);
    }

    return section;
  }

  #buildPageStyleSection(options, selectedValue) {
    const section = this.#createSection(t('theme.sections.pageStyle', 'Estilo de página')).setData('role', 'theme-section-page-style');
    const group = component.create('div').setClassName('mt-3 flex').setData('role', 'theme-page-style-group').setParent(section);

    for (const option of options) {
      const active = option.value === selectedValue;
      const button = component.create('button', {
        type: 'button',
        ariaLabel: option.label,
        onClick: () => {
          updateUiPreferences({ pageStyle: option.value });
        },
      }).setClassName(active
        ? 'theme-option-card is-active relative flex items-center justify-center overflow-hidden'
        : 'theme-option-card relative flex items-center justify-center overflow-hidden')
        .setData('role', 'theme-page-style-option')
        .setData('value', option.value);

      this.#createPageStylePreview(option.value).setParent(button);
      this.#createCheckBadge(active).setParent(button);
      button.setParent(group);
    }

    return section;
  }

  #buildThemeColorSection(options, selectedValue) {
    const section = this.#createSection(t('theme.sections.themeColor', 'Color del tema')).setData('role', 'theme-section-theme-color');
    const group = component.create('div').setClassName('mt-3').setData('role', 'theme-color-group').setParent(section);

    for (const option of options) {
      const active = option.value === selectedValue;
      const button = component.create('button', {
        type: 'button',
        ariaLabel: option.label,
        onClick: () => {
          updateUiPreferences({ themeColor: option.value });
        },
      }).setClassName(active
        ? 'theme-color-swatch is-active relative flex items-center justify-center'
        : 'theme-color-swatch relative flex items-center justify-center')
        .setData('role', 'theme-color-option')
        .setData('value', option.value);

      component.create('span', {
        className: 'block h-full w-full',
      }).setAttribute('style', `background:${option.swatch}`).setParent(button);

      this.#createCheckBadge(active, 'theme-color').setParent(button);
      button.setParent(group);
    }

    return section;
  }

  #createSection(title) {
    const section = component.create('sectionTag');
    component.create('h4', {
      className: 'font-semibold text-slate-900',
      text: title,
    }).setParent(section);
    return section;
  }

  #createPageStylePreview(pageStyle) {
    const wrapper = component.create('div').setClassName('flex h-full w-full overflow-hidden rounded-md');
    const leftTone = pageStyle === 'dark' ? '#0f172a' : '#ffffff';
    const rightTone = pageStyle === 'dark' ? '#334155' : '#e2e8f0';

    component.create('span', {
      className: 'block h-full flex-1',
    }).setAttribute('style', `background:${leftTone}`).setParent(wrapper);
    component.create('span', {
      className: 'block h-full w-[42%]',
    }).setAttribute('style', `background:${rightTone}`).setParent(wrapper);
    return wrapper;
  }

  #createNavigationModePreview(mode) {
    const wrapper = component.create('div').setClassName('theme-navigation-mode-preview flex h-full w-full overflow-hidden rounded');
    const topTone = '#1e79ff';
    const bodyTone = '#e2e8f0';
    const sideTone = '#334155';

    if (mode === 'top') {
      component.create('span', { className: 'block h-full w-full' })
        .setAttribute('style', `background:linear-gradient(to bottom, ${topTone} 0 34%, ${bodyTone} 34% 100%)`)
        .setParent(wrapper);
      return wrapper;
    }

    if (mode === 'side') {
      component.create('span', { className: 'block h-full w-full' })
        .setAttribute('style', `background:linear-gradient(to right, ${topTone} 0 30%, ${bodyTone} 30% 100%)`)
        .setParent(wrapper);
      return wrapper;
    }

    component.create('span', { className: 'block h-full w-full' })
      .setAttribute('style', `background:linear-gradient(to bottom, ${sideTone} 0 28%, transparent 28% 100%), linear-gradient(to right, ${topTone} 0 30%, ${bodyTone} 30% 100%)`)
      .setParent(wrapper);
    return wrapper;
  }

  #createCheckBadge(active, variant = 'default') {
    const activeClassName = variant === 'theme-color'
      ? 'theme-check-badge theme-check-badge-color absolute inset-0 flex items-center justify-center text-white'
      : 'absolute bottom-1 right-1 flex h-4 w-4 items-center justify-center font-semibold text-brand-500';

    const badge = component.create('span').setClassName(active
      ? activeClassName
      : 'hidden');

    if (active) {
      component.create('i', {
        className: variant === 'theme-color' ? 'fa-solid fa-check text-xs' : 'fa-solid fa-check',
        attributes: { 'aria-hidden': 'true' },
      }).setParent(badge);
    }

    return badge;
  }

  #syncPanelVisibility() {
    if (!(this.#root instanceof HTMLElement)
      || !(this.#panel instanceof HTMLElement)
      || !(this.#backdrop instanceof HTMLElement)) {
      return;
    }

    this.#root.dataset.state = this.#expanded ? 'open' : 'closed';
    this.#backdrop.hidden = !this.#expanded;
    this.#panel.hidden = !this.#expanded;
    this.#panel.setAttribute('aria-hidden', this.#expanded ? 'false' : 'true');

    if (this.#trigger instanceof HTMLElement) {
      this.#trigger.setAttribute('aria-expanded', this.#expanded ? 'true' : 'false');
    }
  }
}
