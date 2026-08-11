import { test, expect } from '@playwright/test';
import { ADMIN_EMAIL, ADMIN_PASSWORD } from './_helpers.js';

test.describe('Login', () => {
  test('logs in with valid admin credentials and reaches the shell', async ({ page }) => {
    await page.goto('');

    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.click('[data-role="login-submit"]');

    await expect(page.locator('[data-role="shell-menu-nav"]')).toBeVisible();
    await expect(page).not.toHaveURL(/#\/login$/);
  });

  test('shows a global error banner on invalid credentials', async ({ page }) => {
    await page.goto('#/login');

    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', 'wrong-password');
    await page.click('[data-role="login-submit"]');

    const banner = page.locator('[data-role="login-error"]');
    await expect(banner).toBeVisible();
    await expect(banner).not.toHaveText('');
  });
});
