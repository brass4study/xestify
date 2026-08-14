import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

// `test_entity_ctrl` is a leftover fixture plugin from backend integration tests.
// It is safe to toggle without touching the plugins the other specs (entity-crud,
// theme-wysiwyg) rely on staying active (persons, comments). Its status at rest is
// not guaranteed across runs, so the test reads its current state instead of
// assuming one, and always restores it before finishing.
const TARGET_SLUG = 'test_entity_ctrl';

test.describe('PluginManager', () => {
  test('activates and deactivates a plugin from the list', async ({ page }) => {
    await loginAsAdmin(page);
    await page.click('[data-role="navbar-link"][data-page="plugins"]');
    await expect(page).toHaveURL(/#\/plugins$/);

    const row = page.locator('tr', { has: page.locator(`[data-slug="${TARGET_SLUG}"]`) });
    await expect(row).toBeVisible();

    const badge = row.locator('[data-role="plugin-status-badge"]');
    const startedActive = (await badge.textContent())?.trim() === 'Activo';
    const [firstAction, secondAction] = startedActive ? ['deactivate', 'activate'] : ['activate', 'deactivate'];
    const [firstLabel, secondLabel] = startedActive ? ['Inactivo', 'Activo'] : ['Activo', 'Inactivo'];

    await row.locator(`[data-role="plugin-action"][data-action="${firstAction}"]`).click();
    await expect(badge).toHaveText(firstLabel);

    await row.locator(`[data-role="plugin-action"][data-action="${secondAction}"]`).click();
    await expect(badge).toHaveText(secondLabel);
  });
});
