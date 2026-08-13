export async function copyToClipboard(text) {
	if (typeof text !== 'string' || text === '') {
		return false;
	}

	if (navigator?.clipboard?.writeText) {
		await navigator.clipboard.writeText(text);
		return true;
	}

	return false;
}
