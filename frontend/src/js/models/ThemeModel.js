const UI_STORAGE_KEY = 'xestify_ui_preferences';

let currentUiPreferences;
let uiListeners = [];

const PRIMARY_THEME_PRESETS = Object.freeze({
  blue: Object.freeze({
    50: '#eef8ff',
    100: '#d7edff',
    200: '#afd8ff',
    300: '#7dbdff',
    400: '#489bff',
    500: '#1e79ff',
    600: '#125fdc',
    700: '#124daf',
    800: '#154389',
    900: '#173c72',
  }),
  sky: Object.freeze({
    50: '#eff6ff',
    100: '#dbeafe',
    200: '#bfdbfe',
    300: '#93c5fd',
    400: '#60a5fa',
    500: '#3b82f6',
    600: '#2563eb',
    700: '#1d4ed8',
    800: '#1e40af',
    900: '#1e3a8a',
  }),
  cyan: Object.freeze({
    50: '#ecfeff',
    100: '#cffafe',
    200: '#a5f3fc',
    300: '#67e8f9',
    400: '#22d3ee',
    500: '#06b6d4',
    600: '#0891b2',
    700: '#0e7490',
    800: '#155e75',
    900: '#164e63',
  }),
  green: Object.freeze({
    50: '#f0fdf4',
    100: '#dcfce7',
    200: '#bbf7d0',
    300: '#86efac',
    400: '#4ade80',
    500: '#22c55e',
    600: '#16a34a',
    700: '#15803d',
    800: '#166534',
    900: '#14532d',
  }),
  teal: Object.freeze({
    50: '#f0fdfa',
    100: '#ccfbf1',
    200: '#99f6e4',
    300: '#5eead4',
    400: '#2dd4bf',
    500: '#14b8a6',
    600: '#0d9488',
    700: '#0f766e',
    800: '#115e59',
    900: '#134e4a',
  }),
  amber: Object.freeze({
    50: '#fffbeb',
    100: '#fef3c7',
    200: '#fde68a',
    300: '#fcd34d',
    400: '#fbbf24',
    500: '#f59e0b',
    600: '#d97706',
    700: '#b45309',
    800: '#92400e',
    900: '#78350f',
  }),
  orange: Object.freeze({
    50: '#fff7ed',
    100: '#ffedd5',
    200: '#fed7aa',
    300: '#fdba74',
    400: '#fb923c',
    500: '#f97316',
    600: '#ea580c',
    700: '#c2410c',
    800: '#9a3412',
    900: '#7c2d12',
  }),
  red: Object.freeze({
    50: '#fef2f2',
    100: '#fee2e2',
    200: '#fecaca',
    300: '#fca5a5',
    400: '#f87171',
    500: '#ef4444',
    600: '#dc2626',
    700: '#b91c1c',
    800: '#991b1b',
    900: '#7f1d1d',
  }),
  indigo: Object.freeze({
    50: '#eef2ff',
    100: '#e0e7ff',
    200: '#c7d2fe',
    300: '#a5b4fc',
    400: '#818cf8',
    500: '#6366f1',
    600: '#4f46e5',
    700: '#4338ca',
    800: '#3730a3',
    900: '#312e81',
  }),
  violet: Object.freeze({
    50: '#f5f3ff',
    100: '#ede9fe',
    200: '#ddd6fe',
    300: '#c4b5fd',
    400: '#a78bfa',
    500: '#8b5cf6',
    600: '#7c3aed',
    700: '#6d28d9',
    800: '#5b21b6',
    900: '#4c1d95',
  }),
});

const SECONDARY_COLOR_PRESETS = Object.freeze({
  slate: '#0f172a',
  ink: '#172033',
  graphite: '#334155',
  stone: '#44403c',
});

const ACCENT_COLOR_PRESETS = Object.freeze({
  amber: '#f59e0b',
  coral: '#fb7185',
  lime: '#84cc16',
  cyan: '#06b6d4',
});

const FONT_FAMILY_PRESETS = Object.freeze({
  plex: '"IBM Plex Sans", system-ui, sans-serif',
  system: 'system-ui, sans-serif',
  serif: '"Source Serif 4", Georgia, serif',
});

const RADIUS_PRESETS = Object.freeze({
  compact: Object.freeze({
    surface: '0.5rem',
    control: '0.375rem',
  }),
  comfortable: Object.freeze({
    surface: '0.75rem',
    control: '0.5rem',
  }),
  expressive: Object.freeze({
    surface: '1rem',
    control: '0.75rem',
  }),
});

export const DEFAULT_UI_PREFERENCES = Object.freeze({
  pageStyle: 'light',
  menuStyle: 'brand',
  themeColor: 'blue',
  navigationMode: 'top',
  sideMenuType: 'default',
  contentWidth: 'boxed',
  fixedHeader: true,
  fixedSidebar: false,
  splitMenus: false,
  weakMode: false,
  regions: Object.freeze({
    header: true,
    footer: true,
    menu: true,
    menuHeader: true,
  }),
  tokens: Object.freeze({
    secondaryColor: 'slate',
    accentColor: 'amber',
    fontFamilyBase: 'plex',
    radiusPreset: 'comfortable',
    borderStyle: 'soft',
  }),
});

export const UI_THEME_SCHEMA = Object.freeze({
  pageStyle: Object.freeze([
    Object.freeze({ value: 'light', label: 'Claro' }),
    Object.freeze({ value: 'dark', label: 'Oscuro' }),
  ]),
  menuStyle: Object.freeze([
    Object.freeze({ value: 'brand', label: 'Marca' }),
    Object.freeze({ value: 'dark', label: 'Oscuro' }),
    Object.freeze({ value: 'light', label: 'Claro' }),
  ]),
  themeColor: Object.freeze([
    Object.freeze({ value: 'blue', label: 'Azul', swatch: '#1e79ff' }),
    Object.freeze({ value: 'sky', label: 'Sky', swatch: '#3b82f6' }),
    Object.freeze({ value: 'red', label: 'Rojo', swatch: '#ef4444' }),
    Object.freeze({ value: 'orange', label: 'Orange', swatch: '#f97316' }),
    Object.freeze({ value: 'amber', label: 'Ambar', swatch: '#f59e0b' }),
    Object.freeze({ value: 'teal', label: 'Teal', swatch: '#14b8a6' }),
    Object.freeze({ value: 'green', label: 'Verde', swatch: '#22c55e' }),
    Object.freeze({ value: 'indigo', label: 'Indigo', swatch: '#6366f1' }),
    Object.freeze({ value: 'violet', label: 'Violeta', swatch: '#8b5cf6' }),
  ]),
  navigationMode: Object.freeze([
    Object.freeze({ value: 'top', label: 'Menú superior' }),
    Object.freeze({ value: 'side', label: 'Menú lateral' }),
    Object.freeze({ value: 'mixed', label: 'Mixto' }),
  ]),
  sideMenuType: Object.freeze([
    Object.freeze({ value: 'default', label: 'Expandido' }),
    Object.freeze({ value: 'compact', label: 'Compacto' }),
  ]),
  contentWidth: Object.freeze([
    Object.freeze({ value: 'boxed', label: 'Boxed' }),
    Object.freeze({ value: 'fluid', label: 'Fluid' }),
  ]),
  secondaryColor: Object.freeze([
    Object.freeze({ value: 'slate', label: 'Slate', swatch: '#0f172a' }),
    Object.freeze({ value: 'ink', label: 'Ink', swatch: '#172033' }),
    Object.freeze({ value: 'graphite', label: 'Graphite', swatch: '#334155' }),
    Object.freeze({ value: 'stone', label: 'Stone', swatch: '#44403c' }),
  ]),
  accentColor: Object.freeze([
    Object.freeze({ value: 'amber', label: 'Ambar', swatch: '#f59e0b' }),
    Object.freeze({ value: 'coral', label: 'Coral', swatch: '#fb7185' }),
    Object.freeze({ value: 'lime', label: 'Lime', swatch: '#84cc16' }),
    Object.freeze({ value: 'cyan', label: 'Cian', swatch: '#06b6d4' }),
  ]),
  fontFamilyBase: Object.freeze([
    Object.freeze({ value: 'plex', label: 'IBM Plex Sans' }),
    Object.freeze({ value: 'system', label: 'System UI' }),
    Object.freeze({ value: 'serif', label: 'Source Serif' }),
  ]),
  radiusPreset: Object.freeze([
    Object.freeze({ value: 'compact', label: 'Compacto' }),
    Object.freeze({ value: 'comfortable', label: 'Confort' }),
    Object.freeze({ value: 'expressive', label: 'Expresivo' }),
  ]),
  borderStyle: Object.freeze([
    Object.freeze({ value: 'soft', label: 'Suave' }),
    Object.freeze({ value: 'sharp', label: 'Marcado' }),
  ]),
});

export function createDefaultUiPreferences() {
  return normalizeUiPreferences(DEFAULT_UI_PREFERENCES);
}

currentUiPreferences = readStoredUiPreferences(typeof window !== 'undefined' ? window.localStorage : null);

export function getUiThemeSchema() {
  return UI_THEME_SCHEMA;
}

export function getPrimaryThemePreset(themeColor) {
  return PRIMARY_THEME_PRESETS[themeColor] ?? PRIMARY_THEME_PRESETS.blue;
}

export function readStoredUiPreferences(storage = null) {
  if (!storage || typeof storage.getItem !== 'function') {
    return createDefaultUiPreferences();
  }

  const rawValue = storage.getItem(UI_STORAGE_KEY);
  if (typeof rawValue !== 'string' || rawValue.trim() === '') {
    return createDefaultUiPreferences();
  }

  try {
    return normalizeUiPreferences(JSON.parse(rawValue));
  } catch {
    return createDefaultUiPreferences();
  }
}

export function persistUiPreferences(settings, storage = null) {
  if (!storage || typeof storage.setItem !== 'function') {
    return;
  }

  storage.setItem(UI_STORAGE_KEY, JSON.stringify(normalizeUiPreferences(settings)));
}

export function subscribeUi(listener) {
  if (typeof listener !== 'function') {
    return () => {};
  }

  uiListeners.push(listener);
  return () => {
    uiListeners = uiListeners.filter((entry) => entry !== listener);
  };
}

export function notifyUi() {
  const snapshot = getUiPreferences();
  for (const listener of uiListeners) {
    listener(snapshot);
  }
}

export function setUiPreferences(preferences) {
  currentUiPreferences = mergeUiPreferences(createDefaultUiPreferences(), preferences);
  persistUiPreferences(currentUiPreferences, typeof window !== 'undefined' ? window.localStorage : null);
  notifyUi();
}

export function updateUiPreferences(patch) {
  currentUiPreferences = mergeUiPreferences(currentUiPreferences, patch);
  persistUiPreferences(currentUiPreferences, typeof window !== 'undefined' ? window.localStorage : null);
  notifyUi();
}

export function getUiPreferences() {
  return mergeUiPreferences(createDefaultUiPreferences(), currentUiPreferences);
}

export function resetUiPreferences() {
  currentUiPreferences = createDefaultUiPreferences();
  persistUiPreferences(currentUiPreferences, typeof window !== 'undefined' ? window.localStorage : null);
  notifyUi();
}

export function normalizeUiPreferences(value) {
  const source = value && typeof value === 'object' ? value : {};
  const defaults = DEFAULT_UI_PREFERENCES;
  const sourceRegions = source.regions && typeof source.regions === 'object' ? source.regions : {};
  const sourceTokens = source.tokens && typeof source.tokens === 'object' ? source.tokens : {};

  return {
    pageStyle: normalizeOption(source.pageStyle, defaults.pageStyle, UI_THEME_SCHEMA.pageStyle),
    menuStyle: normalizeOption(source.menuStyle, defaults.menuStyle, UI_THEME_SCHEMA.menuStyle),
    themeColor: normalizeOption(source.themeColor, defaults.themeColor, UI_THEME_SCHEMA.themeColor),
    navigationMode: normalizeOption(source.navigationMode, defaults.navigationMode, UI_THEME_SCHEMA.navigationMode),
    sideMenuType: normalizeOption(source.sideMenuType, defaults.sideMenuType, UI_THEME_SCHEMA.sideMenuType),
    contentWidth: normalizeOption(source.contentWidth, defaults.contentWidth, UI_THEME_SCHEMA.contentWidth),
    fixedHeader: normalizeBoolean(source.fixedHeader, defaults.fixedHeader),
    fixedSidebar: normalizeBoolean(source.fixedSidebar, defaults.fixedSidebar),
    splitMenus: normalizeBoolean(source.splitMenus, defaults.splitMenus),
    weakMode: normalizeBoolean(source.weakMode, defaults.weakMode),
    regions: {
      header: normalizeBoolean(sourceRegions.header, defaults.regions.header),
      footer: normalizeBoolean(sourceRegions.footer, defaults.regions.footer),
      menu: normalizeBoolean(sourceRegions.menu, defaults.regions.menu),
      menuHeader: normalizeBoolean(sourceRegions.menuHeader, defaults.regions.menuHeader),
    },
    tokens: {
      secondaryColor: normalizeOption(sourceTokens.secondaryColor, defaults.tokens.secondaryColor, UI_THEME_SCHEMA.secondaryColor),
      accentColor: normalizeOption(sourceTokens.accentColor, defaults.tokens.accentColor, UI_THEME_SCHEMA.accentColor),
      fontFamilyBase: normalizeOption(sourceTokens.fontFamilyBase, defaults.tokens.fontFamilyBase, UI_THEME_SCHEMA.fontFamilyBase),
      radiusPreset: normalizeOption(sourceTokens.radiusPreset, defaults.tokens.radiusPreset, UI_THEME_SCHEMA.radiusPreset),
      borderStyle: normalizeOption(sourceTokens.borderStyle, defaults.tokens.borderStyle, UI_THEME_SCHEMA.borderStyle),
    },
  };
}

export function mergeUiPreferences(baseSettings, patch) {
  return normalizeUiPreferences({
    ...normalizeUiPreferences(baseSettings),
    ...(patch && typeof patch === 'object' ? patch : {}),
    regions: {
      ...normalizeUiPreferences(baseSettings).regions,
      ...(patch?.regions && typeof patch.regions === 'object' ? patch.regions : {}),
    },
    tokens: {
      ...normalizeUiPreferences(baseSettings).tokens,
      ...(patch?.tokens && typeof patch.tokens === 'object' ? patch.tokens : {}),
    },
  });
}

export function applyUiPreferencesToDocument(settings, options = {}) {
  const normalized = normalizeUiPreferences(settings);
  const root = resolveHTMLElement(options.root, document.documentElement);
  const body = resolveHTMLElement(options.body, document.body);
  const app = resolveHTMLElement(options.app, document.getElementById('app'));
  const palette = getPrimaryThemePreset(normalized.themeColor);
  const secondaryColor = SECONDARY_COLOR_PRESETS[normalized.tokens.secondaryColor] ?? SECONDARY_COLOR_PRESETS.slate;
  const accentColor = ACCENT_COLOR_PRESETS[normalized.tokens.accentColor] ?? ACCENT_COLOR_PRESETS.amber;
  const fontFamily = FONT_FAMILY_PRESETS[normalized.tokens.fontFamilyBase] ?? FONT_FAMILY_PRESETS.plex;
  const radiusPreset = RADIUS_PRESETS[normalized.tokens.radiusPreset] ?? RADIUS_PRESETS.comfortable;

  setThemeDataset(root, normalized);
  setThemeDataset(body, normalized);
  setThemeDataset(app, normalized);

  setCssVariable(root, '--x-ui-font-sans', fontFamily);
  setCssVariable(root, '--x-ui-secondary', secondaryColor);
  setCssVariable(root, '--x-ui-accent', accentColor);
  setCssVariable(root, '--x-ui-surface-radius', radiusPreset.surface);
  setCssVariable(root, '--x-ui-control-radius', radiusPreset.control);
  setCssVariable(root, '--x-ui-border-width', normalized.tokens.borderStyle === 'sharp' ? '2px' : '1px');
  setCssVariable(root, '--x-ui-shell-max-width', normalized.contentWidth === 'fluid' ? '100%' : '1280px');
  setCssVariable(root, '--x-ui-shell-background', normalized.pageStyle === 'dark' ? '#141414' : '#f1f5f9');
  setCssVariable(root, '--x-ui-page-foreground', normalized.pageStyle === 'dark' ? '#f5f5f5' : '#0f172a');
  setCssVariable(root, '--x-ui-page-surface', normalized.pageStyle === 'dark' ? '#1f1f1f' : '#ffffff');
  setCssVariable(root, '--x-ui-muted-surface', normalized.pageStyle === 'dark' ? '#262626' : '#e2e8f0');
  setCssVariable(root, '--x-ui-shell-glow-a', withAlpha(palette[500] ?? palette[600], normalized.pageStyle === 'dark' ? 0.08 : 0.14));
  setCssVariable(root, '--x-ui-shell-glow-b', withAlpha(palette[400] ?? palette[500], normalized.pageStyle === 'dark' ? 0.06 : 0.12));

  for (const [tone, color] of Object.entries(palette)) {
    setCssVariable(root, `--x-brand-${tone}`, color);
  }
}

function normalizeOption(value, fallback, options) {
  const allowedValues = options.map((entry) => entry.value);
  return allowedValues.includes(value) ? value : fallback;
}

function normalizeBoolean(value, fallback) {
  if (typeof value === 'boolean') {
    return value;
  }

  return fallback;
}

function resolveHTMLElement(candidate, fallback) {
  return candidate instanceof HTMLElement ? candidate : fallback;
}

function setThemeDataset(element, settings) {
  if (!(element instanceof HTMLElement)) {
    return;
  }

  element.dataset.uiPageStyle = settings.pageStyle;
  element.dataset.uiMenuStyle = settings.menuStyle;
  element.dataset.uiThemeColor = settings.themeColor;
  element.dataset.uiNavigationMode = settings.navigationMode;
  element.dataset.uiSideMenuType = settings.sideMenuType;
  element.dataset.uiContentWidth = settings.contentWidth;
  element.dataset.uiWeakMode = settings.weakMode ? 'true' : 'false';
  element.dataset.uiBorderStyle = settings.tokens.borderStyle;
}

function setCssVariable(element, propertyName, value) {
  if (!(element instanceof HTMLElement) || typeof propertyName !== 'string' || propertyName === '') {
    return;
  }

  element.style.setProperty(propertyName, value);
}

function withAlpha(hexColor, alpha) {
  if (typeof hexColor !== 'string' || !hexColor.startsWith('#') || (hexColor.length !== 7 && hexColor.length !== 4)) {
    return `rgba(30, 121, 255, ${alpha})`;
  }

  const normalized = hexColor.length === 4
    ? `#${hexColor[1]}${hexColor[1]}${hexColor[2]}${hexColor[2]}${hexColor[3]}${hexColor[3]}`
    : hexColor;

  const red = Number.parseInt(normalized.slice(1, 3), 16);
  const green = Number.parseInt(normalized.slice(3, 5), 16);
  const blue = Number.parseInt(normalized.slice(5, 7), 16);
  return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}
