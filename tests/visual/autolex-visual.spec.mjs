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
  { name: '1440', width: 1440, height: 1100 },
  { name: 'reference', width: 1672, height: 941 }
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
          scrollHeight: document.documentElement.scrollHeight,
          clientWidth: document.documentElement.clientWidth,
          bodyBackground: getComputedStyle(document.body).backgroundColor,
          bodyColor: getComputedStyle(document.body).color,
          darkMedia: matchMedia('(prefers-color-scheme: dark)').matches,
          mainCount: document.querySelectorAll('main').length
        }));

        expect(diagnostics.scrollWidth).toBeLessThanOrEqual(diagnostics.clientWidth + 1);
        expect(diagnostics.bodyBackground).not.toBe('rgb(0, 0, 0)');
        expect(diagnostics.mainCount).toBe(1);

        if (name === 'home') {
          await expect(page.locator('[data-reference-dashboard="true"]')).toHaveCount(1);
          await expect(page.locator('.alx-home-rail--left')).toBeVisible();
          await expect(page.locator('.alx-home-center')).toBeVisible();
          await expect(page.locator('.alx-home-rail--right')).toBeVisible();
          await expect(page.locator('.alx-mobile-card')).toBeVisible();
          await expect(page.locator('.alx-safety-card')).toBeVisible();
          await expect(page.locator('.alx-metrics')).toBeVisible();
          await expect(page.locator('.alx-safety-strip')).toBeVisible();
          await expect(page.locator('.alx-recent-updates-card')).toBeVisible();
          await expect(page.locator('.alx-brand-explore-card')).toBeHidden();

          if (viewport.name === 'reference') {
            const geometry = await page.evaluate(() => {
              const rect = (selector) => {
                const node = document.querySelector(selector);
                if (!node) return null;
                const box = node.getBoundingClientRect();
                return { width: box.width, height: box.height, left: box.left, right: box.right, top: box.top, bottom: box.bottom };
              };
              const quickLinks = document.querySelectorAll('.alx-quick-panel .alx-quick-links a');
              const lastQuick = quickLinks.length ? quickLinks[quickLinks.length - 1].getBoundingClientRect() : null;
              const visibleNav = Array.from(document.querySelectorAll('.alx-primary-nav a')).filter((node) => getComputedStyle(node.parentElement).display !== 'none');
              const firstNav = visibleNav.length ? visibleNav[0].getBoundingClientRect() : null;
              const lastNav = visibleNav.length ? visibleNav[visibleNav.length - 1].getBoundingClientRect() : null;
              return {
                header: rect('.alx-site-header'),
                grid: rect('.alx-home-grid'),
                hero: rect('.alx-hero'),
                quick: rect('.alx-quick-panel'),
                featured: rect('.alx-featured-vehicle-card'),
                mobile: rect('.alx-mobile-card'),
                safetyCard: rect('.alx-safety-card'),
                brand: rect('.alx-brand-panel'),
                knowledge: rect('.alx-knowledge-card'),
                recent: rect('.alx-recent-updates-card'),
                lastQuick: lastQuick ? { top: lastQuick.top, bottom: lastQuick.bottom, height: lastQuick.height } : null,
                footer: rect('.alx-site-footer'),
                navCount: visibleNav.length,
                firstNav: firstNav ? { left: firstNav.left, right: firstNav.right } : null,
                lastNav: lastNav ? { left: lastNav.left, right: lastNav.right } : null
              };
            });

            expect(geometry.header.height).toBeGreaterThanOrEqual(54);
            expect(geometry.header.height).toBeLessThanOrEqual(62);
            expect(geometry.grid.width).toBeGreaterThanOrEqual(1400);
            expect(geometry.grid.width).toBeLessThanOrEqual(1442);
            expect(geometry.hero.height).toBeGreaterThanOrEqual(500);
            expect(geometry.hero.height).toBeLessThanOrEqual(650);
            expect(geometry.quick.width).toBeGreaterThanOrEqual(geometry.grid.width - 2);
            expect(geometry.featured.width).toBeGreaterThanOrEqual(geometry.grid.width - 2);
            expect(geometry.mobile.width).toBeGreaterThanOrEqual(geometry.grid.width - 2);
            expect(geometry.safetyCard.width).toBeGreaterThanOrEqual(geometry.grid.width - 2);
            expect(geometry.brand.width).toBeGreaterThanOrEqual(geometry.grid.width - 2);
            expect(geometry.knowledge.width).toBeGreaterThanOrEqual(geometry.grid.width - 2);
            expect(geometry.recent.width).toBeGreaterThanOrEqual(geometry.grid.width - 2);
            expect(geometry.quick.height).toBeGreaterThanOrEqual(130);
            expect(geometry.lastQuick.bottom).toBeLessThanOrEqual(geometry.quick.bottom - 4);
            expect(geometry.recent.top).toBeGreaterThanOrEqual(geometry.knowledge.bottom + 10);
            expect(geometry.footer.height).toBeLessThanOrEqual(180);
            expect(geometry.navCount).toBe(6);
            expect(geometry.firstNav.left).toBeGreaterThanOrEqual(0);
            expect(geometry.lastNav.right).toBeLessThanOrEqual(viewport.width);
            expect(diagnostics.scrollHeight).toBeGreaterThan(viewport.height);
            expect(diagnostics.scrollHeight).toBeLessThanOrEqual(6000);
          }
        }

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