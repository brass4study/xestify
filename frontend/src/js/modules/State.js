/**
 * State.js — Minimal global state container for Xestify frontend.
 *
 * Requirements:
 *   - Plain vanilla object
 *   - Simple setter/getter methods
 *   - No listeners and no Proxy
 */

export const AppState = {
  /** @type {object|null} */
  user: null,

  /** @type {string|null} */
  currentEntity: null,

  /** @type {Array<object>} */
  entities: [],

  /** @type {Array<object>} */
  records: [],

  /** @type {object} */
  metadata: {},

  /** @type {string|null} */
  token: null,

  /** @type {boolean} */
  loading: false,

  /** @type {object|null} */
  error: null,

  setUser(user) {
    this.user = user ?? null;
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

  reset() {
    this.user = null;
    this.currentEntity = null;
    this.entities = [];
    this.records = [];
    this.metadata = {};
    this.token = null;
    this.loading = false;
    this.error = null;
  },
};
