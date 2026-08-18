import { test, expect } from '@playwright/test';
import { ADMIN_EMAIL, ADMIN_PASSWORD, loginAsAdmin } from './_helpers.js';

test.describe('Login', () => {
  test('logs in with valid admin credentials and reaches the shell', async ({ page }) => {
    await page.goto('');

    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.click('[data-role="login-submit"]');

    await expect(page.locator('[data-role="shell-menu-nav"]')).toBeVisible();
    await expect(page).not.toHaveURL(/#\/login$/);
  });

  test('shows a fixed, neutral message on invalid credentials', async ({ page }) => {
    await page.goto('#/login');

    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', 'wrong-password');
    await page.click('[data-role="login-submit"]');

    const feedback = page.locator('[data-role="login-feedback"]');
    await expect(feedback).toBeVisible();
    await expect(feedback).toContainText('inválidas');
  });

  test('blocks submit and warns when a required field is empty', async ({ page }) => {
    await page.goto('#/login');

    await page.fill('input[name="email"]', ADMIN_EMAIL);
    // Password left empty on purpose.
    await page.click('[data-role="login-submit"]');

    const feedback = page.locator('[data-role="login-feedback"]');
    await expect(feedback).toBeVisible();
    await expect(feedback).toContainText('obligatoria');
    await expect(page).toHaveURL(/#\/login$/);
    await expect(page.locator('input[name="password"]')).toBeFocused();
  });

  test('blocks submit and warns on an invalid email format', async ({ page }) => {
    await page.goto('#/login');

    await page.fill('input[name="email"]', 'not-an-email');
    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.click('[data-role="login-submit"]');

    const feedback = page.locator('[data-role="login-feedback"]');
    await expect(feedback).toBeVisible();
    await expect(feedback).toContainText('formato válido');
  });

  test('a rapid double submit only sends a single login request', async ({ page }) => {
    await page.goto('#/login');

    let requestCount = 0;
    await page.route('**/api/v1/auth/login', async (route) => {
      requestCount += 1;
      await route.continue();
    });

    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', ADMIN_PASSWORD);

    // Two real Playwright .click() calls won't do: the button disables itself
    // synchronously on the first click (before any network await), so Playwright's
    // own actionability retries just block the second call until the button is
    // gone. Dispatch both clicks in the same JS tick instead, to genuinely race
    // the #isSubmitting guard the way a real rapid double-click would.
    await page.evaluate(() => {
      const button = document.querySelector('[data-role="login-submit"]');
      button.click();
      button.click();
    });

    await expect(page.locator('[data-role="shell-menu-nav"]')).toBeVisible();
    expect(requestCount).toBe(1);
  });

  test('quick-access buttons match the backend APP_DEBUG flag', async ({ page, request, baseURL }) => {
    // Ask the backend directly instead of guessing from the DOM — otherwise a bug
    // that makes the buttons never appear looks identical to "this environment
    // just has APP_DEBUG=false" and the test would pass either way.
    const health = await request.get(new URL('health', baseURL).toString());
    const { data } = await health.json();
    const expectDebug = data.debug === true;

    await page.goto('#/login');

    const adminButton = page.locator('[data-role="login-quick-admin"]');
    const userButton = page.locator('[data-role="login-quick-user"]');

    if (!expectDebug) {
      await expect(adminButton).toHaveCount(0);
      await expect(userButton).toHaveCount(0);
      return;
    }

    await expect(adminButton).toBeVisible();
    await expect(userButton).toBeVisible();

    await adminButton.click();
    await expect(page.locator('[data-role="shell-menu-nav"]')).toBeVisible();
    await expect(page).not.toHaveURL(/#\/login$/);
  });

  test('quick-access user button logs in as the seeded non-admin user', async ({ page, request, baseURL }) => {
    // The test above only ever exercises the admin quick-access button —
    // this covers the "usuario normal" one (QUICK_ACCESS_USER in Login.js,
    // usuario@xestify.local / usuario123, seeded by UserSeeder.php since
    // STORY 10.1) actually logging in, not just being visible.
    const health = await request.get(new URL('health', baseURL).toString());
    const { data } = await health.json();
    test.skip(data.debug !== true, 'APP_DEBUG is not enabled in this environment');

    await page.goto('#/login');
    await page.click('[data-role="login-quick-user"]');

    await expect(page.locator('[data-role="shell-menu-nav"]')).toBeVisible();
    await expect(page).not.toHaveURL(/#\/login$/);
  });

  test('an invalid stored session redirects to login with a session-expired warning', async ({ page }) => {
    await loginAsAdmin(page);

    await page.evaluate(() => {
      localStorage.setItem('xestify_access_token', 'this-is-not-a-valid-token');
    });

    await page.reload();

    await expect(page).toHaveURL(/#\/login$/);
    const feedback = page.locator('[data-role="login-feedback"]');
    await expect(feedback).toBeVisible();
    await expect(feedback).toContainText('caducado');
  });
});
