import { BaseComponent } from './BaseComponent.js';

const SIZE_CLASSES = {
  xs: 'text-xs',
  sm: 'text-sm',
  md: 'text-base',
  lg: 'text-lg',
  xl: 'text-xl',
};

const WEIGHT_CLASSES = {
  normal: 'font-normal',
  medium: 'font-medium',
  semibold: 'font-semibold',
  bold: 'font-bold',
};

const ALIGN_CLASSES = {
  left: 'text-left',
  center: 'text-center',
  right: 'text-right',
};

export class TypographyComponent extends BaseComponent {
  initialize(options = {}) {
    const text = typeof options.text === 'string' ? options.text : '';
    const size = options.size ?? 'md';
    const weight = options.weight ?? 'medium';
    const color = options.color ?? 'slate-700';

    this.className = [
      SIZE_CLASSES[size] ?? SIZE_CLASSES.md,
      WEIGHT_CLASSES[weight] ?? WEIGHT_CLASSES.medium,
      `text-${color}`,
      ALIGN_CLASSES[options.align] ?? '',
    ].join(' ').trim();
    this.textContent = text;

    if (options.className) {
      this.className = `${this.className} ${options.className}`.trim();
    }

    return this;
  }

  setAlign(align) {
    Object.values(ALIGN_CLASSES).forEach((cls) => this.classList.remove(cls));
    const next = ALIGN_CLASSES[align];
    if (next) {
      this.classList.add(next);
    }
    return this;
  }
}
