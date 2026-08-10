const STORAGE_USER_EMAIL_KEY = 'xestify_user_email';
const STORAGE_USER_NAME_KEY = 'xestify_user_name';
const STORAGE_USER_AVATAR_KEY = 'xestify_user_avatar';

import {
	createDefaultUiPreferences,
	mergeUiPreferences,
	persistUiPreferences,
	readStoredUiPreferences,
} from './ThemeModel.js';

function normalizeRoles(roles) {
	if (Array.isArray(roles)) {
		return roles.filter((role) => typeof role === 'string' && role !== '');
	}

	if (typeof roles === 'string') {
		const trimmed = roles.trim();
		if (trimmed === '') {
			return [];
		}

		try {
			const parsed = JSON.parse(trimmed);
			return normalizeRoles(parsed);
		} catch {
			return [trimmed];
		}
	}

	return [];
}

function normalizeUser(user) {
	if (user === null || typeof user !== 'object') {
		return null;
	}

	const normalized = { ...user };
	normalized.roles = normalizeRoles(normalized.roles);
	return normalized;
}

function persistUserIdentity(user) {
	if (typeof window === 'undefined' || !window.localStorage) {
		return;
	}

	if (user === null || typeof user !== 'object') {
		window.localStorage.removeItem(STORAGE_USER_EMAIL_KEY);
		window.localStorage.removeItem(STORAGE_USER_NAME_KEY);
		window.localStorage.removeItem(STORAGE_USER_AVATAR_KEY);
		return;
	}

	if (typeof user.email === 'string' && user.email !== '') {
		window.localStorage.setItem(STORAGE_USER_EMAIL_KEY, user.email);
	} else {
		window.localStorage.removeItem(STORAGE_USER_EMAIL_KEY);
	}

	if (typeof user.name === 'string' && user.name !== '') {
		window.localStorage.setItem(STORAGE_USER_NAME_KEY, user.name);
	} else {
		window.localStorage.removeItem(STORAGE_USER_NAME_KEY);
	}

	if (typeof user.avatar === 'string' && user.avatar !== '') {
		window.localStorage.setItem(STORAGE_USER_AVATAR_KEY, user.avatar);
	} else {
		window.localStorage.removeItem(STORAGE_USER_AVATAR_KEY);
	}
}

export const AppState = {
	user: null,
	listeners: [],
	uiListeners: [],
	currentEntity: null,
	entities: [],
	records: [],
	metadata: {},
	token: null,
	loading: false,
	error: null,
	notification: null,
	navigationState: {},
	uiPreferences: readStoredUiPreferences(typeof window !== 'undefined' ? window.localStorage : null),

	subscribe(listener) {
		if (typeof listener !== 'function') {
			return () => {};
		}

		this.listeners.push(listener);
		return () => {
			this.listeners = this.listeners.filter((entry) => entry !== listener);
		};
	},

	notify() {
		for (const listener of this.listeners) {
			listener(this.user);
		}
	},

	subscribeUi(listener) {
		if (typeof listener !== 'function') {
			return () => {};
		}

		this.uiListeners.push(listener);
		return () => {
			this.uiListeners = this.uiListeners.filter((entry) => entry !== listener);
		};
	},

	notifyUi() {
		const snapshot = this.getUiPreferences();
		for (const listener of this.uiListeners) {
			listener(snapshot);
		}
	},

	setUser(user) {
		this.user = normalizeUser(user ?? null);
		persistUserIdentity(this.user);
		this.notify();
	},

	getUser() {
		return this.user;
	},

	setCurrentEntity(entitySlug) {
		this.currentEntity = entitySlug ?? null;
	},

	getCurrentEntity() {
		return this.currentEntity;
	},

	setEntities(entities) {
		this.entities = Array.isArray(entities) ? [...entities] : [];
	},

	getEntities() {
		return this.entities;
	},

	setRecords(records) {
		this.records = Array.isArray(records) ? [...records] : [];
	},

	getRecords() {
		return this.records;
	},

	setMetadata(metadata) {
		this.metadata = metadata && typeof metadata === 'object' ? { ...metadata } : {};
	},

	getMetadata() {
		return this.metadata;
	},

	setToken(token) {
		this.token = token ?? null;
	},

	getToken() {
		return this.token;
	},

	getUserEmail() {
		if (this.user !== null && typeof this.user === 'object' && typeof this.user.email === 'string') {
			return this.user.email;
		}
		return null;
	},

	setLoading(isLoading) {
		this.loading = Boolean(isLoading);
	},

	isLoading() {
		return this.loading;
	},

	setError(error) {
		this.error = error ?? null;
	},

	getError() {
		return this.error;
	},

	setNotification(notification) {
		this.notification = notification && typeof notification === 'object' ? { ...notification } : null;
	},

	getNotification() {
		return this.notification;
	},

	clearNotification() {
		this.notification = null;
	},

	setNavigationState(state) {
		this.navigationState = state && typeof state === 'object' ? { ...state } : {};
	},

	getNavigationState() {
		return this.navigationState;
	},

	setUiPreferences(preferences) {
		this.uiPreferences = mergeUiPreferences(createDefaultUiPreferences(), preferences);
		persistUiPreferences(this.uiPreferences, typeof window !== 'undefined' ? window.localStorage : null);
		this.notifyUi();
	},

	updateUiPreferences(patch) {
		this.uiPreferences = mergeUiPreferences(this.uiPreferences, patch);
		persistUiPreferences(this.uiPreferences, typeof window !== 'undefined' ? window.localStorage : null);
		this.notifyUi();
	},

	getUiPreferences() {
		return mergeUiPreferences(createDefaultUiPreferences(), this.uiPreferences);
	},

	resetUiPreferences() {
		this.uiPreferences = createDefaultUiPreferences();
		persistUiPreferences(this.uiPreferences, typeof window !== 'undefined' ? window.localStorage : null);
		this.notifyUi();
	},

	reset() {
		this.user = null;
		this.currentEntity = null;
		this.entities = [];
		this.records = [];
		this.metadata = {};
		this.token = null;
		this.loading = false;
		this.error = null;
		this.notification = null;
		this.navigationState = {};
		this.notify();
	},
};
