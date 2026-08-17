/**
 * AxisGauge — semicircular 0-180° protractor that plots one colored line per
 * axis value (STORY 10.5). Shared between the optometries and contact-lenses
 * extension panels, which each render one gauge per eye.
 *
 * Read-only: it only reflects values the caller passes via setLines(), it
 * never lets the user drag/pick an angle.
 */

const SVG_NS = 'http://www.w3.org/2000/svg';

// Wide-short canvas keeps the gauge visually compact when rendered w-full
// inside an eye column. The vertical extents are deliberately tight: the
// topmost ink is the 90 label (baseline at CENTER_Y-104, glyph top ≈ y=2)
// and the bottommost is the 0/180/letter baseline row at y=CENTER_Y — so
// the viewBox height leaves no dead band above the graphic.
const RADIUS = 90;
const CENTER_X = 200;
const CENTER_Y = 116;
const VIEWBOX = '0 0 400 120';
const TICK_STEP_DEGREES = 6;
const ARC_STEP_DEGREES = 2;

/**
 * @param {number} degrees 0-180, 0 = right, 90 = top, 180 = left
 * @param {number} radius
 * @returns {{x: number, y: number}}
 */
function polarPoint(degrees, radius) {
  const rad = (degrees * Math.PI) / 180;
  return {
    x: CENTER_X + radius * Math.cos(rad),
    y: CENTER_Y - radius * Math.sin(rad),
  };
}

function svgEl(tag, attributes = {}) {
  const el = document.createElementNS(SVG_NS, tag);
  Object.entries(attributes).forEach(([name, value]) => el.setAttribute(name, String(value)));
  return el;
}

export class AxisGauge {
  /** @type {SVGSVGElement} */
  #element;

  /** @type {SVGGElement} */
  #linesGroup;

  /**
   * @param {{ letter: 'D'|'I' }} options
   */
  constructor({ letter }) {
    this.#element = this.#build(letter);
  }

  /** @returns {SVGSVGElement} */
  get element() {
    return this.#element;
  }

  /**
   * @param {Array<{ degrees: number|null|undefined, color: string }>} lines
   */
  setLines(lines) {
    this.#linesGroup.replaceChildren();

    for (const { degrees, color } of Array.isArray(lines) ? lines : []) {
      if (typeof degrees !== 'number' || Number.isNaN(degrees)) {
        continue;
      }
      const clamped = Math.min(180, Math.max(0, degrees));
      const { x, y } = polarPoint(clamped, RADIUS);
      this.#linesGroup.appendChild(svgEl('line', {
        x1: CENTER_X, y1: CENTER_Y, x2: x, y2: y,
        stroke: color, 'stroke-width': 2.5, 'stroke-linecap': 'round',
      }));
    }
  }

  /**
   * @param {'D'|'I'} letter
   * @returns {SVGSVGElement}
   */
  #build(letter) {
    const svg = svgEl('svg', { viewBox: VIEWBOX });
    svg.classList.add('w-full', 'h-auto');

    // Arc traced as short segments rather than a single <path> A-command —
    // sidesteps sweep-flag sign mistakes for a value that only matters
    // visually, not functionally.
    const arcPoints = [];
    for (let deg = 0; deg <= 180; deg += ARC_STEP_DEGREES) {
      const { x, y } = polarPoint(deg, RADIUS);
      arcPoints.push(`${x},${y}`);
    }
    svg.appendChild(svgEl('polyline', {
      points: arcPoints.join(' '), fill: 'none', stroke: '#94a3b8', 'stroke-width': 1.5,
    }));

    const ticks = svgEl('g');
    for (let deg = 0; deg <= 180; deg += TICK_STEP_DEGREES) {
      const inner = polarPoint(deg, RADIUS);
      const outer = polarPoint(deg, RADIUS + 8);
      ticks.appendChild(svgEl('line', {
        x1: inner.x, y1: inner.y, x2: outer.x, y2: outer.y, stroke: '#94a3b8', 'stroke-width': 1,
      }));
    }
    svg.appendChild(ticks);

    // Anchors point AWAY from the arc ('end' at 180 extends the text
    // leftward, 'start' at 0 rightward) so neither label overlaps the
    // ticks/arc. RADIUS+14 leaves ~6 units of clearance from the tick ends
    // (ticks reach RADIUS+8) — close, without touching.
    const labels = svgEl('g');
    [[180, 'end'], [90, 'middle'], [0, 'start']].forEach(([deg, anchor]) => {
      const { x, y } = polarPoint(deg, RADIUS + 14);
      const text = svgEl('text', {
        x, y, 'text-anchor': anchor, 'font-size': 10, fill: '#2563eb', 'font-weight': 600,
      });
      text.textContent = String(deg);
      labels.appendChild(text);
    });
    svg.appendChild(labels);

    const letterEl = svgEl('text', {
      x: CENTER_X, y: CENTER_Y, 'text-anchor': 'middle', 'font-size': 16,
      fill: '#334155', 'font-weight': 700,
    });
    letterEl.textContent = letter;
    svg.appendChild(letterEl);

    this.#linesGroup = svgEl('g');
    svg.appendChild(this.#linesGroup);

    return svg;
  }
}
