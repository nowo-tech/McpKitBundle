import { test, expect } from '@playwright/test';

/** REQ-DEMO-013: the demo boots, shows the client setup and answers a real MCP call. */
test.describe('McpKit demo', () => {
  test('home page renders on the UiKit shell with the demo tokens', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);
    await expect(page.getByTestId('demo-masthead')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Demo tokens (throwaway)' })).toBeVisible();
    await expect(page.locator('code', { hasText: 'demo-read-token-change-me' }).first()).toBeVisible();
    expect(errors).toEqual([]);
  });

  test('POST /mcp lists the tools the read token may use', async ({ request }) => {
    const response = await request.post('/mcp', {
      headers: {
        Authorization: 'Bearer demo-read-token-change-me',
        'Content-Type': 'application/json',
        'MCP-Protocol-Version': '2026-07-28',
        'Mcp-Method': 'tools/list',
      },
      data: {
        jsonrpc: '2.0',
        id: 1,
        method: 'tools/list',
        params: { _meta: { 'io.modelcontextprotocol/protocolVersion': '2026-07-28', 'io.modelcontextprotocol/clientCapabilities': {} } },
      },
    });
    expect(response.status()).toBe(200);
    const names = ((await response.json()).result.tools as { name: string }[]).map((t) => t.name);
    expect(names).toContain('note_list');
    expect(names).not.toContain('note_archive');
  });
});
