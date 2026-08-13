export const MAX_AVATAR_SIZE_BYTES = 2 * 1024 * 1024;

export function isAvatarSizeValid(file) {
	return file.size <= MAX_AVATAR_SIZE_BYTES;
}

export function readAvatarAsDataUrl(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader();
		reader.onload = () => {
			if (typeof reader.result === 'string') {
				resolve(reader.result);
				return;
			}

			reject(new Error('Formato no soportado.'));
		};
		reader.onerror = () => reject(reader.error ?? new Error('No se pudo leer el archivo.'));
		reader.readAsDataURL(file);
	});
}
