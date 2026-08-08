import { AppState } from './StateModel.js';

const AUTH_STORAGE_KEYS = Object.freeze({
  token: 'xestify_access_token',
  email: 'xestify_user_email',
  name: 'xestify_user_name',
  avatar: 'xestify_user_avatar',
});

export const SessionModel = {
  readStoredSession(storage = localStorage) {
    return {
      token: storage.getItem(AUTH_STORAGE_KEYS.token),
      email: storage.getItem(AUTH_STORAGE_KEYS.email),
      name: storage.getItem(AUTH_STORAGE_KEYS.name),
      avatar: storage.getItem(AUTH_STORAGE_KEYS.avatar),
    };
  },

  persistAccessToken(token, storage = localStorage) {
    storage.setItem(AUTH_STORAGE_KEYS.token, token);
  },

  persistUserSnapshot(user, storage = localStorage) {
    const normalizedUser = user && typeof user === 'object' ? user : {};

    if (typeof normalizedUser.email === 'string' && normalizedUser.email !== '') {
      storage.setItem(AUTH_STORAGE_KEYS.email, normalizedUser.email);
    } else {
      storage.removeItem(AUTH_STORAGE_KEYS.email);
    }

    if (typeof normalizedUser.name === 'string' && normalizedUser.name !== '') {
      storage.setItem(AUTH_STORAGE_KEYS.name, normalizedUser.name);
    } else {
      storage.removeItem(AUTH_STORAGE_KEYS.name);
    }

    if (typeof normalizedUser.avatar === 'string' && normalizedUser.avatar !== '') {
      storage.setItem(AUTH_STORAGE_KEYS.avatar, normalizedUser.avatar);
    } else {
      storage.removeItem(AUTH_STORAGE_KEYS.avatar);
    }
  },

  clearStoredSession(storage = localStorage) {
    storage.removeItem(AUTH_STORAGE_KEYS.token);
    storage.removeItem(AUTH_STORAGE_KEYS.email);
    storage.removeItem(AUTH_STORAGE_KEYS.name);
    storage.removeItem(AUTH_STORAGE_KEYS.avatar);
  },

  buildFallbackUser(token, overrides = {}) {
    const payload = decodeJwtPayload(token);
    const roleList = Array.isArray(payload?.roles) ? payload.roles : [];

    return {
      email: resolveUserString(overrides.email, typeof payload?.email === 'string' ? payload.email : null),
      name: resolveUserString(overrides.name, null),
      avatar: resolveUserString(overrides.avatar, null),
      roles: roleList,
    };
  },

  normalizeUserProfile(profile, fallbackUser = null) {
    const baseUser = getUserObject(profile);
    const fallback = getUserObject(fallbackUser);

    return {
      id: resolveUserString(baseUser.id, fallback.id),
      email: resolveUserString(baseUser.email, fallback.email),
      name: resolveUserString(baseUser.name, fallback.name),
      avatar: resolveUserString(baseUser.avatar, fallback.avatar),
      created_at: resolveDateField(
        baseUser.created_at,
        baseUser.creation_stamp,
        fallback.created_at,
        fallback.creation_stamp
      ),
      roles: resolveUserRoles(baseUser.roles, fallback.roles),
    };
  },

  setToken(token) {
    AppState.setToken(token);
  },

  getToken() {
    return AppState.getToken();
  },

  setUser(user) {
    AppState.setUser(user);
  },

  getUser() {
    return AppState.getUser();
  },

  getUserEmail() {
    return AppState.getUserEmail();
  },

  setEntities(entities) {
    AppState.setEntities(entities);
  },

  subscribe(listener) {
    return AppState.subscribe(listener);
  },

  reset() {
    AppState.reset();
  },

  currentUserIsAdmin() {
    const user = AppState.getUser();
    if (user === null || typeof user !== 'object') {
      return false;
    }

    return Array.isArray(user.roles) && user.roles.includes('admin');
  },
};

function decodeJwtPayload(token) {
  if (typeof token !== 'string' || token === '') {
    return null;
  }

  const parts = token.split('.');
  if (parts.length < 2) {
    return null;
  }

  try {
    const base64 = parts[1].replaceAll('-', '+').replaceAll('_', '/');
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
    const decoded = atob(padded);
    return JSON.parse(decoded);
  } catch {
    return null;
  }
}

function getUserObject(value) {
  return value && typeof value === 'object' ? value : {};
}

function resolveUserString(value, fallbackValue) {
  if (typeof value === 'string') {
    return value;
  }

  if (typeof fallbackValue === 'string') {
    return fallbackValue;
  }

  return null;
}

function resolveDateField(primaryValue, secondaryValue, fallbackPrimaryValue, fallbackSecondaryValue) {
  const direct = resolveUserString(primaryValue, secondaryValue);
  if (direct !== null) {
    return direct;
  }

  return resolveUserString(fallbackPrimaryValue, fallbackSecondaryValue);
}

function resolveUserRoles(value, fallbackValue) {
  if (Array.isArray(value)) {
    return value;
  }

  if (Array.isArray(fallbackValue)) {
    return fallbackValue;
  }

  return [];
}
