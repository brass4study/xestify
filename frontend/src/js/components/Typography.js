import { BaseComponent } from './BaseComponent.js';

export class TypographyComponent extends BaseComponent {
  initialize(options = {}) {
    const text = typeof options.text === 'string' ? options.text : '';
    const size = options.size ?? 'md';
    const weight = options.weight ?? 'medium';
    const color = options.color ?? 'slate-700';

    const sizeClasses = {
      xs: 'text-xs',
      sm: 'text-sm',
      md: 'text-base',
      lg: 'text-lg',
      xl: 'text-xl',
    };

    const weightClasses = {
      normal: 'font-normal',
      medium: 'font-medium',
      semibold: 'font-semibold',
      bold: 'font-bold',
    };

    this.className = [sizeClasses[size] ?? sizeClasses.md, weightClasses[weight] ?? weightClasses.medium, `text-${color}`].join(' ');
    this.textContent = text;

    if (options.className) {
      this.className = `${this.className} ${options.className}`.trim();
    }

    return this;
  }
}
