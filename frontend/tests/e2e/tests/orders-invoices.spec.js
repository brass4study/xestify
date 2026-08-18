import { test, expect } from '@playwright/test';
import { loginAsAdmin, selectCustomOption, selectCustomOptionByValue, selectFirstCustomOption } from './_helpers.js';

// STORY 11.2: no E2E spec touched `orders`/`invoices` before this — the only
// two business entities in the demo catalog whose relation is exercised end
// to end here (invoices.id_order -> orders, plugins/invoices/schema.json).
// The invoice's `id_order` relation picker is selected by the order's real
// id (selectCustomOptionByValue), not by its display label:
// EntityOptionLabelBuilder falls back to "<order_date> <status>" for orders
// (no dedicated "name" field), and plenty of seeded/test orders share both.
//
// `orders`' on-disk plugins/orders/schema.json has no relations at all, but
// the *live* schema in `plugins.schema_json` (GET /entities/orders/schema)
// adds a required `id_distributor` relation on top of it — an admin-time
// config change (STORY 7.3), not something visible from the plugin template
// alone. Any distributor works for this flow, so the first option returned
// by GET /entities/distributors/options is used.
test.describe('Orders and invoices', () => {
  test('creates an order and a linked invoice', async ({ page }) => {
    await loginAsAdmin(page);

    await page.click('[data-role="navbar-link"][data-page="entity:orders"]');
    await expect(page).toHaveURL(/#\/entity\/orders$/);
    await expect(page.locator('table')).toBeVisible();

    // EntityEdit#loadAndRenderTabs reparents the whole "Datos" panel into a
    // fresh detached <div> while it builds the tabs, then reattaches it (the
    // same regression class documented in entity-crud.spec.js's focus test).
    // A relation <select> hydrated by setFieldOptions() *before* that swap
    // loses its options/enabled state when the panel gets rebuilt, so every
    // field interaction here waits for the tabs request to settle first.
    const orderTabsLoaded = page.waitForResponse(
      (res) => res.url().includes('/entities/orders/tabs') && res.request().method() === 'GET'
    );
    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="order_date"]')).toBeVisible();
    await orderTabsLoaded;

    await page.fill('input[name="order_date"]', '2026-08-18');
    await selectCustomOption(page, 'status', 'Pendiente');
    await page.fill('input[name="total_amount"]', '199.9');
    await selectFirstCustomOption(page, 'id_distributor');

    const [createOrderResponse] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('/entities/orders/records') && res.request().method() === 'POST'),
      page.click('[data-role="entity-edit-save"]'),
    ]);
    const createdOrder = await createOrderResponse.json();
    const orderId = createdOrder.data.id;
    expect(orderId).toBeTruthy();
    await expect(page).toHaveURL(/#\/entity\/orders$/);

    await page.click('[data-role="navbar-link"][data-page="entity:invoices"]');
    await expect(page).toHaveURL(/#\/entity\/invoices$/);
    await expect(page.locator('table')).toBeVisible();

    const optionsLoaded = page.waitForResponse(
      (res) => res.url().includes('/entities/orders/options') && res.request().method() === 'GET'
    );
    const invoiceTabsLoaded = page.waitForResponse(
      (res) => res.url().includes('/entities/invoices/tabs') && res.request().method() === 'GET'
    );
    await page.click('[data-role="record-create"]');
    await expect(page.locator('input[name="invoice_number"]')).toBeVisible();
    await invoiceTabsLoaded;
    const optionsResponse = await optionsLoaded;
    const { data: orderOptions } = await optionsResponse.json();
    // listOptions() (EntityService.php) returns {id, label} pairs, not {value, label} —
    // the frontend remaps id -> value itself when hydrating the <select> (EntityEdit.js).
    const orderOption = orderOptions.find((option) => option.id === orderId);
    expect(orderOption, `order ${orderId} must appear in /entities/orders/options`).toBeTruthy();

    const invoiceNumber = `E2E-${Date.now()}`;
    await page.fill('input[name="invoice_number"]', invoiceNumber);
    await page.fill('input[name="issue_date"]', '2026-08-18');
    await page.fill('input[name="amount"]', '199.9');
    await selectCustomOption(page, 'payment_status', 'Pendiente');
    await selectCustomOptionByValue(page, 'id_order', orderId);

    const [createInvoiceResponse] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('/entities/invoices/records') && res.request().method() === 'POST'),
      page.click('[data-role="entity-edit-save"]'),
    ]);
    const createdInvoice = await createInvoiceResponse.json();
    // `data.content` is the raw plugin_entity_data.content column, returned as
    // a JSON-encoded string rather than an already-decoded object
    // (EntityControllerTest.php does the same `JSON.parse`/json_decode dance).
    const invoiceContent = JSON.parse(createdInvoice.data.content);
    expect(invoiceContent.id_order).toBe(orderId);
    await expect(page).toHaveURL(/#\/entity\/invoices$/);
    // Not asserting the new row is visible in the list table here: it sorts
    // by created_at ascending by default (EntityList#recordsUrl), so a
    // freshly created invoice lands on the *last* page against a seeded
    // dataset this size, not the first one shown — the API response already
    // confirmed the record was created and correctly linked above.
    await expect(page.locator('table')).toBeVisible();
  });
});
