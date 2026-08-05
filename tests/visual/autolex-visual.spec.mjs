import { test, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const routes = [
  ['home', '/'],
  ['catalog', '/autok/'],
  ['brands', '/markak/'],
  ['models', '/modellek/'],
  ['generations', '/generaciok/'],
  ['vehicle', '/jarmu/'],
  ['sources', '/forrasok/'],
  ['safety', '/visszahivasok/'],
  ['comparison', '/osszehasonlitas/'],
  ['not-found', '/nem-letezo-autolex-oldal/']
];

const viewports = [
  { name: '320', width: 320, height: 900 },
  { name: '375', width: 375, height: 900 },
  { name: '768', width: 768, height: 1024 },
  { name: '1024', width: 1024, height: 1100 },
  { name: '1440', width: 1440, height: 1100 }
];

const output = path.resolve('artifacts/screenshots');

test.beforeAll(async () => {
  await fs.mkdir(output, { recursive: true });
});

for (const viewport of viewports) {
  test.describe(`viewport ${viewport.name}`, () => {
    test.use({ viewport: { width: viewport.width, height: viewport.height } });

    for (const [name, route] of routes) {
      test(`${name} renders without overflow`, async ({ page }) => {
        const response = await page.goto(route, { waitUntil: 'networkidle' });
        expect(response, `No response for ${route}`).not.toBeNull();
        if (name === 'not-found') {
          expect(response.status()).toBe(404);
        } else {
          expect(response.status()).toBeLessThan(400);
        }

        await expect(page.locator('body')).toBeVisible();
        await expect(page.locator('h1')).toHaveCount(1);

        const diagnostics = await page.evaluate(() => ({
          scrollWidth: document.documentElement.scrollWidth,
          clientWidth: document.documentElement.clientWidth,
          bodyBackground: getComputedStyle(document.body).backgroundColor,
          bodyColor: getComputedStyle(document.body).color,
          darkMedia: matchMedia('(prefers-color-scheme: dark)').matches
        }));

        expect(diagnostics.scrollWidth).toBeLessThanOrEqual(diagnostics.clientWidth + 1);
        expect(diagnostics.bodyBackground).not.toBe('rgb(0, 0, 0)');

        await page.screenshot({
          path: path.join(output, `${name}-${viewport.name}.png`),
          fullPage: true,
          animations: 'disabled'
        });
      });
    }

    if (viewport.width <= 375) {
      test('mobile drawer is keyboard reachable', async ({ page }) => {
        await page.goto('/', { waitUntil: 'networkidle' });
        const trigger = page.locator('[data-autolex-menu-toggle]');
        await expect(trigger).toBeVisible();
        await trigger.focus();
        await page.keyboard.press('Enter');
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await page.screenshot({
          path: path.join(output, `mobile-menu-${viewport.name}.png`),
          fullPage: true,
          animations: 'disabled'
        });
      });
    }
  });
}
