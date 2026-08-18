import { test, expect } from '@playwright/test';
import { loginAsAdmin, selectCustomOption, selectFirstCustomOption } from './_helpers.js';

// `clients` is the seeded business-facing instance of the `persons` plugin in
// local dev (STORY 10.3 §6 — manual plugin instance creation); there is no
// instance whose slug is literally `persons`.
//
// Used `products` as the second entity until STORY 11.2: that plugin instance
// is inactive in the current local catalog (a leftover from EPIC 3, before
// EPIC 10 introduced the real demo entities), so it never renders a navbar
// link and this spec silently timed out — nobody had re-run the full E2E
// suite since `products` was deactivated. `distributors` (STORY 10.4) is one
// of the always-active demo entities instead.
test.describe('Shell navigation', () => {
  test('navigating between entities keeps a single persistent shell', async ({ page }) => {
    await loginAsAdmin(page);

    const shellMenu = page.locator('[data-role="shell-menu"]');
    await expect(shellMenu).toHaveCount(1);

    await page.click('[data-role="navbar-link"][data-page="entity:clients"]');
    await expect(page).toHaveURL(/#\/entity\/clients$/);
    await expect(page.locator('[data-role="navbar-link"][data-page="entity:clients"]')).toHaveAttribute('aria-current', 'page');
    await expect(shellMenu).toHaveCount(1);

    await page.click('[data-role="navbar-link"][data-page="entity:distributors"]');
    await expect(page).toHaveURL(/#\/entity\/distributors$/);
    await expect(page.locator('[data-role="navbar-link"][data-page="entity:distributors"]')).toHaveAttribute('aria-current', 'page');
    // The shell itself must not be re-created when switching entities.
    await expect(shellMenu).toHaveCount(1);
  });

  test('back and forward restore the previous entity view', async ({ page }) => {
    await loginAsAdmin(page);

    await page.click('[data-role="navbar-link"][data-page="entity:clients"]');
    await expect(page).toHaveURL(/#\/entity\/clients$/);

    await page.click('[data-role="navbar-link"][data-page="entity:distributors"]');
    await expect(page).toHaveURL(/#\/entity\/distributors$/);

    await page.goBack();
    await expect(page).toHaveURL(/#\/entity\/clients$/);

    await page.goForward();
    await expect(page).toHaveURL(/#\/entity\/distributors$/);
  });

  // Regression test for STORY 11.2: AppController.showEntityList() is async
  // and, until this story, had no protection against two overlapping calls —
  // saving a record auto-navigates back to its list, and if the user (or an
  // automatic redirect) then navigates to a *different* entity almost
  // immediately after, both calls run concurrently. If the older one's fetch
  // resolves after the newer one already rendered, it silently overwrote the
  // page actually on screen, including rebinding "Crear nuevo registro" to
  // the wrong entity, with no visible error. Fixed with the same
  // renderToken/isCurrentRender pattern EntityEdit.js already used
  // (EntityList's `isCurrent` option, AppController's generation counter).
  //
  // Deliberately delays only the *first* entity's records fetch instead of
  // racing on real network timing, which would make this test unreliable.
  test('a slower navigation to a previous entity does not overwrite a newer one', async ({ page }) => {
    await loginAsAdmin(page);

    await page.route('**/entities/orders/records**', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 1500));
      await route.continue();
    });

    await page.click('[data-role="navbar-link"][data-page="entity:orders"]');
    // Not awaiting the orders navigation to settle — it's deliberately slow,
    // and the whole point is to navigate away before it resolves.
    await page.click('[data-role="navbar-link"][data-page="entity:invoices"]');

    await expect(page).toHaveURL(/#\/entity\/invoices$/);
    await expect(page.locator('table')).toBeVisible();

    // Give the delayed 'orders' fetch time to resolve and, pre-fix, silently
    // overwrite the invoices page this test is actually looking at.
    await page.waitForTimeout(2000);

    await expect(page).toHaveURL(/#\/entity\/invoices$/);
    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="invoice_number"]')).toBeVisible();
  });

  // Regression test for a second variant of the same STORY 11.2 navigation
  // race, found manually (not by an automated spec) during the story's own
  // checklist walkthrough. Different code path than the test above: this one
  // is EntityEdit's onSaved/onCancel/onDelete callbacks (AppController.js),
  // not EntityList's render. Saving a record auto-redirects back to its list
  // once the save's network round-trip completes; if the user has already
  // navigated to a *different* entity in the meantime (e.g. clicking a
  // navbar link right after "Guardar", without waiting to see it confirmed
  // on screen), the completed save used to still redirect them back to the
  // entity they just left, overwriting wherever they'd since navigated to.
  // Fixed with AppController#isCurrentEntityRoute(), the same
  // currentEntityRoute identity check onTabsReady already used nearby for
  // an unrelated reason.
  test('saving a record does not redirect away from a page the user already navigated to', async ({ page }) => {
    await loginAsAdmin(page);

    await page.click('[data-role="navbar-link"][data-page="entity:orders"]');
    await expect(page.locator('table')).toBeVisible();

    await page.route('**/entities/orders/records', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.continue();
        return;
      }
      await new Promise((resolve) => setTimeout(resolve, 1500));
      await route.continue();
    });

    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="order_date"]')).toBeVisible();
    await page.fill('input[name="order_date"]', '2026-08-18');
    await selectCustomOption(page, 'status', 'Pendiente');
    await page.fill('input[name="total_amount"]', '100');
    await selectFirstCustomOption(page, 'id_distributor');

    await page.click('[data-role="entity-edit-save"]');
    // Not awaiting the save to resolve — navigate away immediately, the way
    // an impatient user clicking a nav link right after "Guardar" would.
    await page.click('[data-role="navbar-link"][data-page="entity:invoices"]');
    await expect(page).toHaveURL(/#\/entity\/invoices$/);
    await expect(page.locator('table')).toBeVisible();

    // Give the delayed save time to resolve and, pre-fix, redirect back to
    // orders regardless of where the user has since navigated.
    await page.waitForTimeout(2000);

    await expect(page).toHaveURL(/#\/entity\/invoices$/);
    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="invoice_number"]')).toBeVisible();
  });
});
