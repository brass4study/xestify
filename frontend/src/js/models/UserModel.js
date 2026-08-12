/**
 * Helpers puros para mostrar y normalizar datos de usuario, compartidos por
 * UserManager y UserConfig (antes duplicados carácter por carácter en ambas páginas).
 */

export function normalizeRoleList(rawRoles) {
	if (Array.isArray(rawRoles)) {
		return rawRoles
			.filter((role) => typeof role === 'string' && role.trim() !== '')
			.map((role) => role.trim());
	}

	if (typeof rawRoles === 'string') {
		const trimmed = rawRoles.trim();
		if (trimmed === '') {
			return [];
		}

		try {
			const parsed = JSON.parse(trimmed);
			if (Array.isArray(parsed)) {
				return normalizeRoleList(parsed);
			}
		} catch {
			// Fall back to comma-separated or single role formats.
		}

		if (trimmed.includes(',')) {
			return trimmed
				.split(',')
				.map((role) => role.trim())
				.filter((role) => role !== '');
		}

		return [trimmed];
	}

	return [];
}

export function displayEmail(user) {
	if (typeof user?.email === 'string' && user.email.trim() !== '') {
		return user.email.trim();
	}

	return 'Sin email';
}

export function displayName(user) {
	if (typeof user?.name === 'string' && user.name.trim() !== '') {
		return user.name.trim();
	}

	return displayEmail(user);
}

export function getInitials(name, email = '') {
	const base = typeof name === 'string' && name !== '' ? name : email;
	const words = String(base).trim().split(/\s+/).filter(Boolean);
	if (words.length === 0) {
		return 'US';
	}

	if (words.length === 1) {
		return words[0].slice(0, 2).toUpperCase();
	}

	return `${words[0][0]}${words[1][0]}`.toUpperCase();
}
