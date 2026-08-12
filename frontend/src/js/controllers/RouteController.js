import { getPageFromHash, hashFromPage } from './RouteMapController.js';

export class RouteController {
	constructor(options = {}) {
		this.resolvePage = options.resolvePage ?? getPageFromHash;
		this.toHash = options.toHash ?? hashFromPage;
		this.onNavigate = options.onNavigate ?? (async () => {});

		this.fallbackPage = '';
		this.hashNavigationHandler = null;
	}

	async start(fallbackPage) {
		this.stop();
		this.fallbackPage = fallbackPage;

		this.hashNavigationHandler = () => {
			const nextPage = this.resolvePage(window.location.hash, this.fallbackPage);
			void this.navigate(nextPage, { updateHash: true, replaceHash: true });
		};

		window.addEventListener('hashchange', this.hashNavigationHandler);

		const initialPage = this.resolvePage(window.location.hash, this.fallbackPage);
		await this.navigate(initialPage, { updateHash: true, replaceHash: true });
		return initialPage;
	}

	stop() {
		if (this.hashNavigationHandler !== null) {
			window.removeEventListener('hashchange', this.hashNavigationHandler);
			this.hashNavigationHandler = null;
		}
	}

	async navigate(page, options = {}) {
		const isHashTarget = typeof page === 'string' && page.startsWith('#');
		const resolvedPage = isHashTarget ? this.resolvePage(page, this.fallbackPage) : page;
		const shouldUpdateHash = options.updateHash === true || (isHashTarget && options.updateHash !== false);

		if (shouldUpdateHash) {
			this.updateHash(resolvedPage, options.replaceHash === true);
		}

		if (options.notify !== false) {
			await this.onNavigate(resolvedPage, { updateHash: false });
		}
	}

	updateHash(page, replaceHash = false) {
		const targetHash = this.toHash(page);
		if (targetHash === '' || window.location.hash === targetHash) {
			return;
		}

		window.history[replaceHash ? 'replaceState' : 'pushState'](null, '', targetHash);
	}
}
