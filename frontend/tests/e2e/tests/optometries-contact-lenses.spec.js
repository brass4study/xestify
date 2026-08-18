import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

// STORY 11.2: no E2E spec touched the extension plugins `optometries`/
// `contact_lenses` before this — both target the `clients` instance of
// `persons` (plugins/optometries/manifest.json, plugins/contact_lenses/
// manifest.json) and render as tabs on a person's own edit page
// (PluginPanelRegistry, EntityEdit.js). Adding a ficha from either tab
// navigates to the standalone PluginItemEdit.js page (STORY 10.5), which has
// no dedicated frontend integration runner of its own — this spec exercises
// it end to end instead, against the real plugin.js `buildDetailForm()`.
//
// Only `date` is required on both schemas (plugins/optometries/schema.json,
// plugins/contact_lenses/schema.json); PluginItemEdit.js#loadContent()
// pre-fills it with today's date for a new item, so saving without touching
// any field is already a valid, minimal ficha.
test.describe('Optometries and contact lenses fichas', () => {
  test('adds an optometry ficha and a contact lenses ficha to a person', async ({ page }) => {
    await loginAsAdmin(page);

    await page.click('[data-role="navbar-link"][data-page="entity:clients"]');
    await expect(page).toHaveURL(/#\/entity\/clients$/);
    await expect(page.locator('table')).toBeVisible();

    await page.locator('[data-role="record-row"]').first().click();
    await expect(page).toHaveURL(/#\/entity\/clients\/[^/]+$/);
    await expect(page.locator('input[name="name"]')).toBeVisible();

    await addFicha(page, {
      tabName: 'Optometrías',
      pluginSlug: 'optometries',
    });

    // Adding the contact-lenses ficha re-opens the person's own edit page
    // first (PluginItemEdit#onDone navigates back), so the tab bar has to be
    // located again rather than reused from the optometries step above.
    await addFicha(page, {
      tabName: 'Lentillas',
      pluginSlug: 'contact_lenses',
    });
  });
});

async function addFicha(page, { tabName, pluginSlug }) {
  await page.getByRole('tab', { name: tabName }).click();
  await expect(page.getByText('Añadir', { exact: true })).toBeVisible();
  await page.getByText('Añadir', { exact: true }).click();

  await expect(page.locator('input[name="date"]')).toBeVisible();

  const [createResponse] = await Promise.all([
    page.waitForResponse(
      (res) => res.url().includes(`/plugins/${pluginSlug}/clients/`) && res.request().method() === 'POST'
    ),
    page.click('[data-role="plugin-item-save"]'),
  ]);
  const created = await createResponse.json();
  expect(created.data?.id, `${pluginSlug} ficha must be created with an id`).toBeTruthy();

  // #onDone() navigates back to the person's own page with the same tab
  // still active (not the "Datos" tab), landing straight on the fichas list
  // — so the more direct success signal is the tab's own table, not a field
  // that lives on a different tab.
  await expect(page.getByRole('tab', { name: tabName })).toBeVisible();
  await expect(page.getByText('Añadir', { exact: true })).toBeVisible();
}
