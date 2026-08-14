import { buildAppUrl } from './BasePathModel.js';

export const API_BASE_URL = buildAppUrl('/api/v1');

let onSessionExpired = null;

/**
 * Registers the single handler invoked when an authenticated request (one made with a
 * token set) comes back 401. A 401 on a request with no token (e.g. the login call
 * itself) never triggers this — that is an invalid-credentials response, not an expired
 * session.
 */
export function setSessionExpiredHandler(handler) {
	onSessionExpired = typeof handler === 'function' ? handler : null;
}

export class ApiError extends Error {
	constructor(code, message, details = {}) {
		super(message);
		this.name = 'ApiError';
		this.code = code;
		this.details = details;
	}
}

export class Api {
	#baseUrl;
	#token = null;

	constructor(baseUrl = API_BASE_URL) {
		this.#baseUrl = baseUrl.replace(/\/$/, '');
	}

	setToken(token) {
		this.#token = token ?? null;
	}

	async get(path) {
		return this.#request('GET', path);
	}

	async post(path, body = {}) {
		return this.#request('POST', path, body);
	}

	async put(path, body = {}) {
		return this.#request('PUT', path, body);
	}

	async delete(path) {
		return this.#request('DELETE', path);
	}

	#buildHeaders() {
		const headers = { 'Content-Type': 'application/json' };

		if (this.#token !== null) {
			headers.Authorization = `Bearer ${this.#token}`;
		}

		return headers;
	}

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

		if (response.status === 401 && this.#token !== null && onSessionExpired !== null) {
			onSessionExpired();
		}

		throw new ApiError(
			err.code ?? response.status,
			err.message ?? 'Unknown error',
			err.details ?? {}
		);
	}
}

export function createApi() {
	return new Api(API_BASE_URL);
}

export function createApiWithToken(token) {
	const api = createApi();
	api.setToken(token);
	return api;
}

export function isUnauthorizedError(error) {
	return error instanceof ApiError && error.code === 401;
}
