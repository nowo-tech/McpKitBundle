import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — the demo platform shell with the McpKit page (no Symfony toolbar).
 * Playwright clips to the viewport, so the viewport is enlarged before capture.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

type Box = { x: number; y: number; width: number; height: number };
type Page = import('@playwright/test').Page;

async function boxOf(page: Page, selector: string): Promise<Box | null> {
  const loc = page.locator(selector).first();
  if ((await loc.count()) === 0) {
    return null;
  }
  return loc.boundingBox();
}

async function clipDemoFrame(page: Page): Promise<Box> {
  const masthead = await boxOf(page, '[data-testid="demo-platform"]');
  const main = await boxOf(page, 'main');
  if (!masthead || !main) {
    throw new Error('Missing demo platform shell or main for the McpKit screenshot clip');
  }
  const boxes = [masthead, main].filter(Boolean) as Box[];
  const x = Math.min(...boxes.map((b) => b.x));
  const y = Math.min(...boxes.map((b) => b.y));
  const right = Math.max(...boxes.map((b) => b.x + b.width));
  const bottom = Math.max(...boxes.map((b) => b.y + b.height));
  const pad = 8;
  return {
    x: Math.max(0, x - pad),
    y: Math.max(0, y - pad),
    width: right - x + pad * 2,
    height: bottom - y + pad * 2,
  };
}

/** The Symfony toolbar is not part of the demo platform: README screenshots never show it. */
async function hideToolbar(page: Page) {
  await page.addStyleTag({ content: '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"]{display:none!important}' });
}

async function capture(page: Page, file: string) {
  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
  await page.evaluate(() => document.fonts.ready).catch(() => {});
  await hideToolbar(page);
  await page.setViewportSize({ width: 1280, height: 720 });
  const height = await page.evaluate(() => document.documentElement.scrollHeight);
  await page.setViewportSize({ width: 1280, height: Math.min(Math.max(height, 720), 1800) });
  await page.screenshot({ path: resolve(outDir, file), clip: await clipDemoFrame(page) });
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('McpKit screenshots (full demo context)', () => {
  test('endpoint — client setup page with tokens and curl example', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 1000 });
    await page.goto('/');
    await expect(page.getByTestId('demo-platform')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Model Context Protocol endpoint' })).toBeVisible();
    await capture(page, 'endpoint.png');
  });
});
