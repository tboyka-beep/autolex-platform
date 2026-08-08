import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: /(?:autolex-visual|vehicle-media|public-presentation)\.spec\.mjs/,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  retries: 0,
  reporter: [['list'], ['html', { outputFolder: 'artifacts/playwright-report', open: 'never' }]],
  use: {
    baseURL: process.env.AUTOLEX_BASE_URL || 'http://localhost:8888',
    browserName: 'chromium',
    locale: 'hu-HU',
    colorScheme: 'light',
    reducedMotion: 'reduce',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off'
  },
  outputDir: 'artifacts/test-results'
});
