import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

// Used to rely on `test_entity_ctrl`, a fixture plugin left behind by the
// backend integration test EntityControllerTest.php. STORY 10.3 added a final
// cleanup step to that PHP test (it now deletes its own fixture row on exit),
// so the row is no longer reliably present when this suite runs — it broke
// this spec without anyone noticing because the E2E suite wasn't part of that
// story's verification. Register a throwaway plugin instance of our own
// instead, via the real "manual registration" API (STORY 10.3 §6,
// POST /api/v1/plugins — any plugin_name discovered on disk can have extra
// instances registered under a fresh slug). Self-contained and safe to
// toggle without touching the plugins other specs rely on staying active
// (clients, comments); always deleted again afterward regardless of outcome.
test.describe('PluginManager', () => {
  test('activates and deactivates a plugin from the list', async ({ page }) => {
    await loginAsAdmin(page);
    const token = await page.evaluate(() => localStorage.getItem('xestify_access_token'));
    const targetSlug = `e2e_plugin_toggle_${Date.now()}`;

    const registerResponse = await page.request.post('api/v1/plugins', {
      headers: { Authorization: `Bearer ${token}` },
      data: { plugin_name: 'demoinventory', slug: targetSlug },
    });
    expect(registerResponse.ok(), await registerResponse.text()).toBeTruthy();

    try {
      await page.click('[data-role="navbar-link"][data-page="plugins"]');
      await expect(page).toHaveURL(/#\/plugins$/);

      const row = page.locator('tr', { has: page.locator(`[data-slug="${targetSlug}"]`) });
      await expect(row).toBeVisible();

      // registerNew() activates the instance immediately (STORY 10.3 §6), so
      // it always starts "Activo" here.
      const badge = row.locator('[data-role="plugin-status-badge"]');
      await expect(badge).toHaveText('Activo');

      await row.locator('[data-role="plugin-action"][data-action="deactivate"]').click();
      await expect(badge).toHaveText('Inactivo');

      await row.locator('[data-role="plugin-action"][data-action="activate"]').click();
      await expect(badge).toHaveText('Activo');
    } finally {
      await page.request.delete(`api/v1/plugins/${targetSlug}`, {
        headers: { Authorization: `Bearer ${token}` },
      });
    }
  });
});
