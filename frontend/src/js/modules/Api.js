/**
 * Api.js — Generic HTTP client for Xestify frontend.
 *
 * Wraps fetch() with:
 *   - Automatic Authorization: Bearer <token> header when a token is set
 *   - JSON request/response handling
 *   - Enveloped response validation ({ ok, data, error })
 *   - Basic error propagation via ApiError
 *
 * Usage:
 *   import { Api } from './modules/Api.js';
 *   const api = new Api('/api/v1');
 *   api.setToken(localStorage.getItem('token'));
 *   const { data } = await api.get('/entities/client/records');
 */

export class ApiError extends Error {
  /**
   * @param {number} code     HTTP status code or API error code
   * @param {string} message  Human-readable error message
   * @param {object} details  Per-field validation errors (may be empty)
   */
  constructor(code, message, details = {}) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
    this.details = details;
  }
}

export class Api {
  /** @type {string} */
  #baseUrl;

  /** @type {string|null} */
  #token = null;

  /**
   * @param {string} baseUrl  Base URL prefix, e.g. '/api/v1'
   */
  constructor(baseUrl = '/api/v1') {
    this.#baseUrl = baseUrl.replace(/\/$/, '');
  }

  /**
   * Store a Bearer token sent on every subsequent request.
   * Pass null to clear the token (logout).
   *
   * @param {string|null} token
   */
  setToken(token) {
    this.#token = token ?? null;
  }

  /**
   * GET request.
   *
   * @param {string} path
   * @returns {Promise<{data: any, meta?: object}>}
   */
  async get(path) {
    return this.#request('GET', path);
  }

  /**
   * POST request.
   *
   * @param {string} path
   * @param {object} body
   * @returns {Promise<{data: any, meta?: object}>}
   */
  async post(path, body = {}) {
    return this.#request('POST', path, body);
  }

  /**
   * PUT request.
   *
   * @param {string} path
   * @param {object} body
   * @returns {Promise<{data: any, meta?: object}>}
   */
  async put(path, body = {}) {
    return this.#request('PUT', path, body);
  }

  /**
   * DELETE request.
   *
   * @param {string} path
   * @returns {Promise<{data: any, meta?: object}>}
   */
  async delete(path) {
    return this.#request('DELETE', path);
  }

  // ---------------------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------------------

  /**
   * Build request headers, injecting the Bearer token when available.
   *
   * @returns {Record<string, string>}
   */
  #buildHeaders() {
    const headers = { 'Content-Type': 'application/json' };

    if (this.#token !== null) {
      headers['Authorization'] = `Bearer ${this.#token}`;
    }

    return headers;
  }

  /**
   * Execute the fetch call, parse the enveloped response, and return
   * { data, meta } on success or throw ApiError on failure.
   *
   * @param {string}  method
   * @param {string}  path
   * @param {object|undefined} body
   * @returns {Promise<{data: any, meta?: object}>}
   */
  async #request(method, path, body) {
    const url = `${this.#baseUrl}${path}`;

    const init = {
      method,
      headers: this.#buildHeaders(),
    };

    if (body !== undefined) {
      init.body = JSON.stringify(body);
    }

    let response;
    try {
      response = await fetch(url, init);
    } catch {
      throw new ApiError(0, 'Network error — server unreachable');
    }

    let envelope;
    try {
      envelope = await response.json();
    } catch {
      throw new ApiError(response.status, 'Invalid JSON response from server');
    }

    if (envelope.ok === true) {
      return { data: envelope.data, meta: envelope.meta };
    }

    const err = envelope.error ?? {};
    throw new ApiError(
      err.code ?? response.status,
      err.message ?? 'Unknown error',
      err.details ?? {}
    );
  }
}
