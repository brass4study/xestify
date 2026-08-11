export const ADMIN_EMAIL = 'admin@xestify.local';
export const ADMIN_PASSWORD = 'admin123';

export async function loginAsAdmin(page) {
  await page.goto('');
  await page.fill('input[name="email"]', ADMIN_EMAIL);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await page.click('[data-role="login-submit"]');
  await page.locator('[data-role="shell-menu-nav"]').waitFor({ state: 'visible' });
  // Wait for the initial entity redirect's async load to settle before handing
  // control back — navigating away too early races its in-flight request against
  // the page that follows and can leave stale content rendered.
  await page.waitForSelector('table tbody tr');
}
