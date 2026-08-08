import { getPageFromHash, hashFromPage } from './RouteMapController.js';

export class RouteController {
	constructor(options = {}) {
		this.resolvePage = options.resolvePage ?? getPageFromHash;
		this.toHash = options.toHash ?? hashFromPage;
		this.onNavigate = options.onNavigate ?? (async () => {});

		this.fallbackPage = '';
		this.hashNavigationHandler = null;
		this.suppressNextHashNavigation = false;
	}

	async start(fallbackPage) {
		this.stop();
		this.fallbackPage = fallbackPage;

		this.hashNavigationHandler = () => {
			if (this.suppressNextHashNavigation) {
				this.suppressNextHashNavigation = false;
				return;
			}

			const nextPage = this.resolvePage(window.location.hash, this.fallbackPage);
			void this.onNavigate(nextPage, { updateHash: false });
		};

		window.addEventListener('hashchange', this.hashNavigationHandler);

		const initialPage = this.resolvePage(window.location.hash, this.fallbackPage);
		await this.navigate(initialPage, { updateHash: true });
		return initialPage;
	}

	stop() {
		if (this.hashNavigationHandler !== null) {
			window.removeEventListener('hashchange', this.hashNavigationHandler);
			this.hashNavigationHandler = null;
		}

		this.suppressNextHashNavigation = false;
	}

	async navigate(page, options = {}) {
		const shouldUpdateHash = options.updateHash === true;

		if (shouldUpdateHash) {
			this.updateHash(page);
		}

		await this.onNavigate(page, { updateHash: false });
	}

	updateHash(page) {
		const targetHash = this.toHash(page);
		if (targetHash === '' || window.location.hash === targetHash) {
			return;
		}

		this.suppressNextHashNavigation = true;
		window.location.hash = targetHash;
	}

	resolveFromHash(hashValue) {
		return this.resolvePage(hashValue, this.fallbackPage);
	}
}
