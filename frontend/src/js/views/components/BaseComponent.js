export class BaseComponent extends HTMLElement {
  static create(tagName = 'div', options = {}) {
    return createComponentInstance(this, tagName, options);
  }

  append(child) {
    if (child instanceof Node) {
      this.appendChild(child);
    } else if (Array.isArray(child)) {
      child.forEach((item) => this.append(item));
    }
    return this;
  }

  setText(text) {
    this.textContent = String(text ?? '');
    return this;
  }

  setClassName(className) {
    this.className = String(className ?? '');
    return this;
  }

  addClass(...classNames) {
    const classes = classNames.filter(Boolean).flatMap((entry) => String(entry).split(/\s+/).filter(Boolean));
    if (classes.length === 0) {
      return this;
    }
    const next = new Set(this.className.split(/\s+/).filter(Boolean));
    classes.forEach((entry) => next.add(entry));
    this.className = Array.from(next).join(' ');
    return this;
  }

  removeClass(...classNames) {
    const classes = classNames.filter(Boolean).flatMap((entry) => String(entry).split(/\s+/).filter(Boolean));
    if (classes.length === 0) {
      return this;
    }
    const next = new Set(this.className.split(/\s+/).filter(Boolean));
    classes.forEach((entry) => next.delete(entry));
    this.className = Array.from(next).join(' ');
    return this;
  }

  setAttribute(name, value) {
    if (value === undefined || value === null) {
      HTMLElement.prototype.removeAttribute.call(this, name);
    } else {
      HTMLElement.prototype.setAttribute.call(this, name, String(value));
    }
    return this;
  }

  setAttributes(attributes = {}) {
    Object.entries(attributes).forEach(([name, value]) => this.setAttribute(name, value));
    return this;
  }

  setRole(role) {
    if (typeof role === 'string' && role !== '') {
      this.setAttribute('role', role);
    }
    return this;
  }

  setId(id) {
    if (typeof id === 'string' && id !== '') {
      this.id = id;
    }
    return this;
  }

  setVisible(visible) {
    this.hidden = !visible;
    return this;
  }

  setParent(parent) {
    const target = this._resolveParent(parent);
    if (target instanceof Node) {
      target.appendChild(this);
    }
    return this;
  }

  setData(name, value) {
    if (typeof name === 'string' && name !== '') {
      this.dataset[name] = value === undefined || value === null ? '' : String(value);
    }
    return this;
  }

  _resolveParent(parent) {
    if (parent instanceof Node) {
      return parent;
    }

    if (typeof parent === 'string') {
      if (parent.startsWith('#') || parent.startsWith('.') || parent.includes('[')) {
        const resolved = document.querySelector(parent);
        if (resolved instanceof Node) {
          return resolved;
        }
      }

      const byId = document.getElementById(parent);
      if (byId instanceof Node) {
        return byId;
      }

      const byRole = document.querySelector(`[data-role="${parent}"]`);
      if (byRole instanceof Node) {
        return byRole;
      }

      const byDataId = document.querySelector(`[data-id="${parent}"]`);
      if (byDataId instanceof Node) {
        return byDataId;
      }
    }

    return null;
  }
}

export class InputComponent extends BaseComponent {
  static BASE_CLASSNAME = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500';
  static CHECKABLE_CLASSNAME = 'h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500';

  initialize(options = {}) {
    if (typeof options.name === 'string' && options.name !== '') {
      this.name = options.name;
    }

    if (typeof options.placeholder === 'string' && options.placeholder !== '') {
      this.placeholder = options.placeholder;
    }

    if (options.disabled === true) {
      this.disabled = true;
    }

    if (options.required === true) {
      this.required = true;
    }

    const value = options.value ?? '';
    this.value = String(value);
    return this;
  }

  setType(type) {
    if (typeof type === 'string' && type !== '') {
      this.type = type;
    }
    return this;
  }

  setValue(value) {
    this.value = String(value ?? '');
    return this;
  }

  setError(message) {
    if (typeof message === 'string' && message !== '') {
      this.removeClass('border-slate-300', 'focus:border-brand-500');
      this.addClass('border-red-300', 'focus:border-red-500');
      this.setAttribute('aria-invalid', 'true');
      this.dataset.error = message;
    } else {
      this.removeClass('border-red-300', 'focus:border-red-500');
      this.addClass('border-slate-300', 'focus:border-brand-500');
      this.removeAttribute('aria-invalid');
      delete this.dataset.error;
    }
    return this;
  }
}

export function createElement(tagName, options = {}) {
  const element = document.createElement(tagName);

  if (typeof options.className === 'string' && options.className !== '') {
    element.className = options.className;
  }

  if (typeof options.text === 'string') {
    element.textContent = options.text;
  }

  if (options.hidden === true) {
    element.hidden = true;
  }

  if (options.dataset && typeof options.dataset === 'object') {
    Object.entries(options.dataset).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        element.dataset[key] = String(value);
      }
    });
  }

  if (options.attributes && typeof options.attributes === 'object') {
    Object.entries(options.attributes).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        element.setAttribute(key, String(value));
      }
    });
  }

  if (Array.isArray(options.children)) {
    options.children.forEach((child) => {
      if (child instanceof Node) {
        element.appendChild(child);
      }
    });
  }

  return element;
}

function bindPrototypeChain(target, prototype) {
  if (!prototype || prototype === Object.prototype) {
    return;
  }

  bindPrototypeChain(target, Object.getPrototypeOf(prototype));

  Object.getOwnPropertyNames(prototype).forEach((name) => {
    if (name === 'constructor' || name === 'initialize') {
      return;
    }

    const descriptor = Object.getOwnPropertyDescriptor(prototype, name);
    if (descriptor && typeof descriptor.value === 'function') {
      target[name] = descriptor.value.bind(target);
    }
  });
}

export function createComponentInstance(ComponentClass, tagName, options = {}) {
  const element = createElement(tagName, options);
  bindPrototypeChain(element, ComponentClass.prototype);

  if (typeof ComponentClass.prototype.initialize === 'function') {
    element.initialize = ComponentClass.prototype.initialize.bind(element);
    element.initialize(options);
  }

  return element;
}
