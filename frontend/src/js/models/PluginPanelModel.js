const registry = new Map();

export const PluginPanelRegistry = {
	register(slug, PanelClass) {
		registry.set(slug, PanelClass);
	},

	build(slug, options) {
		const PanelClass = registry.get(slug);
		if (PanelClass === undefined) {
			return null;
		}
		return new PanelClass(options);
	},
};
