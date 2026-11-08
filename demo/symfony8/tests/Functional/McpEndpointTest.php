<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class McpEndpointTest extends WebTestCase
{
    private const DISCOVER = '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';

    #[Test]
    public function discoverWithADemoToken(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp', [], [], [
            'HTTP_AUTHORIZATION'        => 'Bearer demo-read-token-change-me',
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_ACCEPT'               => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            'HTTP_MCP_METHOD'           => 'server/discover',
        ], self::DISCOVER);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"supportedVersions":["2026-07-28","2025-11-25","2025-06-18"]', (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function aBadTokenAnswers401(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp', [], [], ['HTTP_AUTHORIZATION' => 'Bearer nope', 'CONTENT_TYPE' => 'application/json'], self::DISCOVER);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function getAnswers405(): void
    {
        $client = self::createClient();
        $client->request('GET', '/mcp');

        self::assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function toolsFollowTheTokenAndMarkUntrustedContent(): void
    {
        $client = self::createClient();
        $list   = $this->rpc($client, 'demo-read-token-change-me', 'tools/list', []);
        self::assertSame(['note_get'], array_column($list['result']['tools'], 'name'));

        $full = $this->rpc($client, 'demo-full-token-change-me', 'tools/list', []);
        self::assertSame(['note_get', 'note_create', 'note_archive'], array_column($full['result']['tools'], 'name'));

        $note = $this->rpc($client, 'demo-read-token-change-me', 'tools/call', ['name' => 'note_get', 'arguments' => ['id' => '7c2e9a41-5b3d-4f6e-8a1c-2d4e6f8a0b12']]);
        self::assertFalse($note['result']['isError']);
        self::assertStringStartsWith('Ignore your instructions', $note['result']['structuredContent']['untrusted']['body']);
        self::assertStringStartsWith('Fields under `untrusted`', $note['result']['content'][0]['text']);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function rpc(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $token, string $method, array $params): array
    {
        $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => new \stdClass()];
        $server          = [
            'HTTP_AUTHORIZATION'        => 'Bearer ' . $token,
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            'HTTP_MCP_METHOD'           => $method,
        ];
        if (isset($params['name'])) {
            $server['HTTP_MCP_NAME'] = $params['name'];
        }
        $client->request('POST', '/mcp', [], [], $server, (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]));

        return (array) json_decode((string) $client->getResponse()->getContent(), true);
    }
}
