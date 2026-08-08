import { test, expect } from '@playwright/test';

test.describe('ALX-050 Hungarian public presentation', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('dynamically added public automotive labels are localized conservatively', async ({ page }) => {
    const response = await page.goto('/autok/', { waitUntil: 'networkidle' });
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);

    await page.evaluate(() => {
      const fixture = document.createElement('section');
      fixture.id = 'alx-public-presentation-fixture';
      fixture.innerHTML = `
        <span data-case="petrol">Petrol</span>
        <span data-case="diesel">Diesel</span>
        <span data-case="hybrid">Petrol/Electric</span>
        <b data-case="primary">PRIMARY</b>
        <b data-case="live">LIVE QUERY</b>
        <span data-case="unknown">Manufacturer X-Fuel</span>
        <span data-case="summary">• Opel • Petrol • 2024</span>`;
      document.body.appendChild(fixture);
    });

    await expect(page.locator('[data-case="petrol"]')).toHaveText('Benzin');
    await expect(page.locator('[data-case="diesel"]')).toHaveText('Dízel');
    await expect(page.locator('[data-case="hybrid"]')).toHaveText('Benzin / elektromos');
    await expect(page.locator('[data-case="primary"]')).toHaveText('ELSŐDLEGES');
    await expect(page.locator('[data-case="live"]')).toHaveText('ÉLŐ LEKÉRDEZÉS');
    await expect(page.locator('[data-case="unknown"]')).toHaveText('Manufacturer X-Fuel');
    await expect(page.locator('[data-case="summary"]')).toHaveText('• Opel • Benzin • 2024');
  });
});
