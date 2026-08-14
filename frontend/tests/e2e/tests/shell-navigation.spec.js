import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

test.describe('Shell navigation', () => {
  test('navigating between entities keeps a single persistent shell', async ({ page }) => {
    await loginAsAdmin(page);

    const shellMenu = page.locator('[data-role="shell-menu"]');
    await expect(shellMenu).toHaveCount(1);

    await page.click('[data-role="navbar-link"][data-page="entity:persons"]');
    await expect(page).toHaveURL(/#\/entity\/persons$/);
    await expect(page.locator('[data-role="navbar-link"][data-page="entity:persons"]')).toHaveAttribute('aria-current', 'page');
    await expect(shellMenu).toHaveCount(1);

    await page.click('[data-role="navbar-link"][data-page="entity:products"]');
    await expect(page).toHaveURL(/#\/entity\/products$/);
    await expect(page.locator('[data-role="navbar-link"][data-page="entity:products"]')).toHaveAttribute('aria-current', 'page');
    // The shell itself must not be re-created when switching entities.
    await expect(shellMenu).toHaveCount(1);
  });

  test('back and forward restore the previous entity view', async ({ page }) => {
    await loginAsAdmin(page);

    await page.click('[data-role="navbar-link"][data-page="entity:persons"]');
    await expect(page).toHaveURL(/#\/entity\/persons$/);

    await page.click('[data-role="navbar-link"][data-page="entity:products"]');
    await expect(page).toHaveURL(/#\/entity\/products$/);

    await page.goBack();
    await expect(page).toHaveURL(/#\/entity\/persons$/);

    await page.goForward();
    await expect(page).toHaveURL(/#\/entity\/products$/);
  });
});
