import { BaseComponent } from './BaseComponent.js';
import { component } from '../modules/ComponentFactory.js';

const TRACK_CLASS_NAME = 'h-2 overflow-hidden rounded-full bg-slate-200';
const FILL_CLASS_NAME = 'h-full rounded-full';
const STRENGTH_COLORS = Object.freeze({
  red: [220, 38, 38],
  orange: [249, 115, 22],
  green: [22, 163, 74],
});

function normalizeRules(rules) {
  if (!Array.isArray(rules)) {
    return [];
  }
  return rules.filter((rule) => rule !== null
    && typeof rule === 'object'
    && typeof rule.key === 'string'
    && rule.key !== ''
    && typeof rule.label === 'string'
    && typeof rule.test === 'function');
}

function normalizeContext(context) {
  return context !== null && typeof context === 'object' ? context : {};
}

function interpolateChannel(start, end, ratio) {
  return Math.round(start + ((end - start) * ratio));
}

export function strengthColor(score) {
  const normalizedScore = Math.max(0, Math.min(100, Number(score) || 0));
  const isFirstHalf = normalizedScore <= 50;
  const start = isFirstHalf ? STRENGTH_COLORS.red : STRENGTH_COLORS.orange;
  const end = isFirstHalf ? STRENGTH_COLORS.orange : STRENGTH_COLORS.green;
  const ratio = isFirstHalf ? normalizedScore / 50 : (normalizedScore - 50) / 50;
  const channels = start.map((channel, index) => interpolateChannel(channel, end[index], ratio));
  return `rgb(${channels.join(' ')})`;
}

export function evaluatePasswordStrength(rules, value, context = {}) {
  const normalizedRules = normalizeRules(rules);
  const normalizedValue = String(value ?? '');
  const normalizedContext = normalizeContext(context);
  const states = Object.fromEntries(normalizedRules.map((rule) => [
    rule.key,
    rule.test(normalizedValue, normalizedContext) === true,
  ]));
  const metRules = Object.values(states).filter(Boolean).length;
  const totalRules = normalizedRules.length;
  const score = totalRules === 0 ? 0 : Math.round((metRules / totalRules) * 100);

  return {
    score,
    metRules,
    totalRules,
    isValid: totalRules > 0 && metRules === totalRules,
    rules: states,
    failedRules: normalizedRules.filter((rule) => states[rule.key] !== true),
  };
}

export class PasswordStrengthComponent extends BaseComponent {
  initialize(options = {}) {
    this.className = 'grid gap-2';
    this.dataset.role = 'password-strength';
    this.dataset.passwordStrength = '';
    this._rules = normalizeRules(options.rules);
    this._value = String(options.value ?? '');
    this._context = normalizeContext(options.context);
    this.hidden = options.visible === true ? false : this._value === '';
    this._renderedWidth = 0;
    this._renderedColor = strengthColor(0);

    this._track = component.create('div', {
      className: TRACK_CLASS_NAME,
      dataset: { role: 'password-meter' },
      attributes: { role: 'progressbar', 'aria-valuemin': '0' },
    });
    this._fill = this._createFill(0, strengthColor(0));
    this._fill.setParent(this._track);
    this._rulesList = component.create('div', {
      className: 'grid gap-1 text-xs text-slate-600',
      dataset: { role: 'password-rules' },
    });

    this._buildRules();
    this.appendChild(this._track);
    this.appendChild(this._rulesList);
    this.update({ value: this._value, context: this._context, visible: !this.hidden });
    return this;
  }

  update(options = {}) {
    if (Object.hasOwn(options, 'value')) {
      this._value = String(options.value ?? '');
    }
    if (Object.hasOwn(options, 'context')) {
      this._context = normalizeContext(options.context);
    }
    if (options.visible === true) {
      this.hidden = false;
    }

    const result = this.evaluate(this._value, this._context);
    this._renderResult(result);
    return result;
  }

  evaluate(value = this._value, context = this._context) {
    return evaluatePasswordStrength(this._rules, value, context);
  }

  _buildRules() {
    for (const rule of this._rules) {
      const row = component.create('div', {
        className: 'flex items-center gap-2',
        dataset: { role: 'password-rule', rule: rule.key },
      });
      component.create('span', {
        className: 'text-slate-400',
        text: '•',
        dataset: { role: 'password-rule-icon' },
      }).setParent(row);
      component.create('span', { text: rule.label }).setParent(row);
      row.setParent(this._rulesList);
    }
  }

  _createFill(width, color) {
    return component.create('div', {
      className: FILL_CLASS_NAME,
      dataset: { role: 'password-fill', width, color },
      attributes: { 'aria-hidden': 'true' },
    });
  }

  _renderResult(result) {
    this._track.setAttribute('aria-valuenow', String(result.metRules));
    this._track.setAttribute('aria-valuemax', String(result.totalRules));
    this.dataset.level = String(result.metRules);
    this.dataset.score = String(result.score);
    this.dataset.levelCount = String(result.totalRules + 1);
    const width = Math.round(((result.metRules + 1) / (result.totalRules + 1)) * 100);
    const color = strengthColor(result.score);
    this._fill.dataset.width = String(width);
    this._fill.dataset.color = color;
    this._fill.getAnimations().forEach((animation) => animation.cancel());
    this._fill.animate([
      { width: `${this._renderedWidth}%`, backgroundColor: this._renderedColor },
      { width: `${width}%`, backgroundColor: color },
    ], {
      duration: 200,
      easing: 'cubic-bezier(0, 0, 0.2, 1)',
      fill: 'forwards',
    });
    this._renderedWidth = width;
    this._renderedColor = color;

    this._rulesList.querySelectorAll('[data-role="password-rule"]').forEach((item) => {
      const ruleState = result.rules[item.dataset.rule] === true;
      item.classList.toggle('text-emerald-700', ruleState);
      item.classList.toggle('text-red-700', !ruleState);
      const icon = item.querySelector('[data-role="password-rule-icon"]');
      if (icon instanceof HTMLElement) {
        icon.className = ruleState ? 'text-emerald-600' : 'text-red-600';
        icon.textContent = ruleState ? '✓' : '✕';
      }
    });
  }
}