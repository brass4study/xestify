import { expect } from '@playwright/test';

export const ADMIN_EMAIL = 'admin@xestify.local';
export const ADMIN_PASSWORD = 'admin123';

// DynamicTable paginates client-side at 10 rows by default
// (DynamicTable.js#pageSize, "silently drops rows past the first page" per
// its own comment) via a page-size *cookie* shared by every table in the
// app. The plugin catalog now has 11+ entity-type plugins on its own
// (STORY 10.6's demo entities), so a freshly registered fixture instance —
// appended last — reliably lands on page 2 and never renders in the DOM at
// all. Must run before the first navigation (addInitScript, not
// page.evaluate) so DynamicTable's very first render already sees it.
export async function useLargeTablePageSize(page) {
  await page.addInitScript(() => {
    document.cookie = 'xestify_table_page_size=200; Path=/; Max-Age=31536000; SameSite=Lax';
  });
}

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

// InputSelect.js backs every `type: "select"` field (including relation
// pickers) with a real but visually hidden native <select> — kept only to
// preserve the FormData contract — behind a custom trigger/listbox pair for
// the actual styled UI. A plain Playwright `selectOption()` fails its
// visibility actionability check against the hidden native element, so this
// drives the real trigger+listbox the same way a user's mouse would: open
// the dropdown, then click the visible option row.
//
// Relation fields (schema.relations[], e.g. `id_order`) render disabled with
// zero options and only become interactive once their own GET .../options
// request resolves and EntityEdit calls setFieldOptions() (see
// EntityEdit.js#hydrateRelationOptions). Waiting for that network response
// from the test is not enough — the trigger can still be mid-toggle from
// disabled to enabled a tick later — so this waits on the trigger's actual
// `disabled` state instead of the network.
async function openCustomOptionPanel(page, fieldName) {
  const root = page.locator(`[data-role="input-select"]:has(select[name="${fieldName}"])`);
  const trigger = root.locator('[data-role="input-select-trigger"]');
  await expect(trigger).toBeEnabled();
  // InputSelect._openDropdown() portals the listbox to <body> with
  // `position: fixed`, placed at the trigger's *current* getBoundingClientRect().
  // A field near the bottom of a long form (e.g. a relation picker after
  // several other fields) can sit flush with the viewport's bottom edge, so
  // the panel opens entirely below it — a plain scrollIntoViewIfNeeded()
  // only nudges the trigger itself into view and leaves zero room for the
  // panel underneath, which Playwright then reports as "outside of the
  // viewport" forever (a `position: fixed` element doesn't move when the
  // page scrolls, so no amount of retrying fixes it after the fact).
  // Centering the trigger first guarantees headroom below it.
  await trigger.evaluate((element) => element.scrollIntoView({ block: 'center' }));
  await trigger.click();
  return page.locator('[data-role="input-select-panel"]:visible');
}

// The listbox itself scrolls (`max-h-64 overflow-auto`) once there are more
// rows than fit — on demo/seeded data a relation over `orders`/`clients` etc.
// easily has hundreds. Playwright's own click-time auto-scroll doesn't
// reliably reach a row deep inside that kind of nested scrollable container
// portalled under a `position: fixed` ancestor, so this scrolls the target
// row into view itself first.
async function clickOptionRow(row) {
  await row.evaluate((element) => element.scrollIntoView({ block: 'center' }));
  await row.click();
}

export async function selectCustomOption(page, fieldName, optionLabel) {
  const panel = await openCustomOptionPanel(page, fieldName);
  await clickOptionRow(panel.getByText(optionLabel, { exact: true }));
}

// Relation pickers over demo/seeded data can have multiple rows sharing the
// exact same display label (EntityOptionLabelBuilder falls back to the first
// couple of summaryView fields when there's no dedicated "name" field, e.g.
// orders show "<order_date> <status>" — plenty of seeded/test orders share
// both). Selecting by the row's `data-value` (the record's real id, already
// known to the caller) sidesteps that ambiguity entirely instead of relying
// on the label being unique.
export async function selectCustomOptionByValue(page, fieldName, optionValue) {
  const panel = await openCustomOptionPanel(page, fieldName);
  await clickOptionRow(panel.locator(`[role="option"][data-value="${optionValue}"]`));
}

// For fields where any option is acceptable (e.g. picking *a* distributor to
// satisfy a required relation, with no assertion tied to which one).
export async function selectFirstCustomOption(page, fieldName) {
  const panel = await openCustomOptionPanel(page, fieldName);
  await clickOptionRow(panel.locator('[role="option"]').first());
}
