const DEFAULT_LOCALE = 'es';
let currentLocale = DEFAULT_LOCALE;

const TRANSLATIONS = Object.freeze({
  es: Object.freeze({
    'ui.loading': 'Cargando…',
    'ui.error.generic': 'Ha ocurrido un error inesperado.',
    'ui.error.network': 'No se pudo completar la solicitud. Revise la conexión e intente nuevamente.',
    'ui.error.title': 'Error',
    'ui.success': 'Éxito',
    'ui.actions.cancel': 'Cancelar',
    'ui.actions.confirm': 'Confirmar',
    'ui.actions.edit': 'Editar',
    'ui.actions.actions': 'Acciones',
    'forms.save': 'Guardar',
    'theme.panel.title': 'Configuración UI',
    'theme.panel.open': 'Abrir configuración visual',
    'theme.panel.close': 'Cerrar configuración visual',
    'theme.panel.ariaLabel': 'Configuración visual',
    'theme.sections.pageStyle': 'Estilo de página',
    'theme.sections.themeColor': 'Color del tema',
    'theme.sections.navigationMode': 'Modo de navegación',
    'forms.saving': 'Guardando…',
    'entities.empty.title': 'Sin entidades',
    'entities.empty.description': 'No hay entidades disponibles.',
    'users.empty.title': 'Sin usuarios',
    'users.empty.description': 'No hay usuarios disponibles todavía.',
    'users.avatar': 'Avatar',
    'plugins.sync': 'Sincronizar',
    'plugins.update': 'Actualizar',
    'plugins.rollback': 'Revertir',
    'plugins.configure': 'Configurar',
    'plugins.activate': 'Activar',
    'plugins.deactivate': 'Desactivar',
    'plugins.empty.title': 'No hay plugins instalados',
    'plugins.empty.description': 'Sincroniza los plugins disponibles para comenzar.',
    'plugins.status.active': 'Activo',
    'plugins.status.inactive': 'Inactivo',
    'plugins.update.available': 'Actualización disponible',
  }),
  en: Object.freeze({
    'ui.loading': 'Loading…',
    'ui.error.generic': 'An unexpected error occurred.',
    'ui.error.network': 'The request could not be completed. Please check your connection and try again.',
    'ui.error.title': 'Error',
    'ui.success': 'Success',
    'ui.actions.cancel': 'Cancel',
    'ui.actions.confirm': 'Confirm',
    'ui.actions.edit': 'Edit',
    'ui.actions.actions': 'Actions',
    'forms.save': 'Save',
    'theme.panel.title': 'UI Settings',
    'theme.panel.open': 'Open visual settings',
    'theme.panel.close': 'Close visual settings',
    'theme.panel.ariaLabel': 'Visual settings',
    'theme.sections.pageStyle': 'Page style',
    'theme.sections.themeColor': 'Theme color',
    'theme.sections.navigationMode': 'Navigation mode',
    'forms.saving': 'Saving…',
    'entities.empty.title': 'No entities',
    'entities.empty.description': 'No entities are available.',
    'users.empty.title': 'No users',
    'users.empty.description': 'No users are available yet.',
    'users.avatar': 'Avatar',
    'plugins.sync': 'Synchronize',
    'plugins.update': 'Update',
    'plugins.rollback': 'Rollback',
    'plugins.configure': 'Configure',
    'plugins.activate': 'Activate',
    'plugins.deactivate': 'Deactivate',
    'plugins.empty.title': 'No plugins installed',
    'plugins.empty.description': 'Synchronize the available plugins to get started.',
    'plugins.status.active': 'Active',
    'plugins.status.inactive': 'Inactive',
    'plugins.update.available': 'Update available',
  }),
});

export function setLocale(locale) {
  currentLocale = typeof locale === 'string' && locale in TRANSLATIONS ? locale : DEFAULT_LOCALE;
  return currentLocale;
}

export function t(key, fallback = '') {
  const locale = TRANSLATIONS[currentLocale] ?? TRANSLATIONS[DEFAULT_LOCALE];
  return locale[key] ?? fallback;
}
