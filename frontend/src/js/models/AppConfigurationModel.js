import { ApiError } from './ApiClientModel.js';
import { normalizeUiPreferences } from './ThemeModel.js';

const UI_PREFERENCES_PATH = '/configurations/ui-preferences';

export async function loadUiPreferences(api) {
  if (api === null || typeof api?.get !== 'function') {
    return null;
  }

  try {
    const response = await api.get(UI_PREFERENCES_PATH);
    const payload = response?.data ?? response;
    return normalizeUiPreferences(payload?.value ?? {});
  } catch (error) {
    if (error instanceof ApiError && error.code === 404) {
      return normalizeUiPreferences({});
    }

    throw error;
  }
}

export async function saveUiPreferences(api, settings) {
  if (api === null || typeof api?.put !== 'function') {
    return null;
  }

  const normalized = normalizeUiPreferences(settings);
  const response = await api.put(UI_PREFERENCES_PATH, { value: normalized });
  const payload = response?.data ?? response;
  return normalizeUiPreferences(payload?.value ?? normalized);
}
