const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
  const output = process.argv[2];
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: 'en-US' });
    const base = 'http://127.0.0.1:8080';
    await page.goto(base + '/wp-login.php');
    await page.locator('#user_login').fill('gallery-admin');
    await page.locator('#user_pass').fill(process.env.GALLERY_PASSWORD);
    await page.locator('#wp-submit').click();
    await page.waitForURL('**/wp-admin/**');
    await page.goto(base + '/wp-admin/admin.php?page=wpessential-checkout-impact');
    await page.locator('.wpecir-topbar').getByText('Pro', { exact: true }).waitFor();
    await page.getByRole('tab', { name: 'Alerts', exact: true }).click();
    await page.locator('#wpecir-pro-recipients').waitFor();
    await page.waitForFunction(() => document.querySelector('#wpecir-pro-recipients')?.value.includes('ops@example.test'));
    await page.screenshot({ path: path.join(output, 'gallery-01-alerts.png'), animations: 'disabled' });
    await page.getByRole('tab', { name: 'Reports', exact: true }).click();
    const csv = page.getByRole('button', { name: 'Export CSV', exact: true });
    const json = page.getByRole('button', { name: 'Export JSON', exact: true });
    await page.waitForFunction(() => [...document.querySelectorAll('.wpecir-pro-export button')].length === 2 && [...document.querySelectorAll('.wpecir-pro-export button')].every(b => !b.disabled));
    assert(await csv.isEnabled() && await json.isEnabled());
    await page.screenshot({ path: path.join(output, 'gallery-02-export.png'), animations: 'disabled' });
    await page.locator('.wpecir-report summary').first().click();
    await page.locator('.wpecir-report__body').first().waitFor({ state: 'visible' });
    await page.getByText('Against approved baseline', { exact: true }).first().waitFor();
    await page.screenshot({ path: path.join(output, 'gallery-03-comparison.png'), animations: 'disabled' });
    for (const file of fs.readdirSync(output).filter(f => f.endsWith('.png'))) {
      const png = fs.readFileSync(path.join(output, file));
      assert(png.readUInt32BE(16) === 1440 && png.readUInt32BE(20) === 900, file + ': unexpected dimensions');
    }
    assert.equal(fs.readdirSync(output).filter(f => f.endsWith('.png')).length, 3);
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
