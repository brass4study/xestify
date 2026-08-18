import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './_helpers.js';

// Regression test for STORY 11.2: InputSelect._openDropdown() portals its
// listbox to <body> with `position: fixed`, placed below the trigger's
// current getBoundingClientRect(). A relation field with many options (e.g.
// `id_order` on `invoices`, hundreds of seeded orders) sitting near the
// bottom of a short form used to render that listbox partially or fully
// below the visible viewport, with no way to bring it into view — a `fixed`
// element doesn't move when the page scrolls, and the form is short enough
// that there's nothing to scroll anyway. Fixed by flipping the panel above
// the trigger when there isn't enough room below.
test.describe('InputSelect viewport positioning', () => {
  test('a relation picker near the bottom of a short form opens within the viewport', async ({ page }) => {
    await loginAsAdmin(page);
    await page.click('[data-role="navbar-link"][data-page="entity:invoices"]');
    await expect(page).toHaveURL(/#\/entity\/invoices$/);
    await expect(page.locator('table')).toBeVisible();

    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="invoice_number"]')).toBeVisible();

    const trigger = page.locator(
      '[data-role="input-select"]:has(select[name="id_order"]) [data-role="input-select-trigger"]'
    );
    await expect(trigger).toBeEnabled();
    await trigger.click();

    const panel = page.locator('[data-role="input-select-panel"]:visible');
    await expect(panel).toBeVisible();

    const panelBox = await panel.boundingBox();
    const viewport = page.viewportSize();
    expect(panelBox).not.toBeNull();
    expect(panelBox.y).toBeGreaterThanOrEqual(0);
    expect(panelBox.y + panelBox.height).toBeLessThanOrEqual(viewport.height);
  });
});
