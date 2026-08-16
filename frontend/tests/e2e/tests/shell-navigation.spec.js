import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

// `clients` is the seeded business-facing instance of the `persons` plugin in
// local dev (STORY 10.3 §6 — manual plugin instance creation); there is no
// instance whose slug is literally `persons`.
test.describe('Shell navigation', () => {
  test('navigating between entities keeps a single persistent shell', async ({ page }) => {
    await loginAsAdmin(page);

    const shellMenu = page.locator('[data-role="shell-menu"]');
    await expect(shellMenu).toHaveCount(1);

    await page.click('[data-role="navbar-link"][data-page="entity:clients"]');
    await expect(page).toHaveURL(/#\/entity\/clients$/);
    await expect(page.locator('[data-role="navbar-link"][data-page="entity:clients"]')).toHaveAttribute('aria-current', 'page');
    await expect(shellMenu).toHaveCount(1);

    await page.click('[data-role="navbar-link"][data-page="entity:products"]');
    await expect(page).toHaveURL(/#\/entity\/products$/);
    await expect(page.locator('[data-role="navbar-link"][data-page="entity:products"]')).toHaveAttribute('aria-current', 'page');
    // The shell itself must not be re-created when switching entities.
    await expect(shellMenu).toHaveCount(1);
  });

  test('back and forward restore the previous entity view', async ({ page }) => {
    await loginAsAdmin(page);

    await page.click('[data-role="navbar-link"][data-page="entity:clients"]');
    await expect(page).toHaveURL(/#\/entity\/clients$/);

    await page.click('[data-role="navbar-link"][data-page="entity:products"]');
    await expect(page).toHaveURL(/#\/entity\/products$/);

    await page.goBack();
    await expect(page).toHaveURL(/#\/entity\/clients$/);

    await page.goForward();
    await expect(page).toHaveURL(/#\/entity\/products$/);
  });
});
