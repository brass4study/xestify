import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

// The `persons` plugin backs several independent business-facing instances
// (STORY 10.3 §6 — manual plugin instance creation), each with its own slug.
// In local dev the only one seeded is `clients`; there is no instance whose
// slug is literally `persons`. `clients` already carries a large,
// ever-growing seeded/demo dataset, so scrolling through table pagination to
// find a just-created/edited row is slow and timing-sensitive. Instead we
// capture the record id straight from the API response and navigate to its
// edit URL directly (`#/entity/clients/:id`, the same route EntityEdit itself
// exposes), reloading before each visit so every check runs against a fresh
// app boot rather than reusing in-SPA router state.
test.describe('Entity CRUD (clients)', () => {
  test('creates and edits a person record via its direct edit URL', async ({ page }) => {
    await loginAsAdmin(page);
    await page.click('[data-role="navbar-link"][data-page="entity:clients"]');
    await expect(page).toHaveURL(/#\/entity\/clients$/);
    await expect(page.locator('table')).toBeVisible();

    const uniqueName = `Playwright E2E ${Date.now()}`;

    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="name"]')).toBeVisible();

    await page.fill('input[name="name"]', uniqueName);
    await page.fill('input[name="surnames"]', 'Automated Suite');
    await page.fill('input[name="mail"]', `playwright-${Date.now()}@xestify.local`);

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
    await expect(page.locator('input[name="name"]')).toHaveValue(updatedName, { timeout: 10000 });
  });

  // Regression: EntityEdit#loadAndRenderTabs reparents the whole "Datos" panel
  // (including whichever field the browser just focused) into a fresh detached
  // <div> while building the tabs, then reattaches it. That detach/reattach used
  // to blur the field back to <body> as soon as the tabs request resolved,
  // silently undoing the initial autofocus a moment after it happened.
  test('keeps the first field focused after opening a record, even once tabs finish loading', async ({ page }) => {
    await loginAsAdmin(page);
    await page.click('[data-role="navbar-link"][data-page="entity:clients"]');
    await expect(page).toHaveURL(/#\/entity\/clients$/);

    const tabsLoaded = page.waitForResponse(
      (res) => res.url().includes('/entities/clients/tabs') && res.request().method() === 'GET'
    );
    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="name"]')).toBeFocused();

    await tabsLoaded;
    await expect(page.locator('input[name="name"]')).toBeFocused();
  });
});
