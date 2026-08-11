import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

// Theme preferences are persisted server-side per client (not per browser), so this
// spec reads whatever is active before changing anything and restores it afterwards
// to avoid leaving the shared local dataset in a different visual state.
test.describe('Theme WYSIWYG', () => {
  test('changing the theme color previews instantly and survives a reload', async ({ page }) => {
    await loginAsAdmin(page);

    await page.click('[data-role="theme-settings-trigger"]');
    await expect(page.locator('[data-role="theme-settings-root"]')).toHaveAttribute('data-state', 'open');

    const originalColor = await page
      .locator('[data-role="theme-color-option"].is-active')
      .getAttribute('data-value');
    expect(originalColor).toBeTruthy();

    const targetColor = originalColor === 'violet' ? 'teal' : 'violet';

    const applyColor = async (color) => {
      const [response] = await Promise.all([
        page.waitForResponse((res) => res.url().includes('/configurations/ui-preferences') && res.request().method() === 'PUT'),
        page.click(`[data-role="theme-color-option"][data-value="${color}"]`),
      ]);
      expect(response.ok()).toBeTruthy();
      // WYSIWYG: the change must be visible on the live document immediately, not
      // only after the save round-trip resolves.
      await expect(page.locator('html')).toHaveAttribute('data-ui-theme-color', color);
    };

    await applyColor(targetColor);

    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-ui-theme-color', targetColor);

    await page.click('[data-role="theme-settings-trigger"]');
    await applyColor(originalColor);
  });
});
