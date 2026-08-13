export const NotificationModel = {
	notification: null,
	listeners: [],

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
			listener(this.notification);
		}
	},

	setNotification(notification) {
		this.notification = notification && typeof notification === 'object' ? { ...notification } : null;
		this.notify();
	},

	getNotification() {
		return this.notification;
	},

	clearNotification() {
		this.notification = null;
		this.notify();
	},
};
