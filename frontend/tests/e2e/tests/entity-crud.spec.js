import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

// `clients` already carries a large, ever-growing seeded/demo dataset in local dev,
// so scrolling through table pagination to find a just-created/edited row is slow
// and timing-sensitive. Instead we capture the record id straight from the API
// response and navigate to its edit URL directly (`#/entity/clients/:id`, the same
// route EntityEdit itself exposes), reloading before each visit so every check runs
// against a fresh app boot rather than reusing in-SPA router state.
test.describe('Entity CRUD (clients)', () => {
  test('creates and edits a client record via its direct edit URL', async ({ page }) => {
    await loginAsAdmin(page);
    await page.click('[data-role="navbar-link"][data-page="entity:clients"]');
    await expect(page).toHaveURL(/#\/entity\/clients$/);
    await expect(page.locator('table')).toBeVisible();

    const uniqueName = `Playwright E2E ${Date.now()}`;

    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="name"]')).toBeVisible();

    await page.fill('input[name="name"]', uniqueName);
    await page.fill('input[name="surnames"]', 'Automated Suite');
    await page.fill('input[name="email"]', `playwright-${Date.now()}@xestify.local`);

    const [createResponse] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('/entities/clients/records') && res.request().method() === 'POST'),
      page.click('[data-role="entity-edit-save"]'),
    ]);
    const created = await createResponse.json();
    const recordId = created.data.id;
    expect(recordId).toBeTruthy();
    await expect(page).toHaveURL(/#\/entity\/clients$/);

    await page.goto(`#/entity/clients/${recordId}`);
    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('input[name="name"]')).toHaveValue(uniqueName, { timeout: 10000 });

    const updatedName = `${uniqueName} (editado)`;
    await page.fill('input[name="name"]', updatedName);
    await Promise.all([
      page.waitForResponse((res) => res.url().includes(`/records/${recordId}`) && res.request().method() === 'PUT'),
      page.click('[data-role="entity-edit-save"]'),
    ]);
    await expect(page).toHaveURL(/#\/entity\/clients$/);

    await page.goto(`#/entity/clients/${recordId}`);
    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('input[name="name"]')).toHaveValue(updatedName, { timeout: 10000 });
  });
});
